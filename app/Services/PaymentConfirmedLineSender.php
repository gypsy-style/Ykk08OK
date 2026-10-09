<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\PaymentConfirmedLineSend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 振込確認のお知らせLINEの送信（1加盟店・1ヶ月分）
 *
 * push なので月間通数を消費する。成功済みの月には再送しない。
 */
class PaymentConfirmedLineSender
{
    private $lineMessageService;

    public function __construct(LineMessageService $lineMessageService)
    {
        $this->lineMessageService = $lineMessageService;
    }

    /**
     * @param Merchant $merchant
     * @param string $month YYYY-MM
     * @return string
     */
    public function buildBody(Merchant $merchant, $month)
    {
        $monthLabel = Carbon::createFromFormat('Y-m-d', $month . '-01')->format('Y年n月分');

        return "いつもお世話になっております。\n"
            . "{$monthLabel}のお振込みを確認いたしました。\n"
            . "お忙しい中、ご対応いただきありがとうございます。\n\n"
            . "引き続き、どうぞよろしくお願いいたします。";
    }

    /**
     * @param Merchant $merchant
     * @param string $month YYYY-MM
     * @return array ['success' => bool, 'skipped' => bool, 'message' => string, 'sent_at' => Carbon|null]
     */
    public function send(Merchant $merchant, $month)
    {
        // 外して付け直したときに同じ内容を二度送らない
        $sent = PaymentConfirmedLineSend::where('merchant_id', $merchant->id)
            ->where('month', $month)
            ->where('status', 'success')
            ->exists();
        if ($sent) {
            return $this->result(false, true, '送信済みのため再送しません');
        }

        $lineId = optional($merchant->owner)->line_id;
        if (!$lineId) {
            $this->record($merchant, $month, null, 'skipped', 'オーナーのLINE IDが未登録');
            return $this->result(false, true, 'オーナーのLINE IDが未登録');
        }

        $result = $this->lineMessageService->sendMessage($lineId, $this->buildBody($merchant, $month));
        $success = ($result['status'] ?? '') === 'success';

        if ($success) {
            $sentAt = Carbon::now();
            $this->record($merchant, $month, $lineId, 'success', null, $sentAt);
            return $this->result(true, false, '送信しました', $sentAt);
        }

        $error = $result['message'] ?? '送信に失敗しました';
        $this->record($merchant, $month, $lineId, 'failed', $error);
        Log::error('振込確認LINE送信に失敗', [
            'merchant_id' => $merchant->id,
            'month' => $month,
            'result' => $result,
        ]);

        return $this->result(false, false, $error);
    }

    private function record(Merchant $merchant, $month, $lineId, $status, $error, $sentAt = null)
    {
        PaymentConfirmedLineSend::updateOrCreate(
            ['merchant_id' => $merchant->id, 'month' => $month],
            [
                'line_id' => $lineId,
                'status' => $status,
                'error' => $error,
                'sent_at' => $sentAt,
            ]
        );
    }

    private function result($success, $skipped, $message, $sentAt = null)
    {
        return [
            'success' => $success,
            'skipped' => $skipped,
            'message' => $message,
            'sent_at' => $sentAt,
        ];
    }
}
