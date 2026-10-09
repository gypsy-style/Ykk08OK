<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 加盟店が登録されたことを、通知対象ユーザーのLINEへ知らせる
 *
 * 宛先は users.is_notify_target が立っていて line_id を持つユーザー全員。
 * is_notify_target は設定画面「加盟店登録LINE通知」とユーザー一覧のチェックで切り替える。
 * 通知の失敗で加盟店登録そのものを巻き添えにしてはいけないため、
 * このクラスは例外を外へ投げない。失敗はログにだけ残す。
 */
class MerchantRegisteredNotifier
{
    private $lineMessageService;

    public function __construct(LineMessageService $lineMessageService)
    {
        $this->lineMessageService = $lineMessageService;
    }

    /**
     * 送信する本文を組み立てる
     *
     * @param Merchant $merchant
     * @return string
     */
    public function buildBody(Merchant $merchant)
    {
        $lines = [];
        $lines[] = '加盟店が登録されました。';
        $lines[] = '';

        // LINE 内ブラウザだと管理画面のログイン状態が引き継がれないため、
        // openExternalBrowser=1 を付けて端末のデフォルトブラウザで開かせる
        $lines[] = route('admin.merchants.edit', $merchant->id) . '?openExternalBrowser=1';

        return implode("\n", $lines);
    }

    /**
     * 通知対象ユーザー全員へ送信する
     *
     * @param Merchant $merchant
     * @return void
     */
    public function notify(Merchant $merchant)
    {
        try {
            // line_id が無いユーザーは送れないのでクエリの段階で外す
            $targets = User::where('is_notify_target', true)
                ->whereNotNull('line_id')
                ->get();

            if ($targets->isEmpty()) {
                Log::warning('加盟店登録通知: 送信対象のユーザーがいません', [
                    'merchant_id' => $merchant->id,
                ]);
                return;
            }

            $body = $this->buildBody($merchant);

            foreach ($targets as $target) {
                try {
                    $result = $this->lineMessageService->sendMessage($target->line_id, $body);

                    // 1件失敗しても残りの宛先には送り切る
                    if (($result['status'] ?? '') !== 'success') {
                        Log::error('加盟店登録通知の送信に失敗', [
                            'merchant_id' => $merchant->id,
                            'user_id' => $target->id,
                            'result' => $result,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Http のタイムアウト・DNS 失敗などで ConnectionException が飛んでも
                    // foreach を抜けさせず、残りの宛先への送信を続ける
                    Log::error('加盟店登録通知の送信に失敗', [
                        'merchant_id' => $merchant->id,
                        'user_id' => $target->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('加盟店登録通知でエラー', [
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
