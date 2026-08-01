# テスト加盟店フラグ 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `merchants.is_test` を追加し、テスト加盟店の注文を本番の集計・CSV・代理店画面・通知メールから除外する。請求書の生成と LINE 送信経路は素通しにして、本番環境で送信テストできるようにする。

**Architecture:** 既存コードは `whereIn('status', SALES_STATUSES)` を各クエリに明示的に書くスタイルで、`SalesController` と `Agency/DashboardController` は `DB::table` の生クエリを使っている。Eloquent のグローバルスコープは `DB::table` に効かず除外漏れを生むため使わない。各クエリにサブクエリ方式の除外条件を明示的に追加する。

**Tech Stack:** Laravel / Blade / MySQL

## Global Constraints

- 設計書: `docs/superpowers/specs/2026-08-01-test-merchant-flag-design.md`
- **テストコードは書かない**（CLAUDE.md の方針）。各タスクの検証はブラウザでの手動確認とする
- 既存ファイルのスタイル（インデント、命名、`DB::table` か Eloquent か）を踏襲する。全面リライトはしない
- 除外条件は以下のサブクエリ方式で**全箇所統一**する。既存クエリの列名を `orders.` 付きに書き換えずに済み、join を足さないので差分が最小になる

```php
->whereNotIn('merchant_id', function ($q) {
    $q->select('id')->from('merchants')->where('is_test', 1);
})
```

- この方式を選ぶ理由: `orders.merchant_id` は NOT NULL（`database/migrations/2024_11_21_132743_create_orders_table.php:18` の `unsignedBigInteger('user_id')` をリネームしたもの）なので `NOT IN` の NULL 問題が起きない。またテスト加盟店が0件のとき `NOT IN (空)` は全行を残すため、フラグを立てるまで既存挙動は完全に不変
- 既に `merchants` を join 済みのクエリでは、サブクエリではなく `where('merchants.is_test', 0)` を使う（join が既にあるため）
- バッジの HTML は**既存の flex コンテナに新しい子要素を足さない**こと。`50c850b` で、`.lma-user_box` に `<p>` を追加したら flex アイテムが増えて `width:100%` でレイアウトが潰れた事故がある。バッジは既存の `<h3>` / `<h4>` の**内側**に入れる

---

### Task 1: マイグレーションと Merchant モデル

**Files:**
- Create: `database/migrations/2026_08_01_000002_add_is_test_to_merchants_table.php`
- Modify: `app/Models/Merchant.php:19-36`

**Interfaces:**
- Produces: `merchants.is_test` カラム（boolean, default 0, not null）。以降の全タスクがこれを参照する
- Produces: `Merchant` モデルの `is_test` 属性が boolean にキャストされる

- [ ] **Step 1: マイグレーションファイルを作成する**

`database/migrations/2026_08_01_000002_add_is_test_to_merchants_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('status'); // テスト加盟店フラグ
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });
    }
};
```

- [ ] **Step 2: `Merchant` モデルに `is_test` を追加する**

`app/Models/Merchant.php` の `$fillable`（19-32行）で `'status',` の直後に1行追加する:

```php
        'status',
        'is_test',
```

同ファイルの `$casts`（34-36行）を以下にする:

```php
    protected $casts = [
        'member_rank' => 'integer',
        'is_test' => 'boolean',
    ];
```

- [ ] **Step 3: マイグレーションを実行して確認する**

Run: `php artisan migrate`

続けてカラムが入ったことを確認する:

Run: `php artisan tinker --execute="var_dump(Schema::hasColumn('merchants', 'is_test'));"`

Expected: `bool(true)`

- [ ] **Step 4: 既存レコードが全て 0 になっていることを確認する**

Run: `php artisan tinker --execute="echo App\Models\Merchant::where('is_test', 1)->count();"`

Expected: `0`

- [ ] **Step 5: コミット**

```bash
git add database/migrations/2026_08_01_000002_add_is_test_to_merchants_table.php app/Models/Merchant.php
git commit -m "merchants に is_test カラムを追加"
```

