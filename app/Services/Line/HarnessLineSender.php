<?php

namespace App\Services\Line;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LINE Harness (OSS) の API 経由で送信するドライバ
 *
 * POST {base_url}/api/friends/{id}/messages
 *   Authorization: Bearer {api_key}
 *   { "content": "本文", "messageType": "text" }
 *
 * ※ Harness 側の :id が「内部の友だちID」か「LINE User ID」かは
 *    ドキュメント上あいまいなため、LINE User ID で 404 が返った場合は
 *    GET /api/friends?limit=... で lineUserId を突き合わせて解決する。
 */
class HarnessLineSender implements LineSender
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.line.harness.base_url'), '/');
        $this->apiKey = config('services.line.harness.api_key');
    }

    public function sendText($lineUserId, $message)
    {
        if (!$this->baseUrl || !$this->apiKey) {
            Log::error('LINE Harness の接続設定が未構成です');
            return ['status' => 'error', 'message' => 'LINE Harness の接続設定（URL/APIキー）が未設定です'];
        }

        $friendId = $this->resolveFriendId($lineUserId);
        if (!$friendId) {
            Log::error('LINE Harness friend not found', ['lineUserId' => $lineUserId]);
            return ['status' => 'error', 'message' => 'LINE Harness 側に該当する友だちが見つかりません'];
        }

        $response = $this->request()->post("{$this->baseUrl}/api/friends/{$friendId}/messages", [
            'content' => $message,
            'messageType' => 'text',
        ]);

        if ($response->successful()) {
            return ['status' => 'success', 'message' => 'メッセージを送信しました'];
        }

        Log::error('LINE Harness push message failed', [
            'lineUserId' => $lineUserId,
            'friendId' => $friendId,
            'status' => $response->status(),
            'details' => $response->json(),
        ]);

        return ['status' => 'error', 'message' => 'メッセージの送信に失敗しました', 'details' => $response->json()];
    }

    /**
     * LINE User ID から Harness 側の友だちIDを解決する
     *
     * @param string $lineUserId
     * @return string|null
     */
    private function resolveFriendId($lineUserId)
    {
        // まず LINE User ID をそのまま ID として引けるか試す
        $response = $this->request()->get("{$this->baseUrl}/api/friends/{$lineUserId}");
        if ($response->successful()) {
            return data_get($response->json(), 'data.id', $lineUserId);
        }

        // 引けない場合は一覧から lineUserId で突き合わせる
        $offset = 0;
        $limit = 100;
        do {
            $list = $this->request()->get("{$this->baseUrl}/api/friends", [
                'limit' => $limit,
                'offset' => $offset,
            ]);
            if (!$list->successful()) {
                return null;
            }

            $friends = data_get($list->json(), 'data', []);
            foreach ($friends as $friend) {
                if (data_get($friend, 'lineUserId') === $lineUserId) {
                    return data_get($friend, 'id');
                }
            }

            $offset += $limit;
        } while (count($friends) === $limit);

        return null;
    }

    private function request()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(15);
    }
}
