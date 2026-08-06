# 請求書の集計対象を「発送済みのみ」に変更する 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 2026-09-01 以降に作成された注文について、発送済み（ステータス6）の注文だけを、発送日の月の請求書に計上する。

**Architecture:** `orders` に `shipped_at` を追加し、管理画面でステータスが 6 になった瞬間に記録する。集計条件は `InvoiceService` の static メソッド 1 本に集約し、「カットオフ前の注文＝旧ルール（注文日 × ステータス 2,3,5,6）」と「カットオフ以降の注文＝新ルール（発送日 × ステータス 6）」を OR で合成する。カットオフを請求月ではなく注文日で切ることで、1件の注文が必ずどちらか一方のルールにしか属さず、二重計上も計上漏れも起きない。

**Tech Stack:** Laravel 9 / PHP 8.2 / MySQL / Blade / Docker Compose（サービス名 `php_ykk08ok`, `db_ykk08ok`）

**Spec:** `docs/superpowers/specs/2026-08-06-invoice-shipped-only-design.md`

## Global Constraints

- **テストコードは書かない**（`/Users/sawadakeisuke/workspace/CLAUDE.md` の方針）。各タスクの検証は tinker と画面での手動確認で行う。
- 既存ファイルのスタイルに合わせる。全面リライト・大規模リファクタはしない。修正は最小限・局所的に。
- `env()` は config ファイル内でのみ使用する。
- DB アクセスは Eloquent かプリペアドステートメント。文字列連結の生 SQL は禁止（本計画で `whereRaw` に埋め込む値はクラス定数のみで、ユーザー入力は必ずバインディングで渡す）。
- カットオフ日は `2026-09-01`。この日以降に**作成された注文**が新ルールの対象。
- 発送済みステータスは `6`。
- 旧ルールの対象ステータスは `[2, 3, 5, 6]`（保留=4・キャンセル=9・代理店未処理=1 は対象外）。
- 請求書の確定タイミング（当月は未確定、前月まで）は変更しない。
- ローカルの動作確認は `docker compose exec -T php_ykk08ok php artisan ...` で行う。アプリは `http://localhost:8884`。
- コミットメッセージは日本語で簡潔に。

---

### Task 1: `shipped_at` カラムを追加し、発送済みになった日時を記録する

**Files:**
- Create: `database/migrations/2026_08_06_000001_add_shipped_at_to_orders_table.php`
- Modify: `app/Models/Order.php:13-23`（`$fillable` に追加、`$casts` を新設）
- Modify: `app/Http/Controllers/Admin/OrderController.php:119-141`（`updateStatus`）
- Modify: `app/Http/Controllers/Admin/OrderController.php:159-177`（`bulkUpdate`）

**Interfaces:**
- Consumes: なし（最初のタスク）
- Produces: `orders.shipped_at`（`datetime`, nullable, インデックス付き）。Order モデルで `shipped_at` は Carbon インスタンスにキャストされる。Task 2 の集計クエリがこの列を参照する。

- [ ] **Step 1: マイグレーションを作成する**

`database/migrations/2026_08_06_000001_add_shipped_at_to_orders_table.php` を新規作成：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('shipped_at')->nullable()->after('status');
            $table->index('shipped_at');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipped_at']);
            $table->dropColumn('shipped_at');
        });
    }
};
```

既存データへのバックフィルは行わない（カットオフ前の注文は旧ルールで集計され `shipped_at` を参照しないため）。

- [ ] **Step 2: マイグレーションを実行する**

Run: `docker compose exec -T php_ykk08ok php artisan migrate`

Expected: `2026_08_06_000001_add_shipped_at_to_orders_table ... DONE`

コンテナが起動していない場合は先に `docker compose up -d` を実行する。

- [ ] **Step 3: Order モデルに `shipped_at` を追加する**

`app/Models/Order.php` の `$fillable`（13-23行目）の `'status',` の直後に `'shipped_at',` を追加し、`$fillable` の閉じ括弧の直後に `$casts` を新設する：

```php
    protected $fillable = [
        'id',
        'user_id',
        'agency_id',
        'merchant_id', 
        'total_price', 
        'order_number', 
        'is_staff_sale', 
        'shipping_fee', 
        'status', 
        'shipped_at',
        'memo'];

    protected $casts = [
        'shipped_at' => 'datetime',
    ];