---

### Task 2: 売上集計からテスト加盟店を除外し、一覧にバッジを出す

**Files:**
- Modify: `app/Http/Controllers/Admin/SalesController.php:28-72`
- Modify: `resources/views/admin/sales/index.blade.php:52`

**Interfaces:**
- Consumes: Task 1 の `merchants.is_test`
- Produces: `$merchantSales` の各行が `is_test` プロパティを持つ（`m.is_test as is_test`）

- [ ] **Step 1: `$productSales` に除外条件を追加する**

`app/Http/Controllers/Admin/SalesController.php:28-41` の `$productSales` で、`->whereIn('o.status', self::SALES_STATUSES)` の直後に以下を挿入する:

```php
            ->whereNotIn('o.merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
```

挿入後の該当部分:

```php
        $productSales = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->whereIn('o.status', self::SALES_STATUSES)
            ->whereNotIn('o.merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(o.created_at, "%Y-%m") = ?', [$month])
```

- [ ] **Step 2: `$headquartersProcessed` に除外条件を追加する**

同ファイル 43-47行。`->whereIn('status', self::SALES_STATUSES)` の直後に挿入する:

```php
        $headquartersProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee')
            ->whereIn('status', self::SALES_STATUSES)
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();
```

- [ ] **Step 3: `$shippingFeeCount` に除外条件を追加する**

同ファイル 49-53行:

```php
        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();
```

- [ ] **Step 4: `$merchantSales` の select に `is_test` を追加する（除外はしない）**

同ファイル 56-72行。`$merchantSales` は既に `merchants as m` を join しているので**除外条件は入れない**。`groupBy` に `m.is_test` を足し、`select` に `'m.is_test as is_test'` を足す:

```php
            ->groupBy('m.id', 'm.name', 'm.member_rank', 'm.is_test', 'a.name')
            ->orderByDesc(DB::raw('SUM(o.total_price + o.shipping_fee)'))
            ->select(
                'm.id as merchant_id',
                'm.name as merchant_name',
                'm.member_rank',
                'm.is_test as is_test',
                'a.name as agency_name',
                DB::raw('COUNT(o.id) as order_count'),
                DB::raw('SUM(o.total_price + o.shipping_fee) as total_amount')
            )
```

`groupBy` に `m.is_test` を足すのは `ONLY_FULL_GROUP_BY` 対策。既に `m.name` や `m.member_rank` を同様に列挙しているので同じ扱いにする。

- [ ] **Step 5: 売上一覧にバッジを表示する**

`resources/views/admin/sales/index.blade.php:52` の1行を差し替える。**`<h3>` の内側に入れる**こと（`.lma-user_box` に新しい flex アイテムを足すとレイアウトが崩れる）:

変更前:
```blade
                            <h3 class="name">{{ $m->merchant_name }}</h3>
```

変更後:
```blade
                            <h3 class="name">{{ $m->merchant_name }}@if ($m->is_test)<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#f60;color:#fff;font-size:11px;vertical-align:middle;">テスト</span>@endif</h3>
```

- [ ] **Step 6: ブラウザで確認する**

まずテスト加盟店を1つ作る。既存の加盟店IDを1つ選んで実行する（`?` を実際のIDに置き換える）:

Run: `php artisan tinker --execute="\$m = App\Models\Merchant::find(?); \$m->is_test = 1; \$m->save(); echo \$m->name;"`

`http://localhost:8884/admin/sales` を開いて以下を確認する:
- テスト加盟店の行に「テスト」バッジが出ている
- 加盟店名とバッジが横並びで、レイアウトが崩れていない
- 「請求書を送信」「PDFを表示」「詳細」のボタンが従来どおり表示されている
- ページ上部の商品別売上・合計金額から、そのテスト加盟店の売上が抜けている（フラグを立てる前の金額をメモしておいて比較する）

- [ ] **Step 7: コミット**

