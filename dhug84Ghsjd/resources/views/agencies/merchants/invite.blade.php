@extends('agencies.layouts.app')

@section('title', '管理画面 [加盟店一覧]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>加盟店一覧</h2>
        </div>
    </div>
    <div class="lma-content_block staff nobg">
        <ul class="lma-user_list">
            <li>
                <div class="lma-user_box">
                    <div class="user_info">
                        <p>以下のURLをコピーするかQRコードを利用してください。</p>
                        <input type="hidden" id="invite-url" value="{{ $inviteUrl }}" readonly>
                        <div class="lma-btn_box">
                            <button onclick="copyToClipboard()">招待リンクをコピー</button>
                        </div>

                    </div>

                    <!-- QRコードの表示 -->


                    <div id="qrcode"></div>
                </div>
            </li>
        </ul>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>


<script>
    // URLをコピーする関数
    function copyToClipboard() {
        const urlField = document.getElementById('invite-url');

        // 新しい Clipboard API を使用
        navigator.clipboard.writeText(urlField.value)
            .then(() => {
                alert('URLをコピーしました: ' + urlField.value);
            })
            .catch(err => {
                console.error('クリップボードへのコピーに失敗しました:', err);
            });
    }

    // QRコードを生成する
    document.addEventListener('DOMContentLoaded', function() {
        const qrcodeElement = document.getElementById('qrcode');
        const inviteUrl = document.getElementById('invite-url').value;
        // const testUrl = 'https://example.com/test?param=value';

        // エンコード処理を削除し、元のURLをそのまま渡す
        new QRCode(qrcodeElement, inviteUrl);
    });
</script>
@endpush