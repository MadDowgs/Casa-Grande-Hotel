Casa Grande Hotel - Booking confirmed

Hello {{ $reservation->guest_name }},

Your booking is confirmed.

Reservation: {{ $reservation->code }}
Room: {{ $reservation->room->name }}
Check-in: {{ $reservation->check_in->format('M j, Y') }}
Check-out: {{ $reservation->check_out->format('M j, Y') }}
Guests: {{ $reservation->guests }}
Nights: {{ $reservation->nights }}
Payment method: {{ ucfirst($payment->method) }}@if ($payment->details) - {{ $payment->details }}@endif
Payment date: {{ $payment->created_at->format('M j, Y g:i A') }}
Total: PHP {{ number_format($payment->amount, 2) }}

We look forward to welcoming you to Casa Grande Hotel.
