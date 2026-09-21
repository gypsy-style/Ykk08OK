# 都道府県カラムの新設と都道府県別の送料設定

作成日: 2026-09-21

指示元: `GO-ON修正20260918.pdf` の 4〜5 ページ目

- p.4「住所を都道府県だけ分ける ／ 送料設定のため都道府県はドロップダウンで選択」
- p.5「送料設定 ／ 送料設定金額を登録でき、設定金額前と後で送料を設定できるようにする」

## 背景

現状、送料は完全に無効化されている。`OrderController::register()` と
`OrderController::store()` のどちらにも

```php
// 送料の計算（20,000円以下なら770円）
// $shippingFee = ($totalPrice <= 20000) ? 770 : 0;
$shippingFee = 0;
```

というコメントアウト済みのロジックが残っており、全注文が送料 0 円で保存される。
この「20,000 円以下なら一律 770 円」を、都道府県ごとに管理画面から設定できる形に置き換える。

また、送料を都道府県で引くには加盟店の都道府県が必要だが、`merchants` テーブルには
都道府県の専用カラムが存在しない。住所は `address` 単一カラムのフリーテキストで、
都道府県は文字列の先頭に埋もれているだけである。

- `merchants` の住所関連カラムは `postal_code1`(3) / `postal_code2`(4) / `address` のみ
  （`database/migrations/2024_12_11_150949_create_merchants_table.php:23-25`）
- 都道府県のセレクトが存在するのは LIFF の加盟店新規登録画面だけ
  （`resources/views/merchants/create.blade.php:25`）。しかもその値は保存されず、
  submit 時に JS が `prefecture + address_detail` を連結して hidden の `address` に
  詰めている（同 :34, :86-92）
- 残り 5 つの住所編集画面はすべて `address` 単一のフリーテキスト入力
- 調査時点のローカル 23 件は全件が都道府県名で始まっており、前方一致での抽出が可能

## スコープ

対象

- `merchants.prefecture` の追加と、加盟店の住所を登録・編集できる全 6 画面への都道府県セレクト設置
- 管理画面の設定に「送料設定」ページを追加（送料設定金額 1 つ ＋ 47 都道府県 × 2 列）
- LIFF の注文確認画面と注文保存処理への送料の自動反映
- 既存データへ `prefecture` を埋める artisan コマンド（実行は別フェーズ）

対象外

- `address` の内容変更。都道府県込みのフルアドレスのまま維持する
- 住所の表示箇所（発送ラベル・CSV・売上詳細・注文詳細・LIFF 加盟店情報）。
  `address` をそのまま使うため改修不要。なお請求書 PDF
  （`resources/views/partials/invoice_pdf.blade.php`）は加盟店の住所を出していない
- 代理店の自社注文（`Agency/OrderController` は `merchant_id = 0` で発注し
  `shipping_fee` を渡さない）。送料 0 円のまま
- `agencies` テーブルの住所
- 過去注文の送料の再計算
- 管理画面の送料手動上書き（`Admin/OrderController.php:160-176`、ステータス 2 のみ）。現状維持
- `InvoiceService`。`orders.shipping_fee` を合算しているだけなので変更不要

## 方針の決定と理由

### `address` は分割せず `prefecture` を併設する

PDF p.4 のモックでは住所欄から都道府県が抜けている（`兵庫県` / `宝塚市花屋敷荘園`）が、
本改修では `address` を都道府県込みのまま残し、`prefecture` を送料計算用のキーとして
並列に持つ。理由は次の 2 点。

- `$merchant->address` を表示している箇所が 5 箇所ある（`Admin/OrderController.php:104`、
  `Admin/SalesController.php:231`、`admin/sales/show.blade.php:20`、
  `admin/orders/show.blade.php:46`、`MerchantController.php:437` 経由の
  `resources/js/liff_information.js:91`）。分割するとこれらを
  `prefecture . address` に書き換える必要がある
- 一括変換を打つまでのあいだ、新規登録分は「都道府県なし」・既存分は「あり」で
  データが不揃いになる。併設ならこの期間が存在しない

将来 PDF の見た目どおりに `address` から都道府県を除去するかは、一括変換フェーズで
改めて判断する。本設計の範囲外とする。

### 都道府県は自動選択で二重入力を避ける

併設にすると、5 つの画面では都道府県をセレクトと住所テキストの先頭で 2 回入力することに
なる。これを避けるため、住所テキストの先頭から都道府県を検出してセレクトを自動選択する
JS を入れる。手動で選び直すこともできる。

### バックフィルはマイグレーションにしない

