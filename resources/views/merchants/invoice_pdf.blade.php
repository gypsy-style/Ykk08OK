<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>請求書</title>
<style>
body {
  font-family: "Yu Gothic", sans-serif;
  background: #f5f5f5;
  padding: 20px;
  margin: 0;
}

.invoice {
  width: 100%;
  max-width: 760px;
  margin: auto;
  background: #fff;
  padding: 35px;
  color: #333;
  box-sizing: border-box;
}

h1 {
  text-align: center;
  letter-spacing: 8px;
  margin-bottom: 40px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 30px;
  margin-bottom: 40px;
}
.company {
  text-align: right;
}

.company-bottom {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 20px;
  margin-top: 10px;
}

.company-text {
  text-align: right;
}

.company img {
  width: 120px;
  height: auto;
}

.total {
  font-size: 24px;
  font-weight: bold;
  border-bottom: 2px solid #333;
  padding-bottom: 15px;
  margin: 40px 0 30px;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

th,
td {
  border: 1px solid #333;
  padding: 12px;
  text-align: center;
  font-size: 14px;
}

th {
  background: #eee;
}

.summary {
  width: 320px;
  margin-left: auto;
  margin-top: 30px;
}

.summary td {
  text-align: right;
}

.btn-area {
  text-align: center;
  margin-bottom: 20px;
}

button {
  padding: 12px 30px;
  background: #222;
  color: #fff;
  border: none;
  cursor: pointer;
  font-size: 16px;
  border-radius: 6px;
}

@media print {
  body {
    background: #fff;
    padding: 0;
  }

  .btn-area {
    display: none;
  }

  .invoice {
    box-shadow: none;
    max-width: 100%;
    padding: 20px;
  }
}
</style>
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
