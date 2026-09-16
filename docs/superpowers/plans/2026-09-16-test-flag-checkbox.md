# テストフラグの代理店対応と「テストを含めない」チェックボックス 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 代理店にもテストフラグを持たせ、管理画面から ON/OFF でき、管理者向けの売上・受注画面に「テストを含めない」チェックボックス（デフォルト ON）を置く。

**Architecture:** テストデータの判定を `App\Services\TestDataFilter` の静的メソッドに一元化し、既存の `whereNotIn('merchant_id', ...)` を全部そこに寄せる。管理者画面の各コントローラは GET の `exclude_test` を見て、その除外を呼ぶかどうかだけを切り替える。

**Tech Stack:** Laravel（PHP）、Blade、jQuery、MySQL、Docker Compose

元設計: `docs/superpowers/specs/2026-09-15-test-flag-checkbox-design.md`

## Global Constraints

- **テストコードは書かない。** このプロジェクトの方針。各タスクの検証は管理画面での手動確認で行う。
- 既存ファイルのスタイルに合わせる。依頼外の大規模リファクタ・整形はしない。
- Eloquent のグローバルスコープは使わない。`DB::table` の生クエリに効かないため。
- ローカル環境: Web は `http://localhost:8884`（`/ykk08ok` プレフィックスなし）。artisan は `docker exec php_ykk08ok php artisan ...`。
- 本番デプロイは main への push で GitHub Actions が `git pull` + `migrate` を実行する。この計画の中ではデプロイしない。
- `exclude_test` パラメータは **未指定なら 1（除外）** として扱う。判定は必ず `$request->query('exclude_test', '1') !== '0'`。
- テストバッジの HTML は既存のものをそのまま使い回す:
  `<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#f60;color:#fff;font-size:11px;vertical-align:middle;">テスト</span>`

---

## File Structure

新規

- `database/migrations/2026_09_16_000001_add_is_test_to_agencies_table.php` — `agencies.is_test` の追加
- `app/Services/TestDataFilter.php` — テストデータ判定の唯一の置き場
- `resources/views/admin/partials/exclude_test_checkbox.blade.php` — チェックボックスの共通パーシャル

変更

- `app/Models/Agency.php` — `$fillable` / `$casts`
- `app/Http/Controllers/Admin/{SalesController,DashboardController,OrderController,ExportController,MerchantController,AgencyController,AnalyticsController}.php`
- `app/Http/Controllers/Agency/{DashboardController,OrderController,MerchantController}.php`
- `app/Http/Controllers/OrderController.php`
- `app/Services/SalonAnalyticsService.php`
- `resources/views/admin/` 配下の Blade（一覧・編集フォーム・月ナビ）

---

## Task 1: agencies にテストフラグ列を足す

**Files:**
- Create: `database/migrations/2026_09_16_000001_add_is_test_to_agencies_table.php`
- Modify: `app/Models/Agency.php`

**Interfaces:**
- Consumes: なし
- Produces: `agencies.is_test`（boolean, default 0, NOT NULL）、`Agency::$is_test`（bool にキャスト済み）

- [ ] **Step 1: マイグレーションを作る**

`merchants` 側（`2026_08_01_000002_add_is_test_to_merchants_table.php`）と同じ形にする。

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
        Schema::table('agencies', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('agency_code'); // テスト代理店フラグ
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });
    }
};
```

- [ ] **Step 2: Agency モデルに fillable と casts を足す**

`app/Models/Agency.php`。`$fillable` の `agency_code` の次に `is_test` を追加し、`$hidden` の下に `$casts` を新設する。

```php
    protected $fillable = [
        'agency_code',
        'is_test',
        'name',
        'postal_code1',
        'postal_code2',
        'address',
        'phone',
        'contact_person',
        'email',
        'password'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_test' => 'boolean',
    ];
