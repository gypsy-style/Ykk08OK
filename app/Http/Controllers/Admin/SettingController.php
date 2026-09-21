<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceLineMessageService;
use App\Services\InvoiceService;
use App\Services\LineMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function customCss()
    {
        $customCss = Setting::getValue('custom_css', '');
        return view('admin.settings.custom_css', compact('customCss'));
    }

    public function updateCustomCss(Request $request)
    {
        $request->validate([
            'custom_css' => 'nullable|string|max:50000',
        ]);

        Setting::updateOrCreate(
            ['key' => 'custom_css'],
            ['value' => $request->input('custom_css')]
        );

        return redirect()->route('admin.settings.custom_css')->with('success', 'カスタムCSSを保存しました。');
    }

    public function privacyPolicy()
    {
        $privacyPolicy = Setting::getValue('privacy_policy', '');
        return view('admin.settings.privacy_policy', compact('privacyPolicy'));
    }

    public function updatePrivacyPolicy(Request $request)
    {
        $request->validate([
            'privacy_policy' => 'nullable|string|max:200000',
        ]);

        Setting::updateOrCreate(
            ['key' => 'privacy_policy'],
            ['value' => $request->input('privacy_policy')]
        );

        return redirect()->route('admin.settings.privacy_policy')->with('success', 'プライバシーポリシーを保存しました。');
    }

    public function userGuide()
    {
        $userGuide = Setting::getValue('user_guide', '');
        return view('admin.settings.user_guide', compact('userGuide'));
    }

    public function updateUserGuide(Request $request)
    {
        $request->validate([
            'user_guide' => 'nullable|string|max:200000',
        ]);

        Setting::updateOrCreate(
            ['key' => 'user_guide'],
            ['value' => $request->input('user_guide')]
        );

        return redirect()->route('admin.settings.user_guide')->with('success', 'ご利用ガイドを保存しました。');
    }

    public function commercialLaw()
    {
        $commercialLaw = Setting::getValue('commercial_law', '');
        return view('admin.settings.commercial_law', compact('commercialLaw'));
    }

    public function updateCommercialLaw(Request $request)
    {
        $request->validate([
            'commercial_law' => 'nullable|string|max:200000',
        ]);

        Setting::updateOrCreate(
            ['key' => 'commercial_law'],
            ['value' => $request->input('commercial_law')]
        );

        return redirect()->route('admin.settings.commercial_law')->with('success', '特定商取引法を保存しました。');
    }

    public function cartNotice()
    {
        $cartNotice = Setting::getValue('cart_notice', '');
        return view('admin.settings.cart_notice', compact('cartNotice'));
    }

    public function updateCartNotice(Request $request)
    {
        $request->validate([
            'cart_notice' => 'nullable|string|max:200000',
        ]);

        Setting::updateOrCreate(
            ['key' => 'cart_notice'],
            ['value' => $request->input('cart_notice')]
        );

        return redirect()->route('admin.settings.cart_notice')->with('success', 'カート画面のお知らせを保存しました。');
    }

    public function companyInfo()
    {
        $companyName = Setting::getValue('company_name', '');
        $companyDetail = Setting::getValue('company_detail', '');
        $companySeal = Setting::getValue('company_seal', '');
        $companyBankInfo = Setting::getValue('company_bank_info', '');
        $companyPaymentNote = Setting::getValue('company_payment_note', '');

        return view('admin.settings.company_info', compact(
            'companyName',
            'companyDetail',
            'companySeal',
            'companyBankInfo',
            'companyPaymentNote'
        ));
    }

    public function updateCompanyInfo(Request $request)
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_detail' => 'nullable|string|max:5000',
            'company_seal' => 'nullable|image|max:5120',
            'company_bank_info' => 'nullable|string|max:5000',
            'company_payment_note' => 'nullable|string|max:5000',
        ]);

        Setting::updateOrCreate(['key' => 'company_name'], ['value' => $request->input('company_name')]);
        Setting::updateOrCreate(['key' => 'company_detail'], ['value' => $request->input('company_detail')]);
        Setting::updateOrCreate(['key' => 'company_bank_info'], ['value' => $request->input('company_bank_info')]);
        Setting::updateOrCreate(['key' => 'company_payment_note'], ['value' => $request->input('company_payment_note')]);

        if ($request->hasFile('company_seal')) {
            $existing = Setting::getValue('company_seal', '');
            if ($existing) {
                Storage::disk('public')->delete($existing);
            }
            $path = $request->file('company_seal')->store('images', 'public');
            Setting::updateOrCreate(['key' => 'company_seal'], ['value' => $path]);
        }

        return redirect()->route('admin.settings.company_info')->with('success', '会社情報を保存しました。');
    }

    public function shipping()
    {
        $prefectures = config('prefectures');
        $threshold = Setting::getValue('shipping_threshold', '');
        $fees = $this->shippingFees($prefectures);

        return view('admin.settings.shipping', compact('prefectures', 'threshold', 'fees'));
    }

    public function updateShipping(Request $request)
    {
        $request->validate([
            'threshold' => 'nullable|integer|min:0',
            'fees.*.under' => 'nullable|integer|min:0',
            'fees.*.over' => 'nullable|integer|min:0',
        ], [
            'threshold.integer' => '送料設定金額は半角数字で入力してください。',
            'threshold.min' => '送料設定金額は0以上で入力してください。',
            'fees.*.under.integer' => '半角数字で入力してください。',
            'fees.*.under.min' => '0以上で入力してください。',
            'fees.*.over.integer' => '半角数字で入力してください。',
            'fees.*.over.min' => '0以上で入力してください。',
        ]);

        $input = (array) $request->input('fees', []);
        $fees = [];

        // 知らない都道府県が紛れ込んでも保存しない。空欄は0として扱う
        foreach (config('prefectures') as $pref) {
            $row = isset($input[$pref]) && is_array($input[$pref]) ? $input[$pref] : [];
            $fees[$pref] = [
                'under' => (int) ($row['under'] ?? 0),
                'over' => (int) ($row['over'] ?? 0),
            ];
        }

        Setting::updateOrCreate(
            ['key' => 'shipping_threshold'],
            ['value' => $request->filled('threshold') ? (string) (int) $request->input('threshold') : '']
        );
        Setting::updateOrCreate(
            ['key' => 'shipping_fees'],
            ['value' => json_encode($fees, JSON_UNESCAPED_UNICODE)]
        );

        return redirect()->route('admin.settings.shipping')->with('success', '送料設定を保存しました。');
    }

    /**
     * 保存済みの都道府県別送料を、全都道府県ぶん0埋めした配列で返す
     *
     * 未保存でもビューが全行を描けるようにするため、欠けている都道府県は0で補う。
     */
    private function shippingFees(array $prefectures)
    {
        $saved = json_decode((string) Setting::getValue('shipping_fees', ''), true);
        if (!is_array($saved)) {
            $saved = [];
        }

        $fees = [];
        foreach ($prefectures as $pref) {
            $row = isset($saved[$pref]) && is_array($saved[$pref]) ? $saved[$pref] : [];
            $fees[$pref] = [
                'under' => (int) ($row['under'] ?? 0),
                'over' => (int) ($row['over'] ?? 0),
            ];
        }

        return $fees;
    }

    public function invoiceLine(InvoiceLineMessageService $messageService, InvoiceService $invoiceService)
    {
        $invoiceLineEnabled = $messageService->isEnabled();
        $invoiceLineMessage = $messageService->template();
        $placeholders = InvoiceLineMessageService::placeholders();
        $invoiceUrl = $invoiceService->invoiceUrl();
        $targetMerchantIds = Setting::getValue(InvoiceLineMessageService::KEY_TARGET_IDS, '');
        $targetMerchants = Merchant::whereIn('id', $messageService->targetMerchantIds())->get();

        return view('admin.settings.invoice_line', compact(
            'invoiceLineEnabled',
            'invoiceLineMessage',
            'placeholders',
            'invoiceUrl',
            'targetMerchantIds',
            'targetMerchants'
        ));
    }

    public function updateInvoiceLine(Request $request)
    {
        $request->validate([
            'invoice_line_message' => 'required|string|max:4000',
            'invoice_line_target_merchant_ids' => 'nullable|string|max:255|regex:/^[0-9,\s]*$/',
        ], [
            'invoice_line_message.required' => 'メッセージ本文を入力してください。',
            'invoice_line_message.max' => 'メッセージ本文は4000文字以内で入力してください。',
            'invoice_line_target_merchant_ids.regex' => 'テスト対象の加盟店IDは半角数字とカンマで入力してください。',
        ]);

        Setting::updateOrCreate(
            ['key' => InvoiceLineMessageService::KEY_ENABLED],
            ['value' => $request->boolean('invoice_line_enabled') ? '1' : '0']
        );
        Setting::updateOrCreate(
            ['key' => InvoiceLineMessageService::KEY_MESSAGE],
            ['value' => $request->input('invoice_line_message')]
        );
        Setting::updateOrCreate(
            ['key' => InvoiceLineMessageService::KEY_TARGET_IDS],
            ['value' => trim((string) $request->input('invoice_line_target_merchant_ids'))]
        );

        return redirect()->route('admin.settings.invoice_line')->with('success', '請求書LINE通知の設定を保存しました。');
    }

    /**
     * 入力中の本文をサンプル値で自分のLINEへテスト送信する
     */
    public function testInvoiceLine(
        Request $request,
        InvoiceLineMessageService $messageService,
        InvoiceService $invoiceService,
        LineMessageService $lineMessageService
    ) {
        $request->validate([
            'invoice_line_message' => 'required|string|max:4000',
            'line_id' => 'required|string|max:255',
        ], [
            'invoice_line_message.required' => 'メッセージ本文を入力してください。',
            'line_id.required' => '送信先のLINEユーザーIDを入力してください。',
        ]);

        $lineId = trim($request->input('line_id'));

        // 未登録のLINE IDへ誤爆しないよう、登録済みユーザーに限定する
        if (!User::where('line_id', $lineId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'そのLINEユーザーIDは登録されていません。',
            ], 422);
        }

        $body = $messageService->renderSample(
            $request->input('invoice_line_message'),
            $invoiceService->invoiceUrl()
        );

        $result = $lineMessageService->sendMessage($lineId, $body);

        return response()->json([
            'success' => ($result['status'] ?? '') === 'success',
            'message' => $result['message'] ?? '',
        ]);
    }
}
