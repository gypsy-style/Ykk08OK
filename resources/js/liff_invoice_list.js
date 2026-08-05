import liff from '@line/liff';

async function main() {
    try {
        let accessToken;
        if (window.LIFF_MOCK) {
            // ローカル確認用：LIFF認証をスキップ
            accessToken = 'dummy_local';
        } else {
            // LIFFの初期化
            await liff.init({ liffId: window.LIFF_ID });

            // ユーザーがログインしていない場合はログインさせる
            if (!liff.isLoggedIn()) {
                liff.login();
                return;
            }

            accessToken = liff.getAccessToken();
            if (!accessToken) {
                throw new Error('アクセストークンが取得できませんでした。');
            }
        }

        const hiddenInput = document.getElementById('access_token');
        if (hiddenInput) {
            hiddenInput.value = accessToken;
        }

        // ローカル（モック時）は /ykk08ok プレフィックスなしで動作する
        const apiBase = window.LIFF_MOCK ? '' : '/ykk08ok';
        const response = await fetch(`${apiBase}/api/merchant/invoice_list`, {
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

        // 請求書ページは外部ブラウザ（Safari / Chrome）で開く。
        // LIFFブラウザは WKWebView で window.print() が動かず、PDF保存ができないため。
        invoiceListContainer.addEventListener('click', function (e) {
            const link = e.target.closest('.js-open-invoice');
            if (!link) {
                return;
            }
            if (window.LIFF_MOCK || !liff.isInClient()) {
                return; // 既に外部ブラウザなら通常遷移
            }
            e.preventDefault();
            liff.openWindow({ url: link.href, external: true });
        });
    } catch (error) {
        console.error('エラーが発生しました:', error);
        const invoiceListContainer = document.getElementById('invoice-list');
        if (invoiceListContainer) {
            invoiceListContainer.innerHTML = '<p>請求書の取得に失敗しました。</p>';
        }
    }
}

main();
