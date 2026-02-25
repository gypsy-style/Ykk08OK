<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Retrieve the LINE profile (user ID and display name) using an access token.
     *
     * @param string $accessToken
     * @return array|null
     */
    public function getLineProfile(string $accessToken): ?array
    {
        // ローカルテスト用：dummy_* トークンの場合は外部LINE APIを呼ばず固定値を返す
        if (app()->environment('local') && str_starts_with($accessToken, 'dummy_')) {
            $dummyLineId = env('DUMMY_LINE_ID', 'DUMMY_LINE_ID');
            return [
                'line_id' => $dummyLineId,
                'display_name' => 'Dummy User',
            ];
        }

        $url = 'https://api.line.me/v2/profile';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);

            if ($response->successful()) {
                $profile = $response->json();
                
                if (isset($profile['userId'], $profile['displayName'])) {
                    // LINE ID と DisplayName を取得
                    $lineId = $profile['userId'];
                    $displayName = $this->sanitizeDisplayName($profile['displayName']);

                    return [
                        'line_id' => $lineId,
                        'display_name' => $displayName,
                    ];
                }
            }
        } catch (\Exception $e) {
            // ログにエラーを記録
            Log::error('Failed to retrieve LINE profile: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Sanitize display name to handle special characters.
     *
     * @param string $displayName
     * @return string
     */
    private function sanitizeDisplayName(string $displayName): string
    {
        // 絵文字や特殊文字を削除または置き換える
        $displayName = preg_replace('/[^\x{0000}-\x{007F}\x{0080}-\x{FFFF}]/u', '*', $displayName);

        // 他の特殊文字の対策が必要ならここで追加
        $displayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');

        return $displayName;
    }
}
