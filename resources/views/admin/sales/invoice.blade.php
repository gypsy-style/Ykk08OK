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
</div>

<script>
(function () {
  var originalTitle = document.title;
  var pdfFilename = 'invoice_{{ $merchant->id }}_{{ $invoiceDate->format('Ymd') }}';

  function restoreTitle() {
    document.title = originalTitle;
    window.removeEventListener('afterprint', restoreTitle);
  }

  document.getElementById('save-pdf-btn').addEventListener('click', function () {
    document.title = pdfFilename;
    window.addEventListener('afterprint', restoreTitle);
    window.print();
  });
})();
</script>

<div id="invoice" class="invoice">
  @include('partials.invoice_pdf')
</div>

</body>
</html>
