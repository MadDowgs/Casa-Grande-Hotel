<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSession extends Model
{
    protected $fillable = ['reservation_id', 'user_id', 'session_token', 'provider', 'amount', 'status', 'expires_at', 'paid_at'];

    protected $casts = [
        'amount' => 'float',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function user() { return $this->belongsTo(User::class); }
}