デプロイ（`.github/workflows/deploy.yml`）は `git pull` のあと
`php artisan migrate --force` を自動実行する。バックフィルをマイグレーションにすると
デプロイと同時に走ってしまい、送料設定の入力が終わる前に送料が有効化されうる。
そのため手動実行の artisan コマンドとする。スキーマ変更（カラム追加）は
通常のマイグレーションでよい。

## データモデル

### マイグレーション

`merchants` に `prefecture` を追加する。

- 型: string(10)
- nullable
- default: null
- 位置: `postal_code2` の後（`after('postal_code2')`）

nullable にする理由は 2 つ。既存レコードが空の状態でも編集画面から保存を通す必要が
あること、および未設定を「送料 0 円」＝現状維持として扱い段階的に移行できることである。

### モデル

`app/Models/Merchant.php` の `$fillable` に `prefecture` を追加する。

### 都道府県の一覧

`config/prefectures.php` を新設し、47 都道府県を北から順に定義する。

```php
<?php

return [
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
    '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
    '岐阜県', '静岡県', '愛知県', '三重県',
    '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
    '鳥取県', '島根県', '岡山県', '広島県', '山口県',
    '徳島県', '香川県', '愛媛県', '高知県',
    '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
];
```

`resources/views/merchants/create.blade.php:27` にベタ書きされている同じ配列は
この config を参照する形に置き換える。

### 送料設定の保存先

既存の `settings` テーブル（キー・バリュー型、`Setting::getValue()` /
`Setting::updateOrCreate()`）に 2 行だけ追加する。専用テーブルは作らない。

| key | value | 説明 |
| --- | --- | --- |
| `shipping_threshold` | 数値文字列または空 | 送料設定金額（円・税込）。空なら送料機能オフ＝全件 0 円 |
| `shipping_fees` | JSON 文字列 | 都道府県別の送料。`{"北海道":{"under":1200,"over":0}, ...}` |

- `under` = 税込商品合計が送料設定金額**以下**のときの送料
- `over` = 税込商品合計が送料設定金額を**超えた**ときの送料
- `settings.value` は text なので JSON が入る。SQL で都道府県を引く必要はなく、
  1 行読んで PHP 側で連想配列として引くだけなので JSON で問題ない

## 送料計算

`app/Services/ShippingFeeService.php` を新設する。`InvoiceService` と同じく
「送料の扱いを変えるときはここだけを直す」位置づけのサービスとする。

```php
ShippingFeeService::calculate(?string $prefecture, int $subtotalExcludingTax): int
```

判定順序は次のとおり。

1. `shipping_threshold` が空または数値でない → **0 円**（送料機能オフ）
2. `$prefecture` が空 → **0 円**
3. `shipping_fees` に該当都道府県のエントリがない → **0 円**
4. 税込商品合計 `(int) round($subtotalExcludingTax * 1.1)` が閾値**以下** → `under`、
   閾値を**超える** → `over`

閾値を税込で比較するのは PDF の入力欄が「円（税込）」表記のため。境界値は
「以下なら `under`」とする。コメントアウトされていた旧ロジック
`($totalPrice <= 20000) ? 770 : 0` の境界の扱いと一致する。

送料そのものも税込として扱う。`InvoiceService::aggregate()` は
`grand_total = round($subtotal * 1.1) + $shippingFee`（`app/Services/InvoiceService.php:221`）
と、税を掛けたあとに送料を足しており、既存の扱いと整合する。

設定値の読み出しはリクエストごとに 1 回で足りるので、サービス内で
static プロパティにメモ化する。

## 画面

### 1. 送料設定ページ（管理画面）

既存の設定セクションのパターンに合わせる。

| 追加・変更するもの | 内容 |
| --- | --- |
| `routes/web.php` | `GET /admin/settings/shipping` → `shipping`、`POST /admin/settings/shipping` → `updateShipping`。既存の設定ルート群（:159-174）と同じミドルウェア配下に置く |
| `app/Http/Controllers/Admin/SettingController.php` | `shipping()` と `updateShipping()` を追加 |
| `resources/views/admin/settings/_nav.blade.php` | 「送料設定」の `<li>` を 1 行追加。位置は「請求書LINE通知」の直前（PDF p.5 の並びに合わせる） |
| `resources/views/admin/settings/shipping.blade.php` | 新規作成 |

ビューは他の設定ページと同じ構造にする。

```blade
<form action="{{ route('admin.settings.update_shipping') }}" method="POST">
    @csrf
    <dl class="lma-form_box">
        <dt>
            @include('admin.settings._nav', ['active' => 'shipping'])
        </dt>
        <dd>
            ... 送料設定金額 と 47 行の入力 ...
        </dd>
    </dl>
    <p class="lma-btn_box">
        <button type="submit" class="btn btn-primary">保存</button>
    </p>
</form>
```

