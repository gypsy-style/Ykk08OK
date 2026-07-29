<?php

namespace App\Services\Line;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LINE Messaging API へ直接プッシュ送信するドライバ
 */
class DirectLineSender implements LineSender
{
    private $lineApiUrl = 'https://api.line.me/v2/bot/message/push';
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.line.channel_access_token');
    }

    public function sendText($lineUserId, $message)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->lineApiUrl, [
            'to' => $lineUserId,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
        ]);

        if ($response->successful()) {
            return ['status' => 'success', 'message' => 'メッセージを送信しました'];
        } else {
            Log::error('LINE push message failed', ['userId' => $lineUserId, 'details' => $response->json()]);
            return ['status' => 'error', 'message' => 'メッセージの送信に失敗しました', 'details' => $response->json()];
        }
    }
}
