@extends('layouts.app')
@section('title', 'Confirmed · Casa Grande Hotel')

@section('content')
<main class="page"><div class="wrap center">
  @include('partials.steps', ['n' => 3])
  <div class="check"><img src="{{ asset('img/logo.png') }}" alt="Casa Grande Hotel logo"></div>
  <p class="eyebrow">Thank you</p>
  <h2 class="h2">Your booking is confirmed</h2>
  <p class="mut" style="margin:14px 0 0">We look forward to welcoming you to Casa Grande.</p>

  <div class="ticket rv">
    <img src="{{ asset('img/' . $res->room->image) }}" alt="{{ $res->room->name }}">
    <div class="bd">
      <div class="row"><span>Reservation</span><span>{{ $res->code }}</span></div>
      <div class="row"><span>Room</span><span>{{ $res->room->name }}</span></div>
      <div class="row"><span>Stay</span><span>{{ $res->check_in->format('M j') }} – {{ $res->check_out->format('M j, Y') }}</span></div>
      @if ($payment)
        <div class="row"><span>Payment</span><span>{{ $payment->code }}</span></div>
        @if ($payment->details)<div class="row"><span>Paid with</span><span>{{ $payment->details }}</span></div>@endif
      @endif
      <div class="row tot"><span>Total paid</span><b>₱{{ number_format($res->total_amount, 2) }}</b></div>
      <div class="actions">
        <a class="btn" href="{{ route('rooms') }}">Book another room</a>
        <a class="btn ghost" href="{{ route('reservations', ['all' => 1]) }}">View reservations</a>
      </div>
    </div>
  </div>
</div></main>
@endsection