```bash
git add app/Http/Controllers/Admin/SalesController.php resources/views/admin/sales/index.blade.php
git commit -m "売上集計からテスト加盟店を除外し、一覧にテストバッジを表示"
```

---

### Task 3: 注文一覧にテストバッジを表示する

**Files:**
- Modify: `resources/views/admin/orders/index.blade.php:106`

**Interfaces:**
- Consumes: Task 1 の `merchants.is_test`。`$order->merchant` は `Admin/OrderController.php:28` の `Order::with(['merchant', ...])` で既にロード済み

集計サマリー（`Admin/OrderController.php:35, 41, 47`）は設計どおり**変更しない**。

- [ ] **Step 1: バッジを追加する**

`resources/views/admin/orders/index.blade.php:106` の1行を差し替える。**`<h4>` の内側に入れる**こと:

変更前:
```blade
                        <h4 class="store">{{ $order->merchant->name ?? '---' }}</h4>
```

変更後:
```blade
                        <h4 class="store">{{ $order->merchant->name ?? '---' }}@if (optional($order->merchant)->is_test)<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#f60;color:#fff;font-size:11px;vertical-align:middle;">テスト</span>@endif</h4>
```

`optional()` を使うのは、`$order->merchant` が null のとき（既存コードも `?? '---'` でその可能性を想定している）にエラーにしないため。

- [ ] **Step 2: ブラウザで確認する**

`http://localhost:8884/admin/orders` を開く。Task 2 でフラグを立てた加盟店の注文がある場合、その行に「テスト」バッジが出ていること。レイアウトが崩れていないこと。

まだテスト加盟店の注文が無ければ Task 7 の通し確認で見る。その場合はこのステップをスキップしてよい。

- [ ] **Step 3: コミット**

```bash
git add resources/views/admin/orders/index.blade.php
git commit -m "管理画面の注文一覧にテストバッジを表示"
```

---

### Task 4: CSV 出力からテスト加盟店を除外する

**Files:**
- Modify: `app/Http/Controllers/Admin/ExportController.php:100-102`

**Interfaces:**
- Consumes: Task 1 の `merchants.is_test`

- [ ] **Step 1: 除外条件を追加する**

`app/Http/Controllers/Admin/ExportController.php:100-102` を以下にする:

```php
            $orders = Order::with(['merchant', 'agency', 'details.product'])
                ->where('status', 3)
                ->whereNotIn('merchant_id', function ($q) {
                    $q->select('id')->from('merchants')->where('is_test', 1);
                })
                ->get();
```

`whereHas` ではなくサブクエリ方式を使うのは、Global Constraints で全箇所統一すると決めたため。

- [ ] **Step 2: ブラウザで確認する**

`http://localhost:8884/admin/export/orders` を開いて CSV をダウンロードし、テスト加盟店の行が含まれていないことを確認する。

比較のため、フラグを立てる前後で行数を控えておくとよい。まだテスト加盟店に status=3 の注文が無い場合は、既存の CSV が従来どおり出力されること（行数が変わらないこと）だけ確認する。

- [ ] **Step 3: コミット**

```bash
git add app/Http/Controllers/Admin/ExportController.php
git commit -m "CSV出力からテスト加盟店の注文を除外"
```

---

### Task 5: テスト注文で代理店への通知メールを飛ばさない

**Files:**
- Modify: `app/Http/Controllers/OrderController.php:314-315`

**Interfaces:**
- Consumes: Task 1 の `merchants.is_test`

`EmailNotificationService::sendOrderNotification()` の宛先は代理店のメールアドレスのみ（`app/Services/EmailNotificationService.php:23-47`）。加盟店にはもともとメールは届かない。このスキップで「代理店にテスト注文の通知が届かない」要件を満たす。

- [ ] **Step 1: 通知呼び出しを条件で囲む**

`app/Http/Controllers/OrderController.php:314-315` を以下にする:

変更前:
```php
            // メール通知
            $this->emailNotificationService->sendOrderNotification($order);
```

