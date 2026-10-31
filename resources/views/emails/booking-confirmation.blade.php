<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Booking confirmed</title>
</head>
<body style="margin:0;background:#f4f1eb;color:#28231c;font-family:Arial,sans-serif">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden">
          <tr>
            <td style="padding:28px 32px;background:#17140f;color:#f5e8cb">
              <p style="margin:0 0 8px;color:#c9a96a;font-size:12px;letter-spacing:3px;text-transform:uppercase">Casa Grande Hotel</p>
              <h1 style="margin:0;font-family:Georgia,serif;font-size:28px;font-weight:400">Your booking is confirmed</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px">
              <p>Hello {{ $reservation->guest_name }},</p>
              <p>Thank you for booking with Casa Grande Hotel. Here are your reservation details:</p>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse">
                <tr><td style="border-bottom:1px solid #e8e2d8;color:#746d62">Reservation</td><td align="right" style="border-bottom:1px solid #e8e2d8"><strong>{{ $reservation->code }}</strong></td></tr>
                <tr><td style="border-bottom:1px solid #e8e2d8;color:#746d62">Room</td><td align="right" style="border-bottom:1px solid #e8e2d8">{{ $reservation->room->name }}</td></tr>
                <tr><td style="border-bottom:1px solid #e8e2d8;color:#746d62">Check-in</td><td align="right" style="border-bottom:1px solid #e8e2d8">{{ $reservation->check_in->format('M j, Y') }}</td></tr>
                <tr><td style="border-bottom:1px solid #e8e2d8;color:#746d62">Check-out</td><td align="right" style="border-bottom:1px solid #e8e2d8">{{ $reservation->check_out->format('M j, Y') }}</td></tr>
                <tr><td style="border-bottom:1px solid #e8e2d8;color:#746d62">Guests / nights</td><td align="right" style="border-bottom:1px solid #e8e2d8">{{ $reservation->guests }} / {{ $reservation->nights }}</td></tr>
                <tr><td style="border-bottom:1px solid #e8e2d8;color:#746d62">Payment method</td><td align="right" style="border-bottom:1px solid #e8e2d8">{{ ucfirst($payment->method) }}@if ($payment->details) — {{ $payment->details }}@endif</td></tr>
                <tr><td style="border-bottom:1px solid #e8e2d8;color:#746d62">Payment date</td><td align="right" style="border-bottom:1px solid #e8e2d8">{{ $payment->created_at->format('M j, Y g:i A') }}</td></tr>
                <tr><td style="padding-top:16px"><strong>Total</strong></td><td align="right" style="padding-top:16px"><strong>₱{{ number_format($payment->amount, 2) }}</strong></td></tr>
              </table>
              <p style="margin:28px 0 0;color:#746d62;font-size:13px">Please keep this email for your records. We look forward to welcoming you.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
