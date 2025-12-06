@extends('layouts.app')
@section('title', 'ユーザー登録')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">ユーザー登録</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">
        <form id="registerForm">
                @csrf
                <div class="lm-form_block lmf-white_block">
					<dl class="lmf-form_box">
						
                    <dt>名前</dt>
                    <dd><input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required></dd>
                    <p class="lmf-btn_box btn_small"><input type="submit" value="ユーザー登録"></p>
				</div>

                <input type="hidden" name="access_token" id="access_token" class="form-control" value="{{ old('access_token') }}" required>
                

            </form>
        </section>
    </main>
</div>
@endsection
@push('scripts')
<script>
    window.LIFF_ID = "{{ config('app.register_liff_id') }}";
</script>
@vite(['resources/js/liff.js'])
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("registerForm").addEventListener("submit", function(event) {
        event.preventDefault(); // ページリロードを防ぐ

        const formData = new FormData(this);

        fetch("{{ route('register.store') }}", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('登録が完了しました。');
                liff.closeWindow();
                // document.getElementById("successMessage").innerText = data.message;
                // document.getElementById("successMessage").style.display = "block";
                // document.getElementById("errorMessages").style.display = "none";
                // document.getElementById("registerForm").reset();

                console.log("Richmenu result:", data.richmenu_result);
            } else {
                document.getElementById("errorMessages").innerText = data.error || "エラーが発生しました";
                document.getElementById("errorMessages").style.display = "block";
            }
        })
        .catch(error => {
            document.getElementById("errorMessages").innerText = "通信エラーが発生しました";
            document.getElementById("errorMessages").style.display = "block";
            console.error("Error:", error);
        });
    });
});
</script>
@endpush