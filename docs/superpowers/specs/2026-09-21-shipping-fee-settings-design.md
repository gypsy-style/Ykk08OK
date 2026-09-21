# 都道府県別の送料設定画面

作成日: 2026-09-21

指示元: `GO-ON修正20260918.pdf` の 5 ページ目
「送料設定 ／ 送料設定金額を登録でき、設定金額前と後で送料を設定できるようにする」

## 今回のスコープ

**管理画面の設定に「送料設定」ページを追加するところまで。** 保存した値を注文に反映させる
のは後続フェーズとし、今回は実装しない。

対象

- 47 都道府県の一覧を `config/prefectures.php` に切り出す
- 管理画面 `/admin/settings/shipping` の追加（表示・保存）
- 送料設定金額 1 つ ＋ 47 都道府県 × 2 列の入力

対象外（後続フェーズ。「後続フェーズで扱うこと」に決定事項を残す）

- `merchants.prefecture` の追加と、加盟店の住所を登録・編集する画面への都道府県セレクト設置
- 注文への送料の自動反映
- 既存データへ `prefecture` を埋める作業

今回の実装だけでは、保存した送料はどこからも参照されない。画面と DB だけが先に用意される
状態になる。これは意図したもので、送料が意図しないタイミングで有効化されるのを防ぐため。

## 背景

送料は現在、完全に無効化されている。`OrderController::register()` :172-174 と
`OrderController::store()` :255-257 のどちらにも

```php
// 送料の計算（20,000円以下なら770円）
// $shippingFee = ($totalPrice <= 20000) ? 770 : 0;
$shippingFee = 0;
```

というコメントアウト済みのロジックが残っており、全注文が送料 0 円で保存される。
この「20,000 円以下なら一律 770 円」を、都道府県ごとに管理画面から設定できる形に
置き換えるのが最終的な目的。本設計はその第一歩として、設定を入れる器だけを作る。

## データモデル

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

同じ配列が `resources/views/merchants/create.blade.php:27` にベタ書きされている
（LIFF の加盟店新規登録画面の都道府県セレクト）。この画面も `config('prefectures')` を
参照する形に置き換え、一覧の定義を 1 箇所にまとめる。挙動は変わらない。

### 送料設定の保存先

既存の `settings` テーブル（キー・バリュー型、`Setting::getValue()` /
`Setting::updateOrCreate()`）に 2 行だけ追加する。専用テーブルもマイグレーションも作らない。

| key | value | 説明 |
| --- | --- | --- |
| `shipping_threshold` | 数値文字列または空 | 送料設定金額（円・税込） |
| `shipping_fees` | JSON 文字列 | 都道府県別の送料。`{"北海道":{"under":1200,"over":0}, ...}` |

- `under` = 税込商品合計が送料設定金額**以下**のときの送料
- `over` = 税込商品合計が送料設定金額を**超えた**ときの送料
- `settings.value` は text なので JSON が入る。SQL で都道府県を引く必要はなく、
  1 行読んで PHP 側で連想配列として引くだけなので JSON で問題ない

`under` / `over` という境界の定義は後続フェーズの計算ロジックで使うものだが、画面の
ラベルと保存形式がここで決まるため本設計に含める。閾値を税込で比較するのは PDF の
入力欄が「円（税込）」表記のため。境界を「以下なら `under`」とするのは、
コメントアウトされていた旧ロジック `($totalPrice <= 20000) ? 770 : 0` と一致させるため。

## 画面

既存の設定セクションのパターンに合わせる。

| 追加・変更するもの | 内容 |
| --- | --- |
| `routes/web.php` | `GET /admin/settings/shipping` → `shipping`（name: `settings.shipping`）、`POST /admin/settings/shipping` → `updateShipping`（name: `settings.update_shipping`）。既存の設定ルート群 :160-174 と同じミドルウェア配下に置く |
| `app/Http/Controllers/Admin/SettingController.php` | `shipping()` と `updateShipping()` を追加 |
| `resources/views/admin/settings/_nav.blade.php` | 「送料設定」の `<li>` を 1 行追加。位置は「請求書LINE通知」の直前（PDF p.5 の並びに合わせる） |
| `resources/views/admin/settings/shipping.blade.php` | 新規作成 |

### ビューの構造

他の設定ページと同じ `<dl class="lma-form_box">` ＋ `@include('admin.settings._nav')` の
形にそろえる（`resources/views/admin/settings/privacy_policy.blade.php:99-118` が代表例）。

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
- 47 行は `config('prefectures')` をループして出す

入力名は `threshold`、`fees[北海道][under]`、`fees[北海道][over]` の形とする。

レイアウトは既存の `admin.css` のクラスで組めるところまでで済ませ、足りなければ
このビュー内のインライン `<style>` で補う。`admin.css` を触ると `npm run build` と
FTP アップロードが必要になるため、そちらは避ける。

### バリデーションと保存

- `threshold`: `nullable|integer|min:0`
- `fees.*.under` / `fees.*.over`: `nullable|integer|min:0`

