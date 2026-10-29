<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['reservation_id', 'amount', 'method', 'status', 'transaction_id', 'details'];
    protected $casts = ['amount' => 'float'];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function getCodeAttribute(): string { return 'PAY-' . (5000 + $this->id); }

    public function toApi(): array
    {
        return ['payment_id' => $this->code, 'status' => $this->status, 'message' => 'Payment successful',
                'method' => $this->method, 'details' => $this->details, 'transaction_id' => $this->transaction_id];
    }
}
