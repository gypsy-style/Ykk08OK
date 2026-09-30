@extends('layouts.app')
@section('title', 'ユーザー登録')
@section('content')
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">ユーザー登録</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">
        {{-- 登録方法の説明 --}}
        <div class="lm-form_block lmf-white_block" id="registerGuide" style="margin-bottom: 20px;">
            <h2 style="font-size: 16px; margin: 0 0 12px; text-align: center;">ご利用までの流れ</h2>
            <ol style="margin: 0; padding: 0; list-style: none; font-size: 14px; line-height: 1.6;">
                <li style="display: flex; gap: 10px; margin-bottom: 12px;">
                    <span style="flex: 0 0 26px; height: 26px; border-radius: 50%; background: #06c755; color: #fff; font-weight: bold; text-align: center; line-height: 26px;">1</span>
                    <span><b>友だち追加</b><br><span style="font-size: 12px; color: #666;">LINE公式アカウントを友だち追加します。注文のお知らせなどはLINEでお届けします。</span></span>
                </li>
                <li style="display: flex; gap: 10px; margin-bottom: 12px;">
                    <span style="flex: 0 0 26px; height: 26px; border-radius: 50%; background: #06c755; color: #fff; font-weight: bold; text-align: center; line-height: 26px;">2</span>
                    <span><b>ユーザー登録</b>（この画面）<br><span style="font-size: 12px; color: #666;">お名前を入力して登録します。</span></span>
                </li>
                <li style="display: flex; gap: 10px; margin-bottom: 12px;">
                    <span style="flex: 0 0 26px; height: 26px; border-radius: 50%; background: #06c755; color: #fff; font-weight: bold; text-align: center; line-height: 26px;">3</span>
                    <span><b>サロン登録</b><br><span style="font-size: 12px; color: #666;">サロン名・住所・電話番号を登録します。ユーザー登録のあと、続けて表示されます。</span></span>
                </li>
                <li style="display: flex; gap: 10px;">
                    <span style="flex: 0 0 26px; height: 26px; border-radius: 50%; background: #06c755; color: #fff; font-weight: bold; text-align: center; line-height: 26px;">4</span>
                    <span><b>承認後、ご注文いただけます</b><br><span style="font-size: 12px; color: #666;">本部で内容を確認し、承認のご連絡をLINEでお送りします。承認後にメニューから注文できるようになります。</span></span>
                </li>
            </ol>
            <p style="font-size: 12px; color: #666; line-height: 1.6; margin: 14px 0 0; padding-top: 12px; border-top: 1px solid #eee;">
                ※ すでに登録済みのサロンに<b>スタッフとして追加</b>する場合も、まずユーザー登録をしてください。そのあとのサロン登録画面で、サロンオーナーのQRコードを読み込めます。
            </p>
        </div>

        {{-- 友だち追加していない人向けの案内（友だちでない場合だけ表示） --}}
        <div class="lm-form_block lmf-white_block" id="friendRequiredBlock" style="display: none; margin-bottom: 20px; text-align: center;">
            <p style="font-size: 14px; line-height: 1.7; margin: 0 0 12px;">
                ご登録の前に、<b>LINE公式アカウントの友だち追加</b>をお願いします。<br>
                <span style="font-size: 12px; color: #666;">（注文のお知らせや承認のご連絡をLINEでお送りするために必要です）</span>
            </p>
            <p class="lmf-btn_box btn_small" style="margin: 0 0 10px;"><a href="https://line.me/R/ti/p/{{ '@' }}797lemhx" id="addFriendButton">友だち追加する</a></p>
            <p style="font-size: 12px; color: #666; margin: 0;">追加したら、この画面に戻ってください（自動で登録できるようになります）</p>
        </div>

        <p id="statusChecking" style="text-align: center; font-size: 14px; color: #666; margin: 20px 0;">確認中です…</p>

        <form id="registerForm" style="display: none;">
                @csrf
                <div class="lm-form_block lmf-white_block">
					<dl class="lmf-form_box">
						
                    <dt>名前</dt>
                    <dd><input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required></dd>
                    <p class="lmf-btn_box btn_small"><input type="submit" value="ユーザー登録"></p>
				</div>

                <p id="errorMessages" style="display: none; color: #c00; text-align: center; font-size: 14px;"></p>
                <input type="hidden" name="access_token" id="access_token" class="form-control" value="{{ old('access_token') }}" required>
                <input type="hidden" name="agency_id" id="agency_id" value="{{ request('agency_id') }}">
                

            </form>
        </section>
    </main>
</div>
@endsection
@push('scripts')
<script>
    window.LIFF_ID = "{{ config('app.register_liff_id') }}";
    window.LIFF_ID_MERCHANT_REGISTER = "{{ config('app.register_merchant_liff_id') }}";