保存時、空欄は 0 として JSON 化する。都道府県キーは `config('prefectures')` に
含まれるものだけを採用し、それ以外は捨てる。`Setting::updateOrCreate()` を 2 回呼び、
`redirect()->route('admin.settings.shipping')->with('success', '送料設定を保存しました')`
で戻す。エラー表示は他の設定ページと同じ形にそろえる。

## 後続フェーズで扱うこと

今回は実装しないが、ブレストで決めた方針を残しておく。

### 加盟店の都道府県

送料を都道府県で引くには加盟店の都道府県が必要だが、`merchants` には専用カラムが無い。
住所は `address` 単一カラムのフリーテキストで、都道府県は文字列の先頭に埋もれている
（`database/migrations/2024_12_11_150949_create_merchants_table.php:23-25`）。

決めたこと。

- `address` は分割せず、`merchants.prefecture`（string・nullable）を**併設**する。
  PDF p.4 のモックは住所欄から都道府県が抜けているが、分割すると
  `$merchant->address` の表示 5 箇所（`Admin/OrderController.php:104`、
  `Admin/SalesController.php:231`、`admin/sales/show.blade.php:20`、
  `admin/orders/show.blade.php:46`、`MerchantController.php:437` 経由の
  `resources/js/liff_information.js:91`）の改修が必要になり、かつ移行完了まで
  新規分と既存分でデータが不揃いになるため
- nullable にして、未設定は送料 0 円＝現状維持として扱う
- 加盟店の住所を登録・編集できる全 6 画面にセレクトを置く。うち
  `resources/views/merchants/create.blade.php` は都道府県セレクトが既にあり、
  submit 時に JS が `prefecture + address_detail` を hidden の `address` に連結している
  （:34, :86-92）だけで保存はしていないので、保存対象に加えるだけでよい。
  残り 5 画面は `address` 単一のフリーテキストなので、セレクトを追加したうえで、
  住所テキストの先頭から都道府県を検出して自動選択する JS を入れ、二重入力を避ける

### 既存データへの反映

`php artisan merchants:backfill-prefecture`（`--dry-run` 付き）を新設し、`address` の
先頭を `config('prefectures')` と前方一致させて `prefecture` に入れる。`address` は
変更しない。

マイグレーションにはしない。デプロイ（`.github/workflows/deploy.yml`）が `git pull` の
あと `php artisan migrate --force` を自動実行するため、マイグレーションにすると
デプロイと同時に走り、送料設定の入力が終わる前に送料が有効化されうるからである。

調査時点のローカル 23 件はすべて都道府県名で始まっており、前方一致で全件抽出できた。

### 注文への反映

`app/Services/ShippingFeeService.php` を新設し、`InvoiceService` と同じく
「送料の扱いを変えるときはここだけを直す」位置づけにする。

`OrderController::register()` :172-176 と `::store()` :255-257 の `$shippingFee = 0;` を
差し替え、結果を `orders.shipping_fee` に確定保存する。設定を後から変えても過去注文は
動かない。請求書（`InvoiceService`）は `orders.shipping_fee` を合算しているだけなので
変更不要。管理画面の手動上書き（`Admin/OrderController.php:160-176`、ステータス 2 のみ）も
そのまま残す。

カート画面にも手当てが要る。`resources/views/order/register.blade.php:66` は
「送料：未確定」のベタ書きで、JS（:223-232）が更新しようとしている `.shipping_price`
要素が存在しない。また :60 と JS :227 の消費税が `合計 − 小計` で計算されており、
送料が 0 でなくなると消費税に送料が混ざる。どちらも同フェーズで直す。

代理店の自社注文（`Agency/OrderController` は `merchant_id = 0` で発注し
`shipping_fee` を渡さない）は対象外で、送料 0 円のまま。

### リリース順

1. 本設計（送料設定画面）をデプロイ。保存した値はまだどこからも参照されない
2. 管理画面で送料設定金額と都道府県別の送料を入力する
3. 都道府県カラムと注文への反映をデプロイ。この時点では全加盟店の `prefecture` が
   空なので送料は全件 0 円で、挙動は変わらない
4. `merchants:backfill-prefecture --dry-run` で未マッチを確認し、本実行する
5. ここで初めて送料が有効になる。未マッチだった加盟店は管理画面から手動で埋める

## 確認方法

本プロジェクトはテストコードを書かない方針のため、画面と artisan tinker で確認する。

- `/admin/settings/shipping` が開き、サイドバーで「送料設定」が選択状態になること
- 送料設定金額と、いくつかの都道府県に値を入れて保存し、再読み込みして保持されていること
- 空欄のまま保存でき、再読み込みで 0 として表示されること
- 負数や文字列を入れたときにバリデーションエラーが出ること
- tinker で `Setting::getValue('shipping_fees')` が想定どおりの JSON になっていること
- 他の設定ページ（カスタム CSS など）が壊れていないこと
- LIFF の加盟店新規登録画面の都道府県セレクトが、config 参照に変えても
  従来どおり 47 件表示され、郵便番号検索での自動選択も動くこと
