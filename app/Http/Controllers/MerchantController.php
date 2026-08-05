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
        return view('merchants.create', compact('agency_id'));
    }

    public function edit($id)
    {

        $merchant = Merchant::findOrFail($id); // IDで検索、見つからなければ404

        return view('merchants.edit', compact('merchant'));
    }


    public function update(Request $request, $id)
    {
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

            // 会員ランクは agency/admin の create/edit からのみ更新できる仕様
            $merchant->update($request->except(['member_rank']));

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
    public function store(LineRichMenuService $lineRichMenuService, Request $request)
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
            $richmenu_id_3 = env('RICHMENU_ID_3');
            $result = $lineRichMenuService->switchRichMenu($line_id, $richmenu_id_3);

            Log::info("Merchant created: {$merchant->id}, Richmenu switched: {$line_id}");

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
        if (!$merchant_id) {
            $liffState = $request->query('liff_state');
            $decodedState = urldecode($liffState);
            $cleanState = ltrim($decodedState, '?'); // 先頭の `?` を削除
            parse_str($cleanState, $params);
            $merchant_id = $params['merchant_id'] ?? null;
        }
        $merchant = Merchant::find($merchant_id);

        // $merchant_id = $request->input('merchant_id');
        if (!$merchant) {
            abort(404, 'Merchant not found');
        }
        return view('merchants.add_member', compact('merchant'));
    }

    public function destroy_member(LineRichMenuService $lineRichMenuService, $id)
    {
        $richmenu_2 = env('RICHMENU_ID_2');
         $merchantMember = MerchantMember::where('user_id', $id)->firstOrFail();
         if($merchantMember && $merchantMember->line_id)
         {
            $lineRichMenuService->switchRichMenu($merchantMember->line_id, $richmenu_2);
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

        // データベースに保存
        $merchantMember = MerchantMember::create([
            'merchant_id' => $merchant_id,
            'user_id' => $user->id,
            'line_id' => $line_id,
        ]);

        // リッチメニュー更新
        $richmenu_id_4 = env('RICHMENU_ID_4');
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
        $accessToken = $request->input('access_token');
        $profile = $this->getLineProfile($accessToken);
        if (!$profile) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }
        $line_id = $profile['line_id'];

        $user = User::where('line_id', $line_id)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // オーナーの店舗のみ請求書を閲覧可能
        $merchant = Merchant::where('user_id', $user->id)->first();
        if (!$merchant) {
            return response()->json(['error' => 'Merchant not found'], 404);
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

    public function getInvoice(Request $request)
    {
        $accessToken = $request->input('access_token');
        $month = $request->input('month');
        if (!preg_match('/^\d{4}-\d{2}$/', $month ?? '')) {
            return response()->json(['error' => 'Invalid month'], 400);
        }
        // 当月は未確定のため表示しない
        if ($month >= Carbon::now()->format('Y-m')) {
            return response()->json(['error' => '当月の請求書はまだ確定していません'], 400);
        }

        $profile = $this->getLineProfile($accessToken);
        if (!$profile) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $user = User::where('line_id', $profile['line_id'])->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // オーナーの店舗のみ請求書を閲覧可能
        $merchant = Merchant::where('user_id', $user->id)->first();
        if (!$merchant) {
            return response()->json(['error' => 'Merchant not found'], 404);
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

        $html = view('partials.invoice_pdf', compact(
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
            'companyPaymentNote'
        ))->render();

        return response()->json([
            'html' => $html,
            'pdf_filename' => 'invoice_' . $merchant->id . '_' . $invoiceDate->format('Ymd'),
        ]);
    }

    public function getMemberList(Request $request)
    {
        $accessToken = $request->input('access_token');
        $profile = $this->getLineProfile($accessToken);
        if ($profile) {
            $line_id = $profile['line_id'];
        } else {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }
        $line_id = $profile['line_id'];

        // `line_id` から `User` を検索
        $user = User::where('line_id', $line_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // `user_id` を持つ `Merchant` を取得
        $merchant = Merchant::where('user_id', $user->id)->first();

        if (!$merchant) {
            return response()->json(['error' => 'Merchant not found'], 404);
        }
        $merchant_id = $merchant->id;
        // 店舗メンバー一覧を取得
        // `merchant_id` に紐づくメンバーを取得
        $members = MerchantMember::where('merchant_id', $merchant_id)->with('user')->get();


        // BladeでHTMLをレンダリング
        $html = $this->renderMemberListHtml($user, $members, $merchant_id);


        return response()->json(['html' => $html,'merchant_id'=>$merchant_id]);
    }

    public function renderMemberListHtml($user, $members, $merchant_id)
    {
        return view('merchants.partials.member_list', compact('user', 'members', 'merchant_id'))->render();
    }

    /**
     * merchant情報を取得
     * @return void 
     */
    public function getMerchantInformation(Request $request)
    {
        $accessToken = $request->input('access_token');

        $profile = $this->getLineProfile($accessToken);

        if (!$profile) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $line_id = $profile['line_id'];

        // `line_id` から `User` を検索
        $user = User::where('line_id', $line_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // `user_id` を持つ `Merchant` を取得
        $merchant = Merchant::where('user_id', $user->id)->first();

        if(!$merchant) {
            // オーナーではない場合
            $merchantMember = MerchantMember::where('user_id', $user->id)->first();
            if (!$merchantMember) {
                return response()->json(['error' => '対応する店舗が見つかりません'], 404);
            }
            $merchant_id = $merchantMember->merchant_id;
            $merchant = Merchant::find($merchant_id);
        }

        if (!$merchant) {
            return response()->json(['error' => 'Merchant not found'], 404);
        }

        return response()->json([
            'user_id' => $user->id,
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
            'has_invoice' => $this->invoiceService->hasInvoice($merchant),
        ]);
    }
}