</script>
@vite(['resources/js/liff.js'])
<script>
// URLから agency_id を取り出す（LIFF経由で liff.state に入っている場合も対応）
function getAgencyIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    let agencyId = params.get('agency_id');
    if (!agencyId && params.get('liff.state')) {
        const state = params.get('liff.state');
        const query = state.includes('?') ? state.slice(state.indexOf('?') + 1) : state;
        agencyId = new URLSearchParams(query).get('agency_id');
    }
    return agencyId && /^\d+$/.test(agencyId) ? agencyId : '';
}

const OFFICIAL_ACCOUNT_URL = 'https://line.me/R/ti/p/{{ '@' }}797lemhx';
let isSubmitting = false; // 登録処理中は振り分けを行わない
let isRedirecting = false;

// フォームを表示（確認できなかった場合も登録はできるようにする）
function showForm() {
    document.getElementById('statusChecking').style.display = 'none';
    document.getElementById('registerForm').style.display = '';
}

// 登録状況に応じて振り分け
//  サロン登録済み → 公式LINE ／ ユーザー登録のみ → サロン登録 ／ 未登録 → この画面で登録
function routeByRegistrationStatus() {
    const csrf = document.querySelector('input[name="_token"]').value;
    return fetch('/ykk08ok/get-registration-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ access_token: liff.getAccessToken() }),
    })
    .then(function(res) { return res.ok ? res.json() : { status: 'none' }; })
    .then(function(data) {
        if (isSubmitting || isRedirecting) return;
        if (data.status === 'salon') {
            isRedirecting = true;
            window.location.href = OFFICIAL_ACCOUNT_URL;
        } else if (data.status === 'user') {
            isRedirecting = true;
            const agencyId = document.getElementById('agency_id').value;
            window.location.href = 'https://liff.line.me/' + window.LIFF_ID_MERCHANT_REGISTER + (agencyId ? '?agency_id=' + encodeURIComponent(agencyId) : '');
        } else {
            showForm();
        }
    });
}

// 友だち追加済みかを確認 → 未追加なら案内を出して登録ボタンを押せないようにする
// → 追加済みなら登録状況で振り分け
function checkFriendship() {
    if (isSubmitting || isRedirecting) return;
    if (!window.liff || !liff.ready) { showForm(); return; }
    liff.ready.then(function() {
        if (!liff.isLoggedIn()) return;
        return liff.getFriendship().then(function(data) {
            const isFriend = !!(data && data.friendFlag);
            const block = document.getElementById('friendRequiredBlock');
            const submit = document.querySelector("#registerForm input[type='submit']");
            if (block) block.style.display = isFriend ? 'none' : 'block';
            if (submit) submit.disabled = !isFriend;
            if (!isFriend) {
                showForm();
                return;
            }
            return routeByRegistrationStatus();
        }, function(err) {
            // 友だち確認ができない環境（公式アカウント未連携など）でも振り分けは行う
            console.error('getFriendship error', err);
            return routeByRegistrationStatus();
        });
    }).catch(function(err) {
        console.error('registration status error', err);
        showForm();
    });
}

document.addEventListener("DOMContentLoaded", function() {
    checkFriendship();
    // 友だち追加して戻ってきたら再確認
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') checkFriendship();
    });
    window.addEventListener('focus', checkFriendship);

    const agencyField = document.getElementById('agency_id');
    if (agencyField && !agencyField.value) {
        agencyField.value = getAgencyIdFromUrl();
    }

    // LIFFの初期化に時間がかかっても、フォームが出ないままにならないようにする
    setTimeout(function() { if (!isRedirecting) showForm(); }, 6000);

    document.getElementById("registerForm").addEventListener("submit", function(event) {
        event.preventDefault(); // ページリロードを防ぐ
        isSubmitting = true;

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
                // 代理店の招待URLから来た場合は、閉じずにそのままサロン登録へ（代理店を引き継ぐ）
                const agencyId = document.getElementById('agency_id').value;
                alert(agencyId ? '登録が完了しました。続けてサロン登録をお願いします。' : '登録が完了しました。');
                liff.sendMessages([
                    {
                        type: 'text',
                        text: '【会員登録済】'
                    }
                ]).catch(function(err) {
                    console.error('sendMessages error', err);
                }).finally(function() {
                    if (agencyId) {
                        window.location.href = 'https://liff.line.me/' + window.LIFF_ID_MERCHANT_REGISTER + '?agency_id=' + encodeURIComponent(agencyId);
                    } else {
                        liff.closeWindow();
                    }
                });
                return;
            } else {
                document.getElementById("errorMessages").innerText = data.error || "エラーが発生しました";
                document.getElementById("errorMessages").style.display = "block";
                isSubmitting = false;
            }
        })
        .catch(error => {
            document.getElementById("errorMessages").innerText = "通信エラーが発生しました";
            document.getElementById("errorMessages").style.display = "block";
            console.error("Error:", error);
            isSubmitting = false;
        });
    });
});
</script>
@endpush