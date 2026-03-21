import liff from '@line/liff';

async function main() {
    try {
        // LIFFの初期化
        await liff.init({ liffId: window.LIFF_ID });

        // ユーザーがログインしていない場合はログインさせる
        if (!liff.isLoggedIn()) {
            liff.login();
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
        alert('LINE IDが取得できませんでしたよ。')
        console.error('Error with LIFF or server communication:', error);

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


main();