```

- [ ] **Step 4: 単票のステータス更新で `shipped_at` を記録する**

`app/Http/Controllers/Admin/OrderController.php` の `updateStatus()`（119-141行目）を書き換える。`use App\Services\InvoiceService;` を 8 行目付近の use 句に追加すること。

```php
    public function updateStatus(Request $request, Order $order)
    {
        // バリデーション
        $validated = $request->validate([
            'status' => 'required|integer|in:2,3,4,5,6,9'
        ]);

        // 変更前の値を保存
        $oldStatus = $order->status;
        $newStatus = (int) $validated['status'];

        // ステータス更新
        $order->status = $newStatus;

        // 発送済みになった瞬間だけ発送日を記録し、発送済みから外れたら取り消す。
        // すでに発送済みのまま再保存された場合は既存の発送日を動かさない
        if ($newStatus === InvoiceService::SHIPPED_STATUS) {
            if ((int) $oldStatus !== InvoiceService::SHIPPED_STATUS) {
                $order->shipped_at = now();
            }
        } else {
            $order->shipped_at = null;
        }

        $order->save();

        // ログを記録
        $this->activityLogService->logOrderStatusUpdated($order, $oldStatus, $newStatus);

        // 成功レスポンス
        return response()->json(['success' => true]);
    }
```

元コードにあった `$newStatus = $order->status;`（保存後の再取得）は、保存前に定義した `$newStatus` と同値のため削除する。

- [ ] **Step 5: 一括更新で `shipped_at` を記録する**

同ファイルの `bulkUpdate()`（159-177行目）を書き換える：

```php
    public function bulkUpdate(Request $request)
    {
        $orderIds = $request->order_ids;
        $newStatus = (int) $request->status;

        // 一括更新前の注文データを取得
        $orders = Order::whereIn('id', $orderIds)->get();

        // 発送済みへの変更なら発送日を記録し、発送済みから外れる場合は取り消す。
        // すでに発送済みだったものは発送日を動かさない
        if ($newStatus === InvoiceService::SHIPPED_STATUS) {
            Order::whereIn('id', $orderIds)
                ->where('status', '!=', InvoiceService::SHIPPED_STATUS)
                ->update(['shipped_at' => now()]);
        } else {
            Order::whereIn('id', $orderIds)
                ->where('status', InvoiceService::SHIPPED_STATUS)
                ->update(['shipped_at' => null]);
        }

        // 一括更新を実行
        Order::whereIn('id', $orderIds)->update(['status' => $newStatus]);

        // 各注文のログを記録
        foreach ($orders as $order) {
            $oldStatus = $order->status;
            $this->activityLogService->logOrderStatusUpdated($order, $oldStatus, $newStatus);
        }

        return response()->json(['success' => true]);
    }
```

`shipped_at` の更新は必ず `status` の更新より**先**に実行すること。先に status を書き換えると `where('status', ...)` の判定が効かなくなる。

- [ ] **Step 6: 動作を確認する**

`docker compose exec -T php_ykk08ok php artisan tinker` に以下を貼って、注文1件の往復を確認する（`$id` は既存注文の ID に置き換える）：

```php
$order = App\Models\Order::orderBy('id', 'desc')->first();
$id = $order->id;
$backupStatus = $order->status;

// 発送済みにすると発送日が入る
$order->status = 6; $order->shipped_at = now(); $order->save();
App\Models\Order::find($id)->shipped_at;   // Carbon インスタンスが返る

// 発送済みから外すと null に戻る
$order->status = 5; $order->shipped_at = null; $order->save();
App\Models\Order::find($id)->shipped_at;   // null

// 後始末
$order->status = $backupStatus; $order->save();
```

Expected: 1回目が Carbon インスタンス、2回目が `null`。

続いて管理画面（`http://localhost:8884/admin/orders?status=5`）でコントローラ経由の挙動を確認する：

1. 注文を1件「発送済み」に変更し、`docker compose exec -T php_ykk08ok php artisan tinker --execute="echo App\Models\Order::find(ID)->shipped_at;"` で日時が入っていること
2. 同じ注文を「発送待ち」に戻し、`shipped_at` が空になること
3. 一覧のチェックボックスで複数件をまとめて「発送済み」にし、全件に `shipped_at` が入ること

- [ ] **Step 7: コミット**

```bash
git add database/migrations/2026_08_06_000001_add_shipped_at_to_orders_table.php app/Models/Order.php app/Http/Controllers/Admin/OrderController.php
git commit -m "注文に発送日(shipped_at)を追加し、発送済みへの変更時に記録する"
```

