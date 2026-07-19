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

        // ローカル（モック時）は /ykk08ok プレフィックスなしで動作する
        const apiBase = window.LIFF_MOCK ? '' : '/ykk08ok';
        const response = await fetch(`${apiBase}/api/merchant/invoice`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ access_token: accessToken, month: window.INVOICE_MONTH }),
        });

        if (!response.ok) {
            throw new Error(`サーバーリクエストエラー: ${response.status}`);
        }

        const invoice = await response.json();
        document.getElementById('invoice').innerHTML = invoice.html;
        setupPdfButton(invoice.pdf_filename);
    } catch (error) {
        console.error('エラーが発生しました:', error);
        const container = document.getElementById('invoice');
        if (container) {
            container.innerHTML = '<p>請求書の取得に失敗しました。</p>';
        }
    }
}

function setupPdfButton(pdfFilename) {
    const btnArea = document.querySelector('.btn-area');
    if (btnArea) {
        btnArea.style.display = 'block';
    }

    const originalTitle = document.title;

    function restoreTitle() {
        document.title = originalTitle;
        window.removeEventListener('afterprint', restoreTitle);
    }

    document.getElementById('save-pdf-btn').addEventListener('click', function () {
        document.title = pdfFilename;
        window.addEventListener('afterprint', restoreTitle);
        window.print();
    });
}

main();
