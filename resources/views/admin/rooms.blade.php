@extends('admin.layout')
@section('title', 'Rooms · Admin')

@section('content')
<p class="eyebrow">Administration</p>
<h1 class="h2 admin-title">Rooms</h1>
<p class="mut">Manage room details, nightly rates, capacity and booking availability.</p>

<section class="panel admin-section">
  <h2>Add a room</h2>
  <form method="POST" action="{{ route('admin.rooms.store') }}" class="admin-room-form">
    @csrf
    <div><label for="new-id">Room ID</label><input id="new-id" name="id" maxlength="10" value="{{ old('id') }}" placeholder="R505" required></div>
    <div><label for="new-name">Name</label><input id="new-name" name="name" maxlength="60" value="{{ old('name') }}" required></div>
    <div><label for="new-type">Type</label><input id="new-type" name="type" maxlength="30" value="{{ old('type') }}" placeholder="Deluxe" required></div>
    <div><label for="new-price">Price per night (₱)</label><input id="new-price" name="price_per_night" type="number" min="1" value="{{ old('price_per_night') }}" required></div>
    <div><label for="new-capacity">Guest capacity</label><input id="new-capacity" name="capacity" type="number" min="1" max="255" value="{{ old('capacity', 2) }}" required></div>
    <div><label for="new-inventory">Total room slots</label><input id="new-inventory" name="inventory_capacity" type="number" min="1" max="65535" value="{{ old('inventory_capacity', 1) }}" required></div>
    <div><label for="new-availability">Booking status</label><select id="new-availability" name="available" required><option value="1" selected>Available</option><option value="0">Fully booked</option></select></div>
    <button class="btn">Add room</button>
  </form>
</section>

<div class="admin-room-list">
  @forelse ($rooms as $room)
    <section class="panel admin-room">
      <div class="admin-section-head"><div><p class="eyebrow">{{ $room->id }}</p><h2>{{ $room->name }}</h2></div>
        <span class="pill {{ $room->available ? 'ok' : 'bad' }}">{{ $room->available ? 'Available' : 'Fully booked' }}</span>
      </div>
      <div class="admin-stats inventory-summary">
        <div class="admin-stat"><small>Total capacity</small><strong>{{ $room->inventory_capacity }}</strong></div>
        <div class="admin-stat"><small>Occupied today</small><strong>{{ $inventory[$room->id]['occupied'] }}</strong></div>
        <div class="admin-stat"><small>Remaining</small><strong>{{ $inventory[$room->id]['remaining'] }}</strong></div>
      </div>
      <form method="POST" action="{{ route('admin.rooms.update', $room) }}" class="admin-room-form">
        @csrf @method('PUT')
        <div><label for="name-{{ $room->id }}">Name</label><input id="name-{{ $room->id }}" name="name" maxlength="60" value="{{ $room->name }}" required></div>
        <div><label for="type-{{ $room->id }}">Type</label><input id="type-{{ $room->id }}" name="type" maxlength="30" value="{{ $room->type }}" required></div>
        <div><label for="price-{{ $room->id }}">Price per night (₱)</label><input id="price-{{ $room->id }}" name="price_per_night" type="number" min="1" value="{{ $room->price_per_night }}" required></div>
        <div><label for="capacity-{{ $room->id }}">Guest capacity</label><input id="capacity-{{ $room->id }}" name="capacity" type="number" min="1" max="255" value="{{ $room->capacity }}" required></div>
        <div><label for="inventory-{{ $room->id }}">Total room slots</label><input id="inventory-{{ $room->id }}" name="inventory_capacity" type="number" min="1" max="65535" value="{{ $room->inventory_capacity }}" required></div>
        <div><label for="availability-{{ $room->id }}">Booking status</label><select id="availability-{{ $room->id }}" name="available" required><option value="1" @selected($room->available)>Available</option><option value="0" @selected(! $room->available)>Fully booked</option></select></div>
        <button class="btn ghost">Save changes</button>
      </form>
      <form method="POST" action="{{ route('admin.rooms.delete', $room) }}" onsubmit="return confirm('Delete this room? Rooms with reservation history cannot be deleted.')">
        @csrf @method('DELETE')
        <button class="btn danger sm">Delete room</button>
      </form>
    </section>
  @empty
    <p class="mut">No rooms are configured.</p>
  @endforelse
</div>
@endsection
