import liff from '@line/liff';

async function main() {
    try {
        // LIFFの初期化
        await liff.init({ liffId: window.LIFF_ID });

        // ユーザーがログインしていない場合はログインさせる
        if (!liff.isLoggedIn()) {
            liff.login();
            return;
        }

        const accessToken = liff.getAccessToken();
        if (!accessToken) {
            throw new Error('アクセストークンが取得できませんでした。');
        }

        const hiddenInput = document.getElementById('access_token');
        if (hiddenInput) {
            hiddenInput.value = accessToken;
        }

        const response = await fetch('/ykk08ok/api/merchant/invoice_list', {
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

        const invoiceList = await response.json();
        const invoiceListContainer = document.getElementById('invoice-list');
        invoiceListContainer.innerHTML = invoiceList.html;
    } catch (error) {
        console.error('エラーが発生しました:', error);
        const invoiceListContainer = document.getElementById('invoice-list');
        if (invoiceListContainer) {
            invoiceListContainer.innerHTML = '<p>請求書の取得に失敗しました。</p>';
        }
    }
}

main();
