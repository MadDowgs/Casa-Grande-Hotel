<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title', 'Admin · Casa Grande Hotel')</title>
  <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
  <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
<header class="admin-nav">
  <a class="admin-brand" href="{{ route('admin.dashboard') }}">
    <img src="{{ asset('img/logo.png') }}" alt="Casa Grande Hotel">
    <span>ADMIN</span>
  </a>
  <nav aria-label="Administration">
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a href="{{ route('admin.reservations') }}">Reservations</a>
    <a href="{{ route('admin.rooms') }}">Rooms</a>
    <a href="{{ route('admin.users') }}">Accounts</a>
  </nav>
  <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="btn ghost sm">Log out</button>
  </form>
</header>

<main class="admin-main">
  @if (session('error'))<div class="inline-msg" role="alert">{{ session('error') }}</div>@endif
  @if (session('notice'))<div class="admin-notice" role="status">{{ session('notice') }}</div>@endif
  @if ($errors->any())<div class="inline-msg" role="alert">{{ $errors->first() }}</div>@endif
  @yield('content')
</main>
</body>
</html>