```

- [ ] **Step 3: マイグレーションを流す**

Run: `docker exec php_ykk08ok php artisan migrate`
Expected: `add_is_test_to_agencies_table` が DONE で終わる

- [ ] **Step 4: 列が入ったか確認**

Run: `docker exec php_ykk08ok php artisan tinker --execute="echo App\Models\Agency::first()->is_test ? 'true' : 'false';"`
Expected: `false`（全代理店が 0 で入っている）

- [ ] **Step 5: コミット**

```bash
git add database/migrations/2026_09_16_000001_add_is_test_to_agencies_table.php app/Models/Agency.php
git commit -m "代理店にテストフラグを追加"
```

---

## Task 2: テストデータ判定を TestDataFilter に一元化する

代理店フラグが ON なら配下の加盟店も全部テスト扱いにする。判定式が変わるので、散らばった
`whereNotIn('merchant_id', fn => merchants.is_test = 1)` を全部この 1 クラスに寄せる。
**このタスクでは挙動は変えない**（どの画面も今まで通り常に除外のまま）。

**Files:**
- Create: `app/Services/TestDataFilter.php`
- Modify: `app/Http/Controllers/Admin/SalesController.php:34-36,52-54,61-63`
- Modify: `app/Http/Controllers/Admin/DashboardController.php:20,27-29,38-40,48-50,59-61`
- Modify: `app/Http/Controllers/Admin/OrderController.php:39-41,48-50,57-59`
- Modify: `app/Http/Controllers/Admin/ExportController.php:102-104`
- Modify: `app/Http/Controllers/Agency/DashboardController.php:20-22,29-31,40-42`
- Modify: `app/Http/Controllers/Agency/OrderController.php:40-42`
- Modify: `app/Http/Controllers/Agency/MerchantController.php:26,52`
- Modify: `app/Http/Controllers/OrderController.php:316`
- Modify: `app/Services/SalonAnalyticsService.php:59,109-111,182,199,231,353`

**Interfaces:**
- Consumes: Task 1 の `agencies.is_test`
- Produces:
  - `TestDataFilter::excludeMerchants($query, $alias = ''): mixed` — `{alias}.merchant_id` がテスト加盟店のものを除外
  - `TestDataFilter::excludeMerchantRows($query, $alias = ''): mixed` — `merchants` テーブル自身を引いているクエリで、テスト加盟店の行を除外
  - `TestDataFilter::excludeAgencyRows($query, $alias = ''): mixed` — `agencies` テーブル自身を引いているクエリで、テスト代理店の行を除外
  - `TestDataFilter::isTestMerchant($merchant): bool` — Merchant モデル（または null）を渡して判定

- [ ] **Step 1: TestDataFilter を作る**

`InvoiceService` と同じ書き方（静的メソッド + `$alias` 引数）に揃える。

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * テストデータの除外ロジック
 *
 * 代理店のテストフラグが立っていれば、配下の加盟店は merchants.is_test の値に
 * 関わらずテスト扱いになる。判定式を変えるときは必ずここだけを直すこと。
 *
 * Eloquent のグローバルスコープは使わない。DB::table の生クエリに効かないため。
 */
class TestDataFilter
{
    /**
     * テスト扱いになる加盟店IDのサブクエリ
     *
     * merchants.agency_id は NULL を許すため left join で引く。代理店が未設定の
     * 加盟店は merchants.is_test だけで判定される。
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public static function testMerchantIds()
    {
        return DB::table('merchants as tm')
            ->leftJoin('agencies as ta', 'ta.id', '=', 'tm.agency_id')
            ->where(function ($q) {
                $q->where('tm.is_test', 1)->orWhere('ta.is_test', 1);
            })
            ->select('tm.id');
    }

    /**
     * merchant_id を持つクエリからテスト加盟店の分を除外する
     *
     * @param mixed $query Eloquent または DB::table のクエリビルダ
     * @param string $alias テーブル別名（join で 'o' などを使っている場合に渡す）
     * @return mixed
     */
    public static function excludeMerchants($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->whereNotIn($p . 'merchant_id', self::testMerchantIds());
    }

    /**
     * merchants テーブル自身を引いているクエリからテスト加盟店の行を除外する
     *
     * @param mixed $query
     * @param string $alias
     * @return mixed
     */
    public static function excludeMerchantRows($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->whereNotIn($p . 'id', self::testMerchantIds());
    }

    /**
     * agencies テーブル自身を引いているクエリからテスト代理店の行を除外する
     *
     * @param mixed $query
     * @param string $alias
     * @return mixed
     */
    public static function excludeAgencyRows($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->where($p . 'is_test', 0);
    }

    /**
     * 加盟店1件がテスト扱いかどうか
     *
     * @param \App\Models\Merchant|null $merchant
     * @return bool
     */
    public static function isTestMerchant($merchant)
    {
        if ($merchant === null) {
            return false;
        }

        return (bool) $merchant->is_test || (bool) optional($merchant->agency)->is_test;
    }
}
```

- [ ] **Step 2: 置き換え対象を洗い出して漏れがないか確認**

Run: `grep -rn "is_test" app/ --include=*.php`
Expected: Task 2 の Files に挙げたファイルだけが出る。ここに出たものを Step 3〜5 で全部つぶす。

- [ ] **Step 3: `whereNotIn` 形式を置き換える**

下記の 5 行のかたまりを、すべて `TestDataFilter::excludeMerchants(...)` の 1 行に置き換える。
`use App\Services\TestDataFilter;` をファイル先頭の use 句に追加すること。

別名なし（`DB::table('orders')` や `Order::` に直接ぶら下がっている形）:

```php
// before
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })

// after
            ->whereNotIn('merchant_id', TestDataFilter::testMerchantIds())
```

別名あり（`o` で join している形）:

```php
// before
            ->whereNotIn('o.merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })

// after
            ->whereNotIn('o.merchant_id', TestDataFilter::testMerchantIds())
```

対象ファイルと箇所:

- `Admin/SalesController.php` — 3箇所（商品別売上 `o.`、本部処理済み、送料件数）
- `Admin/DashboardController.php` — 4箇所（ステータス集計、本部処理済み、送料件数、商品別売上 `o.`）
- `Admin/OrderController.php` — 3箇所（代理店処理済み、本部処理済み、ステータス集計）
- `Admin/ExportController.php` — 1箇所（CSV）
- `Agency/DashboardController.php` — 3箇所（本日注文、本部処理済み、送料件数）
- `SalonAnalyticsService.php:109-111` — 1箇所（`o.` あり）

- [ ] **Step 4: `where('is_test', 0)` 形式を置き換える**

`merchants` テーブル自身を引いている箇所。`use App\Services\TestDataFilter;` を追加すること。

`app/Http/Controllers/Admin/DashboardController.php:20`

```php
// before
            'merchantCount' => \App\Models\Merchant::where('is_test', 0)->count(),

// after
            'merchantCount' => TestDataFilter::excludeMerchantRows(\App\Models\Merchant::query())->count(),
```

`app/Http/Controllers/Agency/MerchantController.php:26`

```php
// before
        $merchants = Merchant::where('agency_id', $agencyId)->where('is_test', 0)->get();

// after
        $merchants = TestDataFilter::excludeMerchantRows(Merchant::where('agency_id', $agencyId))->get();
```

