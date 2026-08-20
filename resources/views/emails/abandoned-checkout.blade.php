@php($a = $order->shipping_address ?? [])
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Your Kattie order is waiting</title>
</head>
<body style="margin:0; padding:0; background-color:#fffaf3; -webkit-font-smoothing:antialiased;">
<span style="display:none; max-height:0; overflow:hidden; opacity:0;">Your personalised design is saved — finish your order whenever you're ready.</span>
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
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:11px; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:#fc5997;">Your design is saved</div>
                        <h1 style="margin:10px 0 0; font-family:Georgia,'Times New Roman',serif; font-size:30px; line-height:1.2; color:#302c2a; font-weight:700;">
                            {{ $stage === 1 ? 'You left something magical behind' : 'Your design is still waiting for you' }}
                        </h1>
                        <p style="margin:16px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#6f6862;">
                            {{ $stage === 1
                                ? "Your personalised artwork is ready and your order is saved — you're just one step from the finish line."
                                : "We've kept your personalised design safe. Pick up right where you left off before it's gone." }}
                        </p>

                        {{-- Items --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:26px; border-top:1px solid #f3ded7;">
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

                        @if($order->total_minor)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px; font-family:Arial,Helvetica,sans-serif;">
                                <tr>
                                    <td style="font-size:16px; font-weight:700; color:#302c2a;">Total</td>
                                    <td align="right" style="font-size:16px; font-weight:700; color:#302c2a;">£{{ number_format($order->total_minor / 100, 2) }}</td>
                                </tr>
                            </table>
                        @endif

                        {{-- CTA --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ $resumeUrl }}" style="display:inline-block; background-color:#fc5997; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:700; text-decoration:none; padding:15px 40px; border-radius:999px;">Complete your order</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:16px 0 0; text-align:center; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#a79a90;">Nothing goes to print until you've approved and paid.</p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:24px 24px 8px; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#a79a90; line-height:1.7;">
                        Made from a moment you love.<br>
                        © {{ now()->year }} Kattie.uk · All rights reserved<br>
                        <a href="{{ $unsubscribeUrl }}" style="color:#a79a90; text-decoration:underline;">Stop reminders about this order</a>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
