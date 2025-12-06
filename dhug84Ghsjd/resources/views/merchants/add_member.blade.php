@extends('layouts.app')
@section('title', 'メンバー追加')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block">
        <h1 class="title">登録スタッフ追加</h1>
    </div>



    <main class="lmf-main_contents">
        <section class="lmf-content">
            <div class="lmf-info_block lmf-white_block">
                <dl class="lmf-info_list">
                    <dt>店舗名</dt>
                    <dd>{{$merchant->name}}</dd>
                </dl>
                <p>上記店舗にスタッフとして追加します。<br>
                    よろしければ下記のボタンを押してください。</p>
                <form id="memberForm">
                    @csrf
                    <input type="hidden" name="merchant_id" id="merchant_id" value="{{$merchant->id}}">
                    <p class="lmf-btn_box btn_small"><input id="submitBtn" type="submit" value="スタッフとして追加"></p>
                </form>
            </div>
        </section>
    </main>
</div><!-- /.lmf-container -->
@endsection

@push('scripts')
<script>
    window.LIFF_ID_ADD_MEMBER = "{{ config('app.add_member_liff_id') }}";
</script>
@vite(['resources/js/liff_merchant_add_member.js'])
@endpush