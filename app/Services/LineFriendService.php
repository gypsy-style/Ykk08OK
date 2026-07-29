<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LineFriendService
{
    private $lineApiUrl = 'https://api.line.me/v2/bot/profile';
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.line.channel_access_token');
    }

    /**
     * LINEの友達登録状態をチェック
     *
     * @param string $userId
     * @return array
     */
    public function checkFriendStatus($userId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->get("{$this->lineApiUrl}/{$userId}");

        if ($response->status() == 404) {
            return ['is_friend' => false, 'message' => 'User is not a friend'];
        }

        return ['is_friend' => true, 'message' => 'User is a friend'];
    }
}