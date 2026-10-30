@extends('admin.layout')
@section('title', 'Admin dashboard · Casa Grande Hotel')

@section('content')
<p class="eyebrow">Administration</p>
<h1 class="h2 admin-title">Dashboard</h1>
<p class="mut">Welcome, {{ auth()->user()->name }}. Manage hotel operations from here.</p>

<div class="admin-stats">
  <a class="panel admin-stat" href="{{ route('admin.rooms') }}"><span class="mut">Rooms</span><strong>{{ $roomCount }}</strong><small>{{ $availableRoomCount }} marked available</small></a>
  <a class="panel admin-stat" href="{{ route('admin.reservations') }}"><span class="mut">Reservations</span><strong>{{ $reservationCount }}</strong><small>{{ $pendingReservationCount }} pending</small></a>
  <a class="panel admin-stat" href="{{ route('admin.users') }}"><span class="mut">Accounts</span><strong>{{ $userCount }}</strong><small>Manage account roles</small></a>
</div>

<section class="panel admin-section">
  <div class="admin-section-head"><div><p class="eyebrow">Latest activity</p><h2>Recent reservations</h2></div><a class="btn ghost sm" href="{{ route('admin.reservations') }}">All reservations</a></div>
  @if ($recentReservations->isEmpty())
    <p class="mut">There are no reservations yet.</p>
  @else
    <div class="tbl"><table>
      <thead><tr><th>Reservation</th><th>Guest</th><th>Room</th><th>Stay</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>@foreach ($recentReservations as $reservation)
        <tr>
          <td>{{ $reservation->code }}</td>
          <td>{{ $reservation->guest_name }}<br><span class="mut small">{{ $reservation->user?->email ?? $reservation->guest_email }}</span></td>
          <td>{{ $reservation->room?->name ?? $reservation->room_id }}</td>
          <td>{{ $reservation->check_in->format('M j') }} – {{ $reservation->check_out->format('M j, Y') }}</td>
          <td>₱{{ number_format($reservation->total_amount, 2) }}</td>
          <td>{{ $reservation->status }}</td>
        </tr>
      @endforeach</tbody>
    </table></div>
  @endif
</section>
<p class="mut small admin-warning">Cancelling a reservation changes its status but does not refund a payment.</p>
@endsection
