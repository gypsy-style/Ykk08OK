<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=800">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>請求書</title>
@include('partials.invoice_pdf_style')
</head>
<body>

<div class="btn-area" style="display:none;">
  <button type="button" id="save-pdf-btn">PDF保存</button>
</div>

<div id="invoice" class="invoice">
  <p>読み込み中...</p>
</div>

<script>
    window.LIFF_ID = "{{ config('app.merchant_information_liff_id') }}";
    window.LIFF_MOCK = {{ config('app.liff_mock') ? 'true' : 'false' }};
    window.INVOICE_MONTH = "{{ $month }}";
</script>
@vite(['resources/js/liff_invoice_pdf.js'])

</body>
</html>
