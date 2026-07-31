<?php

namespace App\Console\Commands;

use App\Models\InvoiceLineSend;
use App\Models\Merchant;
use App\Services\InvoiceLineMessageService;
use App\Services\InvoiceService;
use App\Services\LineMessageService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMonthlyInvoiceLine extends Command
{
    protected $signature = 'invoice:send-line
                            {--month= : 対象月 YYYY-MM（既定は前月）}
                            {--merchant= : 加盟店IDを指定して1件だけ送信}
                            {--to= : 送信先のLINEユーザーIDを上書きする（テスト用。送信履歴は残さない）}
                            {--dry-run : 送信せず対象と本文のみ表示}
                            {--force : 送信済みの加盟店にも再送する}
                            {--ignore-disabled : 通知OFFの状態でも実行する（本番前テスト用）}';

    protected $description = '確定済みの月次請求書を加盟店オーナーのLINEへ送信する';

    public function handle(
        InvoiceService $invoiceService,
        InvoiceLineMessageService $messageService,
        LineMessageService $lineMessageService
    ) {
        $month = $this->option('month') ?: Carbon::now()->subMonth()->format('Y-m');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $ignoreDisabled = (bool) $this->option('ignore-disabled');
        $overrideLineId = $this->option('to');

        if (!$invoiceService->isFixedMonth($month)) {
            $this->error("対象月が不正です（確定済みの過去月をYYYY-MM形式で指定してください）: {$month}");
            return 1;
        }

        // 送信先の上書きはテスト用。加盟店に届かない代わりに履歴も残さない
        if ($overrideLineId) {
            $this->warn("※ 送信先を {$overrideLineId} に上書きします（加盟店本人には届きません）");
            $this->warn('※ テスト送信のため invoice_line_sends に履歴は残りません');
            if (!$dryRun && !$this->confirm('この内容でテスト送信しますか？')) {
                $this->info('中止しました。');
                return 0;
            }
        }

        if (!$messageService->isEnabled() && !$ignoreDisabled && !$dryRun) {
            $this->warn('請求書LINE通知が無効のため処理を終了します。（管理画面 > 設定 > 請求書LINE通知）');
            return 0;
        }

        $template = $messageService->template();
        $invoiceUrl = $invoiceService->invoiceUrl();
        if ($invoiceUrl === '') {
            $this->warn('請求書ページのLIFF IDが未設定です。{invoice_url} は空文字で送信されます。');
        }

        // 段階公開のための絞り込み。--merchant を明示した場合はそちらを優先する
        $targetIds = $messageService->targetMerchantIds();
        $query = Merchant::with('owner')->orderBy('id');

        if ($this->option('merchant')) {
            $query->where('id', $this->option('merchant'));
        } elseif (!empty($targetIds)) {
            $query->whereIn('id', $targetIds);
            $this->warn('※ テスト対象の加盟店が指定されています: ' . implode(', ', $targetIds));
            $this->warn('※ これ以外の加盟店には送信されません。全社へ配信するには管理画面で絞り込みを空にしてください。');
        }

        $merchants = $query->get();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $this->info("対象月: {$month} / 加盟店数: {$merchants->count()}" . ($dryRun ? '（dry-run）' : ''));

        foreach ($merchants as $merchant) {
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

            $body = $messageService->render($template, $merchant, $invoice, $invoiceUrl);

            if ($dryRun) {
                $this->line("  [dry-run] {$merchant->id} {$merchant->name} → {$lineId}");
                $this->line('  ----------------');
                $this->line($body);
                $this->line('  ----------------');
                $sent++;
                continue;
            }

            $result = $lineMessageService->sendMessage($lineId, $body);
            $success = ($result['status'] ?? '') === 'success';

            // テスト送信で履歴を残すと、本番実行時に該当加盟店がスキップされてしまう
            if (!$overrideLineId) {
                InvoiceLineSend::updateOrCreate(
                    ['merchant_id' => $merchant->id, 'month' => $month],
                    [
                        'line_id' => $lineId,
                        'status' => $success ? 'success' : 'failed',
                        'error' => $success ? null : ($result['message'] ?? '送信に失敗しました'),
                        'sent_at' => $success ? Carbon::now() : null,
                    ]
                );
            }

            if ($success) {
                $this->info("  [sent] {$merchant->id} {$merchant->name}");
                $sent++;
            } else {
                // 1件失敗しても後続の加盟店の送信は継続する
                $this->error("  [fail] {$merchant->id} {$merchant->name} : " . ($result['message'] ?? ''));
                Log::error('請求書LINE送信に失敗', [
                    'merchant_id' => $merchant->id,
                    'month' => $month,
                    'result' => $result,
                ]);
                $failed++;
            }
        }

        $summary = "完了: 送信 {$sent} 件 / スキップ {$skipped} 件 / 失敗 {$failed} 件（対象月 {$month}）";
        $this->info($summary);

        if (!$dryRun) {
            Log::info('請求書LINE送信バッチ ' . $summary);
        }

        return 0;
    }
}
