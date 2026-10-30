<?php

use App\Http\Controllers\SiteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login.authenticate');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'store'])->name('register.store');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/', [SiteController::class, 'rooms'])->name('rooms');
Route::middleware('auth')->group(function () {
    Route::get('/book/{room}', [SiteController::class, 'book'])->name('book');
    Route::get('/book/{room}/booked-dates', [SiteController::class, 'bookedDates'])->middleware('throttle:60,1')->name('book.booked-dates');
    Route::get('/book/{room}/availability', [SiteController::class, 'availability'])->middleware('throttle:60,1')->name('book.availability');
    Route::post('/reserve', [SiteController::class, 'reserve'])->middleware('throttle:20,1')->name('reserve');
    Route::get('/pay/{code}', [SiteController::class, 'pay'])->name('pay');
    Route::post('/pay/{code}', [SiteController::class, 'payProcess'])->middleware('throttle:10,1')->name('pay.process');
    Route::post('/pay/{code}/session', [SiteController::class, 'createPaymentSession'])->middleware('throttle:10,1')->name('pay.session');
    Route::get('/pay/{code}/status', [SiteController::class, 'paymentStatus'])->name('pay.status');
    Route::post('/pay/{code}/simulate/{session}', [SiteController::class, 'simulatePayment'])->middleware('throttle:10,1')->name('pay.simulate');
    Route::post('/pay/{code}/session/{session}/cancel', [SiteController::class, 'cancelPaymentSession'])->name('pay.session.cancel');
    Route::post('/pay/{code}/cancel', [SiteController::class, 'cancelPay'])->name('pay.cancel');
    Route::get('/done/{code}', [SiteController::class, 'done'])->name('done');
    Route::get('/reservations', [SiteController::class, 'lookup'])->name('reservations');
    Route::post('/reservations/{code}/cancel', [SiteController::class, 'cancel'])->name('reservations.cancel');
});
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/reservations', [AdminController::class, 'reservations'])->name('reservations');
    Route::post('/reservations/{reservation}/cancel', [AdminController::class, 'cancelReservation'])->name('reservations.cancel');
    Route::get('/rooms', [AdminController::class, 'rooms'])->name('rooms');
    Route::post('/rooms', [AdminController::class, 'storeRoom'])->name('rooms.store');
    Route::put('/rooms/{room}', [AdminController::class, 'updateRoom'])->name('rooms.update');
    Route::delete('/rooms/{room}', [AdminController::class, 'deleteRoom'])->name('rooms.delete');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::put('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('users.role');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
});
Route::get('/log', [SiteController::class, 'log'])->name('log');
Route::post('/log/clear', [SiteController::class, 'clearLog'])->name('log.clear');
