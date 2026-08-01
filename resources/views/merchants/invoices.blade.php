@extends('layouts.app')
@section('title', '請求書一覧')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block">
        <h1 class="title">請求書一覧</h1>
    </div>

    <main class="lmf-main_contents">
        <section class="lmf-content">
            <div id="invoice-list">
                <p class="lmf-loading">読み込み中...</p>
            </div>

            <input type="hidden" name="access_token" id="access_token">
        </section>
    </main>
</div><!-- /.lmf-container -->
@endsection
@push('scripts')
<script>
    window.LIFF_ID = "{{ config('app.invoice_liff_id') ?: config('app.merchant_information_liff_id') }}";
    window.LIFF_MOCK = {{ config('app.liff_mock') ? 'true' : 'false' }};
</script>
@vite(['resources/js/liff_invoice_list.js'])
@endpush
