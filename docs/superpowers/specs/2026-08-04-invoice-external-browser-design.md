# 請求書を外部ブラウザで開き iPhone でも PDF 保存できるようにする設計

作成日: 2026-08-04

## 背景

加盟店（iPhone ユーザー）から「請求書が見れない」と報告があった。調査の結果、
**「PDF保存」ボタンが無反応**であることが原因と判明した。

`resources/js/liff_invoice_pdf.js:65-69` の保存処理は `window.print()` の呼び出し一本だが、
iOS の LINE アプリ内ブラウザ（LIFF ブラウザ）は WKWebView ベースで
**`window.print()` が未実装**のため、押しても何も起きず、エラーも出ない。
`afterprint` も発火しないので `document.title` も元に戻らない。

WKWebView の仕様であり回避できないため、**PDF 化する場所を外部ブラウザ（Safari / Chrome）に移す**。

### 採用しなかった案

| 案 | 却下理由 |
|---|---|
| html2canvas で画像化し長押し保存 | 請求書を画像で保管する運用にできない |
| dompdf / TCPDF でサーバー生成 | 既存 CSS が flex 主体で再現できず、レイアウトを作り直しになる |
| headless Chrome（Browsershot / puppeteer） | 本番がエックスサーバー（共有ホスティング）で Node + Chromium を入れられない |
| PDF 生成だけ Cloud Run 等に外出し | 上記が使えれば最善だが、今回は外部インフラを増やさない判断 |
| 請求書URLを LINE 本文に直接入れる | トーク内の URL も iOS では LINE アプリ内ブラウザで開くため、`window.print()` は動かないまま |

## 方針

請求書**一覧**は LIFF のまま（LINE ログインで本人確認）、
**各月の請求書ページだけを LIFF から切り離し、署名付き URL で外部ブラウザに開かせる**。

外部ブラウザなら `window.print()` が動くため、既存の「PDF保存」ボタンがそのまま機能する。

```
LINE本文のURL
  └─ 請求書一覧（LIFF・要ログイン）
       └─ [PDFを表示] → liff.openWindow({ external: true })
            └─ Safari / Chrome で請求書ページ（署名付きURL・LIFF不要）
                 └─ [PDF保存] → window.print() → OSの印刷画面からPDF保存
```

### この構成の副次的な効果

- **LIFF のエンドポイント URL 問題が消える。**
  現状は一覧が `/merchants/invoices`、詳細が `/merchants/invoice` と別パスで、
  詳細ページが LIFF アプリのエンドポイント配下でない可能性がある。
  配下でない場合 `liff.isLoggedIn()` が false になり `liff.login()` のリダイレクトが走るが、
  これは iOS の LIFF ブラウザで白画面のまま止まりやすい。詳細ページを LIFF から外せばこの経路自体が無くなる
- **署名 URL を一覧から毎回発行する**ため、有効期限を短くできる。
  期限切れでも一覧から開き直せばよく、URL 転送による漏洩リスクを抑えられる
- 詳細ページがサーバーサイドレンダリングになり、JS 実行が前提でなくなる

## スコープ外

- 管理画面側の請求書（`admin.sales.invoice`）。管理者は PC ブラウザで開くため現状で問題ない
- 請求書一覧ページ（`merchants.invoices`）の LIFF 構成。変更しない
- LINE 本文のテンプレートと `{invoice_url}`（一覧の LIFF URL のまま）
- 請求書のレイアウト・集計ロジック（`partials/invoice_pdf.blade.php` と `InvoiceService`）

## ルート

現状:

```php
Route::get('/merchants/invoice', [UserMerchantController::class, 'invoicePdf'])->name('merchants.invoice');
Route::post('/api/merchant/invoice', [UserMerchantController::class, 'getInvoice']);
```

変更後:

```php
Route::get('/merchants/invoice/{merchant}/{month}', [UserMerchantController::class, 'invoicePdf'])
    ->name('merchants.invoice');
```

- `POST /api/merchant/invoice` は**削除**する。SSR になり不要
- 認証は LIFF アクセストークンから**署名付き URL** に変わるため、`merchant` を URL に含める
- `month` は `YYYY-MM`。ルート制約 `where('month', '[0-9]{4}-[0-9]{2}')` を付ける
- `signed` ミドルウェアは**付けない**（理由は「有効期限切れの扱い」を参照）

## 署名付き URL の発行

`UserMerchantController::getInvoiceList()` で一覧 HTML を組み立てる際に、月ごとに発行する。

```php
URL::temporarySignedRoute('merchants.invoice', now()->addDay(), [
    'merchant' => $merchant->id,
    'month' => $month,
]);
```

- **有効期限は 1 日。** 一覧から開き直せば再発行されるため短くて困らない
- 一覧自体が LIFF ログインで守られているので、URL が本人以外に渡るのは
  「トークを転送した」「Safari の履歴を見られた」ケースに限られる
- `APP_KEY` で署名されるため改ざん不可。`?merchant=138` のように数字を変えても 403 になる

`InvoiceService::invoiceUrl()`（LINE 本文用の LIFF URL）は**変更しない**。

## 有効期限切れの扱い

