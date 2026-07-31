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
