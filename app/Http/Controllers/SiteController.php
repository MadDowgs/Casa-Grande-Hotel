<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\ApiLog;
use App\Models\PaymentSession;
use App\Models\Reservation;
use App\Models\Room;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Services\BookingService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function __construct(private BookingService $svc) {}

    private function reservation(string $code): Reservation
    {
        try {
            $reservation = $this->svc->find($code);
        } catch (BookingException $e) {
            abort(404, 'Reservation not found');
        }

        abort_unless($reservation->user_id === request()->user()->id, 404, 'Reservation not found');

        return $reservation;
    }

    public function rooms(Request $request)
    {
        $in = (string) $request->query('check_in');
        $out = (string) $request->query('check_out');
        $guests = max(1, (int) $request->query('guests', 1));
        $valid = $in !== '' && $out !== '' && strtotime($in) && strtotime($out) && strtotime($out) > strtotime($in);
        $nights = $valid ? (int) round((strtotime($out) - strtotime($in)) / 86400) : null;
        $rooms = Room::orderBy('id')->get();
        $availability = [];
        foreach ($rooms as $room) {
            $contextIn = $valid ? $in : now()->toDateString();
            $contextOut = $valid ? $out : now()->addDay()->toDateString();
            $availability[$room->id] = $this->svc->availableRooms($room, $contextIn, $contextOut);
        }

        return view('rooms', [
            'rooms'  => $rooms,
            'search' => compact('in', 'out', 'guests', 'valid', 'nights'),
            'availability' => $availability,
        ]);
    }

    public function book(Room $room)
    {
        $ranges = $this->svc->bookedRanges($room);
        return view('book', compact('room', 'ranges'));
    }

    public function bookedDates(Room $room)
    {
        return response()->json([
            'ranges' => $this->svc->bookedRanges($room),
            'available' => (bool) $room->available,
        ]);
    }

    public function availability(Request $request, Room $room)
    {
        if (! $room->available) {
            return response()->json(['available' => false, 'message' => 'This room has no bookable dates.'], 409);
        }
        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');
        if (! is_string($checkIn) || ! is_string($checkOut)) {
            return response()->json(['available' => false, 'message' => 'Choose both check-in and check-out dates.'], 422);
        }
        $remaining = $this->svc->availableRooms($room, $checkIn, $checkOut);
        return response()->json($remaining === 0
            ? ['available' => false, 'remaining' => 0, 'message' => 'Room is already booked for those dates']
            : ['available' => true, 'remaining' => $remaining, 'message' => $remaining <= 5 ? "Only {$remaining} " . ($remaining === 1 ? 'room' : 'rooms') . ' left for your dates' : 'Available for your dates']);
    }

    public function reserve(Request $request)
    {
        try {
            $res = $this->svc->reserve($request->only(['room_id', 'guest_name', 'guest_email', 'check_in', 'check_out', 'guests']));
            $res->user()->associate($request->user());
            $res->save();

            return redirect()->route('pay', $res->code);
        } catch (BookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function pay(string $code)
    {
        $res = $this->reservation($code);
        if ($res->status !== 'PENDING') {
            return redirect()->route('reservations', ['id' => $res->code])->with('error', "Reservation {$res->code} is {$res->status}.");
        }
        return view('pay', ['res' => $res, 'banks' => config('hotel.banks')]);
    }

    public function payProcess(Request $request, string $code)
    {
        $res = $this->reservation($code);
        if (in_array($request->input('method'), ['gcash', 'maya'], true)) {
            return $this->createPaymentSession($request, $code);
        }
        $in = $request->only(['method', 'bank', 'card_holder', 'card_number', 'expiry', 'cvv', 'mobile_number']);
        $in['reservation_id'] = $res->code;
        $in['amount'] = $res->total_amount;     // never trust an amount coming from the browser
        try {
            $this->svc->pay($in);
            return redirect()->route('done', $res->code);
        } catch (BookingException $e) {
            return back()->withInput($request->only(['method', 'bank', 'card_holder', 'mobile_number']))->with('error', $e->getMessage());
        }
    }

    public function createPaymentSession(Request $request, string $code)
    {
        $reservation = $this->reservation($code);
        $method = $request->validate(['method' => 'required|in:gcash,maya'])['method'];
        try {
            $session = $this->svc->createPaymentSession($reservation, $request->user()->id, $method);
            $payload = json_encode([
                'kind' => 'CASA_GRANDE_DEMO_PAYMENT',
                'provider' => strtoupper($method),
                'reservation' => $reservation->code,
                'session' => $session->session_token,
                'amount' => (float) $session->amount,
                'currency' => 'PHP',
            ], JSON_UNESCAPED_SLASHES);
            $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd());
            $writer = new Writer($renderer);
            $qrSvg = $writer->writeString($payload);

            return response()->json([
                'session_id' => $session->session_token,
                'provider' => $method,
                'reservation_code' => $reservation->code,
                'amount' => (float) $session->amount,
                'currency' => 'PHP',
                'expires_at' => $session->expires_at->toIso8601String(),
                'qr_payload' => $payload,
                'qr_svg' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            ]);
        } catch (BookingException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }
    }

    public function paymentStatus(Request $request, string $code)
    {
        $reservation = $this->reservation($code);
        $sessionId = (string) $request->query('session');
        $session = PaymentSession::where('session_token', $sessionId)
            ->where('reservation_id', $reservation->id)
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($session, 404, 'Payment session not found');

        try {
            return response()->json($this->svc->walletSessionStatus($session));
        } catch (BookingException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }
    }

    public function simulatePayment(Request $request, string $code, string $session)
    {
        if (!in_array(app()->environment(), ['local', 'testing'], true)) abort(404);
        $reservation = $this->reservation($code);
        $paymentSession = PaymentSession::where('session_token', $session)
            ->where('reservation_id', $reservation->id)
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($paymentSession, 404, 'Payment session not found');

        try {
            return response()->json($this->svc->simulateWalletPayment($paymentSession, $request->user()->id));
        } catch (BookingException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }
    }

    public function cancelPaymentSession(Request $request, string $code, string $session)
    {
        $reservation = $this->reservation($code);
        $paymentSession = PaymentSession::where('session_token', $session)
            ->where('reservation_id', $reservation->id)
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($paymentSession, 404, 'Payment session not found');
        try {
            $this->svc->cancelPaymentSession($paymentSession, $request->user()->id);
            return response()->json(['status' => 'CANCELLED']);
        } catch (BookingException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }
    }

    public function cancelPay(string $code)
    {
        $this->svc->cancel($this->reservation($code)->code);
        return redirect()->route('rooms')->with('notice', 'Reservation cancelled.');
    }

    public function done(string $code)
    {
        $res = $this->reservation($code);
        $payment = $res->payments()->where('status', 'PAID')->latest('id')->first();
        return view('done', compact('res', 'payment'));
    }

    public function lookup(Request $request)
    {
        $query = $request->user()->reservations()->with('room')->latest('id');
        $error = null;
        if ($request->has('id')) {
            $id = trim((string) $request->query('id'));
            if ($id === '') $error = 'Enter a reservation ID, or choose List all.';
            else {
                $reservation = Reservation::findByCode($id);
                if ($reservation && $reservation->user_id === $request->user()->id) {
                    $query->whereKey($reservation->id);
                } else {
                    $error = 'Reservation not found';
                }
            }
        }

        $list = $error ? collect() : $query->get();

        return view('reservations', compact('list', 'error'));
    }

    public function cancel(string $code)
    {
        $this->svc->cancel($this->reservation($code)->code);
        return redirect()->route('reservations', ['all' => 1])->with('notice', "Reservation {$code} cancelled.");
    }

    public function log()
    {
        return view('log', ['logs' => ApiLog::latest('id')->limit(20)->get()]);
    }

    public function clearLog()
    {
        ApiLog::truncate();
        return redirect()->route('log');
    }
}
