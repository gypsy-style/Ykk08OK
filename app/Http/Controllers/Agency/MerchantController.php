<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\LineRichMenuService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class MerchantController extends Controller
{
    public function index()
    {
        // ログイン中のagency_idを取得
        $agencyId = auth('agencies')->user()->id;

        // agency_idで絞り込みを追加
        $merchants = Merchant::where('agency_id', $agencyId)->get();

        return view('agencies.merchants.index', compact('merchants'));
    }

    public function invite()
    {
        // LIFF URL取得
        $agencyId = auth('agencies')->user()->id;
        $liff = config('app.register_merchant_liff_id');
        $inviteUrl = 'https://liff.line.me/' . $liff . '?agency_id=' . $agencyId;
        return view('agencies.merchants.invite', compact('inviteUrl'));
    }

    public function create()
    {
        return view('agencies.merchants.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'merchant_code' => 'required|string|max:255',
            'status' => 'required|integer|in:1,2',
            'postal_code1' => 'required|string|max:3',
            'postal_code2' => 'required|string|max:4',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        Merchant::create($request->all());

        return redirect()->route('agencies.merchants.index')->with('success', '加盟店を登録しました。');
    }

    public function edit($id)
    {
        $merchant = Merchant::findOrFail($id);
        return view('agencies.merchants.edit', compact('merchant'));
    }

    public function update(LineRichMenuService $lineRichMenuService, Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'merchant_code' => 'required|string|max:255',
            'status' => 'required|integer|in:1,2',
            'postal_code1' => 'required|string|max:3',
            'postal_code2' => 'required|string|max:4',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $merchant = Merchant::findOrFail($id);
        $merchant->update($request->all());

        // user_id から line_id を取得
        $user_id = $request->input('user_id');
        $user = User::find($request->input('user_id'));

        if (!$user || !$user->line_id) {
            return response()->json([
                'success' => false,
                'error' => 'LINE IDが見つかりません。'
            ], 404);
        }

        $line_id = $user->line_id;

        // ステータスによって処理を分岐
        $status = $request->input('status');


        if ($status == 1) {
            // ステータスが 1 の場合richmenu_4に
            $richmenu_id = env('RICHMENU_ID_4');
            $richmenu_name = 'RICHMENU_ID_4';
        } elseif ($status == 2) {
            // ステータスが 2 の場合richmenu_3に
            $richmenu_id = env('RICHMENU_ID_3');
            $richmenu_name = 'RICHMENU_ID_3';
        }
        $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu_id);


        $user->update(['richmenu_id' => $richmenu_name]);

        return redirect()->route('agencies.merchants.index')->with('success', '加盟店情報を更新しました。');
    }

    /**
     * 倫理削除
     * 削除したらリッチメニューも更新
     * @param LineRichMenuService $lineRichMenuService 
     * @param mixed $id 
     * @return RedirectResponse 
     * @throws InvalidArgumentException 
     * @throws Exception 
     * @throws BindingResolutionException 
     * @throws RouteNotFoundException 
     */
    public function destroy(LineRichMenuService $lineRichMenuService, $id)
    {
        $richmenu_2 = env('RICHMENU_ID_2');
        $merchant = Merchant::findOrFail($id);
        $merchant_id = $merchant->id;
        $owner_id = $merchant->user_id;
        $owner = User::find($owner_id);
        $owner_line_id = $owner->line_id;
        // 店舗オーナーのリッチメニューを変更
        if ($owner && $owner->line_id) {
            $lineRichMenuService->switchRichMenu($owner->line_id, $richmenu_2);
        }
        // 店舗データを削除
        $merchant->delete();

        // 加盟店に紐づいているメンバーを取得
        $members = User::where('merchant_id', $merchant_id)->get();
        foreach ($members as $member) {
            if ($member->line_id) {
                // メンバーのrichmenuを2に変更
                $lineRichMenuService->switchRichMenu($member->line_id, $richmenu_2);
            }
        }
        return redirect()->route('agencies.merchants.index')->with('success', '加盟店を削除しました。');
    }

    /**
     * 削除したデータの復元
     * @param mixed $id 
     * @return RedirectResponse 
     * @throws ModelNotFoundException 
     * @throws BindingResolutionException 
     * @throws RouteNotFoundException 
     */
    public function restore($id)
    {
        $merchant = Merchant::onlyTrashed()->findOrFail($id);
        $merchant->restore(); // 論理削除を解除

        return redirect()->route('agencies.merchants.index')->with('success', '加盟店を復元しました。');
    }
}
