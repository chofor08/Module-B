{{-- resources/views/emails/payment-confirmed.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Confirmation</title>
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
                            <h2 style="margin-top:0; color:#111827;">Payment Received ✅</h2>

                            <p style="color:#374151; font-size:15px;">
                                Hi {{ $userName }},
                            </p>

                            <p style="color:#374151; font-size:15px;">
                                We've successfully processed your payment. Here are your transaction details:
                            </p>

                            {{-- Details table --}}
                            <table width="100%" cellpadding="8" cellspacing="0"
                                   style="border:1px solid #e5e7eb; border-radius:6px; margin:20px 0;">
                                <tr>
                                    <td style="color:#6b7280;">Amount Paid</td>
                                    <td style="text-align:right; font-weight:bold; color:#111827;">
                                        {{ $currency }} {{ $amount }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color:#6b7280;">Order ID</td>
                                    <td style="text-align:right; color:#111827;">#{{ $orderId }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6b7280;">Payment Reference</td>
                                    <td style="text-align:right; color:#111827;">{{ $txnId }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6b7280;">Date</td>
                                    <td style="text-align:right; color:#111827;">{{ $date }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6b7280;">Payment Method</td>
                                    <td style="text-align:right; color:#111827;">{{ $method }}</td>
                                </tr>
                            </table>

                            {{-- Optional itemized list --}}
                            @if(isset($items) && count($items) > 0)
                                <h3 style="color:#111827; font-size:15px;">Items</h3>
                                <table width="100%" cellpadding="8" cellspacing="0" style="margin-bottom:20px;">
                                    @foreach($items as $item)
                                        <tr style="border-bottom:1px solid #f3f4f6;">
                                            <td style="color:#374151;">{{ $item->name }} × {{ $item->quantity }}</td>
                                            <td style="text-align:right; color:#374151;">
                                                {{ $currency }} {{ number_format($item->price, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            <p style="color:#374151; font-size:14px;">
                                If you have any questions about this payment, reply to this email or contact
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
