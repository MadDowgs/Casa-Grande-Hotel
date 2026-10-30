@extends('layouts.app')

@section('content')
@php
  $today = date('Y-m-d');
@endphp

<section class="hero">
  <img class="bg" src="{{ asset('img/hero.jpg') }}" alt="Casa Grande Hotel entrance at night">
  <div class="in">
    <p class="eyebrow">Casa Grande Hotel</p>
    <h1>Stay in <em>quiet luxury</em></h1>
    <p class="lead">Reserve your room in minutes and pay securely with card, GCash or Maya.</p>
    <form class="search" method="GET" action="{{ route('rooms') }}#rooms" data-dates>
      <div class="f"><label for="h-in"><svg class="ic" style="width:14px;height:14px"><use href="#i-cal"/></svg> Check-in</label>
        <input id="h-in" type="date" name="check_in" min="{{ $today }}" value="{{ request('check_in', $today) }}" required></div>
      <div class="f"><label for="h-out"><svg class="ic" style="width:14px;height:14px"><use href="#i-cal"/></svg> Check-out</label>
        <input id="h-out" type="date" name="check_out" value="{{ request('check_out', date('Y-m-d', strtotime('+1 day'))) }}" required></div>
      <div class="f last"><label for="h-g"><svg class="ic" style="width:14px;height:14px"><use href="#i-users"/></svg> Guests</label>
        <select id="h-g" name="guests">@for ($g = 1; $g <= 6; $g++)<option value="{{ $g }}" {{ (int) request('guests', 2) === $g ? 'selected' : '' }}>{{ $g }} {{ $g === 1 ? 'guest' : 'guests' }}</option>@endfor</select></div>
      <button class="btn">Check availability</button>
    </form>
  </div>
  <span class="scroll">Scroll</span>
</section>

<section class="sec" id="rooms">
  <div class="wrap">
    <div class="sec-head rv"><p class="eyebrow">Accommodations</p><h2 class="h2">Rooms &amp; suites</h2></div>

    @if ($search['valid'])
      <div class="found rv">
        <svg class="ic"><use href="#i-cal"/></svg>
        {{ date('M j', strtotime($search['in'])) }} – {{ date('M j', strtotime($search['out'])) }} · {{ $search['nights'] }} {{ $search['nights'] === 1 ? 'night' : 'nights' }} · {{ $search['guests'] }} {{ $search['guests'] === 1 ? 'guest' : 'guests' }}
        <a href="{{ route('rooms') }}#rooms">Clear</a>
      </div>
    @endif

    <div class="rooms">
      @foreach ($rooms as $room)
        @php
          $booked = ! $room->available;
          $remaining = (int) ($availability[$room->id] ?? 0);
          $busyNow = $remaining === 0;
          $small = $room->capacity < $search['guests'];
          $off = $booked || $busyNow || $small;
          $note = $booked || $busyNow ? 'Fully booked' : ($small ? 'Sleeps up to ' . $room->capacity : '');
          $q = $search['valid'] ? ['check_in' => $search['in'], 'check_out' => $search['out'], 'guests' => $search['guests']] : [];
        @endphp
        <article class="rcard rv {{ $off ? 'off' : '' }}" style="--d:{{ $loop->index * 0.08 }}s">
          <div class="ph">
            <img src="{{ asset('img/' . $room->image) }}" alt="{{ $room->name }}" loading="lazy">
            <span class="chip">{{ $room->type }}</span>
            @if (! $off && $remaining >= 1 && $remaining <= 5)<span class="inventory-badge">ONLY {{ $remaining }} LEFT</span>@endif
            @if ($off)<span class="state">{{ $note }}</span>@endif
          </div>
          <div class="bd">
            <h3>{{ $room->name }}</h3>
            <div class="meta"><span><svg class="ic"><use href="#i-users"/></svg> Up to {{ $room->capacity }} {{ $room->capacity === 1 ? 'guest' : 'guests' }}</span></div>
            <div class="price"><b>₱{{ number_format($room->price_per_night) }}</b><span class="mut">/ night</span></div>
            <div class="tot mut small">@if ($search['valid'] && ! $off){{ $search['nights'] }} {{ $search['nights'] === 1 ? 'night' : 'nights' }} · ₱{{ number_format($room->price_per_night * $search['nights']) }} total @endif</div>
            @if ($off)
              <span class="btn ghost block off">Unavailable</span>
            @else
              <a class="btn block" href="{{ route('book', ['room' => $room->id] + $q) }}">Reserve <svg class="ic"><use href="#i-arrow"/></svg></a>
            @endif
          </div>
        </article>
      @endforeach
    </div>
  </div>
</section>

<section class="sec alt" id="experience">
  <div class="wrap split2">
    <div class="rv">
      <p class="eyebrow">The experience</p>
      <h2 class="h2">Designed for slow mornings and unhurried evenings.</h2>
      <p class="body">Warm light, natural materials and quiet garden spaces come together so every stay feels calm and considered.</p>
      <ul class="tick">
        <li><svg class="ic"><use href="#i-check"/></svg> Garden lobby and pool terrace</li>
        <li><svg class="ic"><use href="#i-check"/></svg> Rooms with warm, layered lighting</li>
        <li><svg class="ic"><use href="#i-check"/></svg> Simple online booking and payment</li>
      </ul>
    </div>
    <div class="collage rv" style="--d:.15s">
      <img class="a" src="{{ asset('img/pool.jpg') }}" alt="Pool terrace" loading="lazy">
      <img class="b" src="{{ asset('img/lobby.jpg') }}" alt="Lobby garden" loading="lazy">
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="feats">
      <div class="feat rv"><div class="fi"><svg class="ic"><use href="#i-card"/></svg></div><h3>Pay your way</h3><p>Credit and debit cards from Philippine banks, GCash and Maya.</p></div>
      <div class="feat rv" style="--d:.1s"><div class="fi"><svg class="ic"><use href="#i-zap"/></svg></div><h3>Confirmed instantly</h3><p>Your reservation is confirmed the moment your payment goes through.</p></div>
      <div class="feat rv" style="--d:.2s"><div class="fi"><svg class="ic"><use href="#i-undo"/></svg></div><h3>Change of plans?</h3><p>Cancel any reservation from the Reservations page.</p></div>
    </div>
  </div>
</section>

<section class="sec" style="padding-top:0">
  <div class="wrap"><div class="cta rv"><p class="eyebrow">Your stay awaits</p><h2>Ready when you are.</h2><a class="btn" href="#rooms">Choose a room <svg class="ic"><use href="#i-arrow"/></svg></a></div></div>
</section>
@endsection
