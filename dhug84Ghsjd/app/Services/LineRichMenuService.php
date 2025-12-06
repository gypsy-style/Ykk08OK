<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LineRichMenuService
{
    private $lineApiUrl = 'https://api.line.me/v2/bot/user';
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = env('LINE_CHANNEL_ACCESS_TOKEN');
    }

    /**
     * 指定したユーザーのリッチメニューを切り替える
     *
     * @param string $userId
     * @param string $richMenuId
     * @return array
     */
    public function switchRichMenu($userId, $richMenuId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post("{$this->lineApiUrl}/{$userId}/richmenu/{$richMenuId}");

        if ($response->successful()) {
            return ['status' => 'success', 'message' => 'リッチメニューが切り替えられました'];
        } else {
            return ['status' => 'error', 'message' => 'リッチメニューの変更に失敗しました', 'details' => $response->json()];
        }
    }
}