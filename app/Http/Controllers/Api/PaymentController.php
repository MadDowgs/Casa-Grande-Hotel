<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private BookingService $svc) {}

    public function store(Request $request)
    {
        return response()->json($this->svc->pay($request->all())->toApi(), 201);
    }
}