`app/Http/Controllers/Agency/MerchantController.php:52` も同じ形。実際の行を読んで、
`->where('is_test', 0)` を外し `TestDataFilter::excludeMerchantRows(...)` で包むこと。

`app/Services/SalonAnalyticsService.php:59`

```php
// before
        $rows = DB::table('merchants')
            ->where('is_test', 0)

// after
        $rows = TestDataFilter::excludeMerchantRows(DB::table('merchants'))
```

`app/Services/SalonAnalyticsService.php:182`

```php
// before
            'merchantCount' => Merchant::where('is_test', 0)->count(),

// after
            'merchantCount' => TestDataFilter::excludeMerchantRows(Merchant::query())->count(),
```

`app/Services/SalonAnalyticsService.php:199`

```php
// before
        $merchant = Merchant::withTrashed()->with('agency')->where('is_test', 0)->find($merchantId);

// after
        $merchant = TestDataFilter::excludeMerchantRows(Merchant::withTrashed()->with('agency'))->find($merchantId);
```

`app/Services/SalonAnalyticsService.php:231`

```php
// before
        $merchants = Merchant::withTrashed()
            ->where('is_test', 0)
            ->where('agency_id', $agency->id)

// after
        $merchants = TestDataFilter::excludeMerchantRows(Merchant::withTrashed())
            ->where('agency_id', $agency->id)
```

`app/Services/SalonAnalyticsService.php:353`

```php
// before
        return Merchant::withTrashed()
            ->where('is_test', 0)
            ->orderBy('name')

// after
        return TestDataFilter::excludeMerchantRows(Merchant::withTrashed())
            ->orderBy('name')
```

- [ ] **Step 5: `whereHas` 形式と単体判定を置き換える**

`app/Http/Controllers/Agency/OrderController.php:40-42`

```php
// before
            ->whereHas('merchant', function ($query) use ($agencyId) {
                $query->where('agency_id', $agencyId)
                      ->where('is_test', 0);
            })

// after
            ->whereHas('merchant', function ($query) use ($agencyId) {
                $query->where('agency_id', $agencyId);
            })
```

`whereHas` から抜いた分は、注文側で除外する。上の `whereHas` の直後に 1 行足す。

```php
            ->whereHas('merchant', function ($query) use ($agencyId) {
                $query->where('agency_id', $agencyId);
            })
            ->whereNotIn('merchant_id', TestDataFilter::testMerchantIds())
```

`app/Http/Controllers/OrderController.php:316`（LIFF の注文作成。管理画面ではない）

```php
// before
            // メール通知（テスト加盟店の注文では代理店に通知しない）
            if (!optional($order->merchant)->is_test) {

// after
            // メール通知（テスト加盟店の注文では代理店に通知しない）
            if (!TestDataFilter::isTestMerchant($order->merchant)) {
```

- [ ] **Step 6: 生の is_test 参照が残っていないか確認**

Run: `grep -rn "is_test" app/ --include=*.php | grep -v "app/Services/TestDataFilter.php"`
Expected: `app/Models/Merchant.php`（fillable / casts）と `app/Models/Agency.php`（fillable / casts）の
4 行だけが残る。クエリ内の `is_test` はゼロ件。

- [ ] **Step 7: 挙動が変わっていないことを手で確認**

`http://localhost:8884/admin/index`、`/admin/orders`、`/admin/sales` を開く。
Expected: 変更前と同じ件数・金額が出る。PHP のエラーが出ない。

- [ ] **Step 8: コミット**

```bash
git add app/Services/TestDataFilter.php app/Http/Controllers app/Services/SalonAnalyticsService.php
git commit -m "テストデータの判定を TestDataFilter に一元化"
```

---

## Task 3: 管理画面からテストフラグを操作できるようにする

**Files:**
- Modify: `resources/views/admin/merchants/edit.blade.php:49`（ステータスの `</dd>` の後）
- Modify: `app/Http/Controllers/Admin/MerchantController.php:146`（validate の `status` の次）
- Modify: `resources/views/admin/agencies/edit.blade.php:14`（代理店コードの `</dd>` の後）
- Modify: `app/Http/Controllers/Admin/AgencyController.php:88,100-110`
- Modify: `resources/views/admin/merchants/_list.blade.php:9`
- Modify: `resources/views/admin/agencies/index.blade.php:21`

**Interfaces:**
- Consumes: Task 1 の `agencies.is_test` と `Agency` の fillable
- Produces: 管理者が画面から `merchants.is_test` / `agencies.is_test` を切り替えられる状態

- [ ] **Step 1: 加盟店編集フォームにチェックボックスを足す**

`resources/views/admin/merchants/edit.blade.php`。ステータスの `</dd>`（49行目）の直後に挿入する。
hidden を前に置くことで、未チェックでも 0 が送信される。

```blade
                <dt><label for="is_test">テスト加盟店</label></dt>
                <dd>
                    <input type="hidden" name="is_test" value="0">
                    <label><input type="checkbox" id="is_test" name="is_test" value="1" {{ old('is_test', $merchant->is_test) ? 'checked' : '' }}> テストデータとして扱う</label>
                </dd>
```

- [ ] **Step 2: 加盟店更新にバリデーションを足す**

`app/Http/Controllers/Admin/MerchantController.php`。`update()` の validate 配列、
`'status' => 'required|integer|in:1,2',` の直後に 1 行足す。

```php
                'is_test' => 'required|boolean',
```

`$merchant->update($request->all())` で保存されるため、他の変更は不要（`is_test` は既に fillable）。

- [ ] **Step 3: 代理店編集フォームにチェックボックスを足す**

