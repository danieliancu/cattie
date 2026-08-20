<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>We've received your message</title>
</head>
<body style="margin:0; padding:0; background-color:#fffaf3; -webkit-font-smoothing:antialiased;">
<span style="display:none; max-height:0; overflow:hidden; opacity:0;">Thanks for getting in touch — your reference is {{ $supportRequest->reference }}.</span>
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
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:11px; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:#fc5997;">Support · {{ $supportRequest->reference }}</div>
                        <h1 style="margin:10px 0 0; font-family:Georgia,'Times New Roman',serif; font-size:30px; line-height:1.2; color:#302c2a; font-weight:700;">Thanks — we've got your message</h1>
                        <p style="margin:16px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#6f6862;">
                            Our team will look into it and get back to you by email as soon as we can.
                            @if($supportRequest->order)
                                This is about order <strong style="color:#302c2a;">{{ $supportRequest->order->number }}</strong>.
                            @endif
                            There's nothing more you need to do for now.
                        </p>

                        {{-- Their message --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px; background-color:#eee2d3; border-radius:18px;">
                            <tr>
                                <td style="padding:20px 22px; font-family:Arial,Helvetica,sans-serif;">
                                    <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#8a827d;">Your message</div>
                                    <div style="margin-top:8px; font-size:14px; line-height:1.6; color:#4a443f; white-space:pre-line;">{{ $supportRequest->message }}</div>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:22px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#8a827d;">Please keep the reference <strong style="color:#302c2a;">{{ $supportRequest->reference }}</strong> if you need to follow up.</p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:24px 24px 8px; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#a79a90; line-height:1.6;">
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
