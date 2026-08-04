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
