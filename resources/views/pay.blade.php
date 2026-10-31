@extends('layouts.app')
@section('title', 'Payment · Casa Grande Hotel')

@section('content')
@php $sel = old('method', 'card'); $label = 'Confirm demo booking · ₱' . number_format($res->total_amount, 2); @endphp
<main class="page"><div class="wrap">
  @include('partials.steps', ['n' => 2])
  <div class="split">
    <div class="rv">
      <p class="eyebrow">Demo payment</p><h2 class="h2" style="font-size:clamp(2rem,3.6vw,2.8rem)">How would you like to pay?</h2>
      @if (session('error'))<div class="inline-msg" style="margin-top:20px" role="alert">{{ session('error') }}</div>@endif

      <form method="POST" action="{{ route('pay.process', $res->code) }}" id="payForm" data-label="{{ $label }}" data-session-url="{{ route('pay.session', $res->code) }}" data-status-url="{{ route('pay.status', $res->code) }}" data-simulate-base="{{ url('/pay/' . $res->code . '/simulate') }}" data-cancel-base="{{ url('/pay/' . $res->code . '/session') }}" data-done-url="{{ route('done', $res->code) }}" novalidate style="margin-top:24px">
        @csrf
        <div class="seg" role="radiogroup" aria-label="Payment method">
          <label><input type="radio" name="method" value="card" {{ $sel === 'card' ? 'checked' : '' }}><span>Card</span></label>
          <label><input type="radio" name="method" value="gcash" {{ $sel === 'gcash' ? 'checked' : '' }}><span>GCash</span></label>
          <label><input type="radio" name="method" value="maya" {{ $sel === 'maya' ? 'checked' : '' }}><span>Maya</span></label>
        </div>

        <div id="fcard">
          <label for="bank">Issuing bank</label>
          <input id="bank" name="bank" list="bank-options" autocomplete="organization" value="{{ old('bank') }}" maxlength="80" placeholder="Choose or enter your bank" required>
          <datalist id="bank-options">
            @foreach ($banks as $b)<option value="{{ $b }}">@endforeach
          </datalist>
          <label for="cn">Card number</label>
          <div class="fld"><input id="cn" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" required><span class="brand" id="brand"></span></div>
          <label for="ch">Name on card</label>
          <input id="ch" name="card_holder" autocomplete="cc-name" value="{{ old('card_holder') }}" placeholder="Juan Dela Cruz" required>
          <div class="two">
            <div><label for="ex">Expiry</label><input id="ex" name="expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/YY" maxlength="5" required></div>
            <div><label for="cv">CVV</label><input id="cv" name="cvv" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="3" required></div>
          </div>
        </div>

        <div id="fwallet" hidden></div>

        <div class="actions"><button class="btn">{{ $label }} <svg class="ic"><use href="#i-lock"/></svg></button></div>
      </form>

      <form method="POST" action="{{ route('pay.cancel', $res->code) }}" onsubmit="return confirm('Cancel this reservation?')" style="margin-top:14px">
        @csrf <button class="btn ghost sm">Cancel reservation</button>
      </form>

      <details>
        <summary>Demo payment details</summary>
        <p>Approved cards: 4242 4242 4242 4242 (Visa), 5555 5555 5555 4444 (Mastercard), 3530 1113 3330 0000 (JCB).<br>
        Declined card: 4000 0000 0000 0002. Use any future expiry and any 3-digit CVV. Other issuing banks can be entered above.<br>
        Demo QR: select GCash or Maya, then scan the simulated QR and use the local demo button to complete payment. No real wallet account is connected.</p>
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
        <div class="note"><svg class="ic"><use href="#i-shield"/></svg><span>Demo card data goes to the local simulator. Only a masked card number is kept in the demo API log; no issuer authorization occurs.</span></div>
      </div>
    </aside>
  </div>
</div>
<div class="qr-modal" id="qrModal" role="dialog" aria-modal="true" aria-labelledby="qrTitle" hidden>
  <div class="qr-backdrop"></div>
  <div class="qr-dialog" tabindex="-1">
    <p class="eyebrow" id="qrProvider">GCash</p>
    <h2 class="h2" id="qrTitle">Scan to pay</h2>
    <p class="qr-reservation"><span>{{ $res->code }}</span><strong id="qrAmount"></strong></p>
    <div class="qr-image-wrap"><img id="qrImage" class="qr-image" alt="Payment QR code"></div>
    <p class="qr-instructions">Open your GCash/Maya app → Scan QR → confirm payment</p>
    <p class="qr-countdown" id="qrCountdown">5:00</p>
    <p class="qr-status" id="qrStatus" aria-live="polite">Waiting for payment…</p>
    @if(app()->environment(['local','testing']))
      <button type="button" class="btn block" id="simulatePayment">Demo: Simulate payment done</button>
    @endif
    <button type="button" class="qr-cancel-link" id="qrCancel">Cancel</button>
  </div>
</div>
</main>
@endsection

@section('scripts')
<script src="{{ asset('js/pay.js') }}"></script>
@endsection
