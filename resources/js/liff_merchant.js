import liff from '@line/liff';

// LIFFの初期化とメイン処理
async function main() {
    try {
        // LIFF IDを定義（envなどから設定できるようにするのが望ましい）
        const liffId = window.LIFF_ID;
        const liffIdRegister = window.LIFF_ID_REGISTER;

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
        const userId = await fetchUserIdFromServer(accessToken);

        // ユーザーIDをフォームに設定
        if (userId) {
            console.log('ユーザーIDが取得されました:', userId);
            setUserIdToForm(userId);
        } else {
            console.warn('ユーザーIDが見つかりませんでした。');
            alert('ユーザーが未登録です。先にユーザー登録をしてください');
            // 登録画面へリダイレクト
            window.location.href = "https://liff.line.me/"+liffIdRegister;
            return false;

        }
    } catch (error) {
        console.error('エラーが発生しました:', error);
        alert('エラーが発生しました。コンソールを確認してください。');
    }
}

// サーバーにアクセストークンを送信してユーザーIDを取得
async function fetchUserIdFromServer(accessToken) {
    try {
        const response = await fetch('/ykk08ok/get-user-id', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ access_token: accessToken }),
        });

        if (!response.ok) {
            throw new Error(`サーバーリクエストエラー: ${response.status}`);
        }

        const data = await response.json();
        return data.user_id || null;
    } catch (error) {
        console.error('サーバーからのユーザーID取得中にエラーが発生しました:', error);
        throw error;
    }
}

// ユーザーIDをフォームのhiddenフィールドに設定
function setUserIdToForm(userId) {
    const userIdField = document.getElementById('user_id');
    if (userIdField) {
        userIdField.value = userId;
    } else {
        console.warn('フォーム内にユーザーIDフィールドが見つかりませんでした。');
    }
}

// メイン関数を呼び出す
main();