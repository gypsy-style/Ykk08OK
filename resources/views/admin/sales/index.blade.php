@extends('admin.layouts.app')

@section('title', '管理画面 [売上管理]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>売上管理</h2>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block">
            <div class="records_caption">
                <h2 class="lma-title_bar sky"><em class="label">{{ \Carbon\Carbon::parse($month . '-01')->format('Y年m月') }}</em></h2>
            </div>
            <div class="records_table">
                <dl class="records_list">
                    @forelse ($productSales as $row)
                    <dt style="width:inherit;">{{ $row->product_name }}</dt>
                    <dd>
                        <div class="inner"><span class="num">{{ (int) $row->total_quantity }}件</span><em class="price">{{ number_format($row->total_amount ?? 0) }}円</em></div>
                    </dd>
                    @empty
                    <dt>売上</dt>
                    <dd>
                        <div class="inner"><span class="num">0件</span><em class="price">0円</em></div>
                    </dd>
                    @endforelse
                    <dt>送料</dt>
                    <dd>
                        <div class="inner"><span class="num">{{ $shippingFeeCount }}件</span><em class="price">{{ number_format($headquartersProcessed->shipping_fee ?? 0) }}円</em></div>
                    </dd>
                    <dt>合計</dt>
                    <dd>
                        <div class="inner"><span class="num"></span><em class="price">{{ number_format($grandTotal) }}円</em></div>
                    </dd>
                </dl>
            </div>

        </div>

    </div>

    <div class="lma-content_block staff nobg">
        <ul class="lma-user_list store">
            @forelse ($merchantSales as $m)
                <li>
                    <div class="lma-user_box">
                        <div class="user_info">
                            <h3 class="name">{{ $m->merchant_name }}@if ($m->is_test)<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#f60;color:#fff;font-size:11px;vertical-align:middle;">テスト</span>@endif</h3>
                            <p class="line_id">{{ $m->agency_name ?? '代理店未設定' }}　会員ランク{{ $m->member_rank ?? '-' }}</p>
                        </div>
                        @php
                            $confirmation = $paymentConfirmations[$m->merchant_id] ?? null;
                            $send = $invoiceSends[$m->merchant_id] ?? null;
                        @endphp
                        <div class="lma-select_box" style="flex:0 0 auto;white-space:nowrap;margin-left:.5em;">
                            @if ($isFixedMonth)
                                <label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;">
                                    <input type="checkbox" class="js-payment-confirm" data-merchant="{{ $m->merchant_id }}" {{ $confirmation ? 'checked' : '' }}>
                                    振込確認
                                </label>
                                <span class="js-confirm-label" data-merchant="{{ $m->merchant_id }}" style="font-size:12px;color:#666;margin-left:6px;">{{ $confirmation && $confirmation->confirmed_at ? '確認済 ' . $confirmation->confirmed_at->format('n/j') : '' }}</span>
                            @endif
                        </div>
                        <div class="lma-btn_box btn_list">
                            <a href="{{ route('admin.sales.show', ['merchant' => $m->merchant_id, 'month' => $month]) }}" class="lgy">詳細</a>
                            @if ($isFixedMonth)
                                <a href="{{ route('admin.sales.invoice', ['merchant' => $m->merchant_id, 'month' => $month]) }}" target="_blank" rel="noopener" class="lgy">請求書を確認</a>
                                <a href="#" class="js-send-invoice" data-merchant="{{ $m->merchant_id }}" data-name="{{ $m->merchant_name }}" data-sent="{{ $send ? '1' : '' }}">LINEで送信</a>
                                <p class="js-send-status" data-merchant="{{ $m->merchant_id }}" style="font-size:12px;color:#666;margin:4px 0 0;">{{ $send && $send->sent_at ? '送信済 ' . $send->sent_at->format('n/j') : '' }}</p>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li>
                    <div class="lma-user_box">
                        <div class="user_info">
                            <p class="line_id">この月に売上があった店舗はありません。</p>
                        </div>
                    </div>
                </li>
            @endforelse
        </ul>
    </div>

    <div class="lma-content_block nobg" style="width:100%;">
        <ul class="lma-pnavi_list clearfix">
            <li class="next"><a href="{{ route('admin.sales.index', ['month' => $prevMonth]) }}">先月</a></li>
            <li class="prev"><a href="{{ route('admin.sales.index', ['month' => $nextMonth]) }}">次月</a></li>
        </ul>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var month = @json($month);
    var confirmUrl = @json(route('admin.sales.payment_confirm', ['merchant' => '__ID__']));
    var sendUrl = @json(route('admin.sales.send_invoice', ['merchant' => '__ID__']));

    function post(url, onDone) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ month: month })
        })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) { onDone(result.ok, result.json); })
        .catch(function () { onDone(false, { message: '通信に失敗しました。' }); });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.js-payment-confirm'), function (box) {
        box.addEventListener('change', function () {
            var id = box.dataset.merchant;
            var label = document.querySelector('.js-confirm-label[data-merchant="' + id + '"]');
            var wanted = box.checked;
            box.disabled = true;

            post(confirmUrl.replace('__ID__', id), function (ok, json) {
                box.disabled = false;
                if (!ok) {
                    // 保存できなかったので見た目を元に戻す
                    box.checked = !wanted;
                    label.style.color = '#d64545';
                    label.textContent = json.message || '保存に失敗しました。';
                    return;
                }
                label.style.color = '#666';
                label.textContent = json.confirmed ? '確認済 ' + json.confirmed_at : '';
            });
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('.js-send-invoice'), function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = btn.dataset.merchant;
            var status = document.querySelector('.js-send-status[data-merchant="' + id + '"]');

            var message = btn.dataset.sent
                ? btn.dataset.name + ' にはすでに送信済みです。' + month + 'の請求書をもう一度LINEで送信しますか？'
                : btn.dataset.name + ' に' + month + 'の請求書をLINEで送信します。よろしいですか？';
            if (!window.confirm(message)) {
                return;
            }

            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.5';
            status.style.color = '#666';
            status.textContent = '送信中...';

            post(sendUrl.replace('__ID__', id), function (ok, json) {
                btn.style.pointerEvents = '';
                btn.style.opacity = '';
                var success = ok && json.success;
                var skipped = ok && json.skipped;
                if (success) {
                    status.style.color = '#2f855a';
                    status.textContent = '送信済 ' + json.sent_at;
                    btn.dataset.sent = '1';
                } else if (skipped) {
                    status.style.color = '#666';
                    status.textContent = json.message || 'スキップしました。';
                } else {
                    status.style.color = '#d64545';
                    status.textContent = json.message || '送信に失敗しました。';
                }
            });
        });
    });
});
</script>
@endsection
