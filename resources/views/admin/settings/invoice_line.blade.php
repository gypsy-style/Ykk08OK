@extends('admin.layouts.app')

@section('title', '管理画面 [請求書LINE通知]')

@php
    $lastMonth = \Carbon\Carbon::now()->subMonth();
    $sampleValues = [
        '{merchant_name}' => 'サンプル商店',
        '{month_label}' => $lastMonth->format('Y年n月分'),
        '{month}' => $lastMonth->format('Y-m'),
        '{order_count}' => '12',
        '{subtotal}' => '120,000',
        '{tax}' => '12,000',
        '{shipping_fee}' => '3,000',
        '{total}' => '135,000',
        '{invoice_url}' => $invoiceUrl ?: 'https://liff.line.me/xxxxxxxxxx-yyyyyyyy',
    ];
@endphp

@push('head')
<style>
    .lma-invline_layout {
        display: flex;
        gap: 24px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .lma-invline_editor {
        flex: 1 1 420px;
        min-width: 320px;
    }
    .lma-invline_preview {
        flex: 0 0 340px;
    }
    .lma-invline_editor textarea {
        width: 100%;
        min-height: 320px;
        font-family: inherit;
        font-size: 14px;
        line-height: 1.7;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: vertical;
    }
    .lma-invline_help {
        margin: 8px 0 0;
        padding: 8px 12px;
        background: #f8f9fa;
        border-left: 3px solid #1e6bd6;
        font-size: 12px;
        color: #555;
    }
    .lma-invline_ph {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 10px 0 0;
    }
    .lma-invline_ph button {
        border: 1px solid #cfd8e3;
        background: #fff;
        border-radius: 12px;
        padding: 3px 10px;
        font-size: 12px;
        cursor: pointer;
        color: #1e6bd6;
    }
    .lma-invline_ph button:hover {
        background: #e6f0ff;
    }
    .lma-invline_count {
        font-size: 12px;
        color: #888;
        text-align: right;
        margin: 4px 0 0;
    }
    .lma-invline_count.is-over {
        color: #d64545;
        font-weight: bold;
    }

    /* --- LINEトーク風プレビュー --- */
    .lma-line_phone {
        border: 1px solid #d5d5d5;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
        background: #8cabd8;
    }
    .lma-line_header {
        background: #202b33;
        color: #fff;
        font-size: 13px;
        padding: 10px 12px;
        text-align: center;
    }
    .lma-line_body {
        padding: 14px 10px 20px;
        min-height: 380px;
        max-height: 560px;
        overflow-y: auto;
    }
    .lma-line_row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }
    .lma-line_avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #06c755;
        color: #fff;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
    }
    .lma-line_col {
        max-width: 78%;
    }
    .lma-line_name {
        font-size: 11px;
        color: #f0f4f8;
        margin: 0 0 4px 2px;
    }
    .lma-line_bubble_wrap {
        display: flex;
        align-items: flex-end;
        gap: 5px;
    }
    .lma-line_bubble {
        position: relative;
        background: #fff;
        border-radius: 16px;
        padding: 10px 13px;
        font-size: 13px;
        line-height: 1.65;
        color: #16232b;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .lma-line_bubble::before {
        content: "";
        position: absolute;
        top: 12px;
        left: -6px;
        border-width: 5px 7px 5px 0;
        border-style: solid;
        border-color: transparent #fff transparent transparent;
    }
    .lma-line_bubble a {
        color: #1a73e8;
        text-decoration: underline;
        word-break: break-all;
    }
    .lma-line_time {
        font-size: 10px;
        color: #eef3f8;
        white-space: nowrap;
    }
    .lma-line_empty {
        color: #999;
        font-style: italic;
    }
    .lma-invline_test {
        margin-top: 14px;
        padding: 12px;
        background: #f8f9fa;
        border: 1px solid #e3e7ec;
        border-radius: 6px;
    }
    .lma-invline_test input[type="text"] {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 13px;
        margin-bottom: 8px;
    }
    .lma-invline_test_result {
        font-size: 12px;
        margin: 8px 0 0;
    }
    .lma-invline_target {
        margin-bottom: 18px;
        padding: 12px;
        background: #fbfbfc;
        border: 1px solid #e3e7ec;
        border-radius: 6px;
    }
    .lma-invline_target input[type="text"] {
        width: 100%;
        max-width: 360px;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 13px;
    }
    .lma-invline_warn {
        margin: 10px 0 0;
        padding: 10px 12px;
        background: #fff6e5;
        border-left: 4px solid #e8a33d;
        border-radius: 4px;
        font-size: 12px;
        color: #6b4b12;
    }
    .lma-invline_chip {
        display: inline-block;
        background: #fff;
        border: 1px solid #e0c99b;
        border-radius: 10px;
        padding: 1px 9px;
        margin: 2px 3px 2px 0;
    }
    .lma-invline_toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
        font-size: 14px;
    }
