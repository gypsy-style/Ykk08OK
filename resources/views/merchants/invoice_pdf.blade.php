<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=800">
<title>請求書</title>
@include('partials.invoice_pdf_style')
</head>
<body>

<div class="btn-area">
  <button type="button" id="save-pdf-btn">PDF保存</button>
  <p class="btn-note">ボタンを押すと印刷画面が開きます。iPhoneはプレビューを広げてから、共有ボタンで「ファイルに保存」を選んでください。</p>
</div>

<div class="invoice">
@include('partials.invoice_pdf')
</div>

<script>
(function () {
    var originalTitle = document.title;

    function restoreTitle() {
        document.title = originalTitle;
        window.removeEventListener('afterprint', restoreTitle);
    }

    document.getElementById('save-pdf-btn').addEventListener('click', function () {
        // 印刷ダイアログの既定ファイル名になる
        document.title = @json($pdfFilename);
        window.addEventListener('afterprint', restoreTitle);
        window.print();
    });
})();
</script>

</body>
</html>