---

### Task 2: `InvoiceService` の集計条件を新ルールに切り替える

**Files:**
- Modify: `app/Services/InvoiceService.php`（定数追加、static メソッド 3 本を追加、`monthlyBreakdown()` / `hasInvoice()` / `forMonth()` を差し替え）

**Interfaces:**
- Consumes: Task 1 の `orders.shipped_at`
- Produces: 以下の public static メソッド。Task 3 の `Admin\SalesController` がこれを使う。
  - `InvoiceService::SHIPPED_ONLY_FROM`（`string` `'2026-09-01'`）
  - `InvoiceService::SHIPPED_STATUS`（`int` `6`）
  - `InvoiceService::SALES_STATUSES`（`array` `[2, 3, 5, 6]`、既存）
  - `InvoiceService::applyInvoiceScope($query, string $alias = '')` → 同じクエリビルダを返す
  - `InvoiceService::applyInvoiceMonth($query, string $month, string $alias = '')` → 同じクエリビルダを返す
  - `InvoiceService::applyFixedMonths($query, string $alias = '')` → 同じクエリビルダを返す
  - `InvoiceService::billingDate(Order $order)` → `\Carbon\Carbon|null`

- [ ] **Step 0: 変更前の請求額を控える**

Step 5 で「過去月の金額が変わっていないこと」を比べるため、**コードを触る前に**基準値を取っておく。

Run: `docker compose exec -T php_ykk08ok php artisan tinker`

```php
$svc = app(App\Services\InvoiceService::class);
$m = App\Models\Merchant::first();
echo $m->id;
print_r($svc->forMonth($m, '2026-07'));
print_r(array_keys($svc->monthlyBreakdown($m)));
```

出力された加盟店 ID・`order_count`・`grand_total`・月のキー一覧をメモしておく。

- [ ] **Step 1: 定数と共通スコープを追加する**

`app/Services/InvoiceService.php` の 18 行目 `public const SALES_STATUSES = [2, 3, 5, 6];` の前後を以下に差し替える：

```php
    /** 旧ルールでの売上集計対象ステータス（保留=4以外の確定注文） */
    public const SALES_STATUSES = [2, 3, 5, 6];

    /** 発送済み */
    public const SHIPPED_STATUS = 6;

    /**
     * 「発送済みのみを請求する」新ルールの適用開始日
     *
     * この日以降に作成された注文が新ルールの対象。請求月ではなく注文日で切ることで、
     * 1件の注文が旧ルールと新ルールの両方に計上される二重請求を防いでいる。
     */
    public const SHIPPED_ONLY_FROM = '2026-09-01';

    /**
     * 請求対象の注文に絞り込む
     *
     * 旧ルール（カットオフ前の注文）: 確定注文すべて
     * 新ルール（カットオフ以降の注文）: 発送済みのみ
     *
     * @param mixed $query Eloquent または DB::table のクエリビルダ
     * @param string $alias テーブル別名（DB::table の join で 'o' などを使っている場合に渡す）
     * @return mixed
     */
    public static function applyInvoiceScope($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->where(function ($outer) use ($p) {
            $outer->where(function ($old) use ($p) {
                $old->where($p . 'created_at', '<', self::SHIPPED_ONLY_FROM)
                    ->whereIn($p . 'status', self::SALES_STATUSES);
            })->orWhere(function ($new) use ($p) {
                $new->where($p . 'created_at', '>=', self::SHIPPED_ONLY_FROM)
                    ->where($p . 'status', self::SHIPPED_STATUS)
                    ->whereNotNull($p . 'shipped_at');
            });
        });
    }

    /**
     * 請求上の計上日を表す SQL 式を返す
     *
     * 旧ルールは注文日、新ルールは発送日。埋め込む値はクラス定数のみでユーザー入力を含まない。
     *
     * @param string $alias テーブル別名
     * @return string
     */
    private static function billingDateSql($alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return 'IF(' . $p . 'created_at < "' . self::SHIPPED_ONLY_FROM . '", ' . $p . 'created_at, ' . $p . 'shipped_at)';
    }

    /**
     * 請求上の計上月で絞り込む
     *
     * @param mixed $query
     * @param string $month YYYY-MM
     * @param string $alias テーブル別名
     * @return mixed
     */
    public static function applyInvoiceMonth($query, $month, $alias = '')
    {
        return $query->whereRaw('DATE_FORMAT(' . self::billingDateSql($alias) . ', "%Y-%m") = ?', [$month]);
    }

    /**
     * 請求上の計上日が確定月（前月以前）に入っているものだけに絞り込む
     *
     * @param mixed $query
     * @param string $alias テーブル別名
     * @return mixed
     */
    public static function applyFixedMonths($query, $alias = '')
    {
        return $query->whereRaw(
            self::billingDateSql($alias) . ' < ?',
            [Carbon::now()->startOfMonth()->format('Y-m-d H:i:s')]
        );
    }

    /**
     * 注文の請求上の計上日を返す（旧ルール=注文日、新ルール=発送日）
     *
     * @param Order $order
     * @return \Carbon\Carbon|null
     */
    public static function billingDate(Order $order)
    {
        if ($order->created_at < Carbon::parse(self::SHIPPED_ONLY_FROM)) {
            return $order->created_at;
        }

        return $order->shipped_at;
    }
```