`resources/views/admin/agencies/edit.blade.php`。代理店コードの `</dd>`（14行目）の直後に挿入する。

```blade
                <dt><label for="is_test">テスト代理店</label></dt>
                <dd>
                    <input type="hidden" name="is_test" value="0">
                    <label><input type="checkbox" id="is_test" name="is_test" value="1" {{ old('is_test', $agency->is_test) ? 'checked' : '' }}> テストデータとして扱う（配下の加盟店もすべてテスト扱いになります）</label>
                </dd>
```

- [ ] **Step 4: 代理店更新にバリデーションと保存を足す**

`app/Http/Controllers/Admin/AgencyController.php` の `update()`。
validate 配列の `'agency_code' => ...` の直後に 1 行足す。

```php
            'is_test' => 'required|boolean',
```

`$agency->update([...])` は明示的な配列なので、`'agency_code' => $request->agency_code,` の直後に 1 行足す。

```php
            'is_test' => $request->boolean('is_test'),
```

- [ ] **Step 5: 加盟店一覧にテストバッジを出す**

`resources/views/admin/merchants/_list.blade.php:9`。行は消さない（管理者が編集に到達する必要があるため）。

```blade
                <h3 class="name">{{ $merchant->name }}@if ($merchant->is_test)<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#f60;color:#fff;font-size:11px;vertical-align:middle;">テスト</span>@endif</h3>
```

- [ ] **Step 6: 代理店一覧にテストバッジを出す**

`resources/views/admin/agencies/index.blade.php:21`。

```blade
                        <h3 class="name">{{ $agency->name }}@if ($agency->is_test)<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#f60;color:#fff;font-size:11px;vertical-align:middle;">テスト</span>@endif</h3>
```

- [ ] **Step 7: 手で動作確認**

1. `http://localhost:8884/admin/agencies` で任意の代理店の「編集」を開く
2. 「テスト代理店」にチェックを入れて更新
3. 代理店一覧に「テスト」バッジが出ることを確認
4. `http://localhost:8884/admin/merchants` で、その代理店の配下の加盟店を開き、「テスト加盟店」のチェックを入れずに更新しても保存できることを確認
5. 代理店のチェックを外して元に戻す

Expected: どちらのフォームも保存でき、一覧にバッジが出る。他の項目が壊れない。

- [ ] **Step 8: コミット**

```bash
git add resources/views/admin/merchants/edit.blade.php resources/views/admin/agencies/edit.blade.php resources/views/admin/merchants/_list.blade.php resources/views/admin/agencies/index.blade.php app/Http/Controllers/Admin/MerchantController.php app/Http/Controllers/Admin/AgencyController.php
git commit -m "管理画面から加盟店・代理店のテストフラグを切り替えられるようにした"
```

---

## Task 4: チェックボックスの共通パーシャルを作り、受注一覧に置く

**Files:**
- Create: `resources/views/admin/partials/exclude_test_checkbox.blade.php`
- Modify: `app/Http/Controllers/Admin/OrderController.php`（`index()`）
- Modify: `resources/views/admin/orders/index.blade.php:30,32-75`
- Modify: `app/Http/Controllers/Admin/ExportController.php`（`exportOrders()`）

**Interfaces:**
- Consumes: Task 2 の `TestDataFilter::testMerchantIds()`
- Produces:
  - パーシャル `admin.partials.exclude_test_checkbox`。`$route`（ルート名の文字列）と `$excludeTest`（bool）と `$params`（隠しフィールドにして引き継ぐ追加パラメータの連想配列）を受け取る
  - コントローラでの判定イディオム `$excludeTest = $request->query('exclude_test', '1') !== '0';`

- [ ] **Step 1: 共通パーシャルを作る**

hidden を先に置くことで、チェックを外すと `exclude_test=0` が送信される。チェック時は
`exclude_test=1` が送られるが、サーバー側の既定値も 1 なので結果は同じ。JavaScript は使わない。

`$params` の値は、月やステータスなど「チェックを切り替えても保ちたい」パラメータ。

```blade
{{--
    テストデータ除外のチェックボックス
    $route       ... 送信先のルート名
    $excludeTest ... 現在の状態（bool）
    $params      ... 一緒に引き継ぐGETパラメータの連想配列（省略可）
--}}
<div class="lma-content_block nobg" style="width:100%;">
    <form method="GET" action="{{ route($route) }}" style="padding:4px 0;">
        @foreach ($params ?? [] as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="hidden" name="exclude_test" value="0">
        <label style="font-size:13px;cursor:pointer;">
            <input type="checkbox" name="exclude_test" value="1" onchange="this.form.submit();" {{ $excludeTest ? 'checked' : '' }}>
            テストを含めない
        </label>
    </form>
</div>
```

- [ ] **Step 2: 受注一覧のコントローラで exclude_test を読む**

`app/Http/Controllers/Admin/OrderController.php` の `index()`。
`$status` を取る行の直後にフラグを読み、注文一覧のクエリにも除外をかける（行ごと消すため）。

```php
        // GETパラメータからstatusを取得（デフォルトは1）
        $status = $request->get('status', 2);
        // 未指定ならテストを除外する
        $excludeTest = $request->query('exclude_test', '1') !== '0';

        $ordersQuery = Order::with(['merchant', 'details.product', 'agency', 'statusChangeLogs'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($ordersQuery);
        }
        $orders = $ordersQuery->get();
```

集計 3 本（`$agenciesProcessed` / `$headquartersProcessed` / `$statusCounts`）は Task 2 で
`->whereNotIn('merchant_id', TestDataFilter::testMerchantIds())` になっている。これを条件付きにする。
3 箇所とも、クエリを変数に受けてから分岐させる形にする。

