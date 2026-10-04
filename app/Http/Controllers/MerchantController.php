<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantMember;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\InvoiceService;
use App\Services\LineRichMenuService;
use App\Services\MerchantAccess;
use App\Services\MerchantRegisteredNotifier;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\URL;

class MerchantController extends Controller
{
    /** @var InvoiceService */
    private $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    // 店舗一覧表示
    public function index()
    {
        $merchants = Merchant::all();
        return view('merchants.index', compact('merchants'));
    }

    public function information()
    {
        return view('merchants.information');
    }

    // 新規作成フォーム表示
    public function create(Request $request)
    {
        $agency_id = $request->query('agency_id');
        if (!$agency_id && $request->query('liff_state')) {
            // LIFF経由だとクエリが liff.state に入ってくることがある
            parse_str(ltrim(urldecode($request->query('liff_state')), '?'), $params);
            $agency_id = $params['agency_id'] ?? null;
        }
        return view('merchants.create', compact('agency_id'));
    }

    public function edit(Request $request, $id)
    {
        // 本人確認のため、マイページが権限のある人にだけ発行する署名付き URL で開かせる
        if (!$request->hasValidSignature()) {
            return response()->view('merchants.invoice_expired', [
                'title' => '登録情報の修正',
                'message' => 'このリンクの有効期限が切れています。マイページからもう一度開いてください。',
            ], 403);
        }

        $merchant = Merchant::findOrFail($id); // IDで検索、見つからなければ404

        $updateUrl = URL::temporarySignedRoute(
            'merchants.update',
            Carbon::now()->addDay(),
            ['id' => $merchant->id]
        );

        return view('merchants.edit', compact('merchant', 'updateUrl'));
    }


