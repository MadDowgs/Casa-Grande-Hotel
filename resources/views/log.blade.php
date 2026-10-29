@extends('layouts.app')
@section('title', 'API log · Casa Grande Hotel')

@section('content')
<main class="page"><div class="wrap">
  <div class="rv" style="display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:34px">
    <div><p class="eyebrow">Integration</p><h2 class="h2">API log</h2>
      <p class="mut" style="margin:12px 0 0;max-width:560px">Calls from the Hotel system (System 1) to the Payment API (System 2). Card numbers and CVV are masked.</p></div>
    <form method="POST" action="{{ route('log.clear') }}">@csrf<button class="btn ghost sm">Clear log</button></form>
  </div>

  @forelse ($logs as $l)
  <div class="log rv">
    <div class="hd"><span class="m">{{ $l->method }}</span><span>{{ $l->url }}</span>
      <span class="{{ $l->status >= 200 && $l->status < 300 ? 'ok' : 'bad' }}">HTTP {{ $l->status ?: 'ERR' }}</span>
      <span style="margin-left:auto;color:#6f685f">{{ $l->created_at->format('H:i:s') }}</span></div>
    <div class="bd"><b>Headers</b>
{{ collect($l->headers)->map(fn ($v, $k) => "$k: $v")->implode("\n") }}

<b>Request</b>
{{ json_encode($l->request, JSON_PRETTY_PRINT) }}

<b>Response</b>
{{ json_encode($l->response, JSON_PRETTY_PRINT) }}</div>
  </div>
  @empty
  <p class="mut">No calls yet. Make a payment and it will appear here.</p>
  @endforelse
</div></main>
@endsection
