@extends('layouts.app')
@section('title', 'サロン登録')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">サロン登録</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">

            {{-- 既存サロンにスタッフとして追加する人向けの案内 --}}
            <div class="lm-form_block lmf-white_block" id="staffScanBlock" style="margin-bottom: 20px;">
                <p style="font-size: 14px; line-height: 1.7; margin: 0 0 12px;">
                    すでにサロン登録が完了していて、<b>スタッフとして追加する場合</b>は、サロンオーナーのQRコードを読み込んでください。<br>
                    <span style="font-size: 12px; color: #666;">（QRコードはマイページの「登録スタッフ一覧」の「スタッフを追加」ボタンで確認できます）</span>
                </p>
                <p class="lmf-btn_box btn_small" style="margin: 0;"><a href="#" id="scanStaffQrButton">QRコードを読み込む</a></p>
            </div>

            <form id="merchantForm">
                @csrf
                <input type="hidden" name="agency_id" value="{{ $agency_id }}">
                <div class="lm-form_block lmf-white_block">
                    <dl class="lmf-form_box">

                        <dt>サロン名</dt>
                        <dd><input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required></dd>
                        <dt>郵便番号 (前半3桁)</dt>
                        <dd><input type="text" name="postal_code1" id="postal_code1" class="form-control" maxlength="3" value="{{ old('postal_code1') }}" required></dd>
                        <dt>郵便番号 (後半4桁)</dt>
                        <dd><input type="text" name="postal_code2" id="postal_code2" class="form-control" maxlength="4" value="{{ old('postal_code2') }}" required></dd>
                        <dt>都道府県</dt>
                        <dd>
                            <select name="prefecture" id="prefecture" class="form-control" required>
                                <option value="">選択してください</option>
                                @foreach(config('prefectures') as $pref)
                                    <option value="{{ $pref }}" {{ old('prefecture') == $pref ? 'selected' : '' }}>{{ $pref }}</option>
                                @endforeach
                            </select>
                        </dd>
                        <dt>住所（市区町村以降）</dt>
                        <dd><input type="text" name="address_detail" id="address_detail" class="form-control" value="{{ old('address_detail') }}" required></dd>
                        <input type="hidden" name="address" id="address">
                        <dt>電話番号</dt>
                        <dd><input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}" required></dd>
                        {{-- キャンペーンコードは不要になったため非表示（既存の値は保持） --}}
                        <input type="hidden" name="campaign_code" id="campaign_code" value="{{ old('campaign_code') }}">
                        <dt>振込み口座名（任意）</dt>
                        <dd><textarea name="bank_account_name" id="bank_account_name" class="form-control" rows="4">{{ old('bank_account_name') }}</textarea></dd>
                        <p class="lmf-btn_box btn_small"><input type="submit" value="サロン登録"></p>
                </div>
                <input type="hidden" name="user_id" id="user_id" class="form-control" value="{{ old('user_id') }}" required>
                <input type="hidden" name="status" value="2">


            </form>
        </section>
    </main>
</div>
@endsection
@push('scripts')
<script>
    window.LIFF_ID_REGISTER = "{{ config('app.register_liff_id') }}";
    window.LIFF_ID = "{{ config('app.register_merchant_liff_id') }}";
