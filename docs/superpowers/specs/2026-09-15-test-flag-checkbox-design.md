# テストフラグの代理店対応と「テストを含めない」チェックボックス

作成日: 2026-09-15

## 背景

2026-08-01 に `merchants.is_test` を導入し、本番の集計からテスト加盟店を除外する仕組みを稼働させた。
ただし現状は次の制約がある。

- フラグは加盟店にしかなく、代理店単位でテストを切れない
- フラグの ON/OFF は DB 直接操作（`UPDATE merchants SET is_test = 1 WHERE id = ?`）
- 除外はコードにハードコードされており、管理者がテストデータを見たいときに手段がない
- 一覧はテスト行を残してバッジを出し、金額・件数の集計からだけ抜いているため、一覧が汚れる

本改修では、代理店にも同じフラグを持たせ、管理画面から ON/OFF でき、
管理者向けの売上・受注画面には「テストを含めない」チェックボックス（デフォルト ON）を置く。

## スコープ

対象

- `agencies.is_test` の追加
- テストデータ判定ロジックの一元化
- 管理者画面へのチェックボックス設置と、ON 時の行レベル除外
- 管理画面の加盟店編集・代理店編集へのフラグ操作 UI

対象外

- 代理店ログイン側の画面（挙動は現状維持。常に除外）
- 請求書の詳細・PDF・LINE 送信（意図的に素通し。現状維持）
- 月次バッチ `SendMonthlyInvoiceLine`
- 操作ログ

## データモデル

### マイグレーション

`agencies` に `is_test` を追加する。`merchants.is_test` と同形。

- 型: boolean
- default: 0
- NOT NULL

### モデル

`app/Models/Agency.php`

- `$fillable` に `is_test` を追加
- `$casts` に `'is_test' => 'boolean'` を追加

## テストデータの定義

代理店のフラグが ON なら、その配下の加盟店は `merchants.is_test` の値に関わらずテスト扱いとする。
判定式は次のとおり。

```sql
select m.id from merchants m
left join agencies a on a.id = m.agency_id
where m.is_test = 1 or a.is_test = 1
```

`merchants.agency_id` は NULLABLE のため `left join` を使う。代理店未設定の加盟店は
`m.is_test` のみで判定される。

## 判定ロジックの一元化

判定式が 10 箇所以上に散らばるとズレる。既存の `InvoiceService::applyInvoiceScope($query, 'o')`
と同じ静的ヘルパーの形で `app/Services/TestDataFilter.php` を新設し、既存の
`whereNotIn('merchant_id', fn => merchants.is_test = 1)` をすべて置き換える。

```php
TestDataFilter::excludeMerchants($query, $alias = '');  // {alias}.merchant_id ベースの除外
TestDataFilter::excludeAgencies($query, $alias = '');   // 代理店別集計での代理店そのものの除外
TestDataFilter::isTestMerchant($merchant): bool;        // 単体判定
```

Global Scope は使わない。`DB::table` の生クエリが多くスコープが効かないため（既存方針を踏襲）。

Eloquent の `whereHas('merchant', ...)` で除外している箇所も、同じヘルパーに寄せる。

### 置き換え対象

| ファイル | 内容 |
|---|---|
| `Admin/SalesController::index` | 商品別売上、全体合計、送料件数、加盟店別売上 |
| `Admin/DashboardController::index` | 加盟店数、ステータス集計、売上集計 |
| `Admin/OrderController::index` | 代理店処理済み・本部処理済みの注文集計 |
| `Admin/ExportController::exportOrders` | CSV 出力 |
| `Agency/DashboardController::index` | 本日注文・売上・送料 |
| `Agency/OrderController::index` | 注文一覧 |
| `Agency/MerchantController::index` | 加盟店一覧・集計 |
| `SalonAnalyticsService` | 新規加盟店数、商品別売上（サロン詳細・代理店詳細は絞りを外す。後述） |
| `OrderController.php:316` | 代理店への通知メールのスキップ判定 |

代理店側の 3 コントローラはチェックボックスを持たず、常に除外。ヘルパーに置き換えるだけで
挙動は変わらない。

## チェックボックスの設計

### パラメータ

`exclude_test` を GET で受ける。**未指定なら 1（除外）扱い**とし、ブックマークや直リンクでも
デフォルト除外が効くようにする。

```php
$excludeTest = $request->query('exclude_test', '1') !== '0';
```

### Blade

hidden と checkbox のペアにする。チェックを外すと `exclude_test=0` が送信される。JavaScript は使わない。

```blade
<input type="hidden" name="exclude_test" value="0">
<label>
    <input type="checkbox" name="exclude_test" value="1" @checked($excludeTest)>
    テストを含めない
</label>
```

月ナビゲーション、ステータスタブ、CSV 出力リンク、AJAX の部分更新は、既存のクエリを
`request()->query()` から引き継いで `exclude_test` を落とさないようにする。

### 設置画面（管理者のみ）

| 画面 | ルート | 置き場所 |
|---|---|---|
| 売上管理一覧 | `admin/sales` | 月ナビの横 |
| ダッシュボード | `admin/index` | 見出し付近に単独フォーム |
| 受注一覧 | `admin/orders` | ステータスタブの横。CSV 出力リンクにも引き継ぐ |
| 新規加盟店数 | `admin/analytics/new-merchants` | 既存の月フィルタの横 |
| 商品別売上 | `admin/analytics/product-sales` | 既存の月フィルタの横 |

サロン詳細 `admin/analytics/salon/{merchant}` と代理店詳細 `admin/analytics/agency/{agency}` は
単一対象の詳細画面なのでチェックボックスは置かない。現状 `where('is_test', 0)` で絞っており
テスト対象だと表示できないため、この絞りを外して常に表示する。遷移元の一覧でチェックを
外さないとリンク自体が現れないため、詳細画面で重ねて絞る必要はない。

### ON 時の振る舞い

行ごと消す。従来の「行は残して集計からだけ抜く」方式はやめる。

チェックを外せばテスト行が戻り、既存のテストバッジ付きで表示されるため、テスト注文の
ステータス変更や請求書送信ボタンには到達できる。既存のバッジ表示
（`admin/sales/index.blade.php`、`admin/orders/index.blade.php`）はそのまま残す。

## フラグ操作 UI

### 加盟店

`admin/merchants/{merchant}/edit` のフォームに「テスト加盟店」チェックボックスを追加。

- `Merchant` の `$fillable` には既に `is_test` があるため変更不要
- バリデーション: `boolean`
- 未チェック時に 0 が入るよう hidden とのペアにする

### 代理店

`admin/agencies/{agency}/edit` のフォームに「テスト代理店」チェックボックスを追加。

- `Agency` の `$fillable` に `is_test` を追加
- バリデーション: `boolean`

### 一覧でのバッジ

管理画面の加盟店一覧・代理店一覧にテストバッジを追加し、どれがテストか一覧で判別できるようにする。
これらの一覧は行を消さない（管理者はテスト加盟店・テスト代理店を編集する必要があるため）。

## 影響と確認

- 既存データは `agencies.is_test` が全件 0 で入るため、代理店フラグ導入による挙動変化はない
- 管理者画面はデフォルトでテスト行が消えるため、`merchants.is_test = 1` の加盟店
  （本番では id=137「サロンテスト」）の行が一覧から見えなくなる。チェックを外して復帰できることを確認する
- 請求書経路が素通しのままであること（テスト加盟店の請求書 LINE 送信が従来どおり動くこと）を確認する
- テスト方針としてテストコードは書かない。動作確認は管理画面で手動で行う
