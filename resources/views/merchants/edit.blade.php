@extends('layouts.app')
@section('title', 'サロン情報編集')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">サロン情報編集</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content" id="form-area">

        <form id="merchantForm" method="POST" action="{{ route('merchants.update', $merchant->id) }}">
                @csrf
                @method('PUT')
                <div class="lm-form_block lmf-white_block">
					<dl class="lmf-form_box">
						
                    <dt>サロン名</dt>
                    <dd><input type="text" name="name" id="name" class="form-control" value="{{ old('name', $merchant->name) }}" required></dd>

                    <dt>キャンペーンコード（任意）</dt>
                    <dd><input type="text" name="campaign_code" id="campaign_code" class="form-control" value="{{ old('campaign_code', $merchant->campaign_code) }}"></dd>

                    <dt>郵便番号 (前半3桁)</dt>
                    <dd><input type="text" name="postal_code1" id="postal_code1" class="form-control" maxlength="3" value="{{ old('postal_code1', $merchant->postal_code1) }}" required></dd>
                    
                    <dt>郵便番号 (後半4桁)</dt>
                    <dd><input type="text" name="postal_code2" id="postal_code2" class="form-control" maxlength="4" value="{{ old('postal_code2', $merchant->postal_code2) }}" required></dd>
                    
                    <dt>住所</dt>
                    <dd><input type="text" name="address" id="address" class="form-control" value="{{ old('address', $merchant->address) }}" required></dd>
                    
                    <dt>電話番号</dt>
                    <dd><input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $merchant->phone) }}" required></dd>

                    <p class="lmf-btn_box btn_small"><input type="submit" value="サロン編集"></p>
                    
				</div>
                <input type="hidden" name="status" value="2">
                
            </form>
        </section>
    </main>
</div>
@endsection
@push('scripts')

<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("merchantForm").addEventListener("submit", function(event) {
            event.preventDefault(); // ページリロードを防ぐ

            const form = this;
            const formData = new FormData(form);
            const submitButton = form.querySelector("input[type='submit']");
            submitButton.disabled = true; // 二重送信防止

            fetch("{{ route('merchants.update', $merchant->id) }}", {
                method: "POST", // Laravel の `PUT` は `POST` に `X-HTTP-Method-Override` を追加
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                    "X-HTTP-Method-Override": "PUT",
                    "Accept": "application/json"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitButton.disabled = false;
                if (data.success) {
                    alert('サロンが更新されました');
                    // マイページへリダイレクト
                    const redirectUrl = "https://liff.line.me/{{ config('app.merchant_information_liff_id') }}";
                    window.location.href = redirectUrl;
                } else {
                    document.getElementById("errorMessage").innerText = data.error || "エラーが発生しました";
                    document.getElementById("errorMessage").style.display = "block";
                }
            })
            .catch(error => {
                submitButton.disabled = false;
                document.getElementById("errorMessage").innerText = "通信エラーが発生しました";
                document.getElementById("errorMessage").style.display = "block";
                console.error("Error:", error);
            });
        });
    });
</script>
@endpush