@php($a = $order->shipping_address ?? [])
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Your Kattie order is confirmed</title>
</head>
<body style="margin:0; padding:0; background-color:#fffaf3; -webkit-font-smoothing:antialiased;">
<span style="display:none; max-height:0; overflow:hidden; opacity:0;">Thank you — we've received order {{ $order->number }} and we're preparing your artwork.</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fffaf3;">
    <tr>
        <td align="center" style="padding:28px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%;">

                {{-- Header --}}
                <tr>
                    <td align="center" style="padding:8px 0 24px;">
                        <div style="font-family:Georgia,'Times New Roman',serif; font-size:28px; font-weight:700; color:#302c2a; letter-spacing:.5px;">Kattie<span style="color:#fc5997;">.</span>uk</div>
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#a79a90; margin-top:4px;">Little faces. Big love</div>
                    </td>
                </tr>

                {{-- Main card --}}
                <tr>
                    <td style="background-color:#ffffff; border-radius:24px; padding:36px 32px;">
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:11px; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:#fc5997;">Order {{ $order->number }}</div>
                        <h1 style="margin:10px 0 0; font-family:Georgia,'Times New Roman',serif; font-size:30px; line-height:1.2; color:#302c2a; font-weight:700;">Thank you — your order is confirmed</h1>
                        <p style="margin:16px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#6f6862;">We've received your order and we're preparing your personalised artwork. We'll be in touch as it moves along.</p>

                        {{-- Items --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px; border-top:1px solid #f3ded7;">
                            @foreach($order->items as $item)
                                @php($style = $item->artwork_style_name === 'Storybook Cartoon' ? 'Cartoon' : $item->artwork_style_name)
                                <tr>
                                    <td style="padding:16px 0; border-bottom:1px solid #f3ded7; font-family:Arial,Helvetica,sans-serif;">
                                        <div style="font-size:15px; font-weight:700; color:#302c2a;">{{ $item->product_name }}</div>
                                        <div style="font-size:13px; color:#8a827d; margin-top:3px;">{{ collect([$item->variant_name, $style, 'Qty '.$item->quantity])->filter()->implode(' · ') }}</div>
                                    </td>
                                    <td align="right" style="padding:16px 0; border-bottom:1px solid #f3ded7; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:700; color:#302c2a; white-space:nowrap; vertical-align:top;">£{{ number_format($item->total_price_minor / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </table>

                        {{-- Totals --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#6f6862;">
                            <tr>
                                <td style="padding:4px 0;">Subtotal</td>
                                <td align="right" style="padding:4px 0;">£{{ number_format($order->subtotal_minor / 100, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0;">{{ data_get($order->shipping_method_snapshot, 'name', 'UK delivery') }}</td>
                                <td align="right" style="padding:4px 0;">£{{ number_format($order->shipping_minor / 100, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0;">Tax <span style="color:#a79a90; font-size:12px;">(provisional)</span></td>
                                <td align="right" style="padding:4px 0;">£{{ number_format($order->tax_minor / 100, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 0 0; font-size:18px; font-weight:700; color:#302c2a; border-top:1px solid #f3ded7;">Paid total</td>
                                <td align="right" style="padding:12px 0 0; font-size:18px; font-weight:700; color:#302c2a; border-top:1px solid #f3ded7;">£{{ number_format($order->total_minor / 100, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Delivery address --}}
                <tr>
                    <td style="padding-top:16px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eee2d3; border-radius:24px;">
                            <tr>
                                <td style="padding:28px 32px; font-family:Arial,Helvetica,sans-serif;">
                                    <div style="font-family:Georgia,'Times New Roman',serif; font-size:20px; font-weight:700; color:#302c2a;">Delivery address</div>
                                    <div style="margin-top:12px; font-size:14px; line-height:1.7; color:#4a443f;">
                                        {{ trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? '')) }}<br>
                                        {{ $a['address_line_1'] ?? '' }}<br>
                                        @if($a['address_line_2'] ?? null){{ $a['address_line_2'] }}<br>@endif
                                        {{ $a['city'] ?? '' }}@if($a['county'] ?? null), {{ $a['county'] }}@endif<br>
                                        {{ $a['postcode'] ?? '' }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Support --}}
                <tr>
                    <td align="center" style="padding:28px 24px 8px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#6f6862;">
                        Need help with this order?
                        <a href="{{ route('order-support.create', ['order' => $order->number]) }}" style="color:#fc5997; font-weight:700; text-decoration:underline;">Order Support</a>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:16px 24px 8px; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#a79a90; line-height:1.6;">
                        Made from a moment you love.<br>
                        © {{ now()->year }} Kattie.uk · All rights reserved
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
