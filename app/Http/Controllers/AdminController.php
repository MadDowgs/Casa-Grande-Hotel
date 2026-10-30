<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'roomCount' => Room::count(),
            'availableRoomCount' => Room::where('available', true)->count(),
            'reservationCount' => Reservation::count(),
            'pendingReservationCount' => Reservation::where('status', 'PENDING')->count(),
            'userCount' => User::count(),
            'recentReservations' => Reservation::with(['room', 'user'])->latest()->limit(8)->get(),
        ]);
    }

    public function reservations(): View
    {
        return view('admin.reservations', [
            'reservations' => Reservation::with(['room', 'user'])->latest()->paginate(20),
        ]);
    }

    public function cancelReservation(Reservation $reservation): RedirectResponse
    {
        if ($reservation->status === 'CANCELLED') {
            return back()->with('notice', "Reservation {$reservation->code} is already cancelled.");
        }

        $reservation->update(['status' => 'CANCELLED']);

        return back()->with('notice', "Reservation {$reservation->code} cancelled. This does not issue a payment refund.");
    }

    public function rooms(): View
    {
        $rooms = Room::orderBy('id')->get();
        $inventory = [];
        foreach ($rooms as $room) {
            $occupied = Reservation::where('room_id', $room->id)->where('status', '!=', 'CANCELLED')
                ->whereDate('check_in', '<=', now()->toDateString())
                ->whereDate('check_out', '>', now()->toDateString())->count();
            $inventory[$room->id] = [
                'occupied' => $occupied,
                'remaining' => max(0, $room->inventory_capacity - $occupied),
            ];
        }
        return view('admin.rooms', compact('rooms', 'inventory'));
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/', 'unique:rooms,id'],
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', 'string', 'max:30'],
            'price_per_night' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'capacity' => ['required', 'integer', 'min:1', 'max:255'],
            'inventory_capacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'available' => ['required', 'boolean'],
        ]);

        $data['id'] = strtoupper($data['id']);
        Room::create($data);

        return back()->with('notice', "Room {$data['id']} added.");
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', 'string', 'max:30'],
            'price_per_night' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'capacity' => ['required', 'integer', 'min:1', 'max:255'],
            'inventory_capacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'available' => ['required', 'boolean'],
        ]);

        $committed = $this->maximumCommittedSlots($room);
        if ($data['inventory_capacity'] < $committed) {
            return back()->withInput()->with('error', "Total capacity cannot be below {$committed} rooms already committed to existing reservations.");
        }

        $room->update($data);

        return back()->with('notice', "Room {$room->id} updated.");
    }

    public function deleteRoom(Room $room): RedirectResponse
    {
        if ($room->reservations()->exists()) {
            return back()->with('error', "Room {$room->id} has reservation history and cannot be deleted. Mark it unavailable instead.");
        }

        $room->delete();

        return back()->with('notice', "Room {$room->id} deleted.");
    }

    private function maximumCommittedSlots(Room $room): int
    {
        $reservations = Reservation::where('room_id', $room->id)->where('status', '!=', 'CANCELLED')
            ->whereDate('check_out', '>', now()->toDateString())->get(['check_in', 'check_out']);
        if ($reservations->isEmpty()) return 0;
        $start = now()->startOfDay();
        $end = $reservations->max(fn ($reservation) => $reservation->check_out->getTimestamp());
        $maximum = 0;
        for ($night = $start->getTimestamp(); $night < $end; $night += 86400) {
            $date = date('Y-m-d', $night);
            $count = $reservations->filter(fn ($reservation) =>
                $reservation->check_in->toDateString() <= $date && $reservation->check_out->toDateString() > $date
            )->count();
            $maximum = max($maximum, $count);
        }
        return $maximum;
    }

    public function users(): View
    {
        return view('admin.users', [
            'users' => User::withCount('reservations')->orderBy('name')->paginate(25),
        ]);
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_admin' => ['required', Rule::in(['0', '1'])],
        ]);
        $makeAdmin = $data['is_admin'] === '1';

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot change your own administrator role.');
        }

        if (! $makeAdmin && $user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'The last administrator cannot be removed.');
        }

        $user->is_admin = $makeAdmin;
        $user->save();

        return back()->with('notice', $makeAdmin
            ? "{$user->name} is now an administrator."
            : "{$user->name} is no longer an administrator.");
    }

    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account while signed in.');
        }

        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'The last administrator account cannot be deleted.');
        }

        $name = $user->name;
        $user->delete();

        return back()->with('notice', "Account {$name} deleted. Reservation and payment history was retained.");
    }
}
