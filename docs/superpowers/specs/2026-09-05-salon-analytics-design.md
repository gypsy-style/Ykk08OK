# サロン分析画面 設計

## 背景と目的

本部が加盟店の状況を横断して見る場所がない。既存のダッシュボードは単月の
スナップショットで、伸びているサロンも止まっているサロンも区別がつかない。

直近6ヶ月の推移を1画面で見られるようにし、フォローすべきサロンを見つけられる
ようにする。

## スコープ

本部の管理画面に「サロン分析」を1画面追加する。

含むもの:

- 月ごとの新規ユーザー数・新規加盟店数（6ヶ月分）
- サロンごとの月次売上と6ヶ月合計（6ヶ月分）
- 終点の月を切り替える先月／次月リンク

含まないもの:

- 代理店画面への同等機能の追加
- グラフ描画（Chart.js 等の新規導入はしない）
- CSV などのエクスポート
- サロンごとのユーザー数

## 集計の定義

| 項目 | 定義 |
| --- | --- |
| 対象期間 | `?month=YYYY-MM` を終点とする直近6ヶ月。未指定なら当月 |
| 新規ユーザー数 | `users.created_at` がその月に入る行数 |
| 新規加盟店数 | `merchants.created_at` がその月に入る行数（`is_test = 1` を除く） |
| サロン別売上 | 月ごとに `SUM(orders.total_price)` を求め、`round(合計 × 1.1)` で税込にする |
| 売上の計上月 | `orders.shipped_at` の年月 |
| 発送済み判定 | `InvoiceService::applyInvoiceScope()`（`status = 6` かつ `shipped_at IS NOT NULL`） |
| 並び順 | 6ヶ月合計の降順。同額なら加盟店名の昇順 |

### 決定事項とその理由

**税込は月ごとに丸める。** 請求書が月単位で `round($subtotal * 1.1)` しているため、
同じ単位で丸めないと請求額と1円ずれる。合計列は各月の税込を足した値とし、
6ヶ月の税抜合計をまとめて丸め直すことはしない。

**送料は含めない。** サロン間で比べたいのは商品の売上であり、送料は本部が
立て替える実費という位置づけのため。既存のダッシュボードも売上と送料を
分けて表示している。

**ユーザー数はサロンで絞り込まない。** `users.merchant_id` はカラムとしては
存在するが `UserController::create()` がセットしておらず、実質使われていない。
ユーザーとサロンの紐付けは `merchants.user_id`（オーナー）と
`merchant_members`（スタッフ）の2経路で行われる。よって `users` は
「LINE で友だち登録した人」全体を指し、サロン単位の内訳は出せない。
既存の「ユーザー管理」画面も `users` を素で一覧しており、基準がそろう。

**新規加盟店数はソフトデリート済みも数える。** `DB::table('merchants')` を使い
`deleted_at` で絞らない。絞ると「4月の新規加盟店数」がサロンを削除するたびに
後から変わってしまい、過去の数字として使えなくなるため。

**売上ゼロのサロンも一覧に出す。** 登録済みだが注文がないサロンを見つけるのが
この画面の主目的の一つであるため。

**期間内に売上がある削除済みサロンは `withTrashed()` で名前を引く。**
`Merchant` は `SoftDeletes` なので通常の一覧からは消えるが、売上データは残る。
除外すると一覧の合計が売上管理の数字と食い違う。サロン名に「（削除済み）」を
付けて通常どおり並べる。

## 設計

### ルート

`routes/web.php` の Admin グループ、`middleware(['auth:admin', 'admin.permission'])`
の内側に1行追加する。

```php
Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');
```

`Admin\AnalyticsController` と衝突する名前が `routes/web.php` に無いため、
`use App\Http\Controllers\Admin\AnalyticsController;` を素の名前で追加する。

URL は `/admin/analytics`、ルート名は `admin.analytics`。

権限の追加実装は不要。`AdminPermission` は permission = 2（倉庫）に対する
許可リスト方式であり、`WAREHOUSE_ALLOWED_ROUTES` に `admin.analytics` を
加えない限り倉庫ユーザーは 403 になる。

### サービス

`app/Services/SalonAnalyticsService.php` を新規作成する。

```php
class SalonAnalyticsService
{
    public const MONTHS = 6;

    /** 終点の月から遡って6ヶ月分の 'YYYY-MM' を古い順で返す */
    public static function months(string $endMonth): array;

    /** ['2026-04' => ['users' => 12, 'merchants' => 2], ...] */
    public static function monthlySignups(array $months): array;

    /**
     * [
     *   ['merchant_id' => 3, 'name' => 'あさひ堂', 'deleted' => false,
     *    'monthly' => ['2026-04' => 132000, ...], 'total' => 732600],
     *   ...
     * ]
     */
    public static function salonSales(array $months): array;
}
```