    public function update(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'error' => 'このリンクの有効期限が切れています。マイページからもう一度開いてください。',
            ], 403);
        }

        try {
            $merchant = Merchant::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'postal_code1' => 'required|string|size:3',
                'postal_code2' => 'required|string|size:4',
                'address' => 'required|string|max:255',
                'phone' => 'required|string|max:15',
                'campaign_code' => 'nullable|string|max:255',
                'bank_account_name' => 'nullable|string|max:1000',
            ]);

            // サロン自身が変更できるのはこの画面の入力項目だけ。status や member_rank を
            // 受け付けると、編集のたびに承認済みが未承認に戻るなどの事故になる
            $merchant->update($request->only([
                'name',
                'postal_code1',
                'postal_code2',
                'address',
                'phone',
                'campaign_code',
                'bank_account_name',
            ]));

            return response()->json([
                'success' => true,
                'message' => '店舗が更新されました。',
                'merchant' => $merchant,
            ]);
        } catch (\Exception $e) {
            Log::error('Merchant registration error', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => '更新に失敗しました'
            ], 500);
        }
    }

    // データ保存処理
    public function store(LineRichMenuService $lineRichMenuService, MerchantRegisteredNotifier $notifier, Request $request)
    {
        try {
            // バリデーション
            $request->validate([
                'name' => 'required|string|max:255',
                'status' => 'required|integer|in:1,2',
                'campaign_code' => 'nullable|string|max:255',
                'postal_code1' => 'required|string|size:3',
                'postal_code2' => 'required|string|size:4',
                'address' => 'required|string|max:255',
                'phone' => 'required|string|max:15',
                'bank_account_name' => 'nullable|string|max:1000',
                'user_id' => [
                    'required',
                    'exists:users,id',
                    Rule::unique('merchants', 'user_id')->where(function ($query) {
                        $query->whereNull('deleted_at');
                    }),
                ],
            ]);

            // user_id から line_id を取得
            $user = User::find($request->input('user_id'));

            if (!$user || !$user->line_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'LINE IDが見つかりません。'
                ], 404);
            }

            $line_id = $user->line_id;

            // 店舗情報を作成
            // 会員ランクは agency/admin の create/edit からのみ更新できる仕様なので、ここでは固定で1
            $merchant = Merchant::create(array_merge(
                $request->except(['member_rank', 'merchant_code']),
                ['member_rank' => 1]
            ));

            // リッチメニュー更新
            $richmenu_id_3 = \App\Services\RichMenuSlots::id('RICHMENU_ID_3');
            $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu_id_3);
            // 段階を記録（リッチメニュー変更時の既存ユーザーへの再適用に使う）
            $user->update(['richmenu_id' => 'RICHMENU_ID_3']);

            Log::info("Merchant created: {$merchant->id}, Richmenu switched: {$line_id}");

            $notifier->notify($merchant);

            return response()->json([
                'success' => true,
                'message' => '店舗が追加され、リッチメニューが更新されました。',
                'merchant' => $merchant,
                'richmenu_result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Merchant registration error', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => '登録に失敗しました'
            ], 500);
        }
    }

    /**
     * 加盟店メンバー追加画面
     * @return void 
     */
    public function add_member(Request $request)
    {
        $merchant_id = $request->query('merchant_id');
        $invite_token = $request->query('invite_token');
        if (!$merchant_id) {
            $liffState = $request->query('liff_state');
            $decodedState = urldecode($liffState);
            $cleanState = ltrim($decodedState, '?'); // 先頭の `?` を削除
            parse_str($cleanState, $params);
            $merchant_id = $params['merchant_id'] ?? null;
            $invite_token = $params['invite_token'] ?? null;
        }

        // 正しい QR から来た人だけ受け付ける（merchant_id の数字を変えて別のサロンに入れないように）
        if (!MerchantAccess::isValidInviteToken($merchant_id, $invite_token)) {
            return response()->view('merchants.invoice_expired', [
                'title' => 'スタッフ追加',
                'message' => 'このスタッフ追加用のURLは無効です。サロンオーナーに新しいQRコードを出してもらってください。',
            ], 403);
        }

        $merchant = Merchant::find($merchant_id);

        if (!$merchant) {
            abort(404, 'Merchant not found');
        }
        return view('merchants.add_member', compact('merchant'));
    }

    public function destroy_member(LineRichMenuService $lineRichMenuService, Request $request, $id)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access || !$access->can('can_manage_staff')) {
            return response()->json(['error' => 'スタッフを削除する権限がありません。サロンオーナーにご確認ください。'], 403);
        }

        if ((int) $id === (int) $access->user->id) {
            return response()->json(['error' => '自分自身は削除できません。'], 422);
        }

        // 別のサロンのスタッフは消せないよう、自分のサロンに絞って探す
        $merchantMember = MerchantMember::where('user_id', $id)
            ->where('merchant_id', $access->merchant->id)
            ->first();
        if (!$merchantMember) {
            return response()->json(['error' => 'スタッフが見つかりません。'], 404);
        }

        $richmenu_2 = \App\Services\RichMenuSlots::id('RICHMENU_ID_2');
        if ($merchantMember->line_id) {
            $lineRichMenuService->switchRichMenu($merchantMember->line_id, $richmenu_2);
            User::where('id', $merchantMember->user_id)->update(['richmenu_id' => 'RICHMENU_ID_2']);
        }
        $merchantMember->delete();

        return response()->json(['success' => true, 'message' => 'Member deleted successfully'], 200);
    }

    /**
     * 加盟店メンバー追加処理
     * @return void 
     */
    public function storeMember(LineRichMenuService $lineRichMenuService, Request $request)
    {

        $accessToken = $request->input('access_token');
        $merchant_id = $request->input('merchant_id');

        if (!MerchantAccess::isValidInviteToken($merchant_id, $request->input('invite_token'))) {
            return response()->json([
                'error' => 'invalid invite token',
                'message' => 'このスタッフ追加用のURLは無効です。サロンオーナーに新しいQRコードを出してもらってください。',
            ], 403);
        }

        if (!Merchant::find($merchant_id)) {
            return response()->json(['error' => 'Merchant not found', 'message' => 'サロンが見つかりません。'], 404);
        }


        // LINEのプロフィール取得
        $profile = $this->getLineProfile($accessToken);
        if ($profile) {
            $line_id = $profile['line_id'];
        } else {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        // ユーザー検索
        $user = User::where('line_id', $line_id)->first();
        if (!$user) {

            return response()->json([
                'error' => 'User not found',
                'redirect_url' => url("/register?line_id={$line_id}")
            ], 404);
        }

        // すでにどこかのサロンのオーナーかスタッフである人は追加しない（二重所属を防ぐ）
        if (MerchantAccess::forUser($user)->merchant) {
            return response()->json([
                'error' => 'already belongs',
                'message' => 'すでにサロンに登録されています。',
            ], 409);
        }

        // データベースに保存
        $merchantMember = MerchantMember::create([
            'merchant_id' => $merchant_id,
            'user_id' => $user->id,
            'line_id' => $line_id,
        ]);

        // リッチメニュー更新
        $richmenu_id_4 = $lineRichMenuService->slotMenuIdFor('RICHMENU_ID_4', $user);
        $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu_id_4);

        // ユーザーテーブルのrichmenu_idを更新
        $user->update(['richmenu_id' => 'RICHMENU_ID_4']);

        return response()->json(['success' => true, 'message' => 'Member added successfully'], 200);
    }

    public function memberList()
    {
        return view('merchants.member_list');
    }

    public function invoices()
    {
        return view('merchants.invoices');
    }

    public function getInvoiceList(Request $request)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant || !$access->can('can_view_invoice')) {
            return response()->json(['error' => '請求書を見る権限がありません。サロンオーナーにご確認ください。'], 403);
        }

        // 当月は未確定のため前月までを対象とする
        $monthlyInvoices = $this->invoiceService->monthlyBreakdown($merchant);

        // 請求書ページは外部ブラウザで開くため、LIFF認証ではなく署名付きURLで本人確認する。
        // 一覧を開くたびに再発行されるので有効期限は短くてよい。
        foreach ($monthlyInvoices as $invoiceMonth => $invoice) {
            $monthlyInvoices[$invoiceMonth]['url'] = URL::temporarySignedRoute(
                'merchants.invoice',
                Carbon::now()->addDay(),
                ['merchant' => $merchant->id, 'month' => $invoiceMonth]
            );
        }

        $html = view('merchants.partials.invoice_list', compact('monthlyInvoices', 'merchant'))->render();

        return response()->json(['html' => $html, 'merchant_id' => $merchant->id]);
    }

    public function invoicePdf($merchantId, $month, Request $request)
    {
        if (!$request->hasValidSignature()) {
            return response()->view('merchants.invoice_expired', [
                'message' => 'このリンクの有効期限が切れています。LINEの請求書一覧からもう一度開いてください。',
            ], 403);
        }

        $merchant = Merchant::findOrFail($merchantId);

        // 当月は未確定のため表示しない
        if (!$this->invoiceService->isFixedMonth($month)) {
            return response()->view('merchants.invoice_expired', [
                'message' => 'この月の請求書はまだ確定していません。',
            ], 403);
        }

        $invoice = $this->invoiceService->forMonth($merchant, $month);

        $productAgg = $invoice['products'];
        $monthSubtotal = $invoice['subtotal'];
        $monthShippingFee = $invoice['shipping_fee'];
        $monthTaxAmount = $invoice['tax'];
        $monthGrandTotal = $invoice['grand_total'];
        $invoiceDate = $invoice['invoice_date'];
        $invoiceNumber = $invoice['invoice_number'];

        $companyName = Setting::getValue('company_name', '');
        $companyDetail = Setting::getValue('company_detail', '');
        $companySeal = Setting::getValue('company_seal', '');
        $companyBankInfo = Setting::getValue('company_bank_info', '');
        $companyPaymentNote = Setting::getValue('company_payment_note', '');

        // 印刷時のファイル名になる
        $pdfFilename = 'invoice_' . $merchant->id . '_' . $invoiceDate->format('Ymd');

        return view('merchants.invoice_pdf', compact(
            'merchant',
            'productAgg',
            'monthSubtotal',
            'monthShippingFee',
            'monthTaxAmount',
            'monthGrandTotal',
            'invoiceDate',
            'invoiceNumber',
            'month',
            'companyName',
            'companyDetail',
            'companySeal',
            'companyBankInfo',
            'companyPaymentNote',
            'pdfFilename'
        ));
    }

    /**
     * スタッフの権限を1つ切り替える（オーナーだけ）
     */
    public function updateMemberPermission(Request $request)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access || !$access->merchant || !$access->isOwner) {
            return response()->json(['error' => '権限を変更できるのはサロンオーナーだけです。'], 403);
        }

        $permission = $request->input('permission');
        if (!in_array($permission, MerchantAccess::PERMISSIONS, true)) {
            return response()->json(['error' => '変更できない項目です。'], 422);
        }

        // 別のサロンのスタッフは変えられないよう、自分のサロンに絞って探す
        $member = MerchantMember::where('user_id', $request->input('member_user_id'))
            ->where('merchant_id', $access->merchant->id)
            ->first();
        if (!$member) {
            return response()->json(['error' => 'スタッフが見つかりません。'], 404);
        }

        $member->{$permission} = $request->boolean('value');
        $member->save();

        return response()->json(['success' => true]);
    }

    public function getMemberList(Request $request)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant || !$access->can('can_manage_staff')) {
            return response()->json(['error' => 'スタッフ一覧を見る権限がありません。サロンオーナーにご確認ください。'], 403);
        }

        // 開いた人がスタッフの場合もあるので、オーナーは店舗から引く
        $owner = User::find($merchant->user_id);
        $members = MerchantMember::where('merchant_id', $merchant->id)->with('user')->get();

        $html = $this->renderMemberListHtml($owner, $members, $merchant->id, $access);

        return response()->json(['html' => $html, 'merchant_id' => $merchant->id]);
    }

    public function renderMemberListHtml($owner, $members, $merchant_id, MerchantAccess $access)
    {
        return view('merchants.partials.member_list', [
            'owner' => $owner,
            'members' => $members,
            'merchant_id' => $merchant_id,
            'viewerUserId' => $access->user->id,
            // 権限を変えられるのはオーナーだけ（スタッフ同士で権限を広げ合えないように）
            'canEditPermissions' => $access->isOwner,
            'inviteToken' => MerchantAccess::inviteToken($merchant_id),
        ])->render();
    }

    /**
     * merchant情報を取得
     * @return void 
     */
    public function getMerchantInformation(Request $request)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant) {
            return response()->json(['error' => '対応する店舗が見つかりません'], 404);
        }

        // 登録情報の修正画面は本人確認のため署名付き URL で開かせる
        $editUrl = null;
        if ($access->can('can_edit_merchant')) {
            $editUrl = URL::temporarySignedRoute(
                'merchants.edit',
                Carbon::now()->addDay(),
                ['id' => $merchant->id]
            );
        }

        return response()->json([
            'user_id' => $access->user->id,
            'merchant_user_id' => $merchant->user_id,
            'merchant_id' => $merchant->id,
            'merchant_code' => $merchant->merchant_code,
            'name' => $merchant->name,
            'status' => $merchant->status,
            'postal_code' => $merchant->postal_code1 . '-' . $merchant->postal_code2,
            'address' => $merchant->address,
            'phone' => $merchant->phone,
            'bank_account_name' => $merchant->bank_account_name,
            'agency_name' => optional($merchant->agency)->name,
            'has_invoice' => $access->can('can_view_invoice') && $this->invoiceService->hasInvoice($merchant),
            'is_owner' => $access->isOwner,
            'permissions' => $access->permissions(),
            'edit_url' => $editUrl,
        ]);
    }
}
