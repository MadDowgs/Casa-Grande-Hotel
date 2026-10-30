<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\ApiLog;
use App\Models\Reservation;
use App\Models\Room;
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
        // rooms that already have a (non-cancelled) reservation overlapping the chosen dates
        $busy = $valid
            ? Reservation::where('status', '!=', 'CANCELLED')->where('check_in', '<', $out)->where('check_out', '>', $in)->pluck('room_id')->all()
            : [];

        return view('rooms', [
            'rooms'  => Room::orderBy('id')->get(),
            'search' => compact('in', 'out', 'guests', 'valid', 'nights'),
            'busy'   => $busy,
        ]);
    }

    public function book(Room $room)
    {
        if (! $room->available) return redirect()->route('rooms')->with('error', 'That room is not available.');
        return view('book', compact('room'));
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
