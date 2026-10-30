@extends('layouts.app')
@section('title', 'Create an account · Casa Grande Hotel')

@section('content')
<main class="auth-page">
  <div class="auth-shell">
    <aside class="auth-showcase">
      <a class="auth-mark" href="{{ route('rooms') }}"><img src="{{ asset('img/logo.png') }}" alt="Casa Grande Hotel"></a>
      <div class="auth-showcase-copy">
        <p class="eyebrow">A more considered stay</p>
        <h2>Make room<br>for <em>the good things.</em></h2>
        <p>Create your account and let us take care of the details.</p>
      </div>
      <span class="auth-showcase-caption">CASA GRANDE HOTEL <i></i> EST. IN COMFORT</span>
    </aside>

    <section class="panel auth-card">
      <div class="auth-heading">
        <p class="eyebrow">Your stay awaits</p>
        <h1 class="h2">Create an account</h1>
        <p class="mut">A few details, then your next stay is closer.</p>
      </div>

      @if ($errors->any())
        <div class="inline-msg" role="alert">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <label for="name">Full name</label>
        <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="Your name" required autofocus>

        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" required>

        <div class="auth-password-row">
          <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required>
          </div>
          <div>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat password" required>
          </div>
        </div>

        <button class="btn auth-submit">Create account <span aria-hidden="true">→</span></button>
      </form>

      <p class="mut auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in <span aria-hidden="true">→</span></a></p>
    </section>
  </div>
</main>
@endsection
