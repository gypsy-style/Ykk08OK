<?php

namespace App\Services;

use App\Mail\OrderNotification;
use App\Models\Order;
use App\Models\Merchant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * 注文登録時にメール通知を送信
     *
     * @param Order $order
     * @return void
     */
    public function sendOrderNotification(Order $order)
    {
        try {
            // 注文に関連するagencyを取得
            $agency = $order->agency;
            
            if (!$agency) {
                Log::warning('注文通知: agencyが見つかりません', ['order_id' => $order->id]);
                return;
            }
            
            // agencyのメールアドレスを取得
            $agencyEmail = $agency->email ?? null;
            
            if (!$agencyEmail) {
                Log::warning('注文通知: agencyのメールアドレスが設定されていません', [
                    'order_id' => $order->id,
                    'agency_id' => $agency->id
                ]);
                return;
            }
            
            // メール送信
            $mailers = ['smtp', 'sendmail', 'mailgun', 'postmark', 'ses'];
            $success = false;
            
            foreach ($mailers as $mailer) {
                try {
                    Mail::mailer($mailer)->to($agencyEmail)->send(new OrderNotification($order));
                    $success = true;
                    Log::info('注文通知メール送信完了', [
                        'order_id' => $order->id,
                        'agency_id' => $agency->id,
                        'email' => $agencyEmail
                    ]);
                    break;
                } catch (\Exception $e) {
                    Log::error('注文通知メール送信エラー', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if (!$success) {
                Log::error('注文通知メール送信エラー: すべてのメールサービスで送信に失敗しました', [
                    'order_id' => $order->id,
                    'agency_id' => $agency->id,
                    'email' => $agencyEmail
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('注文通知メール送信エラー', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
} 