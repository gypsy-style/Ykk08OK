<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessageService
{
    private $lineApiUrl = 'https://api.line.me/v2/bot/message/push';
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = env('LINE_CHANNEL_ACCESS_TOKEN');
    }

    /**
     * 指定したユーザーにプッシュメッセージを送信する
     *
     * @param string $userId
     * @param string $message
     * @return array
     */
    public function sendMessage($userId, $message)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->lineApiUrl, [
            'to' => $userId,
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
            Log::error('LINE push message failed', ['userId' => $userId, 'details' => $response->json()]);
            return ['status' => 'error', 'message' => 'メッセージの送信に失敗しました', 'details' => $response->json()];
        }
    }
}