</script>
@vite(['resources/js/liff_merchant.js'])
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // agency_id が空なら URL（liff.state 含む）から補完
        const agencyField = document.querySelector('#merchantForm input[name="agency_id"]');
        if (agencyField && !agencyField.value) {
            const params = new URLSearchParams(window.location.search);
            let agencyId = params.get('agency_id');
            if (!agencyId && params.get('liff.state')) {
                const state = params.get('liff.state');
                agencyId = new URLSearchParams(state.includes('?') ? state.slice(state.indexOf('?') + 1) : state).get('agency_id');
            }
            if (agencyId && /^\d+$/.test(agencyId)) {
                agencyField.value = agencyId;
            }
        }

        // すでにサロン登録済み（オーナー or スタッフ）の人は公式LINEへ
        if (window.liff && liff.ready) {
            liff.ready.then(function() {
                if (!liff.isLoggedIn()) return;
                return fetch('/ykk08ok/get-registration-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ access_token: liff.getAccessToken() }),
                })
                .then(function(res) { return res.ok ? res.json() : {}; })
                .then(function(data) {
                    if (data.status === 'salon') {
                        window.location.href = 'https://line.me/R/ti/p/{{ '@' }}797lemhx';
                    }
                });
            }).catch(function(err) { console.error('registration status error', err); });
        }

        // スタッフ追加用：QRリーダーを起動し、読み取ったスタッフ追加URLへ移動
        document.getElementById('scanStaffQrButton').addEventListener('click', async function(event) {
            event.preventDefault();
            try {
                if (window.liff && liff.isApiAvailable && liff.isApiAvailable('scanCodeV2')) {
                    const result = await liff.scanCodeV2();
                    const value = result && result.value ? result.value.trim() : '';
                    if (!value) return; // キャンセル時
                    if (/^https:\/\/(liff\.line\.me|line\.me|lin\.ee)\//.test(value)) {
                        window.location.href = value;
                    } else {
                        alert('スタッフ追加用のQRコードではありません。サロンオーナーのQRコードを読み込んでください。');
                    }
                } else {
                    // LIFFのQR読み取りが使えない環境では、LINEアプリのQRコードリーダーを開く
                    window.location.href = 'https://line.me/R/nv/QRCodeReader';
                }
            } catch (e) {
                console.error('scanCodeV2 error', e);
                window.location.href = 'https://line.me/R/nv/QRCodeReader';
            }
        });

        // 郵便番号から住所を自動入力
        function fetchAddress() {
            const code1 = document.getElementById('postal_code1').value;
            const code2 = document.getElementById('postal_code2').value;
            if (code1.length === 3 && code2.length === 4) {
                fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + code1 + code2)
                    .then(r => r.json())
                    .then(data => {
                        if (data.results && data.results[0]) {
                            const r = data.results[0];
                            const prefSelect = document.getElementById('prefecture');
                            for (let i = 0; i < prefSelect.options.length; i++) {
                                if (prefSelect.options[i].value === r.address1) {
                                    prefSelect.selectedIndex = i;
                                    break;
                                }
                            }
                            document.getElementById('address_detail').value = r.address2 + r.address3;
                        }
                    })
                    .catch(() => {});
            }
        }
        document.getElementById('postal_code1').addEventListener('input', fetchAddress);
        document.getElementById('postal_code2').addEventListener('input', fetchAddress);

        document.getElementById("merchantForm").addEventListener("submit", function(event) {
            event.preventDefault(); // ページリロードを防ぐ

            // 都道府県と住所を結合してaddressにセット
            const prefecture = document.getElementById('prefecture').value;
            const addressDetail = document.getElementById('address_detail').value;
            document.getElementById('address').value = prefecture + addressDetail;

            const formData = new FormData(this);
            const submitButton = document.querySelector("#merchantForm input[type='submit']");
            submitButton.disabled = true; // 二重送信防止

            fetch("{{ route('merchants.store') }}", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('サロン登録が完了しました');
                        liff.sendMessages([
                            {
                                type: 'text',
                                text: '【店舗登録済】'
                            }
                        ]).catch(function(err) {
                            console.error('sendMessages error', err);
                        }).finally(function() {
                            liff.closeWindow();
                        });
                        return;
                    } else {
                        document.getElementById("errorMessage").innerText = data.error || "エラーが発生しました";
                        document.getElementById("errorMessage").style.display = "block";
                    }
                    submitButton.disabled = false;
                })
                .catch(error => {
                    document.getElementById("errorMessage").innerText = "通信エラーが発生しました";
                    document.getElementById("errorMessage").style.display = "block";
                    console.error("Error:", error);
                    submitButton.disabled = false;
                });
        });
    });
</script>
@endpush