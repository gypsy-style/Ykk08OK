# 請求書を外部ブラウザで開く 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** 各月の請求書ページを LIFF から切り離して署名付き URL の通常ページにし、一覧から Safari / Chrome で開かせることで、iPhone でも「PDF保存」ボタンが動くようにする。

**Architecture:** 請求書一覧は LIFF のまま（LINE ログインで本人確認）。一覧 HTML を組み立てるときに月ごとの署名付き URL（有効期限1日）を発行し、リンククリックを `liff.openWindow({ external: true })` で横取りして外部ブラウザを起動する。遷移先の請求書ページは LIFF SDK も AJAX も使わないサーバーサイドレンダリングにし、署名検証だけで本人確認を済ませる。外部ブラウザなので `window.print()` が動作する。

**Tech Stack:** Laravel 9 / Blade / Vite / LIFF SDK v2 / 素のJS

## Global Constraints

- **テストコードは書かない**（プロジェクト方針）。各タスクの検証は手動確認で行う
- **`resources/js/liff_invoice_list.js` を変更するため、デプロイ時に `npm run build` と `public/build` の手動アップロードが必要。** `public/build` は git 管理外。これを忘れると一覧の外部ブラウザ起動が効かない
- 既存ファイルのスタイルに合わせる。`MerchantController` は手続き型で `compact()` を多用する既存の書き方を踏襲する
- 対象月の判定は必ず `InvoiceService::isFixedMonth()` を使う（当月は未確定）
- 集計ロジック（`InvoiceService::forMonth()`）と請求書のレイアウト（`partials/invoice_pdf.blade.php`）は**一切変更しない**
- 署名付き URL の有効期限は **1日**（`now()->addDay()`）
- `App\Http\Controllers\MerchantController` は `routes/web.php` で `UserMerchantController` としてエイリアスされている
- 設計の出典: `docs/superpowers/specs/2026-08-04-invoice-external-browser-design.md`

## ファイル構成

**新規作成**

| ファイル | 責務 |
|---|---|
| `resources/views/merchants/invoice_expired.blade.php` | 署名切れ・未確定月のときの案内ページ |

**変更**

| ファイル | 変更内容 |
|---|---|
| `routes/web.php:91` | `merchants.invoice` を署名付きの `{merchant}/{month}` 形式に変更 |
| `routes/web.php:73` | `POST /api/merchant/invoice` を削除 |
| `app/Http/Controllers/MerchantController.php` | `invoicePdf()` を署名検証＋SSRに書き換え、`getInvoice()` を削除、`getInvoiceList()` で署名URLを発行 |
| `resources/views/merchants/invoice_pdf.blade.php` | LIFF依存を外し請求書を直接描画 |
| `resources/views/merchants/partials/invoice_list.blade.php` | リンク先を署名URLに、クラス `js-open-invoice` を付与 |
| `resources/views/partials/invoice_pdf_style.blade.php` | 保存方法の注意書き用スタイルを追加 |
| `resources/js/liff_invoice_list.js` | クリックを横取りして `liff.openWindow({ external: true })` |
| `vite.config.js` | input から `liff_invoice_pdf.js` を削除 |

**削除**

| ファイル | 理由 |
|---|---|
| `resources/js/liff_invoice_pdf.js` | 請求書ページがSSRになり不要 |

---

### Task 1: 署名付きURLで請求書ページをSSR表示する

このタスクの完了時点で、手で発行した署名付きURLをブラウザで開くと請求書が表示される。
一覧側はまだ旧リンクのままなので、一覧からは遷移できない（Task 2 で繋ぐ）。

**Files:**
- Modify: `routes/web.php:91`
- Modify: `app/Http/Controllers/MerchantController.php:272-276`（`invoicePdf()`）
- Create: `resources/views/merchants/invoice_expired.blade.php`
- Modify: `resources/views/merchants/invoice_pdf.blade.php`（全面）
- Modify: `resources/views/partials/invoice_pdf_style.blade.php`（末尾付近に追記）

**Interfaces:**
- Consumes: `InvoiceService::forMonth(Merchant $merchant, string $month): array`（既存・変更しない）、`InvoiceService::isFixedMonth(string $month): bool`（既存）
- Produces: 名前付きルート `merchants.invoice`（パラメータ `merchant`＝加盟店ID, `month`＝`YYYY-MM`）。Task 2 が `URL::temporarySignedRoute('merchants.invoice', ...)` で参照する

- [x] **Step 1: ルートを署名付きの形式に変える**

`routes/web.php:91` の1行を差し替える。

```php
Route::get('/merchants/invoice/{merchant}/{month}', [UserMerchantController::class, 'invoicePdf'])
    ->where('month', '[0-9]{4}-[0-9]{2}')
    ->name('merchants.invoice');
```

