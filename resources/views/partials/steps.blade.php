<div class="steps">
  @foreach ([1 => 'Details', 2 => 'Payment', 3 => 'Confirmed'] as $i => $label)
    @if ($i > 1)<span class="bar"></span>@endif
    <span class="s {{ $i === $n ? 'on' : ($i < $n ? 'done' : '') }}"><span class="n">{{ $i < $n ? '✓' : $i }}</span>{{ $label }}</span>
  @endforeach
</div>
