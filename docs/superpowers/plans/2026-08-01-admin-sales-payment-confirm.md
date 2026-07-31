# 売上管理の振込確認・請求書送信 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `admin/sales` の加盟店一覧に「振込確認チェックボックス」と「請求書を送信ボタン」を追加する。

**Architecture:** 振込確認は新テーブル `merchant_payment_confirmations` に「行の有無」で保持する。請求書のLINE送信は、現在 `SendMonthlyInvoiceLine` コマンドに埋まっている送信処理を `InvoiceLineSender` サービスへ切り出し、バッチと管理画面の両方が同じ実装を呼ぶ形にする。画面のJSはビルド不要のインライン `fetch` で書く。

**Tech Stack:** Laravel 9.19 / PHP 8.0 / Blade + 素のJS（jQuery非依存）/ MySQL

**設計書:** `docs/superpowers/specs/2026-07-31-admin-sales-payment-confirm-design.md`

## Global Constraints

- **テストコードは書かない**（`CLAUDE.md` の方針）。各タスクの検証は `php artisan tinker` とブラウザでの手動確認で行う。この計画のテンプレートにあるTDDのステップは、この方針に合わせて手動検証に置き換えている
- 既存ファイルを触るときは**そのファイルの既存スタイルに合わせる**。全面リライトや無関係なリファクタはしない
- DBアクセスは Eloquent かクエリビルダ。文字列連結の生SQLは書かない
- 対象月は必ず `YYYY-MM` 形式。**当月以降は「未確定」として扱い、振込確認も請求書送信も許可しない**（判定は `InvoiceService::isFixedMonth()` に統一）
- ローカルの動作確認URLは `http://localhost:8884`
- Vite で別JSファイルを作らない。`public/build` は git 管理外で本番へ手動アップロードが必要なため、デプロイ漏れを避けて blade 内インライン `<script>` で書く
- 管理画面のレイアウト `admin/layouts/app.blade.php` には `@stack('scripts')` が無い。スクリプトは `@section('content')` の内側、`@endsection` の直前に置く
- CSRFトークンは `<meta name="csrf-token">` から取得できる（レイアウト9行目に既にある）

## File Structure

| ファイル | 役割 |
|---|---|
| `database/migrations/2026_08_01_000001_create_merchant_payment_confirmations_table.php` | 新規。振込確認テーブル |
| `app/Models/MerchantPaymentConfirmation.php` | 新規。振込確認モデル |
| `app/Services/InvoiceLineSender.php` | 新規。1加盟店・1ヶ月分のLINE送信 |
| `app/Console/Commands/SendMonthlyInvoiceLine.php` | 修正。送信処理をサービスへ委譲 |
| `routes/web.php` | 修正。POSTルート2本を追加 |
| `app/Http/Controllers/Admin/SalesController.php` | 修正。`index()` にデータ追加、2メソッド追加 |
| `resources/views/admin/sales/index.blade.php` | 修正。UI＋インラインJS |

---

### Task 1: 振込確認テーブルとモデル

**Files:**
- Create: `database/migrations/2026_08_01_000001_create_merchant_payment_confirmations_table.php`
- Create: `app/Models/MerchantPaymentConfirmation.php`

**Interfaces:**
- Consumes: なし
- Produces: `App\Models\MerchantPaymentConfirmation`（`$fillable`: `merchant_id`, `month`, `admin_id`, `confirmed_at` / `confirmed_at` は Carbon にキャスト / `merchant()` リレーション）

- [ ] **Step 1: マイグレーションを作る**