`{merchant}` は加盟店IDをそのまま受ける。型宣言を付けないのでルートモデルバインディングは働かない。

- [x] **Step 2: `invoicePdf()` を書き換える**

`app/Http/Controllers/MerchantController.php` の `invoicePdf()`（272行目付近）を丸ごと差し替える。

```php
    public function invoicePdf($merchantId, $month, Request $request)
    {
        if (!$request->hasValidSignature()) {
            return response()->view('merchants.invoice_expired', [
                'message' => 'このリンクの有効期限が切れています。LINEの請求書一覧からもう一度開いてください。',
            ], 403);
        }

        $merchant = Merchant::findOrFail($merchantId);

        // 当月は未確定のため表示しない
        if (!$this->invoiceService->isFixedMonth($month)) {
            return response()->view('merchants.invoice_expired', [
                'message' => 'この月の請求書はまだ確定していません。',
            ], 403);
        }

        $invoice = $this->invoiceService->forMonth($merchant, $month);

        $productAgg = $invoice['products'];
        $monthSubtotal = $invoice['subtotal'];
        $monthShippingFee = $invoice['shipping_fee'];
        $monthTaxAmount = $invoice['tax'];
        $monthGrandTotal = $invoice['grand_total'];
        $invoiceDate = $invoice['invoice_date'];
        $invoiceNumber = $invoice['invoice_number'];

        $companyName = Setting::getValue('company_name', '');
        $companyDetail = Setting::getValue('company_detail', '');
        $companySeal = Setting::getValue('company_seal', '');
        $companyBankInfo = Setting::getValue('company_bank_info', '');
        $companyPaymentNote = Setting::getValue('company_payment_note', '');

        // 印刷時のファイル名になる
        $pdfFilename = 'invoice_' . $merchant->id . '_' . $invoiceDate->format('Ymd');

        return view('merchants.invoice_pdf', compact(
            'merchant',
            'productAgg',
            'monthSubtotal',
            'monthShippingFee',
            'monthTaxAmount',
            'monthGrandTotal',
            'invoiceDate',
            'invoiceNumber',
            'month',
            'companyName',
            'companyDetail',
            'companySeal',
            'companyBankInfo',
            'companyPaymentNote',
            'pdfFilename'
        ));
    }
```

`getInvoice()` はまだ残しておく（Task 3 で削除する）。

- [x] **Step 3: 案内ページを作る**

`resources/views/merchants/invoice_expired.blade.php`

```blade
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>請求書</title>
<style>
body {
  font-family: "Yu Gothic", "Hiragino Kaku Gothic ProN", sans-serif;
  background: #f5f5f5;
  margin: 0;
  padding: 60px 24px;
  color: #333;
  line-height: 1.8;
}

.box {
  max-width: 480px;
  margin: auto;
  background: #fff;
  border-radius: 10px;
  padding: 32px 28px;
  text-align: center;
}
</style>
</head>
<body>
<div class="box">
  <p>{{ $message }}</p>
</div>
</body>
</html>
```

- [x] **Step 4: 請求書ページからLIFF依存を外す**

`resources/views/merchants/invoice_pdf.blade.php` を丸ごと差し替える。

```blade
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=800">
<title>請求書</title>
@include('partials.invoice_pdf_style')
</head>
<body>

<div class="btn-area">
  <button type="button" id="save-pdf-btn">PDF保存</button>
  <p class="btn-note">ボタンを押すと印刷画面が開きます。iPhoneはプレビューを広げてから、共有ボタンで「ファイルに保存」を選んでください。</p>
</div>

<div class="invoice">
@include('partials.invoice_pdf')
</div>

<script>
(function () {
    var originalTitle = document.title;

    function restoreTitle() {
        document.title = originalTitle;
        window.removeEventListener('afterprint', restoreTitle);
    }

    document.getElementById('save-pdf-btn').addEventListener('click', function () {
        // 印刷ダイアログの既定ファイル名になる
        document.title = @json($pdfFilename);
        window.addEventListener('afterprint', restoreTitle);
        window.print();
    });
})();
</script>

</body>
</html>
```

変更点: `csrf-token` の meta を削除、`window.LIFF_ID` / `LIFF_MOCK` / `INVOICE_MONTH` の埋め込みを削除、`@vite(['resources/js/liff_invoice_pdf.js'])` を削除、`.btn-area` の `style="display:none;"` を削除、`<div id="invoice">読み込み中...</div>` を `@include('partials.invoice_pdf')` に置換。

- [x] **Step 5: 注意書きのスタイルを追加する**

`resources/views/partials/invoice_pdf_style.blade.php` の `.btn-area` ルール（199行目付近）の直後に追記する。

```css
.btn-note {
  max-width: 760px;
  margin: 10px auto 0;
  font-size: 12px;
  color: #666;
  line-height: 1.6;
  text-align: center;
}
```

