<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>新しい注文が登録されました</title>
</head>
<body>
    <h2>新しい注文が登録されました</h2>

    <div style="margin: 20px 0;">
        <p>
            <a href="{{ route('agencies.orders.show', $order->id) }}" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 5px;">
                注文詳細を確認する
            </a>
        </p>
    </div>
    
    <div style="margin: 20px 0; padding: 20px; background-color: #f8f9fa;">
        <p>このメールは自動送信されています。</p>
    </div>
</body>
</html> 