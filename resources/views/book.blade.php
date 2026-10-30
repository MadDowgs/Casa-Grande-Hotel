@extends('layouts.app')
@section('title', 'Reserve · Casa Grande Hotel')

@section('content')
@php
  $ci = old('check_in', request('check_in', date('Y-m-d')));
  $co = old('check_out', request('check_out', date('Y-m-d', strtotime('+1 day'))));
  $nights = max(0, (int) round((strtotime($co) - strtotime($ci)) / 86400));
@endphp
<main class="page"><div class="wrap">
  @include('partials.steps', ['n' => 1])
  <div class="split">
    <div class="rv">
      <p class="eyebrow">Your stay</p><h2 class="h2" style="font-size:clamp(2rem,3.6vw,2.8rem)">Guest details</h2>
      @if (session('error'))<div class="inline-msg" style="margin-top:20px">{{ session('error') }}</div>@endif
      <form method="POST" action="{{ route('reserve') }}" id="bookForm" data-dates data-validate data-price="{{ $room->price_per_night }}" data-booked='@json($ranges)' data-booked-url="{{ route('book.booked-dates', $room) }}" data-availability-url="{{ route('book.availability', $room) }}">
        @csrf
        <input type="hidden" name="room_id" value="{{ $room->id }}">
        <label for="n">Full name</label>
        <input id="n" name="guest_name" value="{{ old('guest_name', auth()->user()->name) }}" autocomplete="name" placeholder="Juan Dela Cruz" required>
        <label for="m">Email</label>
        <input id="m" name="guest_email" type="email" value="{{ old('guest_email', auth()->user()->email) }}" autocomplete="email" placeholder="you@email.com" required>
        <div class="two">
          <div><label for="i">Check-in</label><input id="i" name="check_in" type="date" min="{{ date('Y-m-d') }}" value="{{ $ci }}" required aria-describedby="dateLegend dateLiveStatus"></div>
          <div><label for="o">Check-out</label><input id="o" name="check_out" type="date" min="{{ date('Y-m-d', strtotime('+1 day')) }}" value="{{ $co }}" required aria-describedby="dateLegend dateLiveStatus"></div>
        </div>
        <div class="date-calendar-note" id="dateLegend" aria-label="Calendar date legend"><span><i class="legend-dot available"></i>Available</span><span><i class="legend-dot selected"></i>Selected</span><span><i class="legend-dot unavailable"></i>Unavailable</span></div>
        <div class="date-live-status" id="dateLiveStatus" aria-live="polite"></div>
        @if(! $room->available)<div class="inline-msg" role="alert" style="margin-top:18px">This room has no bookable dates because it is currently unavailable.</div>@endif
        <label for="g">Guests</label>
        <input id="g" name="guests" type="number" min="1" max="{{ $room->capacity }}" value="{{ old('guests', min((int) request('guests', 1), $room->capacity)) }}" required>
        <div class="actions"><button class="btn" id="reserveSubmit" @if(! $room->available) disabled @endif>Continue to payment <svg class="ic"><use href="#i-arrow"/></svg></button><a class="btn ghost" href="{{ route('rooms') }}#rooms">Back</a></div>
      </form>
    </div>

    <aside class="sumcard rv" style="--d:.12s">
      <img src="{{ asset('img/' . $room->image) }}" alt="{{ $room->name }}">
      <div class="bd">
        <p class="eyebrow">{{ $room->type }}</p>
        <h3>{{ $room->name }}</h3>
        <div class="row"><span>Rate</span><span>₱{{ number_format($room->price_per_night, 2) }} / night</span></div>
        <div class="row"><span>Check-in</span><span id="sIn">{{ date('M j, Y', strtotime($ci)) }}</span></div>
        <div class="row"><span>Check-out</span><span id="sOut">{{ date('M j, Y', strtotime($co)) }}</span></div>
        <div class="row"><span>Nights</span><span id="sNights">{{ $nights ?: '—' }}</span></div>
        <div class="row tot"><span>Total</span><b id="sTotal">{{ $nights ? '₱' . number_format($nights * $room->price_per_night, 2) : '—' }}</b></div>
        <div class="note"><svg class="ic"><use href="#i-users"/></svg><span>Up to {{ $room->capacity }} {{ $room->capacity === 1 ? 'guest' : 'guests' }} in this room.</span></div>
      </div>
    </aside>
  </div>
</div></main>
@endsection

@section('scripts')
<script src="{{ asset('js/forms.js') }}"></script>
@endsection
