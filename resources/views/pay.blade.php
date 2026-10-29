@extends('layouts.app')
@section('title', 'Payment · Casa Grande Hotel')

@section('content')
@php $sel = old('method', 'card'); $label = 'Pay ₱' . number_format($res->total_amount, 2); @endphp
<main class="page"><div class="wrap">
  @include('partials.steps', ['n' => 2])
  <div class="split">
    <div class="rv">
      <p class="eyebrow">Secure payment</p><h2 class="h2" style="font-size:clamp(2rem,3.6vw,2.8rem)">How would you like to pay?</h2>
      @if (session('error'))<div class="inline-msg" style="margin-top:20px" role="alert">{{ session('error') }}</div>@endif

      <form method="POST" action="{{ route('pay.process', $res->code) }}" id="payForm" data-label="{{ $label }}" novalidate style="margin-top:24px">
        @csrf
        <div class="seg" role="radiogroup" aria-label="Payment method">
          <label><input type="radio" name="method" value="card" {{ $sel === 'card' ? 'checked' : '' }}><span>Card</span></label>
          <label><input type="radio" name="method" value="gcash" {{ $sel === 'gcash' ? 'checked' : '' }}><span>GCash</span></label>
          <label><input type="radio" name="method" value="maya" {{ $sel === 'maya' ? 'checked' : '' }}><span>Maya</span></label>
        </div>

        <div id="fcard">
          <label for="bank">Issuing bank</label>
          <select id="bank" name="bank" required>
            <option value="">Select your bank</option>
            @foreach ($banks as $b)<option value="{{ $b }}" {{ old('bank') === $b ? 'selected' : '' }}>{{ $b }}</option>@endforeach
          </select>
          <label for="cn">Card number</label>
          <div class="fld"><input id="cn" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" required><span class="brand" id="brand"></span></div>
          <label for="ch">Name on card</label>
          <input id="ch" name="card_holder" autocomplete="cc-name" value="{{ old('card_holder') }}" placeholder="Juan Dela Cruz" required>
          <div class="two">
            <div><label for="ex">Expiry</label><input id="ex" name="expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/YY" maxlength="5" required></div>
            <div><label for="cv">CVV</label><input id="cv" name="cvv" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="3" required></div>
          </div>
        </div>

        <div id="fwallet" hidden>
          <label for="mb" id="wlabel">GCash mobile number</label>
          <input id="mb" name="mobile_number" inputmode="tel" autocomplete="tel" placeholder="09171234567" value="{{ old('mobile_number') }}" required>
          <p class="mut small" style="margin:10px 0 0">Use the mobile number linked to your account. The +63 format also works.</p>
        </div>

        <div class="actions"><button class="btn">{{ $label }} <svg class="ic"><use href="#i-lock"/></svg></button></div>
      </form>

      <form method="POST" action="{{ route('pay.cancel', $res->code) }}" onsubmit="return confirm('Cancel this reservation?')" style="margin-top:14px">
        @csrf <button class="btn ghost sm">Cancel reservation</button>
      </form>

      <details>
        <summary>Test payment details</summary>
        <p>Approved cards: 4242 4242 4242 4242 (Visa), 5555 5555 5555 4444 (Mastercard), 3530 1113 3330 0000 (JCB).<br>
        Declined card: 4000 0000 0000 0002. Use any future expiry and any 3-digit CVV.<br>
        GCash / Maya: 09171234567 approves. Any number ending in 0000 is declined (insufficient balance).</p>
      </details>
    </div>

    <aside class="sumcard rv" style="--d:.12s">
      <img src="{{ asset('img/' . $res->room->image) }}" alt="{{ $res->room->name }}">
      <div class="bd">
        <p class="eyebrow">{{ $res->code }}</p>
        <h3>{{ $res->room->name }}</h3>
        <div class="row"><span>Check-in</span><span>{{ $res->check_in->format('M j, Y') }}</span></div>
        <div class="row"><span>Check-out</span><span>{{ $res->check_out->format('M j, Y') }}</span></div>
        <div class="row"><span>Guests</span><span>{{ $res->guests }}</span></div>
        <div class="row"><span>Nights</span><span>{{ $res->nights }}</span></div>
        <div class="row tot"><span>Total</span><b>₱{{ number_format($res->total_amount, 2) }}</b></div>
        <div class="note"><svg class="ic"><use href="#i-shield"/></svg><span>Card numbers and CVV are sent only to the payment service and are never stored.</span></div>
      </div>
    </aside>
  </div>
</div></main>
@endsection

@section('scripts')
<script src="{{ asset('js/pay.js') }}"></script>
@endsection
