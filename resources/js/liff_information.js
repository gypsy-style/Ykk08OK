import liff from '@line/liff';

// LIFFの初期化とメイン処理
async function main() {
    try {
        // LIFF IDを定義（envなどから設定できるようにするのが望ましい）
        const liffId = window.LIFF_ID_MERCHANT_INFORMATION;

        if (!liffId) {
            throw new Error('LIFF IDが設定されていません。');
        }

        // LIFFの初期化
        await liff.init({ liffId });
        console.log('LIFFが正常に初期化されました。');

        // ユーザーがログインしていない場合はログイン処理を開始
        if (!liff.isLoggedIn()) {
            console.log('ログインが必要です。リダイレクトします...');
            liff.login();
            return; // ログイン後に処理が再開される
        }

        // ユーザーのアクセストークンを取得
        const accessToken = liff.getAccessToken();
        if (!accessToken) {
            throw new Error('アクセストークンが取得できませんでした。');
        }
        console.log('アクセストークン:', accessToken);

        // サーバーにPOSTリクエストを送信してユーザーIDを取得
        const merchantData = await fetchMerchantInformation(accessToken);

        if (merchantData) {
            console.log('取得した店舗情報:', merchantData);
            updateMerchantInformation(merchantData);
        } else {
            console.warn('店舗情報が見つかりませんでした。');
            alert('ユーザーが未登録です。先にユーザー登録をしてください');
            window.location.href = "https://liff.line.me/" + liffId;
        }
    } catch (error) {
        console.error('エラーが発生しました:', error);
        alert('エラーが発生しました。コンソールを確認してください。');
    }
}

// サーバーにアクセストークンを送信してユーザーIDを取得
async function fetchMerchantInformation(accessToken) {
    console.log(accessToken);
    try {
        const response = await fetch('/ykk08ok/get-merchant-information', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ access_token: accessToken }),
        });
        console.log(response);

        if (!response.ok) {
            throw new Error(`サーバーリクエストエラー: ${response.status}`);
        }

        const data = await response.json();
        console.log(data);
        return data || null;
    } catch (error) {
        console.error('サーバーからのユーザーID取得中にエラーが発生しました:', error);
        throw error;
    }
}


function updateMerchantInformation(data) {
    document.getElementById('agency_name').textContent = data.agency_name || 'N/A';
    document.getElementById('name').textContent = data.name || 'N/A';
    document.getElementById('merchant_code').textContent = data.merchant_code || 'N/A';

    document.getElementById('postal_code').textContent = data.postal_code || 'N/A';

    document.getElementById('address').textContent = data.address || 'N/A';
    document.getElementById('phone').textContent = data.phone || 'N/A';

    // 編集画面のURLを生成
    const merchantId = data.merchant_id;
    window.EDIT_URL = window.EDIT_URL.replace(':id', merchantId);
    $('#edit_link a').attr('href',window.EDIT_URL);

    // ログイン中のユーザーと店舗のオーナーが同一かチェック
    const userId = data.user_id;
    const merchantUserId = data.merchant_user_id;
    if(userId == merchantUserId) {
         // 「登録情報を修正する」ボタンを表示
         document.querySelector('.lmf-btn_box.btn_dgy.btn_small').style.display = 'block';
         // 「登録スタッフ一覧」ボタンを表示
         document.querySelector('.lmf-btn_box.member_list').style.display = 'block';
    }
}


// メイン関数を呼び出す
main();