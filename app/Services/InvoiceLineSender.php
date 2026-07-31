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
