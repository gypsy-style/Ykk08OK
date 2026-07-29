<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineRichMenuService
{
    private $lineApiUrl = 'https://api.line.me/v2/bot/user';
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.line.channel_access_token');
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
        // リッチメニュー制御を LINE Harness 側へ寄せている場合、本アプリからは操作しない
        if (config('services.line.richmenu_driver') === 'harness') {
            Log::info('RichMenu switch skipped (harness driver)', ['userId' => $userId, 'richMenuId' => $richMenuId]);
            return ['status' => 'skipped', 'message' => 'リッチメニュー制御はLINE Harness側に委譲されています'];
        }

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