入力欄の構成は PDF p.5 に合わせる。

- 上部に「送料設定金額」1 つ、単位は「円（税込）」
- その下に 2 列。左の見出しが「送料設定」、右が「送料設定金額を超えた場合」
- 各行は都道府県名 ＋ 入力欄 ＋「円（税込）」を左右に 1 つずつ

入力名は `threshold`、`fees[北海道][under]`、`fees[北海道][over]` の形とする。

バリデーション

- `threshold`: `nullable|integer|min:0`
- `fees.*.under` / `fees.*.over`: `nullable|integer|min:0`

保存時、空欄は 0 として JSON 化する。都道府県キーは `config('prefectures')` に
含まれるものだけを採用し、それ以外は捨てる。保存後は
`redirect()->route('admin.settings.shipping')->with('success', '送料設定を保存しました')`。

### 2. 都道府県セレクト（加盟店の住所を登録・編集する全 6 画面）

| # | ファイル | 現状の住所入力 | 対応 |
| --- | --- | --- | --- |
| 1 | `resources/views/merchants/create.blade.php` | 都道府県セレクト ＋ 市区町村以降 | セレクトは既存。値を保存するだけ |
| 2 | `resources/views/merchants/edit.blade.php:27` | `address` フリーテキスト | セレクト追加 ＋ 自動選択 |
| 3 | `resources/views/admin/merchants/create.blade.php:58` | `address` フリーテキスト | セレクト追加 ＋ 自動選択 |
| 4 | `resources/views/admin/merchants/edit.blade.php:73` | `address` フリーテキスト | セレクト追加 ＋ 自動選択 |
| 5 | `resources/views/agencies/merchants/create.blade.php:58` | `address` フリーテキスト | セレクト追加 ＋ 自動選択 |
| 6 | `resources/views/agencies/merchants/edit.blade.php:56` | `address` フリーテキスト | セレクト追加 ＋ 自動選択 |

セレクトのマークアップは
`resources/views/partials/_prefecture_select.blade.php` として共通化し、
`config('prefectures')` をループする。選択済みの値は変数で受け取る。

設置位置は PDF p.4 に合わせ、郵便番号 2 の直後・住所の直前とする。

自動選択の JS は 2〜6 の 5 画面に入れる。住所テキストの `input` イベントで
先頭の都道府県を前方一致で検出し、セレクトに反映する。すでに手動で選択済みの場合は
上書きしない。画面 1 は住所欄が「市区町村以降」で都道府県を含まないため、この JS は不要。

郵便番号検索（zipcloud）が動いている画面では、レスポンスの `address1` からも
セレクトを選択する。画面 1 にはこの処理がすでにある
（`resources/views/merchants/create.blade.php:70-76`）。

### 3. コントローラ（保存処理）

`prefecture` はすべての画面で `nullable` とする。既存レコードが空のまま
編集・保存されるケースを通すためで、未設定なら送料 0 円＝現状維持になる。

バリデーションルールは全画面共通で
`'prefecture' => ['nullable', 'string', Rule::in(config('prefectures'))]`。

| ファイル | 箇所 |
| --- | --- |
| `app/Http/Controllers/MerchantController.php` | `update()` :60-80 のバリデーションと `only()` 配列、`store()` :102-118 のバリデーションと保存 |
| `app/Http/Controllers/Admin/MerchantController.php` | :114 付近と :160 付近のバリデーションと保存 |
| `app/Http/Controllers/Agency/MerchantController.php` | :62 付近と :97 付近のバリデーションと保存 |

画面 1（LIFF 新規登録）は現在 `prefecture` を POST しているが保存していない。
バリデーションと保存対象に加えるだけでよい。hidden の `address` へ連結する既存の JS
（:86-92）はそのまま残す。

### 4. 注文への反映

| ファイル | 箇所 | 変更 |
| --- | --- | --- |
| `app/Http/Controllers/OrderController.php` | :172-176 `register()` | `$shippingFee = 0;` を `ShippingFeeService::calculate($merchant->prefecture, $totalPrice)` に差し替え。あわせて閾値・`under`・`over` の 3 値をビューに渡す |
| `app/Http/Controllers/OrderController.php` | :255-257 `store()` | 同じく差し替え。結果は既存どおり `orders.shipping_fee` に保存（:273） |
| `resources/views/order/register.blade.php` | :66 | 「送料：未確定」のベタ書きを `<span class="shipping_price">` に置き換える |
| `resources/views/order/register.blade.php` | :223-232 | JS の `let shippingFee = 0;` を、渡された 3 値を使った同じ判定に差し替える |

