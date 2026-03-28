<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\LineRichMenuService;
use App\Services\LineMessageService;

class UserController extends Controller
{
    public function create()
    {
        return view('user.register');
    }

    public function store(LineRichMenuService $lineRichMenuService, LineMessageService $lineMessageService, Request $request)
    {
        try {
            // 1. LINEプロフィールを取得
            $accessToken = $request->input('access_token');
            $profile = $this->getLineProfile($accessToken);

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found or invalid token'
                ], 404);
            }

            $line_id = $profile['line_id'];
            $display_name = $profile['display_name'] ?? null;

            // 2. バリデーション
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // 3. ユーザー登録
            // email はスキーマ上必須のためダミー値を補完
            $email = $request->input('email');
            if (!$email) {
                $email = $line_id . '@example.com';
            }

            $user = User::create([
                'name' => $request->input('name'),
                'display_name' => $display_name,
                'email' => $email,
                'line_id' => $line_id,
                'richmenu_id' => 'RICHMENU_ID_2',
            ]);

            // 4. リッチメニュー更新
            $richmenu_id_2 = env('RICHMENU_ID_2');
            $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu_id_2);

            Log::info("Richmenu updated for LINE ID: {$line_id}", ['result' => $result]);

            // 5. 登録完了LINEメッセージ送信
            $messageResult = $lineMessageService->sendMessage($line_id, "登録が完了しました。\nご利用ありがとうございます。");

            Log::info("LINE message sent for LINE ID: {$line_id}", ['result' => $messageResult]);

            // 成功レスポンスを返す
            return response()->json([
                'success' => true,
                'message' => '登録が完了しました。',
                'user' => $user,
                'richmenu_result' => $result,
                'line_message_result' => $messageResult
            ]);
        } catch (\Exception $e) {
            Log::error('User registration error', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => '登録に失敗しました'
            ], 500);
        }
    }

    // LIFF IDからuser_idを取得
    public function getUserId(Request $request)
    {
        $accessToken = $request->input('access_token');
        $profile = $this->getLineProfile($accessToken);
        Log::alert(print_r($profile, true));
        if ($profile) {
            $line_id = $profile['line_id'];
        } else {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        // `line_id`でユーザーを検索
        $user = User::where('line_id', $line_id)->first();
        Log::alert(print_r($line_id, true));
        Log::alert(print_r($user, true));
        if ($user) {
            return response()->json(['user_id' => $user->id]);
        }

        return response()->json(['user_id' => null], 404);
    }
}
