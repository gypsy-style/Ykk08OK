{{-- 振込督促の一斉送信。CSS/JSは admin.css を触らずに済ませるためこのファイル内に置く --}}
@php
    $remindedCount = collect($reminderTargets)->filter(function ($t) {
        return $t['reminded_at'] !== null;
    })->count();
@endphp

<style>
    .lma-reminder {
        width: 100%;
    }
    .lma-reminder_head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    .lma-reminder_title {
        font-size: 15px;
        font-weight: bold;
        margin: 0;
    }
    .lma-reminder_count {
        font-size: 13px;
        color: #666;
    }
    .lma-reminder_count strong {
        color: #d64545;
        font-size: 16px;
    }
    .lma-reminder_actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .lma-reminder_toggle {
        background: none;
        border: none;
        color: #1e6bd6;
        font-size: 13px;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
    }
    .lma-reminder_send {
        background: #4d6684;
        color: #fff;
        border: none;
        border-radius: 20px;
        padding: 8px 18px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_send:disabled {
        background: #c2cbd6;
        cursor: default;
    }
    .lma-reminder_editor {
        display: none;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #e3e7ec;
        gap: 24px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .lma-reminder_editor.is-open {
        display: flex;
    }
    .lma-reminder_col {
        flex: 1 1 420px;
        min-width: 320px;
    }
    .lma-reminder_col textarea {
        width: 100%;
        min-height: 260px;
        font-family: inherit;
        font-size: 14px;
        line-height: 1.7;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: vertical;
    }
    .lma-reminder_ph {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 10px 0 0;
    }
    .lma-reminder_ph button {
        border: 1px solid #cfd8e3;
        background: #fff;
        border-radius: 12px;
        padding: 3px 10px;
        font-size: 12px;
        cursor: pointer;
        color: #1e6bd6;
    }
    .lma-reminder_ph button:hover {
        background: #e6f0ff;
    }
    .lma-reminder_counter {
        font-size: 12px;
        color: #888;
        text-align: right;
        margin: 4px 0 0;
    }
    .lma-reminder_counter.is-over {
        color: #d64545;
        font-weight: bold;
    }
    .lma-reminder_save {
        margin-top: 10px;
        padding: 6px 16px;
        background: #4d6684;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_saved {
        font-size: 12px;
        margin-left: 8px;
    }
    .lma-reminder_preview {
        flex: 0 0 320px;
    }
    .lma-reminder_phone {
        border: 1px solid #d5d5d5;
        border-radius: 12px;
        overflow: hidden;
        background: #8cabd8;
    }
    .lma-reminder_phone_head {
        background: #202b33;
        color: #fff;
        font-size: 13px;
        padding: 10px 12px;
        text-align: center;
    }
    .lma-reminder_phone_body {
        padding: 14px 10px 20px;
        min-height: 300px;
        max-height: 460px;
        overflow-y: auto;
    }
    .lma-reminder_bubble {
        background: #fff;
        border-radius: 16px;
        padding: 10px 13px;
        font-size: 13px;
        line-height: 1.65;
        color: #16232b;
        white-space: pre-wrap;
        word-break: break-word;
        max-width: 88%;
    }
    .lma-reminder_empty {
        margin: 0;
        font-size: 13px;
        color: #666;
    }
    .lma-reminder_modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .lma-reminder_modal.is-open {
        display: flex;
    }
    .lma-reminder_dialog {
        background: #fff;
        border-radius: 8px;
        width: 100%;
        max-width: 720px;
        max-height: 86vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .lma-reminder_dialog_head {
        padding: 16px 20px;
        border-bottom: 1px solid #e3e7ec;
        font-size: 15px;
        font-weight: bold;
    }
    .lma-reminder_dialog_body {
        padding: 16px 20px;
        overflow-y: auto;
    }
    .lma-reminder_dialog_foot {
        padding: 14px 20px;
        border-top: 1px solid #e3e7ec;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .lma-reminder_warn {
        margin: 0 0 12px;
        padding: 10px 12px;
        background: #fff6e5;
        border-left: 4px solid #e8a33d;
        border-radius: 4px;
        font-size: 12px;
        color: #6b4b12;
    }
    .lma-reminder_targets {
        list-style: none;
        padding: 0;
        margin: 0 0 16px;
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e3e7ec;
        border-radius: 4px;
    }
    .lma-reminder_targets li {
        padding: 7px 12px;
        font-size: 13px;
        border-bottom: 1px solid #f0f2f5;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }
    .lma-reminder_targets li:last-child {
        border-bottom: none;
    }
    .lma-reminder_badge {
        color: #d64545;
        font-size: 12px;
        white-space: nowrap;
    }
    .lma-reminder_body_preview {
        background: #f8f9fa;
        border: 1px solid #e3e7ec;
        border-radius: 4px;
        padding: 12px;
        font-size: 13px;
        line-height: 1.7;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 200px;
        overflow-y: auto;
    }
    .lma-reminder_progress {
        margin: 12px 0 0;
        font-size: 13px;
        color: #444;
    }
    .lma-reminder_result {
        margin: 12px 0 0;
        font-size: 13px;
        line-height: 1.8;
    }
    .lma-reminder_cancel {
        background: #fff;
        border: 1px solid #c2cbd6;
        border-radius: 4px;
        padding: 7px 16px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_exec {
        background: #d64545;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 7px 18px;
        font-size: 13px;
        cursor: pointer;
    }
    .lma-reminder_exec:disabled {
        background: #d9a3a3;
        cursor: default;
    }
</style>

<div class="lma-content_block nobg lma-reminder">
    <div class="lma-reminder_head">
        <div>
            <p class="lma-reminder_title">振込督促の一斉送信</p>
            <p class="lma-reminder_count" style="margin:4px 0 0;">
                未振込 <strong>{{ count($reminderTargets) }}</strong>件 / {{ count($merchantSales) }}件
                @if ($remindedCount > 0)
                    （うち督促済 {{ $remindedCount }}件）
                @endif
            </p>
        </div>
        <div class="lma-reminder_actions">
            <button type="button" class="lma-reminder_toggle" id="js-reminder-toggle">文面を編集 ▼</button>
            @if (count($reminderTargets) > 0)
                <button type="button" class="lma-reminder_send" id="js-reminder-open">
                    @include('admin.sales.partials.btn_icon', ['icon' => 'send'])未振込の{{ count($reminderTargets) }}件に送信
                </button>
            @else
                <button type="button" class="lma-reminder_send" disabled>未振込の加盟店はありません</button>
            @endif
        </div>
    </div>

    <div class="lma-reminder_editor" id="js-reminder-editor">
        <div class="lma-reminder_col">
            <label for="js-reminder-message" style="display:block; margin-bottom:6px; font-weight:bold; font-size:13px;">本文</label>
            <textarea id="js-reminder-message">{{ $reminderMessage }}</textarea>
            <p class="lma-reminder_counter" id="js-reminder-counter"></p>
            <div class="lma-reminder_ph">
                @foreach ($reminderPlaceholders as $key => $label)
                    <button type="button" data-ph="{{ $key }}" title="{{ $label }}">{{ $key }}</button>
                @endforeach
            </div>
            <button type="button" class="lma-reminder_save" id="js-reminder-save">文面を保存</button>
            <span class="lma-reminder_saved" id="js-reminder-saved"></span>
        </div>
        <div class="lma-reminder_preview">
            <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">プレビュー</p>
            <div class="lma-reminder_phone">
                <div class="lma-reminder_phone_head">{{ config('app.name') }}</div>
                <div class="lma-reminder_phone_body">
                    <div class="lma-reminder_bubble" id="js-reminder-bubble"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Task 6: 送信確認モーダルの差し込み位置 --}}
<div class="lma-reminder_modal" id="js-reminder-modal">
    <div class="lma-reminder_dialog">
        <div class="lma-reminder_dialog_head">
            {{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}分の振込督促を送信します
        </div>
        <div class="lma-reminder_dialog_body">
            @if ($remindedCount > 0)
                <p class="lma-reminder_warn">
                    うち <strong>{{ $remindedCount }}件</strong> はすでに督促を送信済みです。もう一度送信されます。
                </p>
            @endif

            <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">送信先（{{ count($reminderTargets) }}件）</p>
            <ul class="lma-reminder_targets">
                @foreach ($reminderTargets as $t)
                    <li data-merchant="{{ $t['merchant_id'] }}">
                        <span>{{ $t['merchant_name'] }}</span>
                        @if ($t['reminded_at'])
                            <span class="lma-reminder_badge">督促済 {{ $t['reminded_at'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <p style="margin:0 0 6px; font-weight:bold; font-size:13px;">送信される文面（例: {{ $reminderTargets[0]['merchant_name'] ?? '' }}）</p>
            <div class="lma-reminder_body_preview">{{ $reminderPreview }}</div>

            <p class="lma-reminder_progress" id="js-reminder-progress" style="display:none;"></p>
            <div class="lma-reminder_result" id="js-reminder-result" style="display:none;"></div>
        </div>
        <div class="lma-reminder_dialog_foot">
            <button type="button" class="lma-reminder_cancel" id="js-reminder-cancel">キャンセル</button>
            <button type="button" class="lma-reminder_exec" id="js-reminder-exec">{{ count($reminderTargets) }}件に送信する</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var MAX_LENGTH = 4000;
    var SAMPLE = @json([
        '{merchant_name}' => 'サンプル商店',
        '{month_label}' => \Carbon\Carbon::parse($month . '-01')->format('Y年n月分'),
        '{month}' => $month,
        '{total}' => '135,000',
        '{payment_due_date}' => \Carbon\Carbon::parse($month . '-01')->addMonth()->day(15)->format('Y年n月j日'),
        '{invoice_url}' => 'https://liff.line.me/xxxxxxxxxx-yyyyyyyy',
    ]);

    var toggle = document.getElementById('js-reminder-toggle');
    var editor = document.getElementById('js-reminder-editor');
    var textarea = document.getElementById('js-reminder-message');
    var bubble = document.getElementById('js-reminder-bubble');
    var counter = document.getElementById('js-reminder-counter');
    var saveBtn = document.getElementById('js-reminder-save');
    var savedLabel = document.getElementById('js-reminder-saved');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;');
    }

    // サーバ側 PaymentReminderMessageService::renderSample と揃えること
    function applySample(text) {
        Object.keys(SAMPLE).forEach(function (key) {
            text = text.split(key).join(SAMPLE[key]);
        });
        return text;
    }

    function updatePreview() {
        var raw = textarea.value;
        counter.textContent = raw.length + ' / ' + MAX_LENGTH + ' 文字';
        counter.classList.toggle('is-over', raw.length > MAX_LENGTH);
        bubble.innerHTML = raw.trim() === ''
            ? '<span style="color:#999;">本文を入力するとここに表示されます</span>'
            : escapeHtml(applySample(raw));
    }

    toggle.addEventListener('click', function () {
        var open = editor.classList.toggle('is-open');
        toggle.textContent = open ? '文面を閉じる ▲' : '文面を編集 ▼';
        if (open) {
            updatePreview();
        }
    });

    textarea.addEventListener('input', updatePreview);

    document.querySelectorAll('.lma-reminder_ph button').forEach(function (btn) {
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

    saveBtn.addEventListener('click', function () {
        saveBtn.disabled = true;
        savedLabel.style.color = '#666';
        savedLabel.textContent = '保存中...';

        fetch('{{ route('admin.sales.payment_reminder_message') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ payment_reminder_message: textarea.value })
        })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) {
            var ok = result.ok && result.json.success;
            savedLabel.style.color = ok ? '#2f855a' : '#d64545';
            savedLabel.textContent = ok
                ? '保存しました'
                : (result.json.message || (result.json.errors && Object.values(result.json.errors)[0][0]) || '保存に失敗しました。');
        })
        .catch(function () {
            savedLabel.style.color = '#d64545';
            savedLabel.textContent = '保存に失敗しました。';
        })
        .finally(function () {
            saveBtn.disabled = false;
        });
    });

    // --- 確認モーダルと順次送信 ---
    var TARGETS = @json($reminderTargets);
    var SEND_URL = @json(route('admin.sales.payment_reminder', ['merchant' => '__ID__']));
    var MONTH = @json($month);

    var openBtn = document.getElementById('js-reminder-open');
    var modal = document.getElementById('js-reminder-modal');
    var cancelBtn = document.getElementById('js-reminder-cancel');
    var execBtn = document.getElementById('js-reminder-exec');
    var progress = document.getElementById('js-reminder-progress');
    var resultBox = document.getElementById('js-reminder-result');
    var sending = false;
    var finished = false;

    function closeModal() {
        // 送信中は閉じさせない
        if (sending) {
            return;
        }
        modal.classList.remove('is-open');
        // 一度でも送ったら「督促済」表示を反映するため読み直す
        if (finished) {
            location.reload();
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            modal.classList.add('is-open');
        });
    }

    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    function renderResult(stats) {
        var html = '<p style="margin:0 0 4px;"><strong>送信完了</strong>　成功 ' + stats.success
            + '件 / スキップ ' + stats.skipped + '件 / 失敗 ' + stats.failed + '件</p>';
        if (stats.notes.length > 0) {
            html += '<ul style="margin:6px 0 0; padding-left:18px; color:#666;">';
            stats.notes.forEach(function (note) {
                html += '<li>' + note + '</li>';
            });
            html += '</ul>';
        }
        resultBox.innerHTML = html;
        resultBox.style.display = '';
    }

    function sendAt(index, stats) {
        if (index >= TARGETS.length) {
            sending = false;
            finished = true;
            progress.textContent = '送信が完了しました。';
            cancelBtn.textContent = '閉じる';
            cancelBtn.disabled = false;
            renderResult(stats);
            return;
        }

        var target = TARGETS[index];
        progress.textContent = '送信中 ' + (index + 1) + ' / ' + TARGETS.length + '（' + target.merchant_name + '）';

        fetch(SEND_URL.replace('__ID__', target.merchant_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ month: MONTH })
        })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) {
            if (result.ok && result.json.success) {
                stats.success++;
            } else if (result.ok && result.json.skipped) {
                stats.skipped++;
                stats.notes.push(escapeHtml(target.merchant_name) + '：' + escapeHtml(result.json.message || 'スキップ'));
            } else {
                stats.failed++;
                stats.notes.push(escapeHtml(target.merchant_name) + '：' + escapeHtml(result.json.message || '送信に失敗しました'));
            }
        })
        .catch(function () {
            stats.failed++;
            stats.notes.push(escapeHtml(target.merchant_name) + '：通信に失敗しました');
        })
        .finally(function () {
            // 1件失敗しても止めずに次へ進む
            sendAt(index + 1, stats);
        });
    }

    execBtn.addEventListener('click', function () {
        if (sending || TARGETS.length === 0) {
            return;
        }
        sending = true;
        execBtn.disabled = true;
        cancelBtn.disabled = true;
        progress.style.display = '';
        resultBox.style.display = 'none';
        sendAt(0, { success: 0, skipped: 0, failed: 0, notes: [] });
    });
});
</script>
