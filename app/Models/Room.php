<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'type', 'price_per_night', 'capacity', 'inventory_capacity', 'available'];
    protected $casts = ['available' => 'boolean', 'price_per_night' => 'integer', 'capacity' => 'integer', 'inventory_capacity' => 'integer'];

    public function reservations() { return $this->hasMany(Reservation::class); }

    public function getImageAttribute(): string
    {
        return ['R101' => 'room1.jpg', 'R202' => 'room2.jpg', 'R303' => 'room1.jpg', 'R404' => 'room3.jpg'][$this->id] ?? 'room1.jpg';
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'price_per_night' => $this->price_per_night,
                'capacity' => $this->capacity, 'inventory_capacity' => $this->inventory_capacity, 'available' => $this->available];
    }
}