- [ ] **Step 2: `monthlyBreakdown()` を差し替える**

同ファイルの `monthlyBreakdown()`（26-48行目）を以下に差し替える：

```php
    public function monthlyBreakdown(Merchant $merchant)
    {
        // 当月は未確定のため前月までを対象とする
        $query = Order::with('details.product')
            ->where('merchant_id', $merchant->id);
        self::applyInvoiceScope($query);
        self::applyFixedMonths($query);
        $orders = $query->orderBy('created_at', 'desc')->get();

        $grouped = [];
        foreach ($orders as $order) {
            $billingDate = self::billingDate($order);
            if (!$billingDate) {
                continue;
            }
            $grouped[$billingDate->format('Y-m')][] = $order;
        }

        krsort($grouped);

        $result = [];
        foreach ($grouped as $month => $monthOrders) {
            $result[$month] = $this->aggregate($monthOrders, $merchant, $month);
        }

        return $result;
    }
```

元コードは `orderBy('created_at', 'desc')` の順序に依存して「新しい月が先」を実現していたが、計上日と注文日がずれる新ルールではその保証がなくなるため `krsort()` で明示的に月の降順にする。

- [ ] **Step 3: `hasInvoice()` を差し替える**

同ファイルの `hasInvoice()`（56-62行目）の本体を以下に差し替える：

```php
    public function hasInvoice(Merchant $merchant)
    {
        $query = Order::where('merchant_id', $merchant->id);
        self::applyInvoiceScope($query);
        self::applyFixedMonths($query);

        return $query->exists();
    }
```

- [ ] **Step 4: `forMonth()` を差し替える**

同ファイルの `forMonth()`（71-80行目）の本体を以下に差し替える：

```php
    public function forMonth(Merchant $merchant, $month)
    {
        $query = Order::with('details.product')
            ->where('merchant_id', $merchant->id);
        self::applyInvoiceScope($query);
        self::applyInvoiceMonth($query, $month);
        $orders = $query->get();

        return $this->aggregate($orders, $merchant, $month);
    }
```

`isFixedMonth()`、`aggregate()`、`invoiceUrl()` は変更しない。

- [ ] **Step 5: 生成される SQL と集計結果を確認する**

Run: `docker compose exec -T php_ykk08ok php artisan tinker`

```php
$svc = app(App\Services\InvoiceService::class);
$m = App\Models\Merchant::first();

// 生成SQLに「旧ルール OR 新ルール」が両方含まれることを確認
$q = App\Models\Order::where('merchant_id', $m->id);
App\Services\InvoiceService::applyInvoiceScope($q);
App\Services\InvoiceService::applyInvoiceMonth($q, '2026-07');
echo $q->toSql();

// 過去月の請求額がカットオフ前なので変わっていないこと
print_r($svc->forMonth($m, '2026-07'));

// 月別内訳が新しい月から並んでいること
print_r(array_keys($svc->monthlyBreakdown($m)));
```

Expected:
- `toSql()` に `created_at` < / >= の両分岐と `status in (?, ?, ?, ?)` / `status = ?` の両方が現れる
- `forMonth($m, '2026-07')` の `order_count` と `grand_total` が **Step 0 で控えた値と同じ**（2026-07 の注文はすべてカットオフ前なので旧ルールが適用される）
- `monthlyBreakdown()` のキーが Step 0 と同じ顔ぶれで、`Y-m` の降順に並んでいる