変更後:
```php
            // メール通知（テスト加盟店の注文では代理店に通知しない）
            if (!optional($order->merchant)->is_test) {
                $this->emailNotificationService->sendOrderNotification($order);
            }
```

- [ ] **Step 2: 本番加盟店では従来どおり通知が走ることを確認する**

`storage/logs/laravel.log` を監視しながら、本番加盟店（`is_test = 0`）で LIFF から注文する。

Run: `tail -f storage/logs/laravel.log`

Expected: `注文通知メール送信完了` のログが出る（メール設定が未整備のローカルでは `注文通知メール送信エラー` でも可。**呼び出し自体が走っている**ことが確認できればよい）

- [ ] **Step 3: テスト加盟店では通知が走らないことを確認する**

同じくログを監視しながら、Task 2 でフラグを立てたテスト加盟店で LIFF から注文する。

Expected: `注文通知メール送信完了` も `注文通知メール送信エラー` も `注文通知: agencyが見つかりません` も**一切出ない**。注文自体は成功する。

- [ ] **Step 4: コミット**

```bash
git add app/Http/Controllers/OrderController.php
git commit -m "テスト加盟店の注文では代理店への通知メールを送らない"
```

---

### Task 6: 代理店画面からテスト加盟店を除外する

**Files:**
- Modify: `app/Http/Controllers/Agency/OrderController.php:37-53`
- Modify: `app/Http/Controllers/Agency/DashboardController.php:17-33`

**Interfaces:**
- Consumes: Task 1 の `merchants.is_test`

- [ ] **Step 1: 代理店の注文一覧から除外する**

`app/Http/Controllers/Agency/OrderController.php:37-46` の `$orders` で、既存の `whereHas('merchant', ...)` クロージャ内に `is_test` の条件を足す:

変更前:
```php
            ->whereHas('merchant', function ($query) use ($agencyId) {
                $query->where('agency_id', $agencyId);
            })
```

変更後:
```php
            ->whereHas('merchant', function ($query) use ($agencyId) {
                $query->where('agency_id', $agencyId)
                      ->where('is_test', 0);
            })
```

- [ ] **Step 2: 代理店のステータス件数から除外する**

同ファイル 48-55行の `$statusCounts` は既に `merchants` を join 済みなので、サブクエリではなく join した列を直接使う。`->where('merchants.agency_id', $agencyId)` の直後に1行足す:

```php
        $statusCounts = DB::table('orders')
            ->join('merchants', 'orders.merchant_id', '=', 'merchants.id')
            ->where('merchants.agency_id', $agencyId)
            ->where('merchants.is_test', 0)
            ->whereIn('orders.status', [1, 2, 3, 4, 5, 6, 9])
            ->select('orders.status', DB::raw('COUNT(*) as count'))
            ->groupBy('orders.status')
            ->pluck('count', 'orders.status')
            ->toArray();
```

- [ ] **Step 3: 代理店ダッシュボードの3クエリから除外する**

`app/Http/Controllers/Agency/DashboardController.php:17-33` を以下にする。3本ともサブクエリ方式で統一する:

```php
        $todayOrders = DB::table('orders')
            ->whereDate('created_at', now()->toDateString())
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->selectRaw('COUNT(*) as order_count, SUM(total_price) as total_price_sum')
            ->first();

        $headquartersProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee')
            ->where('agency_id', $agencyId)
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();

        // shipping_fee が 0以上の件数を取得
        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->where('agency_id', $agencyId)
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();
```

`$todayOrders` に `agency_id` の絞り込みが無いのは既存の挙動。設計書の「スコープ外として記録」のとおり、今回は**直さない**。`is_test` の除外だけ足す。

- [ ] **Step 4: ブラウザで確認する**

テスト加盟店が紐づいている代理店でログインし、以下を確認する:
- `http://localhost:8884/agencies/orders` にテスト加盟店の注文が1件も出ない
- ステータス別の件数バッジにテスト注文が含まれていない
- `http://localhost:8884/agencies/dashboard` の「今日の注文」「売上」「送料件数」にテスト分が含まれていない
- 本番加盟店の注文は従来どおり全て表示されている

