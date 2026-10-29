<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'Casa Grande Hotel')</title>
<meta name="theme-color" content="#0a0908">
<meta name="description" content="Casa Grande Hotel: reserve your room online and pay securely with card, GCash or Maya.">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
<script>document.documentElement.classList.add('js')</script>
</head>
<body>
@include('partials.icons')

<header class="nav" id="nav">
  <a class="logo" href="{{ route('rooms') }}"><img src="{{ asset('img/logo.png') }}" alt="Casa Grande Hotel"></a>
  <nav class="links" aria-label="Main">
    <a href="{{ route('rooms') }}#rooms" class="{{ request()->routeIs('rooms', 'book') ? 'on' : '' }}">Rooms</a>
    <a href="{{ route('rooms') }}#experience">Experience</a>
    <a href="{{ route('reservations') }}" class="{{ request()->routeIs('reservations') ? 'on' : '' }}">Reservations</a>
    <a href="{{ route('log') }}" class="{{ request()->routeIs('log') ? 'on' : '' }}">API log</a>
  </nav>
  <div class="nav-r">
    <a class="btn sm" href="{{ route('rooms') }}#rooms">Book now</a>
    <button class="burger" id="burger" aria-label="Menu"><svg class="ic" style="width:26px;height:26px"><use href="#i-menu"/></svg></button>
  </div>
</header>

@if (session('error') && ! request()->routeIs('book', 'pay'))<div class="flash bad" role="alert"><span>{{ session('error') }}</span><button aria-label="Close">&times;</button></div>@endif
@if (session('notice'))<div class="flash ok" role="status"><span>{{ session('notice') }}</span><button aria-label="Close">&times;</button></div>@endif

@yield('content')

<footer class="ft">
  <div class="wrap">
    <div class="ft-g">
      <div><img src="{{ asset('img/logo.png') }}" alt="Casa Grande Hotel"><p class="mut small" style="max-width:300px;margin:0">Quiet luxury, warm light and a garden calm in every stay.</p></div>
      <div><h4>Explore</h4><a href="{{ route('rooms') }}#rooms">Rooms &amp; suites</a><a href="{{ route('rooms') }}#experience">Experience</a><a href="{{ route('reservations') }}">My reservations</a></div>
      <div><h4>Payments</h4><span class="mut small">Visa · Mastercard · JCB</span><br><span class="mut small">GCash · Maya</span></div>
    </div>
    <div class="ft-b"><span>&copy; {{ date('Y') }} Casa Grande Hotel</span><span>CIT 305 · Systems Integration and Architecture 1</span></div>
  </div>
</footer>

<script src="{{ asset('js/app.js') }}"></script>
@yield('scripts')
</body>
</html>
