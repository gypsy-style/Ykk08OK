<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\LineRichMenuService;

class UserController extends Controller
{
    /**
     * ユーザー一覧表示
     */
    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'line_id', 'richmenu_id', 'is_notify_target'); // name, line_id のみ取得

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('line_id', 'like', "%{$keyword}%");
            });
        }
        if ($richmenuId = $request->query('richmenu_id')) {
            $query->where('richmenu_id', $richmenuId);
        }

        $users = $query->paginate(10)->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    /**
     * ユーザー削除
     */
    public function destroy(LineRichMenuService $lineRichMenuService, $id)
    {
        try {
            $user = User::findOrFail($id);
            $line_id = $user->line_id;
            // 個別の紐付けを外して既定メニュー（①）に戻す。①を個別に紐付けると、
            // あとで既定メニューを変更してもこの人には届かなくなるため
            $result = $lineRichMenuService->unlinkUser($line_id);
            $user->delete();
            return redirect()->route('admin.users.index')->with('success', 'ユーザーを削除しました。');
        } catch (Exception $e) {
            Log::error('User deletion error', ['exception' => $e->getMessage()]);
            return redirect()->back()->withErrors(['error' => '削除に失敗しました。']);
        }
    }

    /**
     * 非同期でリッチメニューをアップデート
     * @param Request $request 
     * @param User $user 
     * @return JsonResponse 
     * @throws MassAssignmentException 
     * @throws BindingResolutionException 
     */
    public function updateRichmenu(LineRichMenuService $lineRichMenuService, Request $request, User $user)
    {
        $request->validate([
            'richmenu_id' => 'required|string',
        ]);

        $richmenu_id = $request->input('richmenu_id');
        $user_id = $user->id;

        // ユーザーIDから line_id を取得
        $line_id = $user->line_id;

        if (!$line_id) {
            return response()->json(['success' => false, 'message' => 'ユーザーのLINE IDが見つかりません。'], 400);
        }

        try {
            // ユーザーのリッチメニューIDを更新
            $user->update([
                'richmenu_id' => $richmenu_id,
            ]);

            // リッチメニュー更新
            $richmenu_value = (\App\Services\RichMenuSlots::isSlot($richmenu_id) ? $lineRichMenuService->slotMenuIdFor($richmenu_id, $user) : null); // 環境変数から適切に取得
            if (!$richmenu_value) {
                return response()->json(['success' => false, 'message' => 'リッチメニューの設定が見つかりません。'], 400);
            }
            Log::alert(print_r($user,true));

            $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu_value);
            // Log::alert(print_r($result,true));

            if (!$result || $result['status'] == 'error') {
                return response()->json(['success' => false, 'message' => 'リッチメニューの更新に失敗しました。'], 500);
            }

            return response()->json(['success' => true, 'message' => 'リッチメニューが更新されました！']);
        } catch (Exception $e) {
            Log::error('リッチメニュー更新エラー', ['exception' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'リッチメニューの更新中にエラーが発生しました。'], 500);
        }
    }

    /**
     * システム通知の送信対象フラグを切り替える
     *
     * 一覧のチェックボックスから即時保存されるため、JSON を返す。
     */
    public function updateNotifyTarget(Request $request, User $user)
    {
        $request->validate([
            'is_notify_target' => 'required|boolean',
        ]);

        $isNotifyTarget = $request->boolean('is_notify_target');

        // LINE ID が無いユーザーは通知を受け取れないので、対象にさせない
        if ($isNotifyTarget && !$user->line_id) {
            return response()->json([
                'success' => false,
                'message' => 'このユーザーはLINE IDが未登録のため、通知対象にできません。',
            ], 400);
        }

        $user->update([
            'is_notify_target' => $isNotifyTarget,
        ]);

        return response()->json(['success' => true]);
    }
}
