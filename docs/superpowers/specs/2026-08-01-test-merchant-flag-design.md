# テスト加盟店フラグ 設計

作成日: 2026-08-01

## 目的

請求書の LINE 送信機能を本番環境で安全にテストしたい。テスト用の加盟店を1つ用意し、そこで注文 → 請求書生成 → LINE 送信まで一気通貫で確認できるようにする。ただしテスト加盟店の売上が本番の集計・CSV・代理店への通知に混ざってはいけない。

## 設計上の前提

請求書は加盟店（`merchants`）単位で集計され、`merchant->owner` の LINE に送られる（`InvoiceLineSender::send()` / `InvoiceService::forMonth()`）。

このため、テストフラグは `users` ではなく `merchants` に置く。`users` に置いて注文を集計から除外すると、その加盟店の請求書が空になり `InvoiceLineSender` が「対象月の注文なし」でスキップしてしまい、送信テストが成立しない。

テスト加盟店の請求書生成・送信経路（`SalesController::show()` / `invoice()` / `sendInvoiceLine()`、`InvoiceService`、加盟店側 `/merchants/invoices`）は**素通しにする**。ここを通常どおり動かすことが本機能の目的そのもの。

## 1. データモデル

`merchants` テーブルに `is_test` を追加するマイグレーションを1本作成する。

- 型: boolean、`default false`、not null
- 既存レコードは全て `0` になる

`Merchant` モデルの `$fillable` と `$casts`（boolean）に `is_test` を追加する。

管理画面での操作 UI は作らない。テスト加盟店の指定は本番 DB で直接 `UPDATE merchants SET is_test = 1 WHERE id = ?;` を1回実行する。

## 2. 除外ロジック

既存コードは `whereIn('status', SALES_STATUSES)` を各クエリに明示的に書くスタイルで、`SalesController` は `DB::table` の生 join を使っている。Eloquent のグローバルスコープは `DB::table` クエリに効かず除外漏れを生むため採用しない。既存スタイルに合わせて各クエリに条件を明示する。

### ① `Admin/SalesController::index()`

4本のクエリのうち3本に除外条件を追加する。

| クエリ | 行 | 対応 |
|---|---|---|
| `$productSales` 商品別売上 | 28-41 | `merchants` を join して `m.is_test = 0` |
| `$headquartersProcessed` 全体合計 | 43-47 | 同上 |
| `$shippingFeeCount` 送料件数 | 49-53 | 同上 |
| `$merchantSales` 加盟店別一覧 | 56-72 | **除外しない**。既に `merchants` を join 済みなので `m.is_test` を select に追加してバッジ表示に使う |

`$grandTotal`（行78-79）は `$productSales` と `$headquartersProcessed` から算出しているため、上の3本を直せば自動的にテスト分が除外される。

### ② `Admin/ExportController::exportOrders()`

`ExportController.php:100` の `Order::with(['merchant', 'agency', 'details.product'])` に以下を追加する。

```php
->whereHas('merchant', fn($q) => $q->where('is_test', 0))
```

### ③ テストバッジ表示

- 売上一覧: `resources/views/admin/sales/index.blade.php:48` の `$merchantSales` ループ内で `$m->is_test` を見てバッジを出す
- 注文一覧: `resources/views/admin/orders/index.blade.php` で `$order->merchant->is_test` を見てバッジを出す

合計金額には含めない（①で除外済み）。行自体は表示する。表示を残すのは、売上一覧の行にある請求書ボタンからテスト送信するため。

### ④ 通知メールのスキップ

`app/Http/Controllers/OrderController.php:315` の通知呼び出しを条件で囲む。

```php
if (!optional($order->merchant)->is_test) {
    $this->emailNotificationService->sendOrderNotification($order);
}
```

`EmailNotificationService::sendOrderNotification()` の宛先は代理店のメールアドレスのみ（`EmailNotificationService.php:23-47`）。加盟店にはもともとメールは届かない。よってこのスキップで「代理店にテスト注文の通知が届かない」要件を満たす。

### ⑤ 代理店画面からの除外

代理店にはメールも画面も一切見せない。

| 箇所 | 行 | 対応 |
|---|---|---|
| `Agency/OrderController::index()` の `$orders` | 37 | 既存の `whereHas('merchant', ...)` に `is_test = 0` を追加 |
| `Agency/OrderController::index()` の `$statusCounts` | 48 | 既に `merchants` を join 済みなので `merchants.is_test = 0` を追加 |
| `Agency/DashboardController::index()` の `$todayOrders` | 17 | `merchants` を join して `is_test = 0` |
| `Agency/DashboardController::index()` の `$headquartersProcessed` | 22 | 同上 |
| `Agency/DashboardController::index()` の `$shippingFeeCount` | 29 | 同上 |

### 変更しないもの

- `Admin/SalesController::show()` / `invoice()` / `sendInvoiceLine()`
- `Services/InvoiceService.php`、`Services/InvoiceLineSender.php`
- 加盟店側 `/merchants/invoices`、`/merchants/invoice`
- `Admin/DashboardController` の集計（26, 35, 42 行）
- `Admin/OrderController` の集計サマリー（35, 41, 47 行）

## 3. セットアップ

1. マイグレーションを実行する（既存レコードは全て `is_test = 0`）
2. テスト用の加盟店を1つ用意し、`UPDATE merchants SET is_test = 1 WHERE id = ?;` を実行する
3. `agency_id` は**通常どおり代理店を紐づける**。NULL にしてはいけない
4. オーナーの `line_id` を自分の LINE に設定する（請求書の送信先になる）

手順3の補足: `merchants.agency_id` は DB 上 nullable だが、`resources/views/order/detail.blade.php:47` と `resources/views/order/partials/history.blade.php:39` が `{{ $order->agency->name }}` をノーガードで参照しており、NULL だと LIFF 側の注文詳細・注文履歴が 500 エラーになる。代理店を紐づけても、④と⑤により代理店にはメールも画面も届かない。

## 4. 動作確認

CLAUDE.md の方針に従いテストコードは書かず、ブラウザでの手動確認とする。

- テスト加盟店で LIFF から注文 → 代理店に通知メールが飛ばないこと
- LIFF の注文詳細・注文履歴が正常に表示されること
- 管理画面の売上一覧 → テスト加盟店の行に「テスト」バッジが出て、合計金額に含まれないこと
- 管理画面の注文一覧 → テスト注文に「テスト」バッジが出ること
- CSV 出力 → テスト注文が含まれないこと
- 代理店の注文一覧・ダッシュボード → テスト注文が一切出ないこと
- テスト加盟店の請求書ページ → 金額が正しく出ること
- 請求書 LINE 送信ボタン → 自分の LINE に届くこと（本機能の目的）
- 本番加盟店の売上・請求書・CSV が従来と変わらないこと（回帰確認）

## 5. スコープ外として記録

今回は対応しないが、調査中に見つかった既存の問題。

- `Agency/DashboardController.php:17-20` の `$todayOrders` に `agency_id` の絞り込みがなく、全代理店の今日の注文を集計している
- `resources/views/order/detail.blade.php:47` と `resources/views/order/partials/history.blade.php:39` の `$order->agency->name` がノーガード。代理店未設定の注文で 500 エラーになる
