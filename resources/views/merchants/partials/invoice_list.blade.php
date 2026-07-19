@if (!empty($monthlyInvoices))
    @foreach ($monthlyInvoices as $month => $invoice)
        <div class="lmf-invoice_block lmf-white_block" style="margin-bottom:20px;padding:15px;">
            <h2 style="font-size:16px;border-bottom:2px solid #333;padding-bottom:8px;margin-bottom:10px;">{{ $invoice['label'] }} 請求書</h2>
            <dl class="lmf-info_list">
                <dt>請求書番号</dt>
                <dd>{{ $invoice['invoice_number'] }}</dd>
                <dt>請求日</dt>
                <dd>{{ $invoice['invoice_date']->format('Y年n月j日') }}</dd>
                <dt>注文件数</dt>
                <dd>{{ $invoice['order_count'] }}件</dd>
            </dl>
            <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                <thead>
                    <tr>
                        <th style="border:1px solid #333;padding:6px;font-size:12px;background:#eee;">品目</th>
                        <th style="border:1px solid #333;padding:6px;font-size:12px;background:#eee;">数量</th>
                        <th style="border:1px solid #333;padding:6px;font-size:12px;background:#eee;">単価</th>
                        <th style="border:1px solid #333;padding:6px;font-size:12px;background:#eee;">金額</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice['products'] as $row)
                        <tr>
                            <td style="border:1px solid #333;padding:6px;font-size:12px;">{{ $row['name'] }}</td>
                            <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:center;">{{ $row['quantity'] }}</td>
                            <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:right;">￥{{ number_format($row['unit_price']) }}</td>
                            <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:right;">￥{{ number_format($row['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="border:1px solid #333;padding:6px;font-size:12px;">送料</td>
                        <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:center;">{{ $invoice['shipping_fee'] > 0 ? 1 : 0 }}</td>
                        <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:right;">￥{{ number_format($invoice['shipping_fee']) }}</td>
                        <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:right;">￥{{ number_format($invoice['shipping_fee']) }}</td>
                    </tr>
                </tbody>
            </table>
            <table style="width:60%;margin-left:auto;border-collapse:collapse;margin-top:10px;">
                <tr>
                    <td style="border:1px solid #333;padding:6px;font-size:12px;">消費税</td>
                    <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:right;">￥{{ number_format($invoice['tax']) }}</td>
                </tr>
                <tr>
                    <td style="border:1px solid #333;padding:6px;font-size:12px;"><strong>合計</strong></td>
                    <td style="border:1px solid #333;padding:6px;font-size:12px;text-align:right;"><strong>￥{{ number_format($invoice['grand_total']) }}</strong></td>
                </tr>
            </table>
        </div>
    @endforeach
@else
    <p class="lmf-no-invoice">請求書はありません</p>
@endif
