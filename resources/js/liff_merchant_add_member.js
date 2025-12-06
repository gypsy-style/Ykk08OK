import liff from '@line/liff';

async function main() {
    try {
        // Bladeで埋め込まれた LIFF ID を取得
        const liffId = window.LIFF_ID_ADD_MEMBER;
        if (!liffId) {
            console.error("LIFF IDが設定されていません。");
            return;
        }

        // URLから `merchant_id` を取得
        const urlParams = new URLSearchParams(window.location.search);
        let merchantId = urlParams.get("merchant_id");

        // `liff.state` から `merchant_id` を取得する
        const liffState = urlParams.get("liff.state");
        if (liffState) {
            const stateParams = new URLSearchParams(liffState.replace("?", ""));
            if (stateParams.has("merchant_id")) {
                merchantId = stateParams.get("merchant_id");
            }
        }

        console.log("取得した merchant_id:", merchantId);

        // `merchant_id` がない場合、エラー
        if (!merchantId) {
            console.error("merchant_id が URL に含まれていません。");
            return;
        }

        // LIFF初期化
        await liff.init({ liffId });
        console.log("LIFF が正常に初期化されました。");

        // LIFFログインチェック
        if (!liff.isLoggedIn()) {
            console.log("ログインが必要です。リダイレクトします...");
            let redirectUri = `${window.location.origin}${window.location.pathname}?merchant_id=${merchantId}`;
            liff.login({ redirectUri });
            return;
        }

        // merchant_id をコンソールに出力してデバッグ
        console.log('Merchant ID:', merchantId);

        // アクセストークン取得
        const accessToken = liff.getAccessToken();
        if (!accessToken) {
            throw new Error('アクセストークンが取得できませんでした。');
        }
        console.log('アクセストークン:', accessToken);

        // merchant_id を hidden input にセット
        if (merchantId) {
            document.getElementById('merchant_id').value = merchantId;
        }

        // 送信ボタンを有効化
        document.getElementById('submitBtn').disabled = false;

        // 非同期フォーム送信
        document.getElementById('submitBtn').addEventListener('click', async (event) => {
            event.preventDefault(); // デフォルトのフォーム送信を防ぐ

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            const payload = {
                merchant_id: merchantId,
                access_token: accessToken
            };
            console.log(payload);

            const response = await fetch('/merchants/store_member', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            console.log(result);

            if (response.ok) {
                alert('メンバーが追加されました');
                liff.closeWindow();
            } else {
                console.error('エラー:', result);

                if (result.redirect_url) {
                    console.log("登録ページへリダイレクト:", result.redirect_url);
                    window.location.href = result.redirect_url; // 登録ページへ遷移
                } else {
                    document.getElementById('message').innerText = 'エラーが発生しました: ' + result.message;
                    document.getElementById('message').style.display = 'block';
                    document.getElementById('message').style.color = 'red';
                }
            }
        });

    } catch (error) {
        console.error('エラー:', error);
        alert('エラーが発生しました。コンソールを確認してください。');
    }
}

// メイン処理実行
main();