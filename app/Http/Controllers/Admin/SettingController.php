<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

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
}
