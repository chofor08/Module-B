{{-- resources/views/emails/refund-confirmed.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Refund Confirmation</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                       style="background:#ffffff; border-radius:8px; overflow:hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background:#111827; padding:24px; text-align:center;">
                            <h1 style="color:#ffffff; font-size:20px; margin:0;">
                                {{ config('app.name') }}
                            </h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            <h2 style="margin-top:0; color:#111827;">Refund Processed 💸</h2>

                            <p style="color:#374151; font-size:15px;">
                                Hi {{ $userName }},
                            </p>

                            <p style="color:#374151; font-size:15px;">
                                Your refund has been processed and should appear on your original payment method
                                within 5–10 business days, depending on your bank.
                            </p>

                            {{-- Details table --}}
                            <table width="100%" cellpadding="8" cellspacing="0"
                                   style="border:1px solid #e5e7eb; border-radius:6px; margin:20px 0;">
                                <tr>
                                    <td style="color:#6b7280;">Refund Amount</td>
                                    <td style="text-align:right; font-weight:bold; color:#111827;">
                                        {{ $currency }} {{ $refundAmount }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color:#6b7280;">Order ID</td>
                                    <td style="text-align:right; color:#111827;">#{{ $orderId }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6b7280;">Transaction ID</td>
                                    <td style="text-align:right; color:#111827;">{{ $txnId }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6b7280;">Date Processed</td>
                                    <td style="text-align:right; color:#111827;">{{ $date }}</td>
                                </tr>
                                @if($reason)
                                <tr>
                                    <td style="color:#6b7280;">Reason</td>
                                    <td style="text-align:right; color:#111827;">{{ $reason }}</td>
                                </tr>
                                @endif
                            </table>

                            <p style="color:#374151; font-size:14px;">
                                If you don't see the refund after 10 business days, or have any questions,
                                reply to this email or contact
                                <a href="mailto:{{ config('mail.support_address', 'support@example.com') }}">support</a>.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#f9fafb; padding:16px; text-align:center;">
                            <p style="color:#9ca3af; font-size:12px; margin:0;">
                                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