`signed` ミドルウェアは期限切れで素の 403 ページを返すため、加盟店には何が起きたか分からない。
コントローラ内で判定し、専用のビューを返す。

```php
if (!$request->hasValidSignature()) {
    return response()->view('merchants.invoice_expired', [], 403);
}
```

`merchants/invoice_expired.blade.php` の内容:

> このリンクの有効期限が切れています。
> LINE の請求書一覧からもう一度開いてください。

## コントローラ

`UserMerchantController::invoicePdf(Request $request, $merchantId, $month)`

処理:

1. 署名を検証。無効なら期限切れビューを 403 で返す
2. `Merchant::findOrFail($merchantId)`
3. `$month` が確定月（当月未満）か検証。当月以降なら 403 で「まだ確定していません」のビュー
4. `InvoiceService::forMonth()` で集計
5. `Setting` から会社情報を取得
6. `merchants.invoice_pdf` ビューを返す

`getInvoice()` は削除する。集計と会社情報の取得はこのメソッドへ移すだけで、ロジックは変えない。

**LINE アクセストークンの検証（`getLineProfile()`）は行わない。** 署名付き URL が本人確認の代わりになる。

## ビュー

### `merchants/invoice_pdf.blade.php`

LIFF 用の足回りを全て外し、請求書 HTML を直接描画する。

- `window.LIFF_ID` / `window.LIFF_MOCK` / `window.INVOICE_MONTH` の埋め込みを削除
- `@vite(['resources/js/liff_invoice_pdf.js'])` を削除
- `<div id="invoice">読み込み中...</div>` を `@include('partials.invoice_pdf')` に置き換え
- `csrf-token` の meta を削除（POST しなくなる）
- 「PDF保存」ボタンは**残す**。`.btn-area` の `display:none` を外し、最初から表示する
- 印刷時のファイル名を請求書番号にする既存挙動（`document.title` の差し替え）は
  インラインの `<script>` に移して維持する

### 保存方法の案内

iOS は印刷画面から PDF にする操作が分かりにくいため、ボタンの下に一行添える。

> ボタンを押すと印刷画面が開きます。iPhone はプレビューを広げてから共有ボタンで「ファイルに保存」を選んでください。

### `merchants/invoice_expired.blade.php`（新規）

`layouts.app` を使わない単独の軽いページ。期限切れの案内のみ。

## 一覧側の変更

### `merchants/partials/invoice_list.blade.php`

「PDFを表示」のリンクを、署名付き URL を持つボタンに変える。

```blade
<a href="{{ $invoice['url'] }}" class="js-open-invoice" ...>PDFを表示</a>
```

`href` は残す。LIFF が使えない環境でも通常遷移で開けるようにするため。

### `resources/js/liff_invoice_list.js`

一覧 HTML を差し込んだ後、クリックを横取りして外部ブラウザで開く。

```js
invoiceListContainer.addEventListener('click', function (e) {
    const link = e.target.closest('.js-open-invoice');
    if (!link) return;
    if (!liff.isInClient()) return;   // 既に外部ブラウザなら通常遷移
    e.preventDefault();
    liff.openWindow({ url: link.href, external: true });
});
```

- **イベント委譲**にする。リンクは `innerHTML` で後から差し込まれるため
- `liff.isInClient()` が false のときは `preventDefault()` せず通常のリンクとして動かす
- `window.LIFF_MOCK` のときも通常遷移（ローカル確認用）

### 削除するファイル

- `resources/js/liff_invoice_pdf.js`
- `vite.config.js` の input からも該当行を外す

## 動作確認手順

テストコードは書かない方針のため、手動で確認する。

1. `LIFF_MOCK=true` のローカルで一覧を開き、「PDFを表示」が通常遷移で開くこと
2. 署名なしで `/merchants/invoice/137/2026-07` を直接開く → 期限切れビューが 403 で出ること
3. 発行された署名 URL の `merchant` を別 ID に書き換える → 403 になること
4. 当月の署名 URL を発行して開く → 「まだ確定していません」が出ること
5. **iPhone 実機**：LINE から一覧を開き「PDFを表示」→ **Safari が起動**すること
6. Safari で請求書が正しく表示されること（金額・商品・振込先が管理画面の請求書と一致）
7. Safari で「PDF保存」→ 印刷画面が開き、PDF として保存できること
8. 保存された PDF のファイル名が `invoice_{merchant_id}_{YYYYMMDD}` になっていること
9. **Android 実機**：同様に Chrome が起動し、印刷から PDF 保存できること
10. 1 日以上前に発行した URL を開く → 期限切れビューが出て、一覧から開き直すと見られること
11. 請求書一覧に確定月だけが並び、金額が従来と変わっていないこと

## 未解決の課題

`merchants/invoice_pdf.blade.php` の `<meta name="viewport" content="width=800">` は
そのままにする。A4 幅前提の flex レイアウトを崩さないためだが、
iPhone では約 47% に縮小表示され、ピンチズームしないと読みづらい。

閲覧性を上げるなら `width=device-width` にしてスマホ用のスタイルを別途当てることになるが、
**印刷結果のレイアウトを保つことを優先**して今回は触らない。
実機確認（手順 6）で読めなさが問題になるようなら、別タスクとして切り出す。