`@media print` の `.btn-area { display: none; }` が親ごと隠すので、印刷時の対応は不要。

- [x] **Step 6: 署名付きURLを発行して表示を確認する**

Run:

```bash
php artisan tinker --execute="echo URL::temporarySignedRoute('merchants.invoice', now()->addDay(), ['merchant' => 137, 'month' => '2026-06']), PHP_EOL;"
```

`137` は本番テスト加盟店（サロンテスト）。ローカルで確定注文のある加盟店IDと月に読み替える。

出力された URL をブラウザで開く。

Expected:
- 請求書が表示される（金額・商品・振込先が管理画面の `admin.sales.invoice` と一致すること）
- 「PDF保存」ボタンと注意書きが上部に出ている
- ボタンを押すと印刷ダイアログが開き、ファイル名が `invoice_137_20260701` 形式になっている

- [x] **Step 7: 署名の検証を確認する**

Run: URL から `&signature=...` を削って開く

Expected: 「このリンクの有効期限が切れています。」が 403 で表示される

Run: URL の `/137/` を別の加盟店IDに書き換えて開く

Expected: 同じく 403（署名が一致しないため）

Run: 当月（例 `2026-08`）の署名付きURLを発行して開く

Expected: 「この月の請求書はまだ確定していません。」が 403 で表示される

- [x] **Step 8: コミット**

```bash
git add routes/web.php app/Http/Controllers/MerchantController.php resources/views/merchants/invoice_pdf.blade.php resources/views/merchants/invoice_expired.blade.php resources/views/partials/invoice_pdf_style.blade.php
git commit -m "請求書ページを署名付きURLのサーバーサイドレンダリングに変更"
```

---

### Task 2: 一覧から署名付きURLを発行し外部ブラウザで開く

**Files:**
- Modify: `app/Http/Controllers/MerchantController.php:244-270`（`getInvoiceList()`）
- Modify: `resources/views/merchants/partials/invoice_list.blade.php:9`
- Modify: `resources/js/liff_invoice_list.js:45-47`

**Interfaces:**
- Consumes: 名前付きルート `merchants.invoice`（Task 1 で定義）
- Produces: 一覧の各月配列に `url` キー（署名付き絶対URL・有効期限1日）。リンクに `js-open-invoice` クラス

- [x] **Step 1: `URL` ファサードを import する**

`app/Http/Controllers/MerchantController.php` の import 群（14行目 `use Illuminate\Validation\Rule;` の直後）に追加する。

```php
use Illuminate\Support\Facades\URL;
```

- [x] **Step 2: 一覧に署名付きURLを持たせる**

`getInvoiceList()` の `$monthlyInvoices` を組み立てている箇所（265行目付近）の直後に追記する。

```php
        // 当月は未確定のため前月までを対象とする
        $monthlyInvoices = $this->invoiceService->monthlyBreakdown($merchant);

        // 請求書ページは外部ブラウザで開くため、LIFF認証ではなく署名付きURLで本人確認する。
        // 一覧を開くたびに再発行されるので有効期限は短くてよい。
        foreach ($monthlyInvoices as $invoiceMonth => $invoice) {
            $monthlyInvoices[$invoiceMonth]['url'] = URL::temporarySignedRoute(
                'merchants.invoice',
                Carbon::now()->addDay(),
                ['merchant' => $merchant->id, 'month' => $invoiceMonth]
            );
        }
```

- [x] **Step 3: 一覧のリンクを差し替える**

`resources/views/merchants/partials/invoice_list.blade.php:9` を差し替える。

```blade
                <a href="{{ $invoice['url'] }}" class="js-open-invoice" style="display:inline-block;background:#4a5d78;color:#fff;padding:8px 18px;border-radius:16px;font-size:12px;font-weight:bold;text-decoration:none;">PDFを表示</a>
```

`href` は残す。LIFF が使えない環境でも通常遷移で開けるようにするため。

- [x] **Step 4: クリックを横取りして外部ブラウザを開く**

`resources/js/liff_invoice_list.js` の `invoiceListContainer.innerHTML = invoiceList.html;`（47行目）の直後に追記する。

```js
        // 請求書ページは外部ブラウザ（Safari / Chrome）で開く。
        // LIFFブラウザは WKWebView で window.print() が動かず、PDF保存ができないため。
        invoiceListContainer.addEventListener('click', function (e) {
            const link = e.target.closest('.js-open-invoice');
            if (!link) {
                return;
            }
            if (window.LIFF_MOCK || !liff.isInClient()) {
                return; // 既に外部ブラウザなら通常遷移
            }
            e.preventDefault();
            liff.openWindow({ url: link.href, external: true });
        });
```

リンクは `innerHTML` で後から差し込まれるため、コンテナ側でのイベント委譲にする。

- [x] **Step 5: ビルドしてローカルで確認する**