```php
        //代理店処理済みの受注
        $agenciesProcessedQuery = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price')
            ->where('status', 2);
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($agenciesProcessedQuery);
        }
        $agenciesProcessed = $agenciesProcessedQuery->first();

        // 本部処理済みの受注
        $headquartersProcessedQuery = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price')
            ->where('status', 3);
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($headquartersProcessedQuery);
        }
        $headquartersProcessed = $headquartersProcessedQuery->first();

        // 各statusの件数を取得
        $statusCountsQuery = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', [2, 3, 4, 5, 6, 9]); // 対象とするステータス
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($statusCountsQuery);
        }
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status') // 結果を 'status' => 'count' の形式で取得
            ->toArray();
```

最後の `return view(...)` の compact に `'excludeTest'` を足す。

```php
        return view('admin.orders.index', compact('status','orders','agenciesProcessed','headquartersProcessed','statusCounts','excludeTest'));
```

- [ ] **Step 3: 受注一覧の Blade にチェックボックスとリンク引き継ぎを入れる**

`resources/views/admin/orders/index.blade.php`。

30行目の CSV リンクに `exclude_test` を引き継ぐ。

```blade
    <p class="lma-btn_box btn_wide"><a href="{{ route('admin.export.orders', ['exclude_test' => $excludeTest ? 1 : 0]) }}">店舗別CSVダウンロード</a></p>
```

32行目の `<div class="lma-content_block nobg">` の直前にチェックボックスを差し込む。

```blade
    @include('admin.partials.exclude_test_checkbox', [
        'route' => 'admin.orders.index',
        'excludeTest' => $excludeTest,
        'params' => ['status' => $status],
    ])
```

ステータスタブの `route('admin.orders.index', [...])` 6 箇所すべてに `exclude_test` を足す。
たとえば 37 行目は次のようになる。他の 5 箇所（status 3 / 5 / 6 / 4 / 9）も同じ形で直す。

```blade
                <a href="{{ route('admin.orders.index', ['status' => 2, 'exclude_test' => $excludeTest ? 1 : 0]) }}">代理店処理済み({{ $statusCounts[2] }})</a>
```

- [ ] **Step 4: CSV 出力を条件付きにする**

`app/Http/Controllers/Admin/ExportController.php` の `exportOrders()`。
メソッドが `Request $request` を受け取っていなければ引数に足す。
`StreamedResponse` のクロージャは `use` で値を持ち込む必要があるため、フラグは外で読む。

```php
        // 未指定ならテストを除外する
        $excludeTest = $request->query('exclude_test', '1') !== '0';

        $response = new StreamedResponse(function () use ($headers, $excludeTest) {
```

クロージャ内のデータ取得を差し替える。

```php
            // データ取得
            $ordersQuery = Order::with(['merchant', 'agency', 'details.product'])
                ->where('status', 3);
            if ($excludeTest) {
                TestDataFilter::excludeMerchants($ordersQuery);
            }
            $orders = $ordersQuery->get();
```

- [ ] **Step 5: 手で動作確認**

事前準備として、ローカルの加盟店 1 件の「テスト加盟店」を ON にしておく（Task 3 の画面から）。
その加盟店に注文がなければ、代理店側でテスト注文を 1 件作るか、DB で既存注文の
`merchant_id` を一時的にその加盟店に付け替える。

1. `http://localhost:8884/admin/orders` を開く
   Expected: チェックボックスが**チェック済み**で表示され、テスト加盟店の注文行が出ない
2. チェックを外す
   Expected: ページが再読込され、URL に `exclude_test=0` が付き、テスト行が「テスト」バッジ付きで出る。件数と金額も増える
3. チェックを外したままステータスタブを切り替える
   Expected: `exclude_test=0` が維持される
4. ステータスを「本部処理済み」にして CSV ダウンロード
   Expected: チェックON時はテスト注文が CSV に入らない。チェックOFF時は入る

- [ ] **Step 6: コミット**

```bash
git add resources/views/admin/partials/exclude_test_checkbox.blade.php resources/views/admin/orders/index.blade.php app/Http/Controllers/Admin/OrderController.php app/Http/Controllers/Admin/ExportController.php
git commit -m "受注一覧とCSV出力にテスト除外チェックボックスを追加"
```

---

## Task 5: 売上管理一覧にチェックボックスを置く

**Files:**
- Modify: `app/Http/Controllers/Admin/SalesController.php`（`index()`）
- Modify: `resources/views/admin/sales/index.blade.php:46`
- Modify: `resources/views/admin/sales/partials/month_nav.blade.php:3,6`

**Interfaces:**
- Consumes: Task 4 のパーシャル `admin.partials.exclude_test_checkbox`
- Produces: なし

- [ ] **Step 1: コントローラで exclude_test を読んで各クエリを条件付きにする**

`app/Http/Controllers/Admin/SalesController.php` の `index()`。`$month` の直後にフラグを読む。

```php
        $month = $request->query('month', Carbon::now()->format('Y-m'));
        // 未指定ならテストを除外する
        $excludeTest = $request->query('exclude_test', '1') !== '0';
```

Task 2 で `->whereNotIn('merchant_id', TestDataFilter::testMerchantIds())` になっている 3 箇所
（`$productSalesQuery` / `$headquartersProcessedQuery` / `$shippingFeeCountQuery`）から、その行を外して
クエリ生成の直後に分岐を置く。`$productSalesQuery` の例:

```php
        // 商品別の月別売上集計
        $productSalesQuery = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($productSalesQuery, 'o');
        }
        InvoiceService::applyInvoiceScope($productSalesQuery, 'o');
```

