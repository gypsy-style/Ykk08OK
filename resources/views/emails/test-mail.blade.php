<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SMTP動作確認テスト</title>
</head>
<body>
    <h2>テストメールです</h2>
    
    <div style="margin: 20px 0;">
        <p>このメールはSMTP動作確認のテストメールです。</p>
        <p>送信日時: {{ now()->format('Y年m月d日 H:i:s') }}</p>
    </div>
    
    <div style="margin: 20px 0; padding: 20px; background-color: #f8f9fa;">
        <p>このメールが受信できれば、SMTP設定は正常に動作しています。</p>
    </div>
</body>
</html> 