集計はここに閉じ込め、コントローラには置かない。発送済み判定は
`InvoiceService` に委譲し、請求書と分析で基準が二重管理にならないようにする。

### クエリ

期間の境界は `$months` の先頭月の初日と、末尾月の翌月初日とする。

```php
$start = Carbon::parse($months[0] . '-01')->startOfMonth();
$end   = Carbon::parse(end($months) . '-01')->addMonth()->startOfMonth();
```

絞り込みは素の日時比較で行い、`DATE_FORMAT` は月への振り分けにだけ使う。
`DATE_FORMAT` を WHERE 句に書くとインデックスが効かないため。

```php
$query = DB::table('orders')
    ->selectRaw('merchant_id, DATE_FORMAT(shipped_at, "%Y-%m") as ym, SUM(total_price) as subtotal')
    ->whereNotIn('merchant_id', function ($q) {
        $q->select('id')->from('merchants')->where('is_test', 1);
    })
    ->where('shipped_at', '>=', $start)
    ->where('shipped_at', '<', $end)
    ->groupBy('merchant_id', 'ym');

InvoiceService::applyInvoiceScope($query);
$rows = $query->get();
```

6ヶ月分を1本のクエリで取り、PHP 側でピボットする。サロンごとにループすると
本番の100件規模で N+1 になるため。

新規ユーザー数・新規加盟店数も同様に、`created_at` の日時比較で絞り、
`GROUP BY DATE_FORMAT(created_at, '%Y-%m')` で月ごとに数える。

### コントローラ

`app/Http/Controllers/Admin/AnalyticsController.php` を新規作成する。
`month` を受け取り、サービスを呼び、ビューに渡すだけとする。

`month` は外部入力のため、`/^\d{4}-\d{2}$/` に一致しなければ当月に
フォールバックする。既存のダッシュボードは `Carbon::parse()` に直接渡しており
不正値で 500 になるが、今回の変更対象ではないため触らない。

先月／次月は既存ダッシュボードと同じ計算とし、当月より先には進めない。

### 画面

`resources/views/admin/analytics/index.blade.php` を新規作成する。
`@extends('admin.layouts.app')`、`<section class="lma-content flex">` の
既存構成に従う。

上段は月次サマリー。`lma-content_block dashboard_records` と
`records_list`（`<dl>`）を流用し、6ヶ月ぶんの新規ユーザー数・新規加盟店数を
並べる。

下段はサロン別売上で、`<table class="lma-detail_tbl">` を使う。
`admin/sales/show.blade.php` が同じクラスで表を組んでおり既存踏襲となる。
8列の行列を `<dl>` で組むのは無理があるため、この画面では表を使う。

```
サロン名        4月      5月      6月      7月      8月      9月      合計
あさひ堂     132,000  107,800  157,300  121,000  170,500   44,000   732,600
うめのや           0        0   33,000   49,500   57,200   19,800   159,500
```

月の切り替えは既存の `lma-pnavi_list`（先月／次月）と同じマークアップとし、
リンク先を `admin.analytics` にする。`admin/sales/partials/month_nav.blade.php`
はリンク先が `admin.sales.index` に固定されているため再利用せず、同じ
マークアップをこの画面に書く。

### サイドバー

`resources/views/admin/layouts/app.blade.php` の
`@if(auth('admin')->user()->permission === 1)` ブロック内、「売上管理」の隣に
1項目追加する。倉庫ユーザーには 403 になるリンクを見せない。

## 影響範囲

既存ファイルの変更は `routes/web.php` とサイドバーの各1箇所のみ。
既存の集計ロジックには手を入れないため、ダッシュボード・売上管理・請求書の
数字は変わらない。

## 完了条件

- `/admin/analytics` が開き、直近6ヶ月の新規ユーザー数・新規加盟店数が月ごとに出る
- サロンごとの月次売上と6ヶ月合計が、合計の降順で並ぶ
- 売上ゼロのサロンも一覧に出る
- 期間内に売上がある削除済みサロンが「（削除済み）」付きで一覧に出る
- テスト加盟店が売上・加盟店数のどちらにも出ない
- 先月／次月で期間がずれ、当月より先には進めない
- `?month=` に不正な値を渡しても 500 にならず当月が表示される
- 倉庫権限（permission = 2）のユーザーがアクセスすると 403 になり、
  サイドバーにも項目が出ない
