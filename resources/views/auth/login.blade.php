@extends('layouts.app')
@section('title', 'Log in · Casa Grande Hotel')

@section('content')
<main class="auth-page">
  <div class="auth-shell">
    <aside class="auth-showcase">
      <a class="auth-mark" href="{{ route('rooms') }}"><img src="{{ asset('img/logo.png') }}" alt="Casa Grande Hotel"></a>
      <div class="auth-showcase-copy">
        <p class="eyebrow">A more considered stay</p>
        <h2>Arrive into<br><em>something special.</em></h2>
        <p>Thoughtful hospitality, warm light and a welcome that feels like yours.</p>
      </div>
      <span class="auth-showcase-caption">CASA GRANDE HOTEL <i></i> EST. IN COMFORT</span>
    </aside>

    <section class="panel auth-card">
      <div class="auth-heading">
        <p class="eyebrow">Welcome back</p>
        <h1 class="h2">Log in</h1>
        <p class="mut">Continue to your stay and reservations.</p>
      </div>

      @if ($errors->any())
        <div class="inline-msg" role="alert">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('login.authenticate') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>

        <label class="remember"><input name="remember" type="checkbox" value="1"> Keep me signed in</label>
        <button class="btn auth-submit">Sign in <span aria-hidden="true">→</span></button>
      </form>

      <p class="mut auth-switch">New to Casa Grande? <a href="{{ route('register') }}">Create your account <span aria-hidden="true">→</span></a></p>
    </section>
  </div>
</main>
@endsection
