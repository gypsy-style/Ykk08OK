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
use App\Services\LineMessageService;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    /** 並び順の選択肢。キーが sort パラメータの値 */
    private const SORTS = [
        'kana_asc' => 'あいうえお順（昇順）',
        'kana_desc' => 'あいうえお順（降順）',
        'created_desc' => '登録日順（新しい順）',
        'created_asc' => '登録日順（古い順）',
    ];

    public function index(Request $request)
    {
        return view('admin.merchants.index', [
            'merchants' => $this->filtered($request),
            'agencies' => Agency::all(),
            'sorts' => self::SORTS,
            'sort' => $this->sort($request),
        ]);
    }

    /** 検索・並び替えで一覧部分だけを差し替えるための部分HTML */
    public function listPartial(Request $request)
    {
        return view('admin.merchants._list', ['merchants' => $this->filtered($request)]);
    }

    private function filtered(Request $request)
    {
        $query = Merchant::with('agency');

        switch ($this->sort($request)) {
            case 'kana_desc':
                // ふりがな未入力は昇順・降順どちらでも末尾に固める
                $query->orderByRaw("(name_kana IS NULL OR name_kana = '') asc")->orderBy('name_kana', 'desc');
                break;
            case 'created_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'created_asc':
                $query->orderBy('created_at', 'asc');
                break;
            default:
                $query->orderByRaw("(name_kana IS NULL OR name_kana = '') asc")->orderBy('name_kana', 'asc');
        }

        if ($keyword = $request->query('keyword')) {
            $query->where('name_kana', 'like', "{$keyword}%");
        }
        if ($agencyId = $request->query('agency_id')) {
            $query->where('agency_id', $agencyId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($memberRank = $request->query('member_rank')) {
            $query->where('member_rank', $memberRank);
        }

        return $query->get();
    }

    private function sort(Request $request)
    {
        $sort = $request->query('sort');

        return is_string($sort) && isset(self::SORTS[$sort]) ? $sort : 'kana_asc';
    }

    public function create()
    {
        return view('admin.merchants.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_kana' => ['nullable', 'string', 'max:255', 'regex:/\A[ぁ-ゖ]/u'],
            'merchant_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('merchants', 'merchant_code'),
            ],
            'campaign_code' => 'nullable|string|max:255',
            'status' => 'required|integer|in:1,2',
            'member_rank' => 'required|integer|in:1,2,3',
            'postal_code1' => 'required|string|max:3',
            'postal_code2' => 'required|string|max:4',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'bank_account_name' => 'nullable|string|max:1000',
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('merchants', 'user_id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
        ], [
            'name_kana.regex' => 'ふりがなはひらがなで始めてください。',
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

    public function update(LineRichMenuService $lineRichMenuService, LineMessageService $lineMessageService, Request $request, $id)
    {

        try{
            $request->validate([
                'name' => 'required|string|max:255',
                'name_kana' => ['nullable', 'string', 'max:255', 'regex:/\A[ぁ-ゖ]/u'],
                'merchant_code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('merchants', 'merchant_code')->ignore($id),
                ],
                'campaign_code' => 'nullable|string|max:255',
                'status' => 'required|integer|in:1,2',
                'member_rank' => 'required|integer|in:1,2,3',
                'postal_code1' => 'required|string|max:3',
                'postal_code2' => 'required|string|max:4',
                'address' => 'required|string|max:255',
                'phone' => 'required|string|max:15',
                'bank_account_name' => 'nullable|string|max:1000',
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
            ], [
                'name_kana.regex' => 'ふりがなはひらがなで始めてください。',
            ]);

            $merchant = Merchant::findOrFail($id);
            $oldStatus = $merchant->status;
            $merchant->update($request->all());

            // user情報取得
            $user_id = $request->input('user_id');
            $user = User::find($user_id);
            if (!$user) {
                throw new Exception("ユーザーが見つかりません: user_id={$user_id}");
            }
            $line_id = $user->line_id;

            // ステータスによってリッチメニューを変える
            $status = (int) $request->input('status');
            if ($status === 1) {
                $richmenu_id = env('RICHMENU_ID_4');
                $richmenu_name = 'RICHMENU_ID_4';
            } else {
                $richmenu_id = env('RICHMENU_ID_3');
                $richmenu_name = 'RICHMENU_ID_3';
            }
            $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu_id);
            Log::info("RichMenu switched: line_id={$line_id}, richmenu={$richmenu_name}", ['result' => $result]);

            $user->update(['richmenu_id' => $richmenu_name]);

            // ステータスが2→1に変更された場合、LINEメッセージを送信
            if ((int) $oldStatus === 2 && $status === 1) {
                $message = "【店舗認証完了のお知らせ】\n\nお待たせいたしました。\nご登録内容の確認が完了し、店舗認証をさせていただきました。\n\n本日より、こちらのLINEから商品の注文が可能となります。\n下部メニューの「注文する」ボタンより、ぜひご利用ください。\n\n引き続き「KAMI注文LINE」をよろしくお願いいたします。";
                $lineMessageService->sendMessage($line_id, $message);
            }
            
            return redirect()->route('admin.merchants.index')->with('success', '加盟店情報を更新しました。');
        }catch(Exception $e) {
            Log::error('User registration error', ['exception' => $e->getMessage()]);
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
        
    }

    public function show($id)
    {
        // 加盟店の月別売上は admin.sales.show へ移動
        return redirect()->route('admin.sales.show', ['merchant' => $id]);
    }

    public function destroy($id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->delete();

        return redirect()->route('admin.merchants.index')->with('success', '加盟店を削除しました。');
    }
}