</style>
@endpush

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>設定</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        @if(session('success'))
            <div class="alert alert-success" style="background: #d4edda; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.update_invoice_line') }}" method="POST" id="invoiceLineForm">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'invoice_line'])
                </dt>
                <dd>
                    <div class="lma-invline_toggle">
                        {{-- 未チェックはPOSTに含まれないため、再表示時は「入力のやり直しかどうか」で参照先を切り替える --}}
                        <input type="checkbox" id="invoice_line_enabled" name="invoice_line_enabled" value="1"
                               @if(session()->hasOldInput() ? old('invoice_line_enabled') : $invoiceLineEnabled) checked @endif>
                        <label for="invoice_line_enabled">毎月1日に請求書のLINE通知を自動送信する</label>
                    </div>

                    <div class="lma-invline_target">
                        <label for="invoice_line_target_merchant_ids" style="display:block; margin-bottom:6px; font-weight:bold; font-size:13px;">
                            テスト対象の加盟店ID（段階公開用）
                        </label>
                        <input type="text" id="invoice_line_target_merchant_ids" name="invoice_line_target_merchant_ids"
                               value="{{ old('invoice_line_target_merchant_ids', $targetMerchantIds) }}"
                               placeholder="例: 1,5　（空欄なら全加盟店に送信）">
                        <p style="margin:6px 0 0; font-size:12px; color:#666;">
                            カンマ区切りで加盟店IDを指定すると、<strong>自動送信の対象がその加盟店だけに限定されます</strong>。<br>
                            本番公開の前に少数でテストするための設定です。テストが終わったら必ず空欄に戻してください。
                        </p>

                        @if($targetMerchants->isNotEmpty())
                            <div class="lma-invline_warn">
                                <p style="margin:0 0 4px; font-weight:bold;">⚠ 現在、送信対象が限定されています</p>
                                <p style="margin:0;">
                                    次の加盟店にのみ送信されます：
                                    @foreach($targetMerchants as $tm)
                                        <span class="lma-invline_chip">{{ $tm->id }}: {{ $tm->name }}</span>
                                    @endforeach
                                </p>
                                <p style="margin:6px 0 0;">これ以外の加盟店には請求書が届きません。</p>
                            </div>
                        @endif

                        @if($errors->has('invoice_line_target_merchant_ids'))
                            <p style="color: red;">{{ $errors->first('invoice_line_target_merchant_ids') }}</p>
                        @endif
                    </div>

                    <div class="lma-invline_layout">
                        <div class="lma-invline_editor">
                            <label for="invoice_line_message" style="display:block; margin-bottom:6px; font-weight:bold;">メッセージ本文</label>
                            <textarea id="invoice_line_message" name="invoice_line_message">{{ old('invoice_line_message', $invoiceLineMessage) }}</textarea>
                            <p class="lma-invline_count" id="invoiceLineCount"></p>

                            <p class="lma-invline_help">
                                下のボタンを押すとカーソル位置にプレースホルダを挿入できます。送信時に実際の値へ置き換わります。
                            </p>
                            <div class="lma-invline_ph">
                                @foreach($placeholders as $key => $label)
                                    <button type="button" data-ph="{{ $key }}" title="{{ $label }}">{{ $key }}</button>
                                @endforeach
                            </div>

                            @if($errors->has('invoice_line_message'))
                                <p style="color: red;">{{ $errors->first('invoice_line_message') }}</p>
                            @endif

                            <div class="lma-invline_test">
                                <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">テスト送信</p>
                                <p style="margin:0 0 8px; font-size:12px; color:#666;">
                                    入力中の本文をサンプル値で差し込み、指定したLINEユーザーへ送信します（登録済みユーザーのみ）。
                                </p>
                                <input type="text" id="testLineId" placeholder="送信先のLINEユーザーID（U から始まる文字列）">
                                <button type="button" class="btn btn-primary" id="testSendBtn">テスト送信する</button>
                                <p class="lma-invline_test_result" id="testSendResult"></p>
                            </div>
                        </div>

                        <div class="lma-invline_preview">
                            <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">プレビュー</p>
                            <div class="lma-line_phone">
                                <div class="lma-line_header">{{ config('app.name') }}</div>
                                <div class="lma-line_body">
                                    <div class="lma-line_row">
                                        <div class="lma-line_avatar">LINE</div>
                                        <div class="lma-line_col">
                                            <p class="lma-line_name">{{ config('app.name') }}</p>
                                            <div class="lma-line_bubble_wrap">
                                                <div class="lma-line_bubble" id="linePreviewBubble"></div>
                                                <span class="lma-line_time">9:00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="lma-invline_help" style="margin-top:8px;">
                                表示はイメージです。実際の改行位置は端末の幅によって変わります。
                            </p>
                        </div>
                    </div>
                </dd>
            </dl>

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var SAMPLE = @json($sampleValues);
    var MAX_LENGTH = 4000;

    var textarea = document.getElementById('invoice_line_message');
    var bubble = document.getElementById('linePreviewBubble');
    var counter = document.getElementById('invoiceLineCount');

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;');
    }

    // サンプル値を差し込む（サーバ側 InvoiceLineMessageService::renderSample と揃えること）
    function applySample(text) {
        Object.keys(SAMPLE).forEach(function (key) {
            text = text.split(key).join(SAMPLE[key]);
        });
        return text;
    }

    // URLらしき文字列をリンク表示にする
    function linkify(html) {
        return html.replace(/(https?:\/\/[^\s<]+)/g, '<a href="#" onclick="return false;">$1</a>');
    }

    function updatePreview() {
        var raw = textarea.value;
        var len = raw.length;

        counter.textContent = len + ' / ' + MAX_LENGTH + ' 文字';
        counter.classList.toggle('is-over', len > MAX_LENGTH);

        if (raw.trim() === '') {
            bubble.innerHTML = '<span class="lma-line_empty">本文を入力するとここに表示されます</span>';
            return;
        }
        bubble.innerHTML = linkify(escapeHtml(applySample(raw)));
    }

    textarea.addEventListener('input', updatePreview);
    updatePreview();

    // プレースホルダをカーソル位置に挿入
    document.querySelectorAll('.lma-invline_ph button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ph = btn.getAttribute('data-ph');
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.value = textarea.value.slice(0, start) + ph + textarea.value.slice(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + ph.length;
            updatePreview();
        });
    });

    // テスト送信
    var testBtn = document.getElementById('testSendBtn');
    var testResult = document.getElementById('testSendResult');

    testBtn.addEventListener('click', function () {
        var lineId = document.getElementById('testLineId').value.trim();
        if (lineId === '') {
            testResult.style.color = '#d64545';
            testResult.textContent = '送信先のLINEユーザーIDを入力してください。';
            return;
        }
        if (!window.confirm('入力中の本文を ' + lineId + ' へ実際に送信します。よろしいですか？')) {
            return;
        }

        testBtn.disabled = true;
        testResult.style.color = '#666';
        testResult.textContent = '送信中...';

        fetch('{{ route('admin.settings.test_invoice_line') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('#invoiceLineForm input[name="_token"]').value,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                line_id: lineId,
                invoice_line_message: textarea.value
            })
        })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) {
            var success = result.ok && result.json.success;
            testResult.style.color = success ? '#2f855a' : '#d64545';
            testResult.textContent = success
                ? 'テスト送信しました。LINEをご確認ください。'
                : (result.json.message || result.json.errors && Object.values(result.json.errors)[0][0] || '送信に失敗しました。');
        })
        .catch(function () {
            testResult.style.color = '#d64545';
            testResult.textContent = '送信に失敗しました。';
        })
        .finally(function () {
            testBtn.disabled = false;
        });
    });
});
</script>
@endsection
