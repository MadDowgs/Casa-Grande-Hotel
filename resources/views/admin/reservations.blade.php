@extends('admin.layout')
@section('title', 'Reservations · Admin')

@section('content')
<p class="eyebrow">Administration</p>
<h1 class="h2 admin-title">Reservations</h1>
<p class="mut admin-warning">Cancelling a reservation does not refund a payment. Handle payment refunds separately.</p>

<section class="panel admin-section">
  @if ($reservations->isEmpty())
    <p class="mut">There are no reservations yet.</p>
  @else
    <div class="tbl"><table>
      <thead><tr><th>ID</th><th>Guest / account</th><th>Room</th><th>Dates</th><th>Guests</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>@foreach ($reservations as $reservation)
        <tr>
          <td>{{ $reservation->code }}</td>
          <td>{{ $reservation->guest_name }}<br><span class="mut small">{{ $reservation->user?->email ?? $reservation->guest_email }}</span></td>
          <td>{{ $reservation->room?->name ?? $reservation->room_id }}</td>
          <td>{{ $reservation->check_in->format('M j') }} – {{ $reservation->check_out->format('M j, Y') }}</td>
          <td>{{ $reservation->guests }}</td>
          <td>₱{{ number_format($reservation->total_amount, 2) }}</td>
          <td><span class="pill {{ $reservation->status === 'CONFIRMED' ? 'ok' : ($reservation->status === 'CANCELLED' ? 'bad' : 'mut') }}">{{ $reservation->status }}</span></td>
          <td>
            <div class="admin-role-form">
              @if ($reservation->status !== 'CANCELLED')
                <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" onsubmit="return confirm('Cancel this reservation? This will not refund a payment.')">
                  @csrf
                  <button class="btn danger sm">Cancel</button>
                </form>
              @endif
              <form method="POST" action="{{ route('admin.reservations.delete', $reservation) }}" onsubmit="return confirm('Permanently delete this reservation and all its payment records? This cannot be undone and does not issue a refund.')">
                @csrf @method('DELETE')
                <button class="btn danger sm">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @endforeach</tbody>
    </table></div>
    <div class="admin-pagination">{{ $reservations->links() }}</div>
  @endif
</section>
@endsection
