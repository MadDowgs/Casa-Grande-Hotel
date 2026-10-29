<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['room_id', 'guest_name', 'guest_email', 'check_in', 'check_out', 'guests', 'nights', 'total_amount', 'currency', 'status'];
    protected $casts = ['check_in' => 'date', 'check_out' => 'date', 'total_amount' => 'float', 'guests' => 'integer', 'nights' => 'integer'];

    public function room() { return $this->belongsTo(Room::class); }
    public function payments() { return $this->hasMany(Payment::class); }

    /** Public ID shown to guests, e.g. RSV-1001 */
    public function getCodeAttribute(): string { return 'RSV-' . (1000 + $this->id); }

    public static function findByCode(string $code): ?self
    {
        return preg_match('/^RSV-(\d+)$/', trim($code), $m) ? static::find(max(0, (int) $m[1] - 1000)) : null;
    }

    public function toApi(): array
    {
        return ['reservation_id' => $this->code, 'room_id' => $this->room_id, 'guest_name' => $this->guest_name,
                'guest_email' => $this->guest_email, 'check_in' => $this->check_in->format('Y-m-d'),
                'check_out' => $this->check_out->format('Y-m-d'), 'guests' => $this->guests, 'nights' => $this->nights,
                'total_amount' => $this->total_amount, 'currency' => $this->currency, 'status' => $this->status];
    }
}
