import liff from '@line/liff';

async function main() {
    try {
        // LIFFの初期化
        await liff.init({ liffId: window.LIFF_ID });

        // ユーザーがログインしていない場合はログインさせる
        if (!liff.isLoggedIn()) {
            liff.login();
        }

        // プロファイル情報を取得
        const accessToken = liff.getAccessToken();
        console.log('AccessToken:', accessToken);

        // Hidden input に値をセット
        const hiddenInput = document.getElementById('access_token');
        if (hiddenInput) {
            hiddenInput.value = accessToken;
            console.log('Hidden input value set:', accessToken);
        } else {
            console.error('Hidden input element with id "line_id" not found.');
        }

        const currentPath = window.location.pathname;
        console.log(currentPath);

        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : null;

        if (!csrfToken) {
            throw new Error('CSRF token is missing.');
        }
        // 注文データを取得
        const orderData = {
            access_token: accessToken,
        };
        console.log(orderData);

        // Laravelに注文データを送信
        const response = await fetch('/ykk08ok/api/merchant/member_list', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify(orderData),
        });
        console.log(response);

        if (response.ok) {
            const memberList = await response.json();
            console.log(memberList);
            const memberListContainer = document.getElementById('staff-list');
            memberListContainer.innerHTML = memberList.html;
            const merchantId = memberList.merchant_id;
            generateQRCode(merchantId);
            // renderOrderHistory(orderHistory);
        } else {
            throw new Error('Failed to send order data.');
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

        // テスト用
        const hiddenInput = document.getElementById('line_id');
        if (hiddenInput) {
            hiddenInput.value = '123456789abcdef';
            console.log('Hidden input value set:', '123456789abcdef');
        } else {
            console.error('Hidden input element with id "line_id" not found.');
        }
    }
}


main();