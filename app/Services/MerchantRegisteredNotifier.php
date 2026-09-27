<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 加盟店が登録されたことを、通知対象ユーザーのLINEへ知らせる
 *
 * 宛先は users.is_notify_target が立っていて line_id を持つユーザー全員。
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
     * LIFF からの登録は status=2（無効）固定で入るが、管理画面・代理店の登録フォームは
     * status を選べる。有効（status=1）で登録された加盟店にまで「無効です」と案内すると
     * 受け取る側の判断を誤らせるため、無効化案内は status=2 のときだけ入れる。
     * 管理画面 URL は内容確認のためどちらの場合も入れる。
     *
     * @param Merchant $merchant
     * @return string
     */
    public function buildBody(Merchant $merchant)
    {
        $lines = [];
        $lines[] = '新しい加盟店が登録されました';
        $lines[] = '';
        $lines[] = 'サロン名：' . $merchant->name;
        $lines[] = '加盟店コード：' . $merchant->merchant_code;
        $lines[] = '電話番号：' . $merchant->phone;
        $lines[] = '登録日時：' . optional($merchant->created_at)->format('Y/m/d H:i');
        $lines[] = '';

        if ((int) $merchant->status === 2) {
            $lines[] = '現在このサロンは「無効」です。';
            $lines[] = '管理画面から有効に切り替えてください。';
        }

        $lines[] = route('admin.merchants.edit', $merchant->id);

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
