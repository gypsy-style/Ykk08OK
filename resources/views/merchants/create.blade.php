@extends('layouts.app')
@section('title', 'サロン登録')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">サロン登録</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">

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
                                @foreach(['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'] as $pref)
                                    <option value="{{ $pref }}" {{ old('prefecture') == $pref ? 'selected' : '' }}>{{ $pref }}</option>
                                @endforeach
                            </select>
                        </dd>
                        <dt>住所（市区町村以降）</dt>
                        <dd><input type="text" name="address_detail" id="address_detail" class="form-control" value="{{ old('address_detail') }}" required></dd>
                        <input type="hidden" name="address" id="address">
                        <dt>電話番号</dt>
                        <dd><input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}" required></dd>
                        <dt>キャンペーンコード（任意）</dt>
                        <dd><input type="text" name="campaign_code" id="campaign_code" class="form-control" value="{{ old('campaign_code') }}"></dd>
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