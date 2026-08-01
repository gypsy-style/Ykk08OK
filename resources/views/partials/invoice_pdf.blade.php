@php
    $paymentDueDate = $invoiceDate->copy()->day(15);
    $itemsSubtotal = $monthSubtotal + $monthShippingFee;
@endphp

<p class="inv-title"><span>請求書</span></p>

<div class="inv-head">
  <div class="inv-to">{{ $merchant->name }} 様</div>
  <div class="inv-meta">
    <p>請求日：{{ $invoiceDate->format('Y年n月j日') }}</p>
    <p>請求書番号：{{ $invoiceNumber }}</p>
  </div>
</div>

<div class="inv-amount">
  <div class="inv-amount_main">
    <p class="inv-amount_label">ご請求金額</p>
    <p class="inv-amount_value">￥{{ number_format($monthGrandTotal) }}</p>
  </div>
  <div class="inv-amount_due">
    <p>支払期限</p>
    <strong>{{ $paymentDueDate->format('Y年n月j日') }}</strong>
  </div>
</div>

<div class="inv-company">
  <div>
    <p class="inv-company_name">{{ $companyName }}</p>
    <p>{!! nl2br(e($companyDetail)) !!}</p>
  </div>
  @if($companySeal)
    <img src="{{ asset('storage/' . $companySeal) }}" alt="">
  @endif
</div>

<p class="inv-section">ご請求明細</p>

<table class="inv-items">
  <thead>
    <tr>
      <th>品目</th>
      <th>数量</th>
      <th>単価</th>
      <th>金額</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($productAgg as $row)
    <tr>
      <td>{{ $row['name'] }}</td>
      <td>{{ $row['quantity'] }}</td>
      <td>￥{{ number_format($row['unit_price']) }}</td>
      <td>￥{{ number_format($row['amount']) }}</td>
    </tr>
    @endforeach
    <tr>
      <td>送料</td>
      <td>{{ $monthShippingFee > 0 ? 1 : 0 }}</td>
      <td>￥{{ number_format($monthShippingFee) }}</td>
      <td>￥{{ number_format($monthShippingFee) }}</td>
    </tr>
  </tbody>
</table>

<table class="inv-summary">
  <tr>
    <td>小計</td>
    <td>￥{{ number_format($itemsSubtotal) }}</td>
  </tr>
  <tr>
    <td>消費税（10%）</td>
    <td>￥{{ number_format($monthTaxAmount) }}</td>
  </tr>
  <tr class="is-total">
    <td>合計</td>
    <td>￥{{ number_format($monthGrandTotal) }}</td>
  </tr>
</table>

<div class="inv-foot">
  <div class="inv-foot_bank">
    <p class="inv-section">お振込先</p>
    <p>{!! nl2br(e($companyBankInfo)) !!}</p>
  </div>
  @if($companyPaymentNote)
  <div class="inv-foot_note">
    <p class="inv-foot_note_title">お支払いについて</p>
    <p>{!! nl2br(e($companyPaymentNote)) !!}</p>
  </div>
  @endif
</div>

<p class="inv-thanks">毎度ありがとうございます。今後ともよろしくお願い申し上げます。</p>