`$headquartersProcessedQuery` と `$shippingFeeCountQuery` は別名なしなので
`TestDataFilter::excludeMerchants($headquartersProcessedQuery);` のように第2引数を省く。

加盟店ごとの一覧 `$merchantSalesQuery`（69行目）は今まで除外していない。ここにも分岐を足して、
チェックON時はテスト加盟店の行を消す。

```php
        // 月内に売上があった店舗一覧（請求額なので発送済みのみ集計）
        $merchantSalesQuery = DB::table('orders as o')
            ->join('merchants as m', 'm.id', '=', 'o.merchant_id')
            ->leftJoin('agencies as a', 'a.id', '=', 'm.agency_id');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($merchantSalesQuery, 'o');
        }
        InvoiceService::applyInvoiceScope($merchantSalesQuery, 'o');
```

`return view(...)` の compact に `'excludeTest'` を足す。compact の書き方はファイル末尾の
実際の記述に合わせること。

- [ ] **Step 2: Blade にチェックボックスを差し込む**

`resources/views/admin/sales/index.blade.php:46` の `@include('admin.sales.partials.month_nav')` の
直前に置く。

```blade
    @include('admin.partials.exclude_test_checkbox', [
        'route' => 'admin.sales.index',
        'excludeTest' => $excludeTest,
        'params' => ['month' => $month],
    ])
```

- [ ] **Step 3: 月ナビに exclude_test を引き継ぐ**

`resources/views/admin/sales/partials/month_nav.blade.php`。

```blade
        <li class="prev"><a href="{{ route('admin.sales.index', ['month' => $prevMonth, 'exclude_test' => $excludeTest ? 1 : 0]) }}">先月</a></li>
        {{-- 当月を表示中は次月へ進めない --}}
        @if ($isFixedMonth)
            <li class="next"><a href="{{ route('admin.sales.index', ['month' => $nextMonth, 'exclude_test' => $excludeTest ? 1 : 0]) }}">次月</a></li>
        @endif
```

- [ ] **Step 4: 手で動作確認**

1. `http://localhost:8884/admin/sales` を開く
   Expected: チェックボックスがチェック済み。テスト加盟店の行が一覧に出ない
2. チェックを外す
   Expected: テスト加盟店の行が「テスト」バッジ付きで出る。商品別売上・送料・合計の金額も増える
3. チェックを外したまま「先月」を押す
   Expected: `exclude_test=0` が維持される
4. チェックを外した状態でテスト加盟店の「請求書」を開く
   Expected: 今まで通り表示される（請求書経路は素通しのまま）

- [ ] **Step 5: コミット**

```bash
git add app/Http/Controllers/Admin/SalesController.php resources/views/admin/sales/index.blade.php resources/views/admin/sales/partials/month_nav.blade.php
git commit -m "売上管理一覧にテスト除外チェックボックスを追加"
```

---

## Task 6: ダッシュボードにチェックボックスを置く

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard.blade.php:7`（`<section class="lma-content flex">` の直後）

**Interfaces:**
- Consumes: Task 4 のパーシャル
- Produces: なし

- [ ] **Step 1: コントローラを条件付きにする**

`app/Http/Controllers/Admin/DashboardController.php`。`$month` の直後にフラグを読む。

```php
        $month = $request->query('month', Carbon::now()->format('Y-m'));
        // 未指定ならテストを除外する
        $excludeTest = $request->query('exclude_test', '1') !== '0';
```

加盟店数（20行目、Task 2 で `TestDataFilter::excludeMerchantRows(...)` になっている）を分岐させる。

```php
        $merchantCountQuery = \App\Models\Merchant::query();
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($merchantCountQuery);
        }

        // ダッシュボード用のデータを取得する場合
        $data = [
            'agencyCount' => \App\Models\Agency::count(),
            'merchantCount' => $merchantCountQuery->count(),
        ];
```

代理店数も同じ扱いにする。`'agencyCount'` の行を次にする。

```php
        $agencyCountQuery = \App\Models\Agency::query();
        if ($excludeTest) {
            TestDataFilter::excludeAgencyRows($agencyCountQuery);
        }
```

```php
            'agencyCount' => $agencyCountQuery->count(),
```

残り 4 本の DB::table クエリ（`$statusCounts` / `$headquartersProcessedQuery` /
`$shippingFeeCountQuery` / `$productSalesQuery`）は、Task 2 で入れた
`->whereNotIn('merchant_id', TestDataFilter::testMerchantIds())` の行を外し、
クエリ生成の直後に `if ($excludeTest) { TestDataFilter::excludeMerchants($クエリ変数); }` を置く。
`$productSalesQuery` だけ第2引数に `'o'` を渡す。

`$statusCounts` はメソッドチェーンで書かれているので、Task 4 Step 2 と同じく
`$statusCountsQuery` に受けてから分岐させる形に直す。

`return view(...)` の compact に `'excludeTest'` を足す。

- [ ] **Step 2: Blade にチェックボックスを差し込む**

`resources/views/admin/dashboard.blade.php`。7行目 `<section class="lma-content flex">` の直後、
`<div class="lma-title_block center">` の前に置く。

```blade
	@include('admin.partials.exclude_test_checkbox', [
		'route' => 'admin.dashboard',
		'excludeTest' => $excludeTest,
		'params' => ['month' => $month],
	])