`register()` の `$totalPrice`（:168 で加算）と `store()` の `$totalPrice`（:253 で加算）は
どちらも税抜の商品合計であり、`ShippingFeeService::calculate()` の引数の定義と一致する。

`register()` の `$merchant`（:126-132）は加盟店が見つからない場合 null になりうる
（:133 が `$merchant->member_rank ?? 1` で null を許容している）。そのため
`optional($merchant)->prefecture` のように null セーフに渡す。`calculate()` の第 1 引数が
nullable なのはこのためで、null なら送料 0 円になる。`store()` 側は :226-228 で
加盟店が無ければ 404 を返しているので null にならない。

JS 側の判定は PHP と同じ順序にする。

```js
let taxIncluded = Math.round(total * 1.1);
let shippingFee = 0;
if (SHIPPING_THRESHOLD !== null) {
    shippingFee = taxIncluded <= SHIPPING_THRESHOLD ? SHIPPING_FEE_UNDER : SHIPPING_FEE_OVER;
}
let grandTotalTaxIncluded = taxIncluded + shippingFee;
```

#### あわせて直す消費税の計算

`resources/views/order/register.blade.php:60` は

```php
$taxAmount = $grandTotalTaxIncluded - $totalPrice;
```

となっており、`$grandTotalTaxIncluded` に送料が含まれるため、送料が 0 でなくなると
消費税の表示に送料が混ざる。JS 側の :227 も同じ式で同じ問題がある。どちらも
送料を引いた式に直す。現状は送料が常に 0 なので表面化していないが、本改修で顕在化する。

## 既存データのバックフィル

`php artisan merchants:backfill-prefecture` を新設する。

- `prefecture` が空の加盟店を対象に、`address` の先頭を `config('prefectures')` と
  前方一致させ、一致した都道府県を `prefecture` に入れる
- `address` は変更しない
- 論理削除済み（`deleted_at` が入っている）加盟店も対象に含める。将来復元されうるため
- `--dry-run` オプションで、更新対象の件数と、前方一致しなかった加盟店の
  ID・サロン名・住所の一覧を出力する。DB は変更しない
- 実行結果として「更新 N 件 / 未マッチ M 件」を表示する

マイグレーションにしない理由は「方針の決定と理由」に記載のとおり。

調査時点のローカル 23 件はすべて都道府県名で始まっており未マッチは 0 件だったが、
本番では未マッチが出る可能性がある。未マッチの加盟店は `prefecture` が空のまま
＝送料 0 円となるので、一覧を見て管理画面から手動で選択する運用とする。

## リリース手順

送料が意図せず有効化されないよう、次の順序で進める。

1. 本設計の実装をデプロイする。この時点では全加盟店の `prefecture` が空なので
   送料は全件 0 円で、挙動は現状と変わらない
2. 管理画面の「送料設定」で送料設定金額と都道府県別の送料を入力する
3. `php artisan merchants:backfill-prefecture --dry-run` を実行し、未マッチを確認する
4. `php artisan merchants:backfill-prefecture` を実行する
5. ここで初めて送料が有効になる。未マッチだった加盟店を管理画面から手動で埋める

手順 1 のデプロイでは `merchants.prefecture` を追加するマイグレーションが
`migrate --force` により自動実行される。カラム追加のみで既存データには影響しない。

`public/build` は git 管理外のため、ビューに Vite 経由のアセット変更が発生した場合は
ローカルで `npm run build` して FTP でアップロードする。本改修はインライン
`<script>` と既存の Blade のみで完結する想定なので、原則として不要。

## 確認方法

本プロジェクトはテストコードを書かない方針のため、画面と artisan tinker で確認する。

- 送料設定ページで値を保存し、再読み込みして保持されていること
- `ShippingFeeService::calculate()` を tinker で直接叩き、次を確認する
  - 閾値未設定 → 0 円
  - `prefecture` が null → 0 円
  - 税抜 18,000 円（税込 19,800 円）・閾値 20,000 円 → `under` の額
  - 税抜 19,000 円（税込 20,900 円）・閾値 20,000 円 → `over` の額
  - 税抜 18,182 円（税込 20,000 円・境界）・閾値 20,000 円 → `under` の額
- LIFF の注文確認画面で、数量を変えたときに送料と消費税と合計が正しく再計算されること
- 注文を保存し、`orders.shipping_fee` に想定の額が入っていること
- 注文詳細・請求書の送料表示が壊れていないこと
- `merchants:backfill-prefecture --dry-run` の出力が実データと合っていること
