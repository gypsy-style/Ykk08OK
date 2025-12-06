@extends('layouts.app')
@section('title', '登録情報')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">登録情報</h1>
    </div>
    <main class="lmf-main_contents">
            <section class="lmf-content">
                <div class="lmf-info_block lmf-white_block">
                    <dl class="lmf-info_list">
                    <dt>代理店名</dt>
                    <dd id="agency_name"></dd>
                        <dt>サロン名</dt>
                        <dd id="name"></dd>
                        <dt>サロンコード</dt>
                        <dd id="merchant_code"></dd>
                        <dt>郵便番号</dt>
                        <dd id="postal_code"></dd>
                        <dt>住所</dt>
                        <dd id="address"></dd>
                        <dt>電話番号</dt>
                        <dd id="phone"></dd>
                    </dl>
                    <p class="lmf-btn_box btn_dgy btn_small" id="edit_link" style="display:none;"><a href="">登録情報を修正する</a></p>
                </div>
                <p class="lmf-btn_box member_list" style="display:none;"><a href="{{ route('merchants.member_list') }}">登録スタッフ一覧</a></p>
            </section>
        </main>
</div>
@endsection
@push('scripts')
<script>
    window.EDIT_URL = "{{ route('merchants.edit', ['id' => ':id']) }}";
    window.LIFF_ID_REGISTER = "{{ config('app.register_liff_id') }}";
    window.LIFF_ID_MERCHANT_INFORMATION = "{{ config('app.merchant_information_liff_id') }}";
</script>
@vite(['resources/js/liff_information.js'])
<script>
    // liff.js の初期化や設定をここに記述
    // LIFF initialization code
</script>
@endpush