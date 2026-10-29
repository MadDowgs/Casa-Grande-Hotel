<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['R101', 'Standard Queen', 'Standard', 2500, 2, true],
            ['R202', 'Deluxe Bay View', 'Deluxe', 4200, 3, true],
            ['R303', 'Family Suite', 'Suite', 6800, 6, true],
            ['R404', 'Executive Penthouse', 'Penthouse', 11500, 4, false],
        ];
        foreach ($rooms as [$id, $name, $type, $price, $cap, $avail]) {
            Room::updateOrCreate(['id' => $id], ['name' => $name, 'type' => $type, 'price_per_night' => $price, 'capacity' => $cap, 'available' => $avail]);
        }
    }
}