- [ ] **Step 5: コミット**

```bash
git add app/Http/Controllers/Agency/OrderController.php app/Http/Controllers/Agency/DashboardController.php
git commit -m "代理店の注文一覧・ダッシュボードからテスト加盟店を除外"
```

---

### Task 7: 通し確認と回帰確認

**Files:**
- 変更なし（確認のみ）

**Interfaces:**
- Consumes: Task 1〜6 の全変更

このタスクの目的は、本機能の本来の目的である「請求書の LINE 送信テストが成立すること」と、「本番加盟店の挙動が一切変わっていないこと」を確認すること。

- [ ] **Step 1: テスト加盟店のセットアップを完成させる**

Task 2 でフラグを立てた加盟店について、以下を満たしていることを確認する:

Run: `php artisan tinker --execute="\$m = App\Models\Merchant::with('owner')->where('is_test', 1)->first(); echo 'name: ' . \$m->name . PHP_EOL; echo 'agency_id: ' . var_export(\$m->agency_id, true) . PHP_EOL; echo 'owner line_id: ' . var_export(optional(\$m->owner)->line_id, true) . PHP_EOL;"`

Expected:
- `agency_id` が **NULL ではない**こと。NULL だと `resources/views/order/detail.blade.php:47` と `resources/views/order/partials/history.blade.php:39` の `{{ $order->agency->name }}` がノーガードのため LIFF 側が 500 エラーになる
- `owner line_id` が自分の LINE ID になっていること（請求書の送信先）

`agency_id` が NULL の場合は代理店を紐づける。`owner` が未設定の場合は該当ユーザーの `merchant_id` を設定する。

- [ ] **Step 2: テスト注文を作って LIFF 側を確認する**

テスト加盟店のアカウントで LIFF から注文する。

- 注文が成功すること
- 注文履歴ページが正常に表示されること（500 エラーにならない）
- 注文詳細ページが正常に表示されること（500 エラーにならない）
- `storage/logs/laravel.log` に通知メール関連のログが**出ていない**こと

- [ ] **Step 3: 管理画面での見え方を確認する**

- `http://localhost:8884/admin/orders` — テスト注文に「テスト」バッジが出ている
- `http://localhost:8884/admin/sales` — テスト加盟店の行にバッジが出ていて、上部の合計金額には含まれていない
- `http://localhost:8884/admin/export/orders` — CSV にテスト注文が含まれていない（注文の status を 3 にしてから確認する）

- [ ] **Step 4: 請求書の生成と送信を確認する（本機能の目的）**

注文の status を確定ステータス（2, 3, 5, 6 のいずれか）にしてから:

- 売上一覧の「PDFを表示」でテスト加盟店の請求書が開き、金額が正しく出ていること
- 売上一覧の「請求書を送信」を押して、自分の LINE に請求書メッセージが届くこと

「請求書を送信」ボタンは確定月（`InvoiceService::isFixedMonth()`）でないと表示されない。当月は未確定のため出ないので、前月以前の注文で確認すること。

- [ ] **Step 5: 回帰確認**

本番加盟店について、変更前と挙動が変わっていないことを確認する:

- `http://localhost:8884/admin/sales` の商品別売上・合計金額が、テスト加盟店分を引いた値になっていること（それ以外は不変）
- 本番加盟店の請求書 PDF の金額が変わっていないこと
- 代理店画面に本番加盟店の注文が全て出ていること
- 本番加盟店で注文したときに通知メールが従来どおり走ること（`storage/logs/laravel.log`）

- [ ] **Step 6: デプロイ**

ローカルでの確認が全て通ったら push する。本番では以下を実行する:

```bash
php artisan migrate
```

続けて本番のテスト加盟店にフラグを立てる:

```sql
UPDATE merchants SET is_test = 1 WHERE id = ?;
```

本番の該当加盟店に代理店が紐づいていること、オーナーの `line_id` が設定されていることを事前に確認しておく。
