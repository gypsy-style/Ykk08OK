@if (!empty($monthlyInvoices))
    @foreach ($monthlyInvoices as $month => $invoice)
        <div class="lmf-invoice_block lmf-white_block" style="display:flex;justify-content:space-between;align-items:center;padding:18px 20px;margin-bottom:15px;border-radius:10px;">
            <div>
                <p style="font-size:17px;font-weight:bold;margin:0;">{{ $invoice['label'] }}</p>
                <p style="font-size:12px;color:#888;margin:4px 0 0;">注文{{ $invoice['order_count'] }}件　合計 ￥{{ number_format($invoice['grand_total']) }}</p>
            </div>
            <p style="margin:0;flex-shrink:0;">
                <a href="{{ route('merchants.invoice', ['month' => $month]) }}" style="display:inline-block;background:#4a5d78;color:#fff;padding:8px 18px;border-radius:16px;font-size:12px;font-weight:bold;text-decoration:none;">PDFを表示</a>
            </p>
        </div>
    @endforeach
@else
    <p class="lmf-no-invoice">請求書はありません</p>
@endif
