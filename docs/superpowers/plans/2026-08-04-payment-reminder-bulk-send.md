# 未振込加盟店への督促LINE一斉送信 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 売上管理画面から、表示中の月に振込確認が付いていない加盟店へ、共通の文面で督促LINEをまとめて送れるようにする。

**Architecture:** 既存の「請求書LINE通知」（`InvoiceLineMessageService` + `InvoiceLineSender` + `invoice_line_sends`）と同じ三層構成を督促用に複製する。UIは設定画面を分けず、売上管理の集計ブロックと加盟店リストの間に督促ブロックを差し込む。一斉送信は専用エンドポイントを作らず、フロントから1加盟店ずつ順にAJAXを投げる。

**Tech Stack:** Laravel 8系 / Blade / 素のJS（fetch）/ MySQL / LINE Messaging API

## Global Constraints

- **テストコードは書かない**（プロジェクト方針）。各タスクの検証は手動確認で行う
- **`resources/css/admin.css` と `resources/js/` を触らない。** CSS/JSはBlade内のインラインで書く。`public/build` はgit管理外で本番へ手動アップロードが必要なため、ビルドを伴う変更を避ける
- **既存ファイルのスタイルに合わせる。** `InvoiceLineSender` / `InvoiceLineMessageService` / `admin/settings/invoice_line.blade.php` が直接の手本
- 対象月の判定は必ず `InvoiceService::isFixedMonth()` を使う（当月は未確定）
- テスト加盟店（`merchants.is_test = 1`）も送信対象に**含める**
- すでに督促を送った加盟店も対象から**外さない**（再送を許可する）
- `Setting` への保存は `Setting::updateOrCreate(['key' => ...], ['value' => ...])`
- 設計の出典: `docs/superpowers/specs/2026-08-03-payment-reminder-bulk-send-design.md`

## ファイル構成

**新規作成**

| ファイル | 責務 |
|---|---|
| `database/migrations/2026_08_04_000001_create_payment_reminder_sends_table.php` | 督促送信履歴テーブル |
| `app/Models/PaymentReminderSend.php` | 同テーブルのモデル |
| `app/Services/PaymentReminderMessageService.php` | 督促テンプレートの保持とプレースホルダ差し込み |
| `app/Services/PaymentReminderSender.php` | 1加盟店・1ヶ月分の督促送信と履歴保存 |
| `resources/views/admin/sales/partials/reminder.blade.php` | 督促ブロック（件数・文面エディタ・モーダル・JS・CSS） |

**変更**

| ファイル | 変更内容 |
|---|---|
| `routes/web.php:143` の直後 | 督促用のPOSTルート2本を追加 |
| `app/Http/Controllers/Admin/SalesController.php` | `index()` にデータ追加、`sendPaymentReminder()` と `updateReminderMessage()` を追加 |
| `resources/views/admin/sales/index.blade.php` | 督促ブロックの `@include`、加盟店行に「督促済」バッジ |

督促まわりのCSS・JS・HTMLは全て `partials/reminder.blade.php` に閉じ込め、`index.blade.php` の肥大化を防ぐ。

---

### Task 1: 履歴テーブルとモデル

**Files:**
- Create: `database/migrations/2026_08_04_000001_create_payment_reminder_sends_table.php`
- Create: `app/Models/PaymentReminderSend.php`

**Interfaces:**
- Consumes: なし
- Produces: `App\Models\PaymentReminderSend`（`$fillable`: merchant_id, month, line_id, status, error, sent_at／`sent_at` は datetime キャスト）。テーブル `payment_reminder_sends`、`unique(['merchant_id','month'])`

- [ ] **Step 1: マイグレーションを作成する**

