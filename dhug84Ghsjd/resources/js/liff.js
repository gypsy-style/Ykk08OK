import liff from '@line/liff';

async function main() {
    try {
        // LIFFの初期化
        await liff.init({ liffId: window.LIFF_ID });

        // ユーザーがログインしていない場合はログインさせる
        if (!liff.isLoggedIn()) {
            liff.login();
            return; // ログイン後に再度実行される想定
        }

        // アクセストークン取得（リトライ付き）
        const accessToken = await getAccessTokenWithRetry();
        console.log('AccessToken:', accessToken);

        // Hidden input に値をセット
        const hiddenInput = document.getElementById('access_token');
        if (hiddenInput) {
            hiddenInput.value = accessToken;
            console.log('Hidden input value set:', accessToken);
        } else {
            console.error('Hidden input element with id "access_token" not found.');
        }

        const currentPath = window.location.pathname;
        console.log(currentPath);

        if (currentPath === '/dhug84Ghsjd/order/history') {
            console.log('order history');
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : null;

            if (!csrfToken) {
                throw new Error('CSRF token is missing.');
            }
            // 注文データを取得
            const orderData = {
                accessToken: accessToken,
            };

            // Laravelに注文データを送信
            const response = await fetch('/dhug84Ghsjd/api/order/history', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify(orderData),
            });
            console.log(response);

            if (response.ok) {
                const orderHistory = await response.json();
                console.log(orderHistory);
                const orderContainer = document.getElementById('order-history');
                orderContainer.innerHTML = orderHistory.html;
                // renderOrderHistory(orderHistory);
            } else {
                throw new Error('Failed to send order data.');
            }
        } else {
            console.log('This is not the /order/history page. Skipping order data processing.');
        }

        // 送信前のガード: トークン未設定なら再取得を試みる
        const confirmButton = document.getElementById('confirm_button');
        if (confirmButton) {
            confirmButton.addEventListener('click', async function (e) {
                const tokenInput = document.getElementById('access_token');
                if (tokenInput && !tokenInput.value) {
                    const token = await getAccessTokenWithRetry();
                    if (token) {
                        tokenInput.value = token;
                    } else {
                        e.preventDefault();
                        alert('LINEへのログインまたはアクセストークン取得に失敗しました。しばらくしてからお試しください。');
                    }
                }
            });
        }

        // LaravelにPOSTリクエストを送信
        // await fetch('/api/save-line-id', {
        //     method: 'POST',
        //     headers: {
        //         'Content-Type': 'application/json',
        //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        //     },
        //     body: JSON.stringify({ line_id: lineId }),
        // });

        console.log('LINE ID has been sent to the server!');
    } catch (error) { 
        alert('LINEのアクセストークンが取得できませんでした。')
        console.error('Error with LIFF or server communication:', error);

        // テスト用フォールバック
        const hiddenInput = document.getElementById('access_token');
        if (hiddenInput) {
            hiddenInput.value = '';
            console.log('Hidden input left empty due to token error');
        } else {
            console.error('Hidden input element with id "access_token" not found.');
        }
    }
}

/**
 * 注文履歴をHTMLに描画
 * @param {Array} orders 注文履歴データ
 */
function renderOrderHistory(orders) {
    console.log(orders.data);
    const orderContainer = document.getElementById('order-history');
    if (!orderContainer) {
        console.error('Element with id "order-history" not found.');
        return;
    }

    if (orders.length === 0) {
        orderContainer.innerHTML = '<p>注文履歴がありません。</p>';
        return;
    }

    // Helper function to convert status to a label
    function getStatusLabel(status) {
        const statusLabels = {
            1: '代理店未処理',
            2: '代理店処理済み',
            3: '本部処理済み',
            4: '保留',
            5: '発送待ち',
            6: '発送済み'
        };
        return statusLabels[status] || '不明なステータス';
    }

    orderContainer.innerHTML = ''; // Clear existing content

    orders.data.forEach(order => {
        const orderStatusLabel = getStatusLabel(order.status); // Get the status label

        const orderBlock = `
            <div class="lm-order_block lmf-white_block">
                <h2 class="lmf-title_bar small gy center"><b class="label">注文番号：${order.id}</b></h2>
                <ul class="lmf_order_info_list">
                    <li><em class="label">注文日時 </em><span class="text">${new Date(order.created_at).toLocaleDateString()}</span></li>
                    <li><em class="label">注文合計金額</em><span class="text">${order.total_price}円</span></li>
                    <li><em class="label">ステータス</em><span class="text">${orderStatusLabel}</span></li>
                </ul>
                <p class="lmf-btn_box">
                    <a href="order_detail/${order.id}">注文詳細</a>
                </p>
            </div>
        `;

        orderContainer.innerHTML += orderBlock; // Append the order block to the container
    });
}

async function getAccessTokenWithRetry(maxRetries = 3, delayMs = 300) {
    let token = liff.getAccessToken();
    if (token) return token;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        await sleep(delayMs * attempt);
        token = liff.getAccessToken();
        if (token) return token;
    }
    return null;
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

main();