Run:

```bash
npm run build
```

Expected: エラーなく完了する

`LIFF_MOCK=true` のローカル（`http://localhost:8884`）で請求書一覧を開く。

Expected:
- 確定月の一覧が従来通り表示される
- 「PDFを表示」をタップすると同じタブで請求書が開く（`LIFF_MOCK` のため通常遷移）
- 請求書の金額が管理画面の請求書と一致する

- [x] **Step 6: コミット**

```bash
git add app/Http/Controllers/MerchantController.php resources/views/merchants/partials/invoice_list.blade.php resources/js/liff_invoice_list.js
git commit -m "請求書一覧から署名付きURLを発行し外部ブラウザで開くようにする"
```

---

### Task 3: 不要になったLIFF経由の請求書取得を削除する

**Files:**
- Modify: `routes/web.php:73`（削除）
- Modify: `app/Http/Controllers/MerchantController.php:278-343`（`getInvoice()` を削除）
- Delete: `resources/js/liff_invoice_pdf.js`
- Modify: `vite.config.js`

**Interfaces:**
- Consumes: なし
- Produces: なし（削除のみ）

- [x] **Step 1: 参照が残っていないことを確認する**

Run:

```bash
grep -rn "liff_invoice_pdf\|api/merchant/invoice\b\|getInvoice\b" resources app routes vite.config.js
```

Expected: `getInvoiceList` 以外のヒットが、これから消す4箇所（`vite.config.js` の input、`routes/web.php:73`、`MerchantController::getInvoice()`、`resources/js/liff_invoice_pdf.js` 自身）だけであること。他にヒットしたら削除を中止して報告する。

- [x] **Step 2: APIルートを削除する**

`routes/web.php:73` の次の1行を削除する。

```php
Route::post('/api/merchant/invoice', [UserMerchantController::class, 'getInvoice']);
```

- [x] **Step 3: `getInvoice()` を削除する**

`app/Http/Controllers/MerchantController.php` の `getInvoice()` メソッドを丸ごと削除する（`getInvoiceList()` は残す）。

- [x] **Step 4: JSファイルを削除し Vite の input から外す**

Run:

```bash
git rm resources/js/liff_invoice_pdf.js
```

`vite.config.js` の input 配列から次の1行を削除する。

```js
                'resources/js/liff_invoice_pdf.js',
```

- [x] **Step 5: ビルドと表示を確認する**

Run:

```bash
npm run build && php artisan view:cache && php artisan view:clear
```

Expected: どちらもエラーなく完了する

Task 1 Step 6 と同じ手順で署名付きURLを発行し、請求書が変わらず表示されること、一覧も従来通り表示されることを確認する。

- [x] **Step 6: コミット**

```bash
git add routes/web.php app/Http/Controllers/MerchantController.php vite.config.js resources/js/liff_invoice_pdf.js
git commit -m "LIFF経由の請求書取得APIとJSを削除"
```

---

### Task 4: 実機確認

コードの変更は無い。本番反映と実機での確認だけを行う。
**このタスクはユーザーの確認が必要**なので、Task 3 まで終えた時点で一度報告する。

**Files:** なし

**Interfaces:**
- Consumes: Task 1〜3 の成果物
- Produces: なし

- [ ] **Step 1: 本番へ反映する**

```bash
git push origin main
```

`npm run build` した `public/build` を本番へ手動アップロードする。**これを忘れると外部ブラウザ起動が効かず、一覧のリンクが LIFF ブラウザ内で開いてしまう。**

- [ ] **Step 2: iPhone 実機で確認する**

LINE から請求書一覧を開き、順に確認する。

Expected:
1. 「PDFを表示」をタップすると **Safari が起動する**（LINEのアプリ内ブラウザのままではない）
2. Safari で請求書が表示され、金額・商品・振込先が管理画面の請求書と一致する
3. 「PDF保存」をタップすると印刷画面が開く
4. プレビューを広げて共有 →「ファイルに保存」で PDF として保存できる
5. 保存された PDF のファイル名が `invoice_{加盟店ID}_{YYYYMMDD}` になっている

- [ ] **Step 3: Android 実機で確認する**

Expected: Chrome が起動し、印刷画面から「PDF に保存」で保存できる

- [ ] **Step 4: 有効期限を確認する**

1日以上前に発行した URL（Task 1 Step 6 で発行したもの）を開く。

Expected: 「このリンクの有効期限が切れています。」が表示され、一覧から開き直すと閲覧できる

- [ ] **Step 5: 閲覧性を判断する**

`viewport` が `width=800` 固定のため、iPhone では約47%に縮小表示される。
実機で読みづらさが問題になる場合は、**このタスクでは直さず**別タスクとして起票する。
印刷結果のレイアウトを保つことを優先する判断（設計書「未解決の課題」）のため。