`database/migrations/2026_08_01_000001_create_merchant_payment_confirmations_table.php`:

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
        Schema::create('merchant_payment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');           // 加盟店ID
            $table->string('month', 7);                          // 対象月 YYYY-MM
            $table->unsignedBigInteger('admin_id')->nullable();  // 確認した管理者
            $table->timestamp('confirmed_at')->nullable();       // 確認日時
            $table->timestamps();

            // 同一加盟店・同一月の重複を防ぐ
            $table->unique(['merchant_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_payment_confirmations');
    }
};
```

- [ ] **Step 2: モデルを作る**

`app/Models/MerchantPaymentConfirmation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantPaymentConfirmation extends Model
{
    protected $fillable = [
        'merchant_id',
        'month',
        'admin_id',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
```

- [ ] **Step 3: マイグレーションを実行する**

Run: `php artisan migrate`
Expected: `Migrating: 2026_08_01_000001_create_merchant_payment_confirmations_table` → `Migrated`

- [ ] **Step 4: テーブルとモデルが動くことを確認する**

Run: `php artisan tinker --execute="\$c = App\Models\MerchantPaymentConfirmation::create(['merchant_id' => 1, 'month' => '2026-06', 'admin_id' => null, 'confirmed_at' => now()]); echo get_class(\$c->confirmed_at), ' ', \$c->confirmed_at->format('n/j'), PHP_EOL; \$c->delete(); echo App\Models\MerchantPaymentConfirmation::count(), PHP_EOL;"`

Expected: `Illuminate\Support\Carbon 8/1` と `0` が出力される（`confirmed_at` が Carbon にキャストされ、削除も効いている）

- [ ] **Step 5: コミット**

```bash
git add database/migrations/2026_08_01_000001_create_merchant_payment_confirmations_table.php app/Models/MerchantPaymentConfirmation.php
git commit -m "振込確認テーブルとモデルを追加"
```

---

### Task 2: 請求書LINE送信を InvoiceLineSender に切り出す

このタスクは**外から見た挙動を1つも変えない**リファクタ。`invoice:send-line` の出力が前後で同じであることが合格条件。

**Files:**
- Create: `app/Services/InvoiceLineSender.php`
- Modify: `app/Console/Commands/SendMonthlyInvoiceLine.php`（`handle()` の中身）

**Interfaces:**
- Consumes: `InvoiceService::forMonth(Merchant, string $month): array`（`order_count`, `label` などを含む）、`InvoiceService::invoiceUrl(): string`、`InvoiceLineMessageService::template(): string`、`InvoiceLineMessageService::render(string $template, Merchant, array $invoice, string $invoiceUrl): string`、`LineMessageService::sendMessage($userId, $message): array`（`['status' => 'success'|...]`）
- Produces:
  - `InvoiceLineSender::buildBody(Merchant $merchant, array $invoice): string`
  - `InvoiceLineSender::send(Merchant $merchant, string $month, ?string $overrideLineId = null): array`
    返り値は `['success' => bool, 'skipped' => bool, 'message' => string, 'sent_at' => \Carbon\Carbon|null]`
    - LINE ID未登録 → `success:false, skipped:true`（履歴を残さない）
    - 対象月の注文が0件 → `success:false, skipped:true`（履歴を残さない）
    - LINE API失敗 → `success:false, skipped:false`（履歴に `failed` を残す）
    - 成功 → `success:true, skipped:false, sent_at` に日時

- [ ] **Step 1: リファクタ前の出力を控える**

Run: `php artisan invoice:send-line --dry-run --month=2026-06 > /tmp/before.txt; cat /tmp/before.txt`
Expected: エラーにならず、対象月と加盟店数のサマリーが出る。この内容が Step 6 で一致するかを見るので必ず先に取る

- [ ] **Step 2: InvoiceLineSender を作る**

`app/Services/InvoiceLineSender.php`:

```php
<?php

namespace App\Services;

use App\Models\InvoiceLineSend;
use App\Models\Merchant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 請求書LINE通知の送信（1加盟店・1ヶ月分）
 *
 * 月次バッチ（invoice:send-line）と管理画面の送信ボタンで共用する。
 * 本文・送信履歴の扱いを変えるときは必ずここだけを直すこと。
 */
class InvoiceLineSender
{
    private $invoiceService;
    private $messageService;
    private $lineMessageService;

    public function __construct(
        InvoiceService $invoiceService,
        InvoiceLineMessageService $messageService,
        LineMessageService $lineMessageService
    ) {
        $this->invoiceService = $invoiceService;
        $this->messageService = $messageService;
        $this->lineMessageService = $lineMessageService;
    }

    /**
     * 送信する本文を組み立てる（dry-run でも使う）
     *
     * @param Merchant $merchant
     * @param array $invoice InvoiceService::forMonth の結果
     * @return string
     */
    public function buildBody(Merchant $merchant, array $invoice)
    {
        return $this->messageService->render(
            $this->messageService->template(),
            $merchant,
            $invoice,
            $this->invoiceService->invoiceUrl()
        );
    }

    /**
     * 1加盟店・1ヶ月分の請求書をLINEで送信する
     *
     * @param Merchant $merchant
     * @param string $month YYYY-MM
     * @param string|null $overrideLineId 送信先の上書き（テスト用。履歴を残さない）
     * @return array ['success' => bool, 'skipped' => bool, 'message' => string, 'sent_at' => Carbon|null]
     */
    public function send(Merchant $merchant, $month, $overrideLineId = null)
    {
        $lineId = $overrideLineId ?: optional($merchant->owner)->line_id;
        if (!$lineId) {
            return $this->skip('オーナーのLINE IDが未登録');
        }

        $invoice = $this->invoiceService->forMonth($merchant, $month);
        if ($invoice['order_count'] === 0) {
            return $this->skip('対象月の注文なし');
        }

        $body = $this->buildBody($merchant, $invoice);
        $result = $this->lineMessageService->sendMessage($lineId, $body);
        $success = ($result['status'] ?? '') === 'success';
        $sentAt = $success ? Carbon::now() : null;

        // テスト送信で履歴を残すと、本番実行時に該当加盟店がスキップされてしまう
        if (!$overrideLineId) {
            InvoiceLineSend::updateOrCreate(
                ['merchant_id' => $merchant->id, 'month' => $month],
                [
                    'line_id' => $lineId,
                    'status' => $success ? 'success' : 'failed',
                    'error' => $success ? null : ($result['message'] ?? '送信に失敗しました'),
                    'sent_at' => $sentAt,
                ]
            );
        }

        if (!$success) {
            Log::error('請求書LINE送信に失敗', [
                'merchant_id' => $merchant->id,
                'month' => $month,
                'result' => $result,
            ]);
        }

        return [
            'success' => $success,
            'skipped' => false,
            'message' => $success ? '送信しました' : ($result['message'] ?? '送信に失敗しました'),
            'sent_at' => $sentAt,
        ];
    }

    /**
     * @param string $message
     * @return array
     */
    private function skip($message)
    {
        return [
            'success' => false,
            'skipped' => true,
            'message' => $message,
            'sent_at' => null,
        ];
    }
}
```

- [ ] **Step 3: コマンドの依存を差し替える**

`app/Console/Commands/SendMonthlyInvoiceLine.php` の `handle()` シグネチャ（26〜30行目）を次に置き換える:

```php
    public function handle(
        InvoiceService $invoiceService,
        InvoiceLineMessageService $messageService,
        InvoiceLineSender $sender
    ) {
```

あわせて `use` 文を差し替える。`LineMessageService` は使わなくなるので削除し、`InvoiceLineSender` を足す:

```php
use App\Models\InvoiceLineSend;
use App\Models\Merchant;
use App\Services\InvoiceLineMessageService;
use App\Services\InvoiceLineSender;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
```

- [ ] **Step 4: 本文組み立ての前処理を削る**

57〜61行目の `$template` と `$invoiceUrl` の取得のうち、`$template` はサービス側で持つため削除する。LIFF ID未設定の警告は残す。該当箇所を次に置き換える:

```php
        if ($invoiceService->invoiceUrl() === '') {
            $this->warn('請求書ページのLIFF IDが未設定です。{invoice_url} は空文字で送信されます。');
        }
```

- [ ] **Step 5: 送信ループを置き換える**

83〜149行目の `foreach ($merchants as $merchant) { ... }` を丸ごと次に置き換える:

```php
        foreach ($merchants as $merchant) {
            // テスト送信は本番の送信履歴を参照しない
            $already = !$overrideLineId && InvoiceLineSend::where('merchant_id', $merchant->id)
                ->where('month', $month)
                ->where('status', 'success')
                ->exists();
            if ($already && !$force) {
                $this->line("  [skip] {$merchant->id} {$merchant->name} : 送信済み");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $lineId = $overrideLineId ?: optional($merchant->owner)->line_id;
                if (!$lineId) {
                    $this->line("  [skip] {$merchant->id} {$merchant->name} : オーナーのLINE IDが未登録");
                    $skipped++;
                    continue;
                }

                $invoice = $invoiceService->forMonth($merchant, $month);
                if ($invoice['order_count'] === 0) {
                    $this->line("  [skip] {$merchant->id} {$merchant->name} : 対象月の注文なし");
                    $skipped++;
                    continue;
                }

                $this->line("  [dry-run] {$merchant->id} {$merchant->name} → {$lineId}");
                $this->line('  ----------------');
                $this->line($sender->buildBody($merchant, $invoice));
                $this->line('  ----------------');
                $sent++;
                continue;
            }

            $result = $sender->send($merchant, $month, $overrideLineId);

            if ($result['success']) {
                $this->info("  [sent] {$merchant->id} {$merchant->name}");
                $sent++;
            } elseif ($result['skipped']) {
                $this->line("  [skip] {$merchant->id} {$merchant->name} : {$result['message']}");
                $skipped++;
            } else {
                // 1件失敗しても後続の加盟店の送信は継続する
                $this->error("  [fail] {$merchant->id} {$merchant->name} : {$result['message']}");
                $failed++;
            }
        }
```

- [ ] **Step 6: 出力がリファクタ前と同じことを確認する**

Run: `php artisan invoice:send-line --dry-run --month=2026-06 > /tmp/after.txt; diff /tmp/before.txt /tmp/after.txt && echo "IDENTICAL"`
Expected: `IDENTICAL` と表示される（差分ゼロ）。差分が出たら原因を潰してから次へ進む

- [ ] **Step 7: ヘルプが壊れていないことを確認する**

Run: `php artisan invoice:send-line --help`
Expected: `--to`, `--dry-run`, `--force`, `--ignore-disabled`, `--month`, `--merchant` が全て表示される

- [ ] **Step 8: コミット**

```bash
git add app/Services/InvoiceLineSender.php app/Console/Commands/SendMonthlyInvoiceLine.php
git commit -m "請求書LINE送信をInvoiceLineSenderに切り出す"
```

---

### Task 3: ルートとコントローラ

**Files:**
- Modify: `routes/web.php:141` の直後
- Modify: `app/Http/Controllers/Admin/SalesController.php`（`use` 文、`index()`、末尾に2メソッド追加）

**Interfaces:**
- Consumes: `MerchantPaymentConfirmation`（Task 1）、`InvoiceLineSender::send()`（Task 2）、`InvoiceService::isFixedMonth(string $month): bool`
- Produces:
  - ルート名 `admin.sales.payment_confirm` / `admin.sales.send_invoice`（どちらもPOST、`{merchant}` と body の `month` を受ける）
  - ビューへ渡す変数3つ:
    - `$isFixedMonth` (bool) — 表示中の月が確定済みか
    - `$paymentConfirmations` — `merchant_id` をキーにした `MerchantPaymentConfirmation` のコレクション
    - `$invoiceSends` — `merchant_id` をキーにした送信成功済み `InvoiceLineSend` のコレクション

- [ ] **Step 1: ルートを追加する**

`routes/web.php` の `Route::get('sales/{merchant}', ...)` の行（141行目）の直後に追加する。GETとPOSTなのでURLが重なっても衝突しない:

```php
        Route::post('sales/{merchant}/payment-confirm', [AdminSalesController::class, 'togglePaymentConfirm'])->name('sales.payment_confirm');
        Route::post('sales/{merchant}/send-invoice', [AdminSalesController::class, 'sendInvoiceLine'])->name('sales.send_invoice');
```

- [ ] **Step 2: ルートが登録されたことを確認する**

Run: `php artisan route:list --name=sales`
Expected: `admin.sales.payment_confirm` と `admin.sales.send_invoice` が POST として並ぶ

- [ ] **Step 3: コントローラの use 文を足す**

`app/Http/Controllers/Admin/SalesController.php` の `use` 文（5〜11行目）に4行足す:

```php
use App\Http\Controllers\Controller;
use App\Models\InvoiceLineSend;
use App\Models\Merchant;
use App\Models\MerchantPaymentConfirmation;
use App\Models\Order;
use App\Models\Setting;
use App\Services\InvoiceLineSender;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 4: index() で振込確認と送信履歴を読む**

`index()` のシグネチャ（18行目）を次に変える:

```php
    public function index(Request $request, InvoiceService $invoiceService)
```

`$grandTotal` の計算（72〜73行目）の直後に追加する。加盟店ごとにクエリを撃たないよう、月まとめて1回ずつ取る:

```php
        // 表示中の月の振込確認・送信履歴（加盟店IDで引けるようにする）
        $isFixedMonth = $invoiceService->isFixedMonth($month);
        $paymentConfirmations = MerchantPaymentConfirmation::where('month', $month)
            ->get()
            ->keyBy('merchant_id');
        $invoiceSends = InvoiceLineSend::where('month', $month)
            ->where('status', 'success')
            ->get()
            ->keyBy('merchant_id');
```

`compact()`（75〜84行目）に3つ足す:

```php
        return view('admin.sales.index', compact(
            'productSales',
            'merchantSales',
            'headquartersProcessed',
            'shippingFeeCount',
            'grandTotal',
            'month',
            'prevMonth',
            'nextMonth',
            'isFixedMonth',
            'paymentConfirmations',
            'invoiceSends'
        ));
```

- [ ] **Step 5: 振込確認のトグルを実装する**

`SalesController` の末尾（`invoice()` メソッドの後、クラスの閉じ括弧の手前）に追加する:

```php
    /**
     * 振込確認のON/OFFを切り替える
     *
     * 行があれば確認済み。もう一度押されたら行を消す。
     */
    public function togglePaymentConfirm($merchantId, Request $request, InvoiceService $invoiceService)
    {
        $merchant = Merchant::findOrFail($merchantId);
        $month = (string) $request->input('month');

        if (!$invoiceService->isFixedMonth($month)) {
            return response()->json(['message' => '当月の振込確認はできません。'], 400);
        }

        $existing = MerchantPaymentConfirmation::where('merchant_id', $merchant->id)
            ->where('month', $month)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['confirmed' => false, 'confirmed_at' => null]);
        }

        $confirmation = MerchantPaymentConfirmation::create([
            'merchant_id' => $merchant->id,
            'month' => $month,
            'admin_id' => Auth::guard('admin')->id(),
            'confirmed_at' => Carbon::now(),
        ]);

        return response()->json([
            'confirmed' => true,
            'confirmed_at' => $confirmation->confirmed_at->format('n/j'),
        ]);
    }

    /**
     * 請求書の案内をオーナーのLINEへ送信する
     *
     * 送信内容と履歴の扱いは月次バッチと同じ（InvoiceLineSender）。
     */
    public function sendInvoiceLine($merchantId, Request $request, InvoiceService $invoiceService, InvoiceLineSender $sender)
    {
        $merchant = Merchant::with('owner')->findOrFail($merchantId);
        $month = (string) $request->input('month');

        if (!$invoiceService->isFixedMonth($month)) {
            return response()->json(['success' => false, 'message' => '当月の請求書はまだ確定していません。'], 400);
        }

        $result = $sender->send($merchant, $month);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'sent_at' => $result['sent_at'] ? $result['sent_at']->format('n/j') : null,
        ]);
    }
```

- [ ] **Step 6: 売上画面がエラーなく開くことを確認する**

ブラウザで `http://localhost:8884/admin/sales?month=2026-06` を開く。
Expected: 従来通り一覧が表示される（この時点では画面の見た目は変わらない）。500エラーが出たら `storage/logs/laravel.log` を見て直す

- [ ] **Step 7: 当月ガードが効くことを確認する**

Run: `php artisan tinker --execute="\$s = app(App\Http\Controllers\Admin\SalesController::class); \$r = new Illuminate\Http\Request(); \$r->merge(['month' => now()->format('Y-m')]); \$res = \$s->togglePaymentConfirm(App\Models\Merchant::first()->id, \$r, app(App\Services\InvoiceService::class)); echo \$res->status(), ' ', \$res->getContent(), PHP_EOL;"`
Expected: `400` と `{"message":"当月の振込確認はできません。"}` が出る

- [ ] **Step 8: 前月のトグルが保存・解除できることを確認する**

Run: `php artisan tinker --execute="\$s = app(App\Http\Controllers\Admin\SalesController::class); \$id = App\Models\Merchant::first()->id; \$r = new Illuminate\Http\Request(); \$r->merge(['month' => '2026-06']); \$svc = app(App\Services\InvoiceService::class); echo \$s->togglePaymentConfirm(\$id, \$r, \$svc)->getContent(), PHP_EOL; echo App\Models\MerchantPaymentConfirmation::count(), PHP_EOL; echo \$s->togglePaymentConfirm(\$id, \$r, \$svc)->getContent(), PHP_EOL; echo App\Models\MerchantPaymentConfirmation::count(), PHP_EOL;"`
Expected: 1回目が `{"confirmed":true,...}` で件数 `1`、2回目が `{"confirmed":false,"confirmed_at":null}` で件数 `0`

- [ ] **Step 9: コミット**

```bash
git add routes/web.php app/Http/Controllers/Admin/SalesController.php
git commit -m "売上管理に振込確認と請求書送信のエンドポイントを追加"
```

---

### Task 4: 売上一覧の画面

**Files:**
- Modify: `resources/views/admin/sales/index.blade.php:55-59`（加盟店の行）と `:79`（`@endsection` の手前にスクリプト追加）

**Interfaces:**
- Consumes: `$isFixedMonth`, `$paymentConfirmations`, `$invoiceSends`, `$month`（Task 3）／ルート `admin.sales.payment_confirm`, `admin.sales.send_invoice`
- Produces: なし（最終成果物）

- [ ] **Step 1: 加盟店の行にチェックボックスとボタンを足す**

55〜59行目の `<div class="lma-select_box"></div>` から `</div>` までを次に置き換える。当月は請求書が未確定なので、`$isFixedMonth` が false のときは何も出さない:

```blade
                        <div class="lma-select_box">
                            @if ($isFixedMonth)
                                @php
                                    $confirmation = $paymentConfirmations[$m->merchant_id] ?? null;
                                @endphp
                                <label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;">
                                    <input type="checkbox" class="js-payment-confirm" data-merchant="{{ $m->merchant_id }}" {{ $confirmation ? 'checked' : '' }}>
                                    振込確認
                                </label>
                                <span class="js-confirm-label" data-merchant="{{ $m->merchant_id }}" style="font-size:12px;color:#666;margin-left:6px;">{{ $confirmation ? '確認済 ' . $confirmation->confirmed_at->format('n/j') : '' }}</span>
                            @endif
                        </div>
                        <div class="lma-btn_box btn_list">
                            <a href="{{ route('admin.sales.invoice', ['merchant' => $m->merchant_id, 'month' => $month]) }}" target="_blank" rel="noopener" class="">PDFを表示</a>
                            <a href="{{ route('admin.sales.show', ['merchant' => $m->merchant_id, 'month' => $month]) }}" class="">詳細</a>
                            @if ($isFixedMonth)
                                @php
                                    $send = $invoiceSends[$m->merchant_id] ?? null;
                                @endphp
                                <a href="#" class="js-send-invoice" data-merchant="{{ $m->merchant_id }}" data-sent="{{ $send ? '1' : '' }}">請求書を送信</a>
                            @endif
                        </div>
                        @if ($isFixedMonth)
                            <p class="js-send-status" data-merchant="{{ $m->merchant_id }}" style="font-size:12px;color:#666;margin:4px 0 0;width:100%;text-align:right;">{{ $send && $send->sent_at ? '送信済 ' . $send->sent_at->format('n/j') : '' }}</p>
                        @endif
```

- [ ] **Step 2: インラインJSを足す**

79行目の `</section>` の直後、`@endsection` の手前に追加する。既存の `admin/settings/invoice_line.blade.php` と同じ素のJS・`fetch` の書き方に合わせる:

```blade
<script>
document.addEventListener('DOMContentLoaded', function () {
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var month = @json($month);
    var confirmUrl = @json(route('admin.sales.payment_confirm', ['merchant' => '__ID__']));
    var sendUrl = @json(route('admin.sales.send_invoice', ['merchant' => '__ID__']));

    function post(url, onDone) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ month: month })
        })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) { onDone(result.ok, result.json); })
        .catch(function () { onDone(false, { message: '通信に失敗しました。' }); });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.js-payment-confirm'), function (box) {
        box.addEventListener('change', function () {
            var id = box.dataset.merchant;
            var label = document.querySelector('.js-confirm-label[data-merchant="' + id + '"]');
            var wanted = box.checked;
            box.disabled = true;

            post(confirmUrl.replace('__ID__', id), function (ok, json) {
                box.disabled = false;
                if (!ok) {
                    // 保存できなかったので見た目を元に戻す
                    box.checked = !wanted;
                    label.style.color = '#d64545';
                    label.textContent = json.message || '保存に失敗しました。';
                    return;
                }
                label.style.color = '#666';
                label.textContent = json.confirmed ? '確認済 ' + json.confirmed_at : '';
            });
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('.js-send-invoice'), function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = btn.dataset.merchant;
            var status = document.querySelector('.js-send-status[data-merchant="' + id + '"]');

            if (btn.dataset.sent && !window.confirm('この加盟店にはすでに送信済みです。再送しますか？')) {
                return;
            }

            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.5';
            status.style.color = '#666';
            status.textContent = '送信中...';

            post(sendUrl.replace('__ID__', id), function (ok, json) {
                btn.style.pointerEvents = '';
                btn.style.opacity = '';
                var success = ok && json.success;
                status.style.color = success ? '#2f855a' : '#d64545';
                status.textContent = success ? '送信済 ' + json.sent_at : (json.message || '送信に失敗しました。');
                if (success) {
                    btn.dataset.sent = '1';
                }
            });
        });
    });
});
</script>
```

- [ ] **Step 3: 前月の画面で表示を確認する**

ブラウザで `http://localhost:8884/admin/sales?month=2026-06` を開く。
Expected: 各加盟店の行に「振込確認」チェックボックスと「請求書を送信」リンクが出る。以前に確認済み・送信済みの加盟店には「確認済 n/j」「送信済 n/j」が出る

- [ ] **Step 4: 振込確認のトグルを画面から確認する**

チェックを入れる → 「確認済 8/1」が出る → **ページをリロード** → チェックが入ったままであること。
もう一度外す → ラベルが消える → リロードして外れたままであること。
Expected: 上記の通り。ブラウザのコンソールにエラーが出ていないこと

- [ ] **Step 5: 当月に何も出ないことを確認する**

ブラウザで `http://localhost:8884/admin/sales?month=2026-08` を開く。
Expected: チェックボックスも「請求書を送信」も表示されない（「PDFを表示」「詳細」は従来通り出る）

- [ ] **Step 6: 送信を確認する**

LINE ID未登録の加盟店で「請求書を送信」を押す。
Expected: 行の下に赤字で「オーナーのLINE IDが未登録」と出て、`invoice_line_sends` に行が増えていないこと

Run: `php artisan tinker --execute="echo App\Models\InvoiceLineSend::where('month','2026-06')->count(), PHP_EOL;"`（押す前後で件数が変わらないこと）

続けてLINE ID登録済みの加盟店で押す。
Expected: 実際にLINEが届き、「送信済 8/1」に変わる。もう一度押すと「すでに送信済みです。再送しますか？」の確認が出る

- [ ] **Step 7: 倉庫アカウントが弾かれることを確認する**

Run: `php artisan tinker --execute="echo App\Models\Admin::where('permission', 2)->count(), PHP_EOL;"`
permission=2 のアカウントがあればそれでログインし、売上管理を開こうとする。
Expected: 403。アカウントが無い（`0` 件）場合はこのステップをスキップしてよい。`AdminPermission` ミドルウェアはホワイトリスト方式で、新ルートは許可リストに載せていないため自動的に弾かれる

- [ ] **Step 8: コミット**

```bash
git add resources/views/admin/sales/index.blade.php
git commit -m "売上一覧に振込確認チェックと請求書送信ボタンを追加"
```

---

## デプロイ時の注意

- 本番で `php artisan migrate --force` を実行すること（テーブル追加があるため）
- **今回は `npm run build` と `public/build` のアップロードは不要**。JSを全てblade内に書いているため
