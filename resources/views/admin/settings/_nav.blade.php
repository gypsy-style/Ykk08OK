{{-- 設定画面ナビ（lma-form_boxのdt内に縦並びで配置） --}}
@php($active = $active ?? '')
<ul class="lma-setting_vnav">
    <li><a href="{{ route('admin.settings.custom_css') }}" @if($active === 'custom_css') class="is-active" @endif>カスタムCSS</a></li>
    <li><a href="{{ route('admin.settings.privacy_policy') }}" @if($active === 'privacy_policy') class="is-active" @endif>プライバシーポリシー</a></li>
    <li><a href="{{ route('admin.settings.user_guide') }}" @if($active === 'user_guide') class="is-active" @endif>ご利用ガイド</a></li>
    <li><a href="{{ route('admin.settings.commercial_law') }}" @if($active === 'commercial_law') class="is-active" @endif>特定商取引法</a></li>
</ul>
<style>
    .lma-setting_vnav {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .lma-setting_vnav li a {
        display: block;
        padding: 6px 10px;
        text-decoration: none;
        color: #333;
        border-radius: 4px;
    }
    .lma-setting_vnav li a:hover {
        background: #f0f0f0;
    }
    .lma-setting_vnav li a.is-active {
        font-weight: bold;
        background: #e6f0ff;
        color: #1e6bd6;
    }
</style>
