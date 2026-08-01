# 登録情報画面：請求書ボタンの上部配置

作成日: 2026-08-01

## 背景

加盟店向け登録情報画面（`/merchants/information`）の請求書ボタンは、現在ページ最下部の「登録スタッフ一覧」の下にある。請求書への導線としては目立たないため、ページ上部（代理店名より上）へ移す。あわせて、請求書が1件も無い加盟店にはボタンを出さない。

## 現状

```
──[ 登録情報 ]────────────
 ┌─ 白ブロック ──────────┐
 │ 代理店名 / サロン名 / …   │
 │  ( 登録情報を修正する )   │
 └───────────────────┘
      ( 登録スタッフ一覧 )
      ( 請求書 )              ← 現在地
```

ボタンはHTMLに常に存在し、`resources/js/liff_information.js` がオーナー判定（`userId == merchantUserId`）を通ったときに `display:block` にしている。

## レイアウト上の制約

`.lmf-title_block.tall` が負のマージンで後続要素を引き上げ、白ブロックをヘッダー帯に重ねている。

```css
/* resources/css/front.css */
.lmf-title_block.tall { padding: 30px 10px 50px; margin-bottom: -50px; }
.lmf-content { padding: 30px; }  /* --gap-m */
```

差し引き、`.lmf-content` の先頭要素は帯に20pxめり込む。請求書ボタンを素直に先頭へ置くと、ボタンの上部が帯に埋まって崩れる。

## 決定した方針

ボタン側で負のマージンを打ち消し、帯への重なりを解除する。ローカルで重なりを活かす案（白ピル）と見比べた上での判断。LIFFは端末幅の幅が広く、20pxの重なりを前提にした見た目は端末によってズレやすいため、確実性を優先する。

```
──[ 登録情報 ]────────────
      ( 請求書 )              ← 帯の下に30px空けて配置
 ┌─ 白ブロック ──────────┐
 │ 代理店名 / サロン名 / …   │
 │  ( 登録情報を修正する )   │
 └───────────────────┘
      ( 登録スタッフ一覧 )
```

白ブロックがヘッダー帯に重ならなくなる点は許容する。

## 変更内容

作業ツリーには案を見比べるための暫定コード（`?ui=` によるクラス切り替えと `.btn_topover`）が入っている。実装時にまず取り除く。

### 1. CSS（`resources/css/front.css`）

ボタンの色バリエーション定義の直後に追加する。

```css
/* コンテンツ先頭に置くボタン
   .lmf-title_block.tall の margin-bottom:-50px を打ち消して帯へのめり込みを防ぐ */
.lmf-btn_box.btn_top {
	margin-top: 50px;
}
```

### 2. ビュー（`resources/views/merchants/information.blade.php`）

請求書ボタンを `.lmf-content` の先頭（白ブロックの上）へ移動し、`btn_top` を付ける。最下部の請求書ボタンは削除。「登録スタッフ一覧」は現状維持。色は既存の `lmf-btn_box` デフォルト（`--color-cust_secondary`）のまま。

### 3. 集計サービス（`app/Services/InvoiceService.php`）

請求書の有無を判定するメソッドを追加する。

```php
public function hasInvoice(Merchant $merchant): bool
{
    return Order::where('merchant_id', $merchant->id)
        ->whereIn('status', self::SALES_STATUSES)
        ->where('created_at', '<', Carbon::now()->startOfMonth())
        ->exists();
}
```

`monthlyBreakdown()` の結果が空かどうかで判定する案もあるが、あちらは `details.product` まで読み込むため真偽値ひとつには重い。`exists()` なら1クエリで済む。除外条件（確定ステータス `SALES_STATUSES`・当月除外）は既存定義をそのまま使うので、一覧側と判定がズレない。

### 4. API（`app/Http/Controllers/MerchantController.php`）

`getMerchantInformation()` のレスポンスに `has_invoice` を追加する。判定対象は、そのメソッドが解決した加盟店（オーナーの店舗、または `MerchantMember` 経由で辿った店舗）。

### 5. フロント（`resources/js/liff_information.js`）

既存のオーナー判定ブロックの中で、`has_invoice` が真のときだけ請求書ボタンを表示する。

```js
if (userId == merchantUserId) {
    // 「登録情報を修正する」「登録スタッフ一覧」は従来どおり
    if (data.has_invoice) {
        document.querySelector('.lmf-btn_box.invoice_list').style.display = 'block';
    }
}
```

要素はHTMLに常に存在するため `querySelector` が null を返すことはない。`has_invoice` が欠けている場合は偽として扱われ、ボタンは非表示のままになる。

## 表示条件のまとめ

| ログインユーザー | 確定済み請求書 | 請求書ボタン |
|---|---|---|
| オーナー | あり | 表示 |
| オーナー | なし | 非表示 |
| スタッフ | あり／なし | 非表示 |

「確定済み請求書」は、その加盟店の注文のうち status が 2/3/5/6 かつ当月より前に作成されたものが1件以上あること。当月分は未確定のため対象外。

## 確認方法

テストコードは書かない方針のため、ローカル（`localhost:8884`, `LIFF_MOCK=true`）で目視確認する。

1. 確定注文のある加盟店でボタンが帯に重ならず表示される
2. 確定注文を持たない加盟店でボタンが出ない
3. スタッフアカウントでボタンが出ない
4. ボタンから請求書一覧へ遷移できる

## 対象外

- 請求書一覧ページおよびPDFの中身
- 「登録スタッフ一覧」ボタンの位置
- ヘッダー帯の重なり演出を他ページで変えること
