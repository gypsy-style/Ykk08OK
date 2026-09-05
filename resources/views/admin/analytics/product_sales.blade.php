@extends('admin.layouts.app')

@section('title', '管理画面 [サロン別商品売上一覧]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>サロン別商品売上一覧</h2>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block analytics_async" data-url="{{ route('admin.analytics.product_sales', ['all' => 1]) }}">
            @include('admin.analytics._product_sales')
        </div>
    </div>

    <div class="lma-content_block nobg" style="width:100%;">
        <p class="lma-btn_box btn_wh btn_min"><a href="{{ route('admin.analytics') }}">サロン分析へ戻る</a></p>
    </div>
</section>
@endsection

@push('head')
@include('admin.analytics._style')
@include('admin.analytics._async_script')
@endpush
