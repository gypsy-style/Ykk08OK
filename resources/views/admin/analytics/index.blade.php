@extends('admin.layouts.app')

@section('title', '管理画面 [サロン分析]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>サロン分析</h2>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block">
            <div class="records_caption">
                <h2 class="lma-title_bar sky"><em class="label">{{ \Carbon\Carbon::parse($months[0] . '-01')->format('Y年n月') }}〜{{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}</em></h2>
            </div>
            <div class="records_table">
                <table class="lma-detail_tbl">
                    <tbody>
                        <tr>
                            <th></th>
                            @foreach ($months as $m)
                            <td>{{ \Carbon\Carbon::parse($m . '-01')->format('n月') }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th>新規加盟店数</th>
                            @foreach ($months as $m)
                            <td>{{ number_format($newMerchants[$m]) }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul class="lma-pnavi_list clearfix">
                <li class="prev"><a href="{{ route('admin.analytics', ['month' => $prevMonth]) }}">先月</a></li>
                {{-- 当月を表示中は次月へ進めない --}}
                @if ($hasNextMonth)
                <li class="next"><a href="{{ route('admin.analytics', ['month' => $nextMonth]) }}">次月</a></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block" id="product_sales_block">
            @include('admin.analytics._product_sales')
        </div>
    </div>
</section>
@endsection

@push('head')
<script>
    $(function () {
        $('#product_sales_block').on('click', '.lma-pnavi_list a', function (e) {
            e.preventDefault();
            $.get('{{ route('admin.analytics.product_sales') }}', { month: $(this).data('month') })
                .done(function (html) {
                    $('#product_sales_block').html(html);
                });
        });
    });
</script>
@endpush
