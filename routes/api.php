<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

// Hotel Reservation REST API  ->  http://127.0.0.1:8000/api/...   (header X-API-Key required)
Route::middleware(['apikey', 'throttle:120,1'])->group(function () {
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/{code}', [ReservationController::class, 'show']);
    Route::delete('/reservations/{code}', [ReservationController::class, 'destroy']);
    Route::post('/payments', [PaymentController::class, 'store']);
});
