<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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

        return view('admin.settings.company_info', compact(
            'companyName',
            'companyDetail',
            'companySeal',
            'companyBankInfo'
        ));
    }

    public function updateCompanyInfo(Request $request)
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_detail' => 'nullable|string|max:5000',
            'company_seal' => 'nullable|image|max:5120',
            'company_bank_info' => 'nullable|string|max:5000',
        ]);

        Setting::updateOrCreate(['key' => 'company_name'], ['value' => $request->input('company_name')]);
        Setting::updateOrCreate(['key' => 'company_detail'], ['value' => $request->input('company_detail')]);
        Setting::updateOrCreate(['key' => 'company_bank_info'], ['value' => $request->input('company_bank_info')]);

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
}
