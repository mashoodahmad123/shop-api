<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Order #{{ $order->id }} confirmed</title>
</head>
<body style="margin:0;padding:24px;background:#F6F7F9;font-family:Arial,sans-serif;color:#101828;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #E4E7EC;border-radius:8px;">
    <tr>
        <td style="padding:24px;">
            <h1 style="margin:0 0 8px;font-size:20px;">Thank you for your order, {{ $customer->name }}!</h1>
            <p style="margin:0 0 20px;color:#5B6474;">Order #{{ $order->id }} placed on {{ $order->created_at->format('d M Y, h:i A') }} &middot; Status: {{ ucfirst($order->status->value) }}</p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr>
                        <th align="left" style="padding:8px;border-bottom:1px solid #E4E7EC;">Product</th>
                        <th align="right" style="padding:8px;border-bottom:1px solid #E4E7EC;">Price</th>
                        <th align="right" style="padding:8px;border-bottom:1px solid #E4E7EC;">Qty</th>
                        <th align="right" style="padding:8px;border-bottom:1px solid #E4E7EC;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid #EEF0F3;">{{ $item->product_name }} <span style="color:#5B6474;">({{ $item->product_sku }})</span></td>
                            <td align="right" style="padding:8px;border-bottom:1px solid #EEF0F3;">&#8377;{{ number_format($item->unit_price, 2) }}</td>
                            <td align="right" style="padding:8px;border-bottom:1px solid #EEF0F3;">{{ $item->quantity }}</td>
                            <td align="right" style="padding:8px;border-bottom:1px solid #EEF0F3;">&#8377;{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" align="right" style="padding:12px 8px;font-weight:bold;">Order total</td>
                        <td align="right" style="padding:12px 8px;font-weight:bold;">&#8377;{{ number_format($order->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            <p style="margin:20px 0 0;color:#5B6474;">We will let you know when your order ships.</p>
        </td>
    </tr>
</table>
</body>
</html>