- [ ] **Step 6: 新ルールが効くことを確認する**

カットオフ後の注文を作って挙動を確認する（確認後にロールバックする）：

```php
DB::beginTransaction();

$m = App\Models\Merchant::first();
$svc = app(App\Services\InvoiceService::class);

// 9/10 に注文され 10/3 に発送された注文
$o = App\Models\Order::create([
    'user_id' => 1, 'merchant_id' => $m->id, 'total_price' => 10000,
    'shipping_fee' => 500, 'status' => 6,
]);
$o->created_at = '2026-09-10 10:00:00';
$o->shipped_at = '2026-10-03 10:00:00';
$o->save();

$svc->forMonth($m, '2026-09')['order_count'];   // この注文は含まれない
$svc->forMonth($m, '2026-10')['order_count'];   // この注文が含まれる

// 未発送なら請求されない
$o->status = 5; $o->shipped_at = null; $o->save();
$svc->forMonth($m, '2026-09')['order_count'];   // 含まれない
$svc->forMonth($m, '2026-10')['order_count'];   // 含まれない

DB::rollBack();
```

Expected: 1回目が 9月分に含まれず 10月分に含まれる、2回目はどちらにも含まれない。それぞれ変更前後の件数差で判断する。

- [ ] **Step 7: コミット**

```bash
git add app/Services/InvoiceService.php
git commit -m "請求書の集計を2026-09-01以降の注文は発送済み・発送日基準に変更"
```

---

### Task 3: 管理画面の請求系集計を新基準に合わせる

**Files:**
- Modify: `app/Http/Controllers/Admin/SalesController.php:23-24`（重複定数の削除）
- Modify: `app/Http/Controllers/Admin/SalesController.php:34-68`（分析系3クエリの定数参照を差し替え）
- Modify: `app/Http/Controllers/Admin/SalesController.php:71-88`（`index()` の加盟店別売上一覧）
- Modify: `app/Http/Controllers/Admin/SalesController.php:183-188`（`show()` の月次明細）
- Modify: `app/Http/Controllers/Admin/SalesController.php:260-264`（`invoice()` の請求書）
- Modify: `resources/views/admin/sales/index.blade.php:52-53`（加盟店別一覧に注記を追加）

**Interfaces:**
- Consumes: Task 2 の `InvoiceService::applyInvoiceScope()` / `applyInvoiceMonth()` / `SALES_STATUSES`
- Produces: なし（最終タスク）

- [ ] **Step 1: 重複していた定数を削除し、分析系クエリを `InvoiceService` の定数に向ける**

`app/Http/Controllers/Admin/SalesController.php` の 23-24 行目を削除する：

```php
    /** 売上集計対象のステータス（保留=4以外の確定注文） */
    private const SALES_STATUSES = [2, 3, 5, 6];
```

そのうえで、**売上分析系の 3 クエリ**の `self::SALES_STATUSES` を `InvoiceService::SALES_STATUSES` に置き換える。これらは請求ではなく全体の売上を見るためのもので、集計基準は従来どおり（注文日 × ステータス 2,3,5,6）のまま変えない。

- 37 行目 `$productSales`: `->whereIn('o.status', InvoiceService::SALES_STATUSES)`
- 54 行目 `$headquartersProcessed`: `->whereIn('status', InvoiceService::SALES_STATUSES)`
- 63 行目 `$shippingFeeCount`: `->whereIn('status', InvoiceService::SALES_STATUSES)`

`use App\Services\InvoiceService;` は 13 行目にすでにあるので追加不要。

- [ ] **Step 2: `index()` の加盟店別売上一覧を新基準にする**

71-88 行目の `$merchantSales` を以下に差し替える。`whereIn('o.status', ...)` と `whereRaw('DATE_FORMAT(o.created_at, ...)')` を `InvoiceService` のスコープに置き換えるのが変更点で、select と groupBy は変更しない：