```

- [ ] **Step 3: 月ナビと注文リンクに引き継ぐ**

`resources/views/admin/dashboard.blade.php` 内の `route('admin.orders.index', ['status' => N])` を
すべて `['status' => N, 'exclude_test' => $excludeTest ? 1 : 0]` にする。
月の前後リンク（`$prevMonth` / `$nextMonth` を使っている箇所）にも同じく `exclude_test` を足す。

- [ ] **Step 4: 手で動作確認**

1. `http://localhost:8884/admin/index` を開く
   Expected: チェック済み。総加盟店数・総代理店数にテスト分が入っていない
2. チェックを外す
   Expected: 加盟店数・代理店数・売上・件数が増える
3. チェックを外したままステータスの数字リンクを押す
   Expected: 受注一覧でも `exclude_test=0` が維持されている

- [ ] **Step 5: コミット**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard.blade.php
git commit -m "ダッシュボードにテスト除外チェックボックスを追加"
```

---

## Task 7: サロン分析にチェックボックスを置く

サロン分析は AJAX で部分テーブルを差し替える。`exclude_test` は差し替え先の URL に
サーバー側で埋め込んで持ち回るので、JavaScript の変更は不要。

サロン詳細 `admin/analytics/salon/{merchant}` と代理店詳細 `admin/analytics/agency/{agency}` は
単一対象の詳細画面なのでチェックボックスを置かず、テスト絞りを外して常に表示できるようにする。
一覧でチェックを外さないとリンク自体が出ないため、詳細側で重ねて絞る意味がない。

**Files:**
- Modify: `app/Services/SalonAnalyticsService.php`
- Modify: `app/Http/Controllers/Admin/AnalyticsController.php`
- Modify: `resources/views/admin/analytics/index.blade.php:54,60`
- Modify: `resources/views/admin/analytics/_new_merchants.blade.php:23,26`
- Modify: `resources/views/admin/analytics/product_sales.blade.php`

**Interfaces:**
- Consumes: Task 4 のパーシャル、Task 2 の `TestDataFilter`
- Produces: `SalonAnalyticsService` の集計メソッドが `$excludeTest`（bool）を末尾引数で受ける

- [ ] **Step 1: サービスの集計メソッドに $excludeTest を足す**

`app/Services/SalonAnalyticsService.php`。既定値 `true` を付けて、呼び出し漏れがあっても
除外側（安全側）に倒れるようにする。

`monthlyNewMerchantsByAgency`:

```php
    public static function monthlyNewMerchantsByAgency(array $months, $excludeTest = true)
    {
        [$start, $end] = self::range($months);
```

その中の merchants クエリ（Task 2 で `TestDataFilter::excludeMerchantRows(DB::table('merchants'))`
になっている）を分岐させる。

```php
        $merchantsQuery = DB::table('merchants');
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($merchantsQuery);
        }
        $rows = $merchantsQuery
            ->selectRaw('agency_id, DATE_FORMAT(created_at, "%Y-%m") as ym, COUNT(*) as cnt')
```

同じメソッド内の代理店一覧（70行目）もテスト代理店を落とす。

```php
        $agenciesQuery = Agency::orderBy('name');
        if ($excludeTest) {
            TestDataFilter::excludeAgencyRows($agenciesQuery);
        }
        $agencies = $agenciesQuery->get(['id', 'name'])->all();
```

`salonProductSales`:

```php
    public static function salonProductSales($month, $excludeTest = true)
```

中の `whereNotIn('o.merchant_id', TestDataFilter::testMerchantIds())` を外し、
`$query` を組み立てた直後に分岐を置く。

```php
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($query, 'o');
        }
