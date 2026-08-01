<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=800">
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
  <h1>請求書</h1>

  <div class="header">
    <div>
      <p>{{ $merchant->name }} 御中</p>
      <p>下記の通りご請求申し上げます。</p>
    </div>
  <div class="company">

  <p>請求日：{{ $invoiceDate->format('Y年n月j日') }}</p>
  <p>請求書番号：{{ $invoiceNumber }}</p>

  <div class="company-bottom">

    <div class="company-text">
      <p><strong>{{ $companyName }}</strong></p>

      <p>{!! nl2br(e($companyDetail)) !!}</p>
    </div>

    @if($companySeal)
      <img src="{{ asset('storage/' . $companySeal) }}" alt="">
    @endif

  </div>

</div>
  </div>

  <div class="total">
    ご請求金額：￥{{ number_format($monthGrandTotal) }}
  </div>

  <table>
    <thead>
      <tr>
        <th>品目</th>
        <th>数量</th>
        <th>単価</th>
        <th>金額</th>
      </tr>
    </thead>
    @foreach ($productAgg as $row)
    <tbody>
      <tr>
        <td>{{ $row['name'] }}</td>
        <td>{{ $row['quantity'] }}</td>
        <td>￥{{ number_format($row['unit_price']) }}</td>
        <td>￥{{ number_format($row['amount']) }}</td>
      </tr>
    </tbody>
    @endforeach
    <tbody>
      <tr>
        <td>送料</td>
        <td>{{ $monthShippingFee > 0 ? 1 : 0 }}</td>
        <td>￥{{ number_format($monthShippingFee) }}</td>
        <td>￥{{ number_format($monthShippingFee) }}</td>
      </tr>
    </tbody>
  </table>

  <table class="summary">
    <tr>
      <td>消費税（10%）</td>
      <td>￥{{ number_format($monthTaxAmount) }}</td>
    </tr>
    <tr>
      <td><strong>合計</strong></td>
      <td><strong>￥{{ number_format($monthGrandTotal) }}</strong></td>
    </tr>
  </table>

  <p style="margin-top:40px;">
    {!! nl2br(e($companyBankInfo)) !!}
  </p>
</div>

</body>
</html>
