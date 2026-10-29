<?php

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'rooms'])->name('rooms');
Route::get('/book/{room}', [SiteController::class, 'book'])->name('book');
Route::post('/reserve', [SiteController::class, 'reserve'])->middleware('throttle:20,1')->name('reserve');
Route::get('/pay/{code}', [SiteController::class, 'pay'])->name('pay');
Route::post('/pay/{code}', [SiteController::class, 'payProcess'])->middleware('throttle:10,1')->name('pay.process');
Route::post('/pay/{code}/cancel', [SiteController::class, 'cancelPay'])->name('pay.cancel');
Route::get('/done/{code}', [SiteController::class, 'done'])->name('done');
Route::get('/reservations', [SiteController::class, 'lookup'])->name('reservations');
Route::post('/reservations/{code}/cancel', [SiteController::class, 'cancel'])->name('reservations.cancel');
Route::get('/log', [SiteController::class, 'log'])->name('log');
Route::post('/log/clear', [SiteController::class, 'clearLog'])->name('log.clear');
