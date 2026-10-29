@extends('layouts.app')
@section('title', 'Reservations · Casa Grande Hotel')

@section('content')
<main class="page"><div class="wrap">
  <div class="rv"><p class="eyebrow">Manage</p><h2 class="h2" style="margin-bottom:32px">Reservations</h2></div>
  @if ($error)<div class="inline-msg" role="alert">{{ $error }}</div>@endif

  <form class="panel rv" method="GET" action="{{ route('reservations') }}" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
    <div style="flex:1;min-width:220px"><label for="id" style="margin-top:0">Reservation ID</label><input id="id" name="id" placeholder="RSV-1001" value="{{ request('id') }}"></div>
    <button class="btn">Look up</button>
    <a class="btn ghost" href="{{ route('reservations', ['all' => 1]) }}">List all</a>
  </form>

  @if ($list !== null && $list->isEmpty())<p class="mut" style="margin-top:32px">No reservations yet.</p>@endif
  @if ($list !== null && $list->isNotEmpty())
  <div class="panel tbl rv" style="margin-top:24px">
    <table>
      <tr><th>ID</th><th>Guest</th><th>Room</th><th>Stay</th><th>Total</th><th>Status</th><th></th></tr>
      @foreach ($list as $v)
      <tr>
        <td>{{ $v->code }}</td><td>{{ $v->guest_name }}</td><td>{{ $v->room_id }}</td>
        <td>{{ $v->check_in->format('M j') }} – {{ $v->check_out->format('M j, Y') }}</td>
        <td>₱{{ number_format($v->total_amount, 2) }}</td>
        <td><span class="pill {{ $v->status === 'CONFIRMED' ? 'ok' : ($v->status === 'CANCELLED' ? 'bad' : 'mut') }}">{{ $v->status }}</span></td>
        <td>
          @if ($v->status !== 'CANCELLED')
          <form method="POST" action="{{ route('reservations.cancel', $v->code) }}" onsubmit="return confirm('Cancel this reservation?')">
            @csrf <button class="btn danger sm">Cancel</button>
          </form>
          @endif
        </td>
      </tr>
      @endforeach
    </table>
  </div>
  @endif
</div></main>
@endsection
