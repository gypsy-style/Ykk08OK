<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Agency;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\LineRichMenuService;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    public function index()
    {
        $merchants = Merchant::with('agency')->get();
        return view('admin.merchants.index', compact('merchants'));
    }

    public function create()
    {
        return view('admin.merchants.create');
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('merchants', 'user_id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
        ]);

        Merchant::create($request->all());

        return redirect()->route('admin.merchants.index')->with('success', '加盟店を登録しました。');
    }

    public function edit($id)
    {
        $merchant = Merchant::findOrFail($id);
        $agencies = Agency::all(); // 代理店の一覧を取得
        return view('admin.merchants.edit', compact('merchant', 'agencies'));
    }

    public function update(LineRichMenuService $lineRichMenuService, Request $request, $id)
    {

        try{
            $request->validate([
                'name' => 'required|string|max:255',
                'merchant_code' => 'required|string|max:255',
                'status' => 'required|integer|in:1,2',
                'postal_code1' => 'required|string|max:3',
                'postal_code2' => 'required|string|max:4',
                'address' => 'required|string|max:255',
                'phone' => 'required|string|max:15',
                'user_id' => [
                    'required',
                    'integer',
                    'exists:users,id',
                    Rule::unique('merchants', 'user_id')
                        ->ignore($id)
                        ->where(function ($query) {
                            $query->whereNull('deleted_at');
                        }),
                ],
                'agency_id' => 'required|integer|exists:agencies,id',
            ]);

            $merchant = Merchant::findOrFail($id);
            $merchant->update($request->all());

            // user情報取得
            $user_id = $request->input('user_id');
            $user = User::find($user_id);
            if(!$user) {
                // ユーザー情報が見つかりませんエラー404
            }
            $line_id = $user->line_id;

            // ステータスによってリッチメニューを変える
            $status = $request->input('status');
            if($status === 1) {
                $richmenu = env('RICHMENU_ID_4');
                $richmenu_name = 'RICHMENU_ID_4';

            }else {
                $richmenu = env('RICHMENU_ID_3');
                $richmenu_name = 'RICHMENU_ID_3';
            }
            $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu);

            $user->update(['richmenu_id'=>$richmenu_name]);
            
            return redirect()->route('admin.merchants.index')->with('success', '加盟店情報を更新しました。');
        }catch(Exception $e) {
            Log::error('User registration error', ['exception' => $e->getMessage()]);
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
        
    }

    public function show($id)
    {
        $agency = Merchant::findOrFail($id);
        return view('admin.agencies.show', compact('agency'));
    }

    public function destroy($id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->delete();

        return redirect()->route('admin.merchants.index')->with('success', '加盟店を削除しました。');
    }
}