`database/migrations/2026_08_04_000001_create_payment_reminder_sends_table.php`

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
        Schema::create('payment_reminder_sends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id'); // 加盟店ID
            $table->string('month', 7);                // 請求対象月 YYYY-MM
            $table->string('line_id')->nullable();     // 送信先のLINEユーザーID
            $table->string('status', 20);              // success / failed
            $table->text('error')->nullable();         // 失敗理由
            $table->timestamp('sent_at')->nullable();  // 送信成功日時
            $table->timestamps();

            // 最後に送った1件だけを保持する（再送は上書き）
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
        Schema::dropIfExists('payment_reminder_sends');
    }
};
```

- [ ] **Step 2: モデルを作成する**

`app/Models/PaymentReminderSend.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReminderSend extends Model
{
    protected $fillable = [
        'merchant_id',
        'month',
        'line_id',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
```

- [ ] **Step 3: マイグレーションを流す**

Run: `docker exec php_ykk08ok php artisan migrate`
Expected: `Migrating: 2026_08_04_000001_create_payment_reminder_sends_table` に続いて `Migrated` が出る

- [ ] **Step 4: テーブルができたことを確認する**

Run: `docker exec db_ykk08ok mysql -uykk08ok -ppassword database -e "SHOW CREATE TABLE payment_reminder_sends\G"`
Expected: 6カラムと `UNIQUE KEY ... (merchant_id, month)` が出力される

- [ ] **Step 5: コミット**

```bash
git add database/migrations/2026_08_04_000001_create_payment_reminder_sends_table.php app/Models/PaymentReminderSend.php
git commit -m "督促LINEの送信履歴テーブルとモデルを追加"
```

---

### Task 2: 督促メッセージのテンプレートサービス

**Files:**
- Create: `app/Services/PaymentReminderMessageService.php`

**Interfaces:**
- Consumes: `App\Models\Setting::getValue()`、`App\Models\Merchant`
- Produces: `App\Services\PaymentReminderMessageService`
  - `const KEY_MESSAGE = 'payment_reminder_message'`
  - `static placeholders(): array` — キー => 説明
  - `static defaultTemplate(): string`
  - `template(): string`
  - `render(string $template, Merchant $merchant, array $invoice, string $invoiceUrl = ''): string`
  - `renderSample(string $template, string $invoiceUrl = ''): string`

`$invoice` は `InvoiceService::forMonth()` の返り値（`label` / `month` / `grand_total` / `invoice_date` を使う）。

- [ ] **Step 1: サービスを作成する**

`app/Services/PaymentReminderMessageService.php`

```php
<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * 振込督促LINEの本文テンプレートを組み立てる
 *
 * 請求書LINE通知（InvoiceLineMessageService）と対になるクラス。
 * 自動送信が無いため、有効フラグと段階公開の絞り込みは持たない。
 */
class PaymentReminderMessageService
{
    /** 本文テンプレートの設定キー */
    public const KEY_MESSAGE = 'payment_reminder_message';

    /**
     * 差し込み可能なプレースホルダ（画面のヘルプ表示にも使う）
     *
     * @return array key => 説明
     */
    public static function placeholders()
    {
        return [
            '{merchant_name}' => '加盟店名',
            '{month_label}' => '請求対象月（例: 2026年7月分）',
            '{month}' => '請求対象月（例: 2026-07）',
            '{total}' => 'ご請求金額（税込・送料込）',
            '{payment_due_date}' => 'お支払期限（例: 2026年8月15日）',
            '{invoice_url}' => '請求書ページのURL',
        ];
    }

    /**
     * 初期テンプレート
     *
     * @return string
     */
    public static function defaultTemplate()
    {
        return "{merchant_name} 様\n\n"
            . "いつもご利用いただきありがとうございます。\n"
            . "{month_label}のご請求につきまして、本日時点でご入金の確認が取れておりません。\n\n"
            . "ご請求金額：{total}円（税込）\n"
            . "お支払期限：{payment_due_date}\n\n"
            . "お手数ですが、ご確認のうえお振込みをお願いいたします。\n"
            . "請求書は下記よりご確認いただけます。\n"
            . "{invoice_url}\n\n"
            . "行き違いでお振込みいただいておりましたら、何卒ご容赦ください。";
    }

    /**
     * 保存済みのテンプレート（未設定なら初期テンプレート）
     *
     * @return string
     */
    public function template()
    {
        $template = Setting::getValue(self::KEY_MESSAGE, '');
        return $template !== '' && $template !== null ? $template : self::defaultTemplate();
    }

    /**
     * 実データを差し込んで本文を生成する
     *
     * @param string $template
     * @param Merchant $merchant
     * @param array $invoice InvoiceService::forMonth の結果
     * @param string $invoiceUrl
     * @return string
     */
    public function render($template, Merchant $merchant, array $invoice, $invoiceUrl = '')
    {
        return $this->replace($template, [
            '{merchant_name}' => $merchant->name,
            '{month_label}' => $invoice['label'],
            '{month}' => $invoice['month'],
            '{total}' => number_format($invoice['grand_total']),
            '{payment_due_date}' => $this->dueDate($invoice['invoice_date'])->format('Y年n月j日'),
            '{invoice_url}' => $invoiceUrl,
        ]);
    }

    /**
     * プレビュー用にサンプル値を差し込む
     *
     * @param string $template
     * @param string $invoiceUrl
     * @return string
     */
    public function renderSample($template, $invoiceUrl = '')
    {
        $lastMonth = Carbon::now()->subMonth();

        return $this->replace($template, [
            '{merchant_name}' => 'サンプル商店',
            '{month_label}' => $lastMonth->format('Y年n月分'),
            '{month}' => $lastMonth->format('Y-m'),
            '{total}' => '135,000',
            '{payment_due_date}' => $this->dueDate($lastMonth->copy()->addMonth())->format('Y年n月j日'),
            '{invoice_url}' => $invoiceUrl ?: 'https://liff.line.me/xxxxxxxxxx-yyyyyyyy',
        ]);
    }

    /**
     * 支払期限（請求日と同じ月の15日）。請求書PDFと同じ計算に揃える
     *
     * @param Carbon $invoiceDate
     * @return Carbon
     */
    private function dueDate(Carbon $invoiceDate)
    {
        return $invoiceDate->copy()->day(15);
    }

    /**
     * @param string $template
     * @param array $values
     * @return string
     */
    private function replace($template, array $values)
    {
        return str_replace(array_keys($values), array_values($values), (string) $template);
    }
}
```

- [ ] **Step 2: tinker で差し込みが効くことを確認する**

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$s = app(App\Services\PaymentReminderMessageService::class); echo \$s->renderSample(\$s->template(), 'https://example.com/inv');"
```
Expected: `サンプル商店 様` で始まり、`ご請求金額：135,000円（税込）`、`お支払期限：2026年8月15日`、`https://example.com/inv` が含まれる。`{` が本文中に残っていないこと

- [ ] **Step 3: コミット**

```bash
git add app/Services/PaymentReminderMessageService.php
git commit -m "振込督促LINEの本文テンプレートサービスを追加"
```

---

### Task 3: 督促送信サービス

**Files:**
- Create: `app/Services/PaymentReminderSender.php`

**Interfaces:**
- Consumes: `InvoiceService`（`forMonth()` / `invoiceUrl()`）、`PaymentReminderMessageService`（`template()` / `render()`）、`LineMessageService::sendMessage($lineId, $body)`（`['status' => 'success'|..., 'message' => string]` を返す）、`App\Models\PaymentReminderSend`、`App\Models\MerchantPaymentConfirmation`
- Produces: `App\Services\PaymentReminderSender`
  - `buildBody(Merchant $merchant, array $invoice): string`
  - `send(Merchant $merchant, string $month, ?string $overrideLineId = null): array`
    返り値 `['success' => bool, 'skipped' => bool, 'message' => string, 'sent_at' => Carbon|null]`

- [ ] **Step 1: サービスを作成する**

`app/Services/PaymentReminderSender.php`

```php
<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantPaymentConfirmation;
use App\Models\PaymentReminderSend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 振込督促LINEの送信（1加盟店・1ヶ月分）
 *
 * 一斉送信でも1件ずつこのクラスを通す。本文・履歴の扱いを変えるときはここだけを直すこと。
 */
class PaymentReminderSender
{
    private $invoiceService;
    private $messageService;
    private $lineMessageService;

    public function __construct(
        InvoiceService $invoiceService,
        PaymentReminderMessageService $messageService,
        LineMessageService $lineMessageService
    ) {
        $this->invoiceService = $invoiceService;
        $this->messageService = $messageService;
        $this->lineMessageService = $lineMessageService;
    }

    /**
     * 送信する本文を組み立てる（プレビューでも使う）
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
     * 1加盟店・1ヶ月分の督促をLINEで送信する
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

        // 一覧を開いたまま別タブで振込確認された場合に、支払い済みの店へ督促が飛ぶのを防ぐ
        $confirmed = MerchantPaymentConfirmation::where('merchant_id', $merchant->id)
            ->where('month', $month)
            ->exists();
        if ($confirmed) {
            return $this->skip('振込確認済みのためスキップ');
        }

        $invoice = $this->invoiceService->forMonth($merchant, $month);
        if ($invoice['order_count'] === 0) {
            return $this->skip('対象月の注文なし');
        }

        $body = $this->buildBody($merchant, $invoice);
        $result = $this->lineMessageService->sendMessage($lineId, $body);
        $success = ($result['status'] ?? '') === 'success';
        $sentAt = $success ? Carbon::now() : null;

        // テスト送信で履歴を残すと「督促済」表示が汚れるため残さない
        if (!$overrideLineId) {
            if ($success) {
                PaymentReminderSend::updateOrCreate(
                    ['merchant_id' => $merchant->id, 'month' => $month],
                    [
                        'line_id' => $lineId,
                        'status' => 'success',
                        'error' => null,
                        'sent_at' => $sentAt,
                    ]
                );
            } else {
                // 再送に失敗しても過去の成功記録は消さない
                $existing = PaymentReminderSend::where('merchant_id', $merchant->id)
                    ->where('month', $month)
                    ->first();
                if (!$existing || $existing->status !== 'success') {
                    PaymentReminderSend::updateOrCreate(
                        ['merchant_id' => $merchant->id, 'month' => $month],
                        [
                            'line_id' => $lineId,
                            'status' => 'failed',
                            'error' => $result['message'] ?? '送信に失敗しました',
                            'sent_at' => null,
                        ]
                    );
                }
            }
        }

        if (!$success) {
            Log::error('振込督促LINE送信に失敗', [
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

- [ ] **Step 2: 本文生成だけを tinker で確認する（LINEには送らない）**

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$m = App\Models\Merchant::first(); \$inv = app(App\Services\InvoiceService::class)->forMonth(\$m, now()->subMonth()->format('Y-m')); echo app(App\Services\PaymentReminderSender::class)->buildBody(\$m, \$inv);"
```
Expected: 加盟店名・金額・支払期限が差し込まれた本文が出力される（`{` が残っていないこと）

- [ ] **Step 3: スキップ判定を確認する**

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$m = App\Models\Merchant::first(); var_dump(app(App\Services\PaymentReminderSender::class)->send(\$m, '2000-01'));"
```
Expected: `'skipped' => true` が返る（LINE ID未登録なら「オーナーのLINE IDが未登録」、登録済みなら「対象月の注文なし」）。**LINEは送信されない**。`payment_reminder_sends` に行が増えていないことも確認する:

```bash
docker exec db_ykk08ok mysql -uykk08ok -ppassword database -e "SELECT COUNT(*) FROM payment_reminder_sends;"
```

- [ ] **Step 4: コミット**

```bash
git add app/Services/PaymentReminderSender.php
git commit -m "振込督促LINEの送信サービスを追加"
```

---

### Task 4: ルートとコントローラ

**Files:**
- Modify: `routes/web.php`（`sales.send_invoice` の行の直後）
- Modify: `app/Http/Controllers/Admin/SalesController.php`

**Interfaces:**
- Consumes: Task 1〜3 の `PaymentReminderSend` / `PaymentReminderMessageService` / `PaymentReminderSender`
- Produces:
  - ルート名 `admin.sales.payment_reminder`（POST `sales/{merchant}/payment-reminder`）
  - ルート名 `admin.sales.payment_reminder_message`（POST `sales/payment-reminder-message`）
  - `index()` がビューへ渡す変数: `$reminderSends`（merchant_id をキーにした Collection）、`$reminderTargets`（`['merchant_id' => int, 'merchant_name' => string, 'reminded_at' => 'n/j'|null]` の配列）、`$reminderMessage`（string）、`$reminderPreview`（string）、`$reminderPlaceholders`（array）

- [ ] **Step 1: ルートを追加する**

`routes/web.php` の143行目 `Route::post('sales/{merchant}/send-invoice', ...)` の直後に追加する。

```php
        Route::post('sales/{merchant}/payment-reminder', [AdminSalesController::class, 'sendPaymentReminder'])->name('sales.payment_reminder');
        Route::post('sales/payment-reminder-message', [AdminSalesController::class, 'updateReminderMessage'])->name('sales.payment_reminder_message');
```

- [ ] **Step 2: use文を追加する**

`app/Http/Controllers/Admin/SalesController.php` の先頭の use 群に追加する（アルファベット順の位置を守る）。

```php
use App\Models\PaymentReminderSend;
use App\Services\PaymentReminderMessageService;
use App\Services\PaymentReminderSender;
```

`use App\Models\Order;` の後に `use App\Models\PaymentReminderSend;`、
`use App\Services\InvoiceService;` の後に `use App\Services\PaymentReminderMessageService;` と `use App\Services\PaymentReminderSender;` を置く。

- [ ] **Step 3: index() のシグネチャに依存を追加する**

`index(Request $request, InvoiceService $invoiceService)` を次に変える。

```php
    public function index(
        Request $request,
        InvoiceService $invoiceService,
        PaymentReminderSender $reminderSender
    ) {
```

- [ ] **Step 4: index() に督促用のデータを組み立てる**

`$merchantSales` の並べ替え（`// 未入金を上に集めたいので、...` のブロック）の直後、`return view(...)` の手前に追加する。

```php
        // 督促ブロック用のデータ（確定月のみ組み立てる）
        $reminderSends = collect();
        $reminderTargets = [];
        $reminderMessage = '';
        $reminderPreview = '';
        $reminderPlaceholders = PaymentReminderMessageService::placeholders();

        if ($isFixedMonth) {
            $reminderSends = PaymentReminderSend::where('month', $month)
                ->where('status', 'success')
                ->get()
                ->keyBy('merchant_id');

            foreach ($merchantSales as $m) {
                if (isset($paymentConfirmations[$m->merchant_id])) {
                    continue;
                }
                $send = $reminderSends[$m->merchant_id] ?? null;
                $reminderTargets[] = [
                    'merchant_id' => (int) $m->merchant_id,
                    'merchant_name' => $m->merchant_name,
                    'reminded_at' => $send && $send->sent_at ? $send->sent_at->format('n/j') : null,
                ];
            }

            $reminderMessage = app(PaymentReminderMessageService::class)->template();

            // 先頭の対象加盟店の実データで、実際に送られる文面を作る
            if (!empty($reminderTargets)) {
                $first = Merchant::find($reminderTargets[0]['merchant_id']);
                if ($first) {
                    $reminderPreview = $reminderSender->buildBody(
                        $first,
                        $invoiceService->forMonth($first, $month)
                    );
                }
            }
        }
```

- [ ] **Step 5: compact に変数を足す**

`return view('admin.sales.index', compact(...))` の引数末尾（`'invoiceSends'` の後）に追加する。

```php
            'invoiceSends',
            'reminderSends',
            'reminderTargets',
            'reminderMessage',
            'reminderPreview',
            'reminderPlaceholders'
```

- [ ] **Step 6: sendPaymentReminder() を追加する**

`sendInvoiceLine()` メソッドの直後（クラスの閉じ括弧の手前）に追加する。

```php
    /**
     * 振込督促をオーナーのLINEへ送信する
     *
     * 一斉送信もフロントから1件ずつこのエンドポイントを叩く。
     */
    public function sendPaymentReminder(
        $merchantId,
        Request $request,
        InvoiceService $invoiceService,
        PaymentReminderSender $sender
    ) {
        $merchant = Merchant::with('owner')->findOrFail($merchantId);
        $month = (string) $request->input('month');

        if (!$invoiceService->isFixedMonth($month)) {
            return response()->json(['success' => false, 'message' => '当月の督促は送信できません。'], 400);
        }

        $result = $sender->send($merchant, $month);

        return response()->json([
            'success' => $result['success'],
            'skipped' => $result['skipped'],
            'message' => $result['message'],
            'sent_at' => $result['sent_at'] ? $result['sent_at']->format('n/j') : null,
        ]);
    }

    /**
     * 督促の本文テンプレートを保存する
     */
    public function updateReminderMessage(Request $request)
    {
        $request->validate([
            'payment_reminder_message' => 'required|string|max:4000',
        ], [
            'payment_reminder_message.required' => '本文を入力してください。',
            'payment_reminder_message.max' => '本文は4000文字以内で入力してください。',
        ]);

        Setting::updateOrCreate(
            ['key' => PaymentReminderMessageService::KEY_MESSAGE],
            ['value' => $request->input('payment_reminder_message')]
        );

        return response()->json(['success' => true]);
    }
```

- [ ] **Step 7: ルートが登録されたことを確認する**

Run: `docker exec php_ykk08ok php artisan route:list --name=payment_reminder`
Expected: `admin.sales.payment_reminder` と `admin.sales.payment_reminder_message` の2行が出る

- [ ] **Step 8: 売上管理が今まで通り開けることを確認する**

Run: `curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8884/admin/sales?month=$(date -v-1m +%Y-%m)"`
Expected: `302`（未ログインのためリダイレクト）。500 が出たらエラーログを確認する:
`docker exec php_ykk08ok tail -30 storage/logs/laravel.log`

- [ ] **Step 9: コミット**

```bash
git add routes/web.php app/Http/Controllers/Admin/SalesController.php
git commit -m "督促LINEの送信・文面保存エンドポイントを追加"
```

---

### Task 5: 督促ブロックと文面エディタ

送信モーダルはまだ作らない。このタスクの完了時点で「ブロックが正しい件数を出し、文面が保存できる」ところまでを確認する。

**Files:**
- Create: `resources/views/admin/sales/partials/reminder.blade.php`
- Modify: `resources/views/admin/sales/index.blade.php`

**Interfaces:**
- Consumes: Task 4 の `$reminderTargets` / `$reminderMessage` / `$reminderPreview` / `$reminderPlaceholders` / `$reminderSends` / `$isFixedMonth` / `$month`
- Produces: 加盟店行に表示する「督促済 n/j」、送信ボタン `#js-reminder-open`（Task 6 がクリックイベントを付ける）、モーダル差し込み位置のコメント

- [ ] **Step 1: パーシャルを作成する**

`resources/views/admin/sales/partials/reminder.blade.php`

```blade
{{-- 振込督促の一斉送信。CSS/JSは admin.css を触らずに済ませるためこのファイル内に置く --}}
@php
    $remindedCount = collect($reminderTargets)->filter(function ($t) {
        return $t['reminded_at'] !== null;
    })->count();
@endphp

<style>
    .lma-reminder {
        width: 100%;
    }
    .lma-reminder_head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    .lma-reminder_title {
        font-size: 15px;
        font-weight: bold;
        margin: 0;
    }
    .lma-reminder_count {
        font-size: 13px;
        color: #666;
    }
    .lma-reminder_count strong {
        color: #d64545;
        font-size: 16px;
    }
    .lma-reminder_actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .lma-reminder_toggle {
        background: none;
        border: none;
        color: #1e6bd6;
        font-size: 13px;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
    }
    .lma-reminder_send {
        background: #4d6684;
        color: #fff;
        border: none;
        border-radius: 20px;
        padding: 8px 18px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_send:disabled {
        background: #c2cbd6;
        cursor: default;
    }
    .lma-reminder_editor {
        display: none;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #e3e7ec;
        gap: 24px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .lma-reminder_editor.is-open {
        display: flex;
    }
    .lma-reminder_col {
        flex: 1 1 420px;
        min-width: 320px;
    }
    .lma-reminder_col textarea {
        width: 100%;
        min-height: 260px;
        font-family: inherit;
        font-size: 14px;
        line-height: 1.7;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: vertical;
    }
    .lma-reminder_ph {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 10px 0 0;
    }
    .lma-reminder_ph button {
        border: 1px solid #cfd8e3;
        background: #fff;
        border-radius: 12px;
        padding: 3px 10px;
        font-size: 12px;
        cursor: pointer;
        color: #1e6bd6;
    }
    .lma-reminder_ph button:hover {
        background: #e6f0ff;
    }
    .lma-reminder_counter {
        font-size: 12px;
        color: #888;
        text-align: right;
        margin: 4px 0 0;
    }
    .lma-reminder_counter.is-over {
        color: #d64545;
        font-weight: bold;
    }
    .lma-reminder_save {
        margin-top: 10px;
        padding: 6px 16px;
        background: #4d6684;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_saved {
        font-size: 12px;
        margin-left: 8px;
    }
    .lma-reminder_preview {
        flex: 0 0 320px;
    }
    .lma-reminder_phone {
        border: 1px solid #d5d5d5;
        border-radius: 12px;
        overflow: hidden;
        background: #8cabd8;
    }
    .lma-reminder_phone_head {
        background: #202b33;
        color: #fff;
        font-size: 13px;
        padding: 10px 12px;
        text-align: center;
    }
    .lma-reminder_phone_body {
        padding: 14px 10px 20px;
        min-height: 300px;
        max-height: 460px;
        overflow-y: auto;
    }
    .lma-reminder_bubble {
        background: #fff;
        border-radius: 16px;
        padding: 10px 13px;
        font-size: 13px;
        line-height: 1.65;
        color: #16232b;
        white-space: pre-wrap;
        word-break: break-word;
        max-width: 88%;
    }
    .lma-reminder_empty {
        margin: 0;
        font-size: 13px;
        color: #666;
    }
</style>

<div class="lma-content_block nobg lma-reminder">
    <div class="lma-reminder_head">
        <div>
            <p class="lma-reminder_title">振込督促の一斉送信</p>
            <p class="lma-reminder_count" style="margin:4px 0 0;">
                未振込 <strong>{{ count($reminderTargets) }}</strong>件 / {{ count($merchantSales) }}件
                @if ($remindedCount > 0)
                    （うち督促済 {{ $remindedCount }}件）
                @endif
            </p>
        </div>
        <div class="lma-reminder_actions">
            <button type="button" class="lma-reminder_toggle" id="js-reminder-toggle">文面を編集 ▼</button>
            @if (count($reminderTargets) > 0)
                <button type="button" class="lma-reminder_send" id="js-reminder-open">
                    @include('admin.sales.partials.btn_icon', ['icon' => 'send'])未振込の{{ count($reminderTargets) }}件に送信
                </button>
            @else
                <button type="button" class="lma-reminder_send" disabled>未振込の加盟店はありません</button>
            @endif
        </div>
    </div>

    <div class="lma-reminder_editor" id="js-reminder-editor">
        <div class="lma-reminder_col">
            <label for="js-reminder-message" style="display:block; margin-bottom:6px; font-weight:bold; font-size:13px;">本文</label>
            <textarea id="js-reminder-message">{{ $reminderMessage }}</textarea>
            <p class="lma-reminder_counter" id="js-reminder-counter"></p>
            <div class="lma-reminder_ph">
                @foreach ($reminderPlaceholders as $key => $label)
                    <button type="button" data-ph="{{ $key }}" title="{{ $label }}">{{ $key }}</button>
                @endforeach
            </div>
            <button type="button" class="lma-reminder_save" id="js-reminder-save">文面を保存</button>
            <span class="lma-reminder_saved" id="js-reminder-saved"></span>
        </div>
        <div class="lma-reminder_preview">
            <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">プレビュー</p>
            <div class="lma-reminder_phone">
                <div class="lma-reminder_phone_head">{{ config('app.name') }}</div>
                <div class="lma-reminder_phone_body">
                    <div class="lma-reminder_bubble" id="js-reminder-bubble"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var MAX_LENGTH = 4000;
    var SAMPLE = @json([
        '{merchant_name}' => 'サンプル商店',
        '{month_label}' => \Carbon\Carbon::parse($month . '-01')->format('Y年n月分'),
        '{month}' => $month,
        '{total}' => '135,000',
        '{payment_due_date}' => \Carbon\Carbon::parse($month . '-01')->addMonth()->day(15)->format('Y年n月j日'),
        '{invoice_url}' => 'https://liff.line.me/xxxxxxxxxx-yyyyyyyy',
    ]);

    var toggle = document.getElementById('js-reminder-toggle');
    var editor = document.getElementById('js-reminder-editor');
    var textarea = document.getElementById('js-reminder-message');
    var bubble = document.getElementById('js-reminder-bubble');
    var counter = document.getElementById('js-reminder-counter');
    var saveBtn = document.getElementById('js-reminder-save');
    var savedLabel = document.getElementById('js-reminder-saved');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;');
    }

    // サーバ側 PaymentReminderMessageService::renderSample と揃えること
    function applySample(text) {
        Object.keys(SAMPLE).forEach(function (key) {
            text = text.split(key).join(SAMPLE[key]);
        });
        return text;
    }

    function updatePreview() {
        var raw = textarea.value;
        counter.textContent = raw.length + ' / ' + MAX_LENGTH + ' 文字';
        counter.classList.toggle('is-over', raw.length > MAX_LENGTH);
        bubble.innerHTML = raw.trim() === ''
            ? '<span style="color:#999;">本文を入力するとここに表示されます</span>'
            : escapeHtml(applySample(raw));
    }

    toggle.addEventListener('click', function () {
        var open = editor.classList.toggle('is-open');
        toggle.textContent = open ? '文面を閉じる ▲' : '文面を編集 ▼';
        if (open) {
            updatePreview();
        }
    });

    textarea.addEventListener('input', updatePreview);

    document.querySelectorAll('.lma-reminder_ph button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ph = btn.getAttribute('data-ph');
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.value = textarea.value.slice(0, start) + ph + textarea.value.slice(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + ph.length;
            updatePreview();
        });
    });

    saveBtn.addEventListener('click', function () {
        saveBtn.disabled = true;
        savedLabel.style.color = '#666';
        savedLabel.textContent = '保存中...';

        fetch('{{ route('admin.sales.payment_reminder_message') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ payment_reminder_message: textarea.value })
        })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) {
            var ok = result.ok && result.json.success;
            savedLabel.style.color = ok ? '#2f855a' : '#d64545';
            savedLabel.textContent = ok
                ? '保存しました'
                : (result.json.message || (result.json.errors && Object.values(result.json.errors)[0][0]) || '保存に失敗しました。');
        })
        .catch(function () {
            savedLabel.style.color = '#d64545';
            savedLabel.textContent = '保存に失敗しました。';
        })
        .finally(function () {
            saveBtn.disabled = false;
        });
    });
});
</script>
```

- [ ] **Step 2: index.blade.php にブロックを差し込む**

集計ブロックの閉じ `</div>` と加盟店リストの `<div class="lma-content_block staff nobg">` の間（現在の44〜46行目あたり）に追加する。

```blade
    @if ($isFixedMonth)
        @include('admin.sales.partials.reminder')
    @endif
```

- [ ] **Step 3: 加盟店行に「督促済」を出す**

`resources/views/admin/sales/index.blade.php` の `@php` ブロックに1行足す。

```blade
                @php
                    $confirmation = $paymentConfirmations[$m->merchant_id] ?? null;
                    $send = $invoiceSends[$m->merchant_id] ?? null;
                    $reminder = $reminderSends[$m->merchant_id] ?? null;
                @endphp
```

`js-send-status` の `<p>` の直後（`@endif` の手前）に追加する。

```blade
                                @if ($reminder && $reminder->sent_at)
                                    <p style="font-size:12px;color:#d64545;margin:2px 0 0;">督促済 {{ $reminder->sent_at->format('n/j') }}</p>
                                @endif
```

- [ ] **Step 4: Blade がコンパイルできることを確認する**

Run: `docker exec php_ykk08ok php artisan view:cache && docker exec php_ykk08ok php artisan view:clear`
Expected: `Blade templates cached successfully.` が出る（構文エラーがあればここで落ちる）

- [ ] **Step 5: 画面を目視で確認する**

ブラウザで `http://localhost:8884/admin/sales?month=2026-07` を開く（管理者でログイン）。

- 集計ブロックとリストの間に「振込督促の一斉送信」ブロックがある
- 「未振込 N件 / M件」の N が、リストで振込確認が未チェックの店の数と一致する
- 「文面を編集」で textarea とプレビューが開き、プレースホルダボタンで差し込める
- 文面を書き換えて「文面を保存」→「保存しました」が出る。リロードしても保存した文面が残る
- 当月（`?month=2026-08`）を開くとブロックが出ない

- [ ] **Step 6: コミット**

```bash
git add resources/views/admin/sales/partials/reminder.blade.php resources/views/admin/sales/index.blade.php
git commit -m "売上管理に振込督促ブロックと文面エディタを追加"
```

---

### Task 6: 確認モーダルと順次送信

**Files:**
- Modify: `resources/views/admin/sales/partials/reminder.blade.php`

**Interfaces:**
- Consumes: Task 5 の `#js-reminder-open` ボタン、Task 4 の `admin.sales.payment_reminder` ルート、`$reminderTargets` / `$reminderPreview` / `$month`
- Produces: なし（このタスクで機能が完成する）

- [ ] **Step 1: モーダルのCSSを追加する**

`reminder.blade.php` の `<style>` の末尾（`.lma-reminder_empty` の後）に追加する。

```css
    .lma-reminder_modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .lma-reminder_modal.is-open {
        display: flex;
    }
    .lma-reminder_dialog {
        background: #fff;
        border-radius: 8px;
        width: 100%;
        max-width: 720px;
        max-height: 86vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .lma-reminder_dialog_head {
        padding: 16px 20px;
        border-bottom: 1px solid #e3e7ec;
        font-size: 15px;
        font-weight: bold;
    }
    .lma-reminder_dialog_body {
        padding: 16px 20px;
        overflow-y: auto;
    }
    .lma-reminder_dialog_foot {
        padding: 14px 20px;
        border-top: 1px solid #e3e7ec;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .lma-reminder_warn {
        margin: 0 0 12px;
        padding: 10px 12px;
        background: #fff6e5;
        border-left: 4px solid #e8a33d;
        border-radius: 4px;
        font-size: 12px;
        color: #6b4b12;
    }
    .lma-reminder_targets {
        list-style: none;
        padding: 0;
        margin: 0 0 16px;
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e3e7ec;
        border-radius: 4px;
    }
    .lma-reminder_targets li {
        padding: 7px 12px;
        font-size: 13px;
        border-bottom: 1px solid #f0f2f5;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }
    .lma-reminder_targets li:last-child {
        border-bottom: none;
    }
    .lma-reminder_badge {
        color: #d64545;
        font-size: 12px;
        white-space: nowrap;
    }
    .lma-reminder_body_preview {
        background: #f8f9fa;
        border: 1px solid #e3e7ec;
        border-radius: 4px;
        padding: 12px;
        font-size: 13px;
        line-height: 1.7;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 200px;
        overflow-y: auto;
    }
    .lma-reminder_progress {
        margin: 12px 0 0;
        font-size: 13px;
        color: #444;
    }
    .lma-reminder_result {
        margin: 12px 0 0;
        font-size: 13px;
        line-height: 1.8;
    }
    .lma-reminder_cancel {
        background: #fff;
        border: 1px solid #c2cbd6;
        border-radius: 4px;
        padding: 7px 16px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_exec {
        background: #d64545;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 7px 18px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_exec:disabled {
        background: #d9a3a3;
        cursor: default;
    }
```

- [ ] **Step 2: モーダルのHTMLを追加する**

`reminder.blade.php` の `</div>`（`lma-content_block` の閉じ）の直後、`<script>` の手前に追加する。

```blade
<div class="lma-reminder_modal" id="js-reminder-modal">
    <div class="lma-reminder_dialog">
        <div class="lma-reminder_dialog_head">
            {{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}分の振込督促を送信します
        </div>
        <div class="lma-reminder_dialog_body">
            @if ($remindedCount > 0)
                <p class="lma-reminder_warn">
                    うち <strong>{{ $remindedCount }}件</strong> はすでに督促を送信済みです。もう一度送信されます。
                </p>
            @endif

            <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">送信先（{{ count($reminderTargets) }}件）</p>
            <ul class="lma-reminder_targets">
                @foreach ($reminderTargets as $t)
                    <li data-merchant="{{ $t['merchant_id'] }}">
                        <span>{{ $t['merchant_name'] }}</span>
                        @if ($t['reminded_at'])
                            <span class="lma-reminder_badge">督促済 {{ $t['reminded_at'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">送信される文面（例: {{ $reminderTargets[0]['merchant_name'] ?? '' }}）</p>
            <div class="lma-reminder_body_preview">{{ $reminderPreview }}</div>

            <p class="lma-reminder_progress" id="js-reminder-progress" style="display:none;"></p>
            <div class="lma-reminder_result" id="js-reminder-result" style="display:none;"></div>
        </div>
        <div class="lma-reminder_dialog_foot">
            <button type="button" class="lma-reminder_cancel" id="js-reminder-cancel">キャンセル</button>
            <button type="button" class="lma-reminder_exec" id="js-reminder-exec">{{ count($reminderTargets) }}件に送信する</button>
        </div>
    </div>
</div>
```

- [ ] **Step 3: 送信のJSを追加する**

`reminder.blade.php` の `<script>` 内、`saveBtn.addEventListener(...)` ブロックの後（`});` で閉じる直前）に追加する。

```js
    // --- 確認モーダルと順次送信 ---
    var TARGETS = @json($reminderTargets);
    var SEND_URL = @json(route('admin.sales.payment_reminder', ['merchant' => '__ID__']));
    var MONTH = @json($month);

    var openBtn = document.getElementById('js-reminder-open');
    var modal = document.getElementById('js-reminder-modal');
    var cancelBtn = document.getElementById('js-reminder-cancel');
    var execBtn = document.getElementById('js-reminder-exec');
    var progress = document.getElementById('js-reminder-progress');
    var resultBox = document.getElementById('js-reminder-result');
    var sending = false;
    var finished = false;

    function closeModal() {
        // 送信中は閉じさせない
        if (sending) {
            return;
        }
        modal.classList.remove('is-open');
        // 一度でも送ったら「督促済」表示を反映するため読み直す
        if (finished) {
            location.reload();
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            modal.classList.add('is-open');
        });
    }

    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    function renderResult(stats) {
        var html = '<p style="margin:0 0 4px;"><strong>送信完了</strong>　成功 ' + stats.success
            + '件 / スキップ ' + stats.skipped + '件 / 失敗 ' + stats.failed + '件</p>';
        if (stats.notes.length > 0) {
            html += '<ul style="margin:6px 0 0; padding-left:18px; color:#666;">';
            stats.notes.forEach(function (note) {
                html += '<li>' + note + '</li>';
            });
            html += '</ul>';
        }
        resultBox.innerHTML = html;
        resultBox.style.display = '';
    }

    function sendAt(index, stats) {
        if (index >= TARGETS.length) {
            sending = false;
            finished = true;
            progress.textContent = '送信が完了しました。';
            cancelBtn.textContent = '閉じる';
            cancelBtn.disabled = false;
            renderResult(stats);
            return;
        }

        var target = TARGETS[index];
        progress.textContent = '送信中 ' + (index + 1) + ' / ' + TARGETS.length + '（' + target.merchant_name + '）';

        fetch(SEND_URL.replace('__ID__', target.merchant_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ month: MONTH })
        })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) {
            if (result.ok && result.json.success) {
                stats.success++;
            } else if (result.ok && result.json.skipped) {
                stats.skipped++;
                stats.notes.push(target.merchant_name + '：' + (result.json.message || 'スキップ'));
            } else {
                stats.failed++;
                stats.notes.push(target.merchant_name + '：' + (result.json.message || '送信に失敗しました'));
            }
        })
        .catch(function () {
            stats.failed++;
            stats.notes.push(target.merchant_name + '：通信に失敗しました');
        })
        .finally(function () {
            // 1件失敗しても止めずに次へ進む
            sendAt(index + 1, stats);
        });
    }

    execBtn.addEventListener('click', function () {
        if (sending || TARGETS.length === 0) {
            return;
        }
        sending = true;
        execBtn.disabled = true;
        cancelBtn.disabled = true;
        progress.style.display = '';
        resultBox.style.display = 'none';
        sendAt(0, { success: 0, skipped: 0, failed: 0, notes: [] });
    });
```

- [ ] **Step 4: Blade がコンパイルできることを確認する**

Run: `docker exec php_ykk08ok php artisan view:cache && docker exec php_ykk08ok php artisan view:clear`
Expected: `Blade templates cached successfully.`

- [ ] **Step 5: モーダルの表示だけを確認する（送信はしない）**

`http://localhost:8884/admin/sales?month=2026-07` で送信ボタンを押す。

- 対象加盟店の一覧が出て、件数が「未振込 N件」と一致する
- 督促済みの店に「督促済 n/j」バッジが出る（Task 6 の初回は0件でよい）
- 文面プレビューに実データ（加盟店名・金額・支払期限）が入っている
- 「キャンセル」「背景クリック」「ESC」で閉じられ、**LINEが1通も飛ばない**

`docker exec db_ykk08ok mysql -uykk08ok -ppassword database -e "SELECT COUNT(*) FROM payment_reminder_sends;"` が 0 のままであることを確認する。

- [ ] **Step 6: 実際に1件だけ送って確認する**

送信対象がテスト加盟店1件だけになる月・状態を作ってから実行する（他店に本物の督促が飛ばないよう、対象が1件であることをモーダルの一覧で必ず目視してから押す）。

- 「1件に送信する」→ 進捗が出て、完了後に「成功 1件 / スキップ 0件 / 失敗 0件」
- LINEに督促が届く
- 閉じるとリロードされ、その店の行に「督促済 n/j」が出る
- もう一度モーダルを開くと「うち1件はすでに督促を送信済みです」が出る

- [ ] **Step 7: コミット**

```bash
git add resources/views/admin/sales/partials/reminder.blade.php
git commit -m "振込督促の確認モーダルと順次送信を追加"
```

---

### Task 7: 通しの動作確認

**Files:**
- 変更なし（確認のみ。問題が見つかった場合のみ該当ファイルを直す）

**Interfaces:**
- Consumes: Task 1〜6 のすべて
- Produces: なし

- [ ] **Step 1: 仕様書の確認手順を上から順に実行する**

`docs/superpowers/specs/2026-08-03-payment-reminder-bulk-send-design.md` の「動作確認手順」1〜13 を実行し、結果を記録する。

特に落としやすいのは次の3つ。

- **11番**: モーダルを開いたまま別タブで振込確認をONにしてから送信 → その店が「振込確認済みのためスキップ」になること（`PaymentReminderSender` のガードが効いているか）
- **12番**: 全員が振込確認済みの月 → 送信ボタンが「未振込の加盟店はありません」で非活性
- **13番**: 倉庫アカウント（`permission = 2`）でのPOSTが403

- [ ] **Step 2: 13番を curl で確認する**

倉庫アカウントでログインしたブラウザの Cookie とCSRFトークンを使うか、`AdminPermission` ミドルウェアのホワイトリストに `sales.payment_reminder` 系が含まれていないことをコードで確認する。

Run: `docker exec php_ykk08ok grep -n "permission" app/Http/Middleware/AdminPermission.php`
Expected: 倉庫アカウントの許可ルートが列挙されており、そこに `sales.payment_reminder` / `sales.payment_reminder_message` が**入っていない**こと（＝自動的に403になる）

- [ ] **Step 3: 既存機能が壊れていないことを確認する**

- 売上管理の「詳細」「請求書を確認」「請求書を送信」が今まで通り動く
- 振込確認のチェックON/OFFが動き、確認済みが下へ移動する
- `docker exec php_ykk08ok php artisan invoice:send-line --dry-run` が従来通り動く（督促の追加で請求書側に影響が無いこと）

- [ ] **Step 4: ローカルのダミー送信履歴を消す**

Run: `docker exec db_ykk08ok mysql -uykk08ok -ppassword database -e "DELETE FROM payment_reminder_sends;"`

本番の状態と揃えるため、確認で作った履歴は消しておく。

- [ ] **Step 5: 未コミットの変更が無いことを確認して push**

```bash
git status --short
git push origin main
```

デプロイ後、本番で `php artisan migrate` が走ること（GitHub Actions）を確認する。
`public/build` の手動アップロードは不要（CSS/JSはBlade内インラインのため）。
