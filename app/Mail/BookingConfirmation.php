<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public Payment $payment,
    ) {}

    public function build(): static
    {
        return $this->subject('Booking confirmed - ' . $this->reservation->code)
            ->view('emails.booking-confirmation')
            ->text('emails.booking-confirmation-text');
    }
}