```php
        // 月内に売上があった店舗一覧（請求額なので発送済みのみ集計）
        $merchantSalesQuery = DB::table('orders as o')
            ->join('merchants as m', 'm.id', '=', 'o.merchant_id')
            ->leftJoin('agencies as a', 'a.id', '=', 'm.agency_id');
        InvoiceService::applyInvoiceScope($merchantSalesQuery, 'o');
        InvoiceService::applyInvoiceMonth($merchantSalesQuery, $month, 'o');
        $merchantSales = $merchantSalesQuery
            ->groupBy('m.id', 'm.name', 'm.member_rank', 'm.is_test', 'm.bank_account_name', 'a.name')
            ->orderByDesc(DB::raw('SUM(o.total_price + o.shipping_fee)'))
            ->select(
                'm.id as merchant_id',
                'm.name as merchant_name',
                'm.member_rank',
                'm.is_test as is_test',
                'm.bank_account_name',
                'a.name as agency_name',
                DB::raw('COUNT(o.id) as order_count'),
                DB::raw('SUM(o.total_price + o.shipping_fee) as total_amount')
            )
            ->get();
```

- [ ] **Step 3: `show()` の月次明細を新基準にする**

183-188 行目の `$orders` を以下に差し替える：

```php
        // 月内のこの店舗の注文を全件取得（請求額なので発送済みのみ集計）
        $ordersQuery = Order::with('details.product')
            ->where('merchant_id', $merchant->id);
        InvoiceService::applyInvoiceScope($ordersQuery);
        InvoiceService::applyInvoiceMonth($ordersQuery, $month);
        $orders = $ordersQuery->orderBy('created_at', 'desc')->get();
```

- [ ] **Step 4: `invoice()` の請求書を新基準にする**

260-264 行目の `$orders` を以下に差し替える：

```php
        $ordersQuery = Order::with('details.product')
            ->where('merchant_id', $merchant->id);
        InvoiceService::applyInvoiceScope($ordersQuery);
        InvoiceService::applyInvoiceMonth($ordersQuery, $month);
        $orders = $ordersQuery->get();
```

- [ ] **Step 5: 加盟店別一覧に集計基準の注記を追加する**

分析系の「合計」（`$grandTotal`）と加盟店別一覧の合計が一致しなくなるため、`resources/views/admin/sales/index.blade.php` の 52-53 行目の間に注記を入れる：

```blade
    <div class="lma-content_block staff nobg">
        <p style="font-size:12px;color:#666;margin:0 0 8px;">加盟店ごとの金額は請求額です。2026年9月以降の注文は発送済みのみ、発送日の月に計上されます。上の売上集計とは基準が異なります。</p>
        <ul class="lma-user_list store">
```

- [ ] **Step 6: 画面で確認する**

Run: `docker compose exec -T php_ykk08ok php artisan view:clear`

ブラウザで以下を確認する（管理画面のログインが必要）：

1. `http://localhost:8884/admin/sales?month=2026-07` — エラーなく表示され、加盟店別一覧の金額が変更前と同じ（2026-07 はすべてカットオフ前のため）。注記が表示されている
2. 加盟店の「詳細」— `admin/sales/show` がエラーなく表示され、明細件数・合計が変更前と同じ
3. 加盟店の「請求書を確認」— `admin/sales/invoice` がエラーなく表示され、金額が加盟店側 LIFF の請求書と一致する
4. 上部の売上集計（商品別・送料・合計）は変更前と同じ値のまま
5. 加盟店側の請求書一覧（`http://localhost:8884/merchants/invoices`、`LIFF_MOCK` 有効時は LIFF 認証をスキップできる）が表示され、月の顔ぶれと金額が管理画面の請求書と一致する。`SendMonthlyInvoiceLine` と `PaymentReminderSender` は `InvoiceService` 経由なので個別の修正は不要

`storage/logs/laravel.log` に新たなエラーが出ていないことも確認する：

Run: `docker compose exec -T php_ykk08ok tail -n 30 storage/logs/laravel.log`

- [ ] **Step 7: コミット**

```bash
git add app/Http/Controllers/Admin/SalesController.php resources/views/admin/sales/index.blade.php
git commit -m "管理画面の請求額集計を発送済み基準に合わせ、売上集計との違いを注記"
```

---

## デプロイ時の注意

- デプロイは push により GitHub Actions が `git pull` と `php artisan migrate --force` を実行する。Task 1 のマイグレーションはこれで本番に反映される。
- 本計画に JS/CSS の変更は含まれないため、`public/build` の手動アップロードは不要。
- 新ルールは 2026-09-01 以降に作成された注文から効くため、デプロイ直後に過去の請求金額が変わることはない。ただし `shipped_at` の記録はデプロイ直後から始まる必要があるので、**2026-09-01 より前にデプロイを完了させること**。
