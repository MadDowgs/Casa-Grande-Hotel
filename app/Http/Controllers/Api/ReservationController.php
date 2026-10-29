<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\BookingService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private BookingService $svc) {}

    public function index()  { return response()->json(Reservation::latest('id')->get()->map->toApi()->values()); }
    public function show(string $code) { return response()->json($this->svc->find($code)->toApi()); }
    public function store(Request $request) { return response()->json($this->svc->reserve($request->all())->toApi(), 201); }

    public function destroy(string $code)
    {
        $r = $this->svc->cancel($code);
        return response()->json(['reservation_id' => $r->code, 'status' => 'CANCELLED']);
    }
}