```

同メソッドは `self::merchants()` で行を組み立てているので、そこにも渡す。

```php
        foreach (self::merchants($excludeTest) as $merchant) {
```

`overview`:

```php
    public static function overview($excludeTest = true)
    {
        $summary = self::summary(self::merchants($excludeTest)->pluck('id')->all(), []);

        $merchantCountQuery = Merchant::query();
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($merchantCountQuery);
        }

        return [
            'merchantCount' => $merchantCountQuery->count(),
```

`merchants()`（private, 350行目付近）:

```php
    private static function merchants($excludeTest = true)
    {
        $query = Merchant::withTrashed();
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($query);
        }

        return $query->orderBy('name')->get(['id', 'name', 'deleted_at']);
    }
```

- [ ] **Step 2: 詳細画面のテスト絞りを外す**

`salonDetail`（199行目）。Task 2 で `TestDataFilter::excludeMerchantRows(...)` で包んだものを、
包まない形に戻す。テスト加盟店の詳細も開けるようにするため。

```php
        $merchant = Merchant::withTrashed()->with('agency')->find($merchantId);
```

`agencyDetail`（231行目）。同じく包みを外す。

```php
        $merchants = Merchant::withTrashed()
            ->where('agency_id', $agency->id)
            ->get(['id', 'name', 'deleted_at', 'created_at']);
```

- [ ] **Step 3: AnalyticsController でフラグを読んで渡す**

`app/Http/Controllers/Admin/AnalyticsController.php`。`month()` と同じ形で private メソッドを足す。

```php
    /** 未指定ならテストを除外する */
    private function excludeTest(Request $request)
    {
        return $request->query('exclude_test', '1') !== '0';
    }
```

`index()`:

```php
    public function index(Request $request)
    {
        $month = $this->month($request);
        $nav = $this->monthNav($month);
        $excludeTest = $this->excludeTest($request);

        return view('admin.analytics.index', array_merge(
            $nav,
            [
                'excludeTest' => $excludeTest,
                'overview' => SalonAnalyticsService::overview($excludeTest),
                'newMerchants' => SalonAnalyticsService::monthlyNewMerchantsByAgency($nav['months'], $excludeTest),
            ],
            $this->productSalesData($month, self::TOP_ROWS, $excludeTest)
        ));
    }
```

`newMerchants()`:

```php
    public function newMerchants(Request $request)
    {
        $nav = $this->monthNav($this->month($request));
        $excludeTest = $this->excludeTest($request);

        return view('admin.analytics._new_merchants', array_merge($nav, [
            'excludeTest' => $excludeTest,
            'newMerchants' => SalonAnalyticsService::monthlyNewMerchantsByAgency($nav['months'], $excludeTest),
        ]));
    }
```

`productSales()` と `productSalesAll()`:

```php
    public function productSales(Request $request)
    {
        $limit = $request->query('all') ? null : self::TOP_ROWS;

        return view('admin.analytics._product_sales', $this->productSalesData($this->month($request), $limit, $this->excludeTest($request)));
    }

    public function productSalesAll(Request $request)
    {
        return view('admin.analytics.product_sales', $this->productSalesData($this->month($request), null, $this->excludeTest($request)));
    }
```

`productSalesData()`:

```php
    private function productSalesData($month, $limit, $excludeTest)
    {
        $date = Carbon::parse($month . '-01');
        $sales = SalonAnalyticsService::salonProductSales($month, $excludeTest);
```

同メソッドの return 配列に 1 行足す。

```php
            'excludeTest' => $excludeTest,
```

`salon()` と `agency()` はチェックボックスを持たないが、Blade 側で `$excludeTest` を参照しないよう
にすること。参照が必要になった場合は `'excludeTest' => $this->excludeTest($request)` を
array_merge に足す。

- [ ] **Step 4: 分析トップの Blade にチェックボックスと AJAX の引き継ぎを入れる**

`resources/views/admin/analytics/index.blade.php`。11行目 `</div>`（lma-main_head の閉じ）の直後に置く。

```blade
    @include('admin.partials.exclude_test_checkbox', [
        'route' => 'admin.analytics.index',
        'excludeTest' => $excludeTest,
        'params' => ['month' => $month],
    ])
```

54行目と60行目の `data-url` に `exclude_test` を埋め込む。jQuery の `$.get` は `month` だけを
追加するので、URL 側に持たせておけば差し替え後も維持される。

```blade
        <div class="record_block analytics_async" data-url="{{ route('admin.analytics.new_merchants', ['exclude_test' => $excludeTest ? 1 : 0]) }}">
```

```blade
        <div class="record_block analytics_async" data-url="{{ route('admin.analytics.product_sales', ['exclude_test' => $excludeTest ? 1 : 0]) }}">
```

- [ ] **Step 5: 部分テーブルのリンクに引き継ぐ**

サロン詳細・代理店詳細へのリンク（`_new_merchants.blade.php:23,26`、`_product_sales.blade.php:23`、
`agency.blade.php`、`salon.blade.php`）は**変更しない**。詳細画面は絞り込みを持たないので、
`exclude_test` を渡しても効かず、URL に無意味なパラメータが残るだけになる。

同じ一覧に戻るリンクだけ引き継ぐ。`resources/views/admin/analytics/_product_sales.blade.php` の 38行目。

```blade
    <p class="lma-btn_box btn_wh btn_min analytics_more"><a href="{{ route('admin.analytics.product_sales_all', ['month' => $productMonth, 'exclude_test' => $excludeTest ? 1 : 0]) }}">もっと見る</a></p>
```

`resources/views/admin/analytics/product_sales.blade.php` の 14行目。
全サロン一覧ページはチェックボックスを持たず、遷移元から受け取った状態を保つだけにする。

```blade
        <div class="record_block analytics_async" data-url="{{ route('admin.analytics.product_sales', ['all' => 1, 'exclude_test' => $excludeTest ? 1 : 0]) }}">
```

- [ ] **Step 6: 手で動作確認**

1. `http://localhost:8884/admin/analytics` を開く
   Expected: チェック済み。総加盟店数・代理店別新規加盟店数・商品別売上にテスト分が入らない
2. チェックを外す
   Expected: テスト加盟店とテスト代理店の行が出る。数字が増える
3. チェックを外したまま、代理店別新規加盟店数の「先月」を押す
   Expected: AJAX で差し替わったあともテスト行が残っている（`exclude_test=0` が維持されている）
4. テスト加盟店の名前をクリックしてサロン詳細を開く
   Expected: 404 にならず詳細が表示される

- [ ] **Step 7: コミット**

```bash
git add app/Services/SalonAnalyticsService.php app/Http/Controllers/Admin/AnalyticsController.php resources/views/admin/analytics
git commit -m "サロン分析にテスト除外チェックボックスを追加"
```

---

## 最終確認

全タスク完了後に通しで確認する。

- [ ] **代理店フラグが配下に効くこと**

1. テスト代理店を 1 件 ON にする（配下の加盟店の `is_test` はすべて 0 のまま）
2. `/admin/orders`、`/admin/sales`、`/admin/index`、`/admin/analytics` を開く
   Expected: 配下の加盟店の注文・売上がすべて消えている
3. 各画面でチェックを外す
   Expected: 戻ってくる
4. 代理店のフラグを OFF に戻す

- [ ] **請求書経路が素通しのままであること**

`/admin/sales` でチェックを外し、テスト加盟店の請求書詳細と PDF を開く。
Expected: 今まで通り表示される。

- [ ] **代理店ログイン側の挙動が変わっていないこと**

代理店でログインし、ダッシュボード・受注一覧・加盟店一覧を開く。
Expected: チェックボックスは出ない。テストデータは出ない。
