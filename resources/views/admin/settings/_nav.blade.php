{{-- 設定画面ナビ（lma-form_boxのdt内に縦並びで配置） --}}
@php($active = $active ?? '')
<ul class="lma-setting_vnav">
    <li><a href="{{ route('admin.settings.custom_css') }}" @if($active === 'custom_css') class="is-active" @endif>カスタムCSS</a></li>
    <li><a href="{{ route('admin.settings.privacy_policy') }}" @if($active === 'privacy_policy') class="is-active" @endif>プライバシーポリシー</a></li>
    <li><a href="{{ route('admin.settings.user_guide') }}" @if($active === 'user_guide') class="is-active" @endif>ご利用ガイド</a></li>
    <li><a href="{{ route('admin.settings.commercial_law') }}" @if($active === 'commercial_law') class="is-active" @endif>特定商取引法</a></li>
    <li><a href="{{ route('admin.settings.cart_notice') }}" @if($active === 'cart_notice') class="is-active" @endif>カート画面のお知らせ</a></li>
    <li><a href="{{ route('admin.settings.company_info') }}" @if($active === 'company_info') class="is-active" @endif>会社情報</a></li>
    <li><a href="{{ route('admin.settings.shipping') }}" @if($active === 'shipping') class="is-active" @endif>送料設定</a></li>
    <li><a href="{{ route('admin.settings.invoice_line') }}" @if($active === 'invoice_line') class="is-active" @endif>請求書LINE通知</a></li>
</ul>
<style>
    /* コンテンツ側と同じ白背景だと境目が見えないため、ナビだけ敷き直す。
       横の padding と border は 9em の dt 幅を削って項目名の折り返しを増やすので入れない。
       選択中の青（#e6f0ff）と紛れないよう、敷き色は青みを抑えたグレーにする */
    .lma-setting_vnav {
        list-style: none;
        padding: 8px 0;
        margin: 0 0 10px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        background: #eef1f6;
        border-radius: 8px;
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
