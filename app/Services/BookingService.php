<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\Validator;

/** Business logic shared by the website (Blade) and the REST API. */
class BookingService
{
    public function __construct(private PaymentGateway $gateway) {}

    public function find(string $code): Reservation
    {
        return Reservation::findByCode($code) ?? throw new BookingException('Reservation not found', 404);
    }

    public function reserve(array $in): Reservation
    {
        $v = Validator::make($in, [
            'room_id'     => 'required|string',
            'guest_name'  => 'required|string|min:2|max:100',
            'guest_email' => 'required|email|max:120',
            'check_in'    => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out'   => 'required|date_format:Y-m-d|after:check_in',
            'guests'      => 'required|integer|min:1',
        ], [
            'required' => 'The :attribute field is required.',
            'guest_email.email' => 'Enter a valid email address.',
            'check_in.after_or_equal' => 'Check-in date cannot be in the past.',
            'check_out.after' => 'Check-out must be after check-in.',
            '*.date_format' => 'Dates must use the format YYYY-MM-DD.',
            'guests.min' => 'At least 1 guest is required.',
        ]);
        if ($v->fails()) {
            throw new BookingException($v->errors()->first(), 400, ['fields' => array_keys($v->errors()->messages())]);
        }

        $room = Room::find($in['room_id']) ?? throw new BookingException('Room not found', 404);
        if (! $room->available) throw new BookingException('Room is not available', 409);
        if ((int) $in['guests'] > $room->capacity) {
            throw new BookingException("Guests must be between 1 and {$room->capacity} for this room", 400);
        }
        $overlap = Reservation::where('room_id', $room->id)->where('status', '!=', 'CANCELLED')
            ->where('check_in', '<', $in['check_out'])->where('check_out', '>', $in['check_in'])->exists();
        if ($overlap) throw new BookingException('Room is already booked for those dates', 409);

        $nights = (int) round((strtotime($in['check_out']) - strtotime($in['check_in'])) / 86400);
        return Reservation::create([
            'room_id' => $room->id, 'guest_name' => trim($in['guest_name']), 'guest_email' => $in['guest_email'],
            'check_in' => $in['check_in'], 'check_out' => $in['check_out'], 'guests' => (int) $in['guests'],
            'nights' => $nights, 'total_amount' => $nights * $room->price_per_night, 'currency' => 'PHP', 'status' => 'PENDING',
        ]);
    }

    public function cancel(string $code): Reservation
    {
        $r = $this->find($code);
        $r->update(['status' => 'CANCELLED']);
        return $r;
    }

    public function pay(array $in): Payment
    {
        $v = Validator::make($in, [
            'reservation_id' => 'required|string',
            'amount'         => 'required|numeric|gt:0',
            'method'         => 'required|in:card,gcash,maya',
        ], ['required' => 'Missing required field: :attribute', 'method.in' => 'Unsupported payment method']);
        if ($v->fails()) throw new BookingException($v->errors()->first(), 400);

        $r = $this->find($in['reservation_id']);
        if ($r->status !== 'PENDING') throw new BookingException("Reservation is {$r->status}, payment not allowed", 409);
        if (abs((float) $in['amount'] - $r->total_amount) > 0.005) throw new BookingException('Amount does not match reservation total', 422);

        $payload = ['reference' => $r->code, 'amount' => $r->total_amount, 'currency' => 'PHP', 'method' => $in['method']];
        if ($in['method'] === 'card') {
            foreach (['bank', 'card_holder', 'expiry', 'cvv'] as $k) if (isset($in[$k])) $payload[$k] = trim((string) $in[$k]);
            $payload['card_number'] = preg_replace('/\D/', '', (string) ($in['card_number'] ?? ''));
        } else {
            $payload['mobile_number'] = $this->normMobile((string) ($in['mobile_number'] ?? ''));
        }

        [$code, $data] = $this->gateway->charge($payload);   // <-- System 1 calls System 2
        $details = $data['details'] ?? null;
        $record = ['reservation_id' => $r->id, 'amount' => $r->total_amount, 'method' => $in['method'], 'details' => $details];

        if ($code === 201) {
            $p = Payment::create($record + ['status' => 'PAID', 'transaction_id' => $data['transaction_id'] ?? null]);
            $r->update(['status' => 'CONFIRMED']);
            return $p;
        }
        if ($code === 402) {
            $p = Payment::create($record + ['status' => 'FAILED']);
            $msg = $data['message'] ?? 'Payment declined';
            throw new BookingException($msg, 402, ['payment_id' => $p->code, 'status' => 'FAILED', 'message' => $msg]);
        }
        throw new BookingException($data['error'] ?? 'Payment service error', in_array($code, [400, 422], true) ? 400 : 502);
    }

    private function normMobile(string $s): string
    {
        $s = preg_replace('/[\s\-()]/', '', $s);
        return preg_match('/^(\+63|63)(9\d{9})$/', $s, $m) ? '0' . $m[2] : $s;
    }
}
