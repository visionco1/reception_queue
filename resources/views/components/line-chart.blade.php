@props(['labels' => [], 'series' => [], 'unit' => '', 'height' => 220, 'title' => ''])
{{--
    مخطط خطي بسيط (SVG) لاتجاه أداء الموظف مقابل متوسط الفريق.
    الزمن يسير من اليمين لليسار في الواجهة العربية. خطوط 2px، نقاط بحلقة بلون السطح،
    وتلميح عند المرور لكل يوم. القيم كاملة في الجدول أسفل المخطط.
--}}
@php
    $W = 720; $H = (int) $height; $padX = 40; $padTop = 16; $padBottom = 30;
    $n = max(1, count($labels));
    $all = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null);
    $max = max(1, (float) $all->max());
    $nice = $max <= 4 ? 4 : ($max <= 10 ? 10 : ($max <= 20 ? 20 : (int) (ceil($max / 10) * 10)));
    $step = $n > 1 ? ($W - 2 * $padX) / ($n - 1) : 0;
    $x = fn ($i) => $n > 1 ? $W - $padX - $i * $step : $W / 2;
    $y = fn ($v) => $padTop + ($H - $padTop - $padBottom) * (1 - $v / $nice);
    $every = max(1, (int) ceil($n / 10));
    $points = function ($values) use ($x, $y) {
        $segs = []; $cur = [];
        foreach ($values as $i => $v) {
            if ($v === null) { if ($cur) { $segs[] = $cur; $cur = []; } continue; }
            $cur[] = round($x($i), 1).','.round($y($v), 1);
        }
        if ($cur) { $segs[] = $cur; }
        return $segs;
    };
@endphp
<figure {{ $attributes->class('relative') }} x-data="{ hover: null }" @mouseleave="hover = null">
    <div class="flex flex-wrap items-center gap-4 text-sm text-ink-soft mb-2">
        @foreach ($series as $s)
            <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-[3px] rounded" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</span>
        @endforeach
    </div>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-auto overflow-visible" role="img" aria-label="{{ $title }}">
        @foreach ([0, $nice / 2, $nice] as $tick)
            <line x1="{{ $padX }}" x2="{{ $W - $padX }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="#EEE6D9" stroke-width="1"/>
            <text x="{{ $W - $padX + 8 }}" y="{{ $y($tick) + 4 }}" font-size="11" fill="#6B716B" text-anchor="start" font-family="Inter">{{ rtrim(rtrim(number_format($tick, 1), '0'), '.') }}</text>
        @endforeach
        @foreach ($labels as $i => $label)
            @if ($i % $every === 0)
                <text x="{{ $x($i) }}" y="{{ $H - 8 }}" font-size="11" fill="#6B716B" text-anchor="middle" font-family="Inter">{{ $label }}</text>
            @endif
        @endforeach
        <line x-show="hover !== null" x-cloak :x1="hover === null ? 0 : {{ $W - $padX }} - hover * {{ $step }}" :x2="hover === null ? 0 : {{ $W - $padX }} - hover * {{ $step }}"
              y1="{{ $padTop }}" y2="{{ $H - $padBottom }}" stroke="#BFB6A6" stroke-width="1"/>
        @foreach ($series as $s)
            @foreach ($points($s['values']) as $seg)
                @if (count($seg) > 1)
                    <polyline points="{{ implode(' ', $seg) }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                @endif
            @endforeach
            @php($last = collect($s['values'])->filter(fn ($v) => $v !== null)->keys()->last())
            @if ($last !== null)
                <circle cx="{{ $x($last) }}" cy="{{ $y($s['values'][$last]) }}" r="4.5" fill="{{ $s['color'] }}" stroke="#fff" stroke-width="2"/>
            @endif
            @foreach ($s['values'] as $i => $v)
                @if ($v !== null)
                    <circle x-show="hover === {{ $i }}" x-cloak cx="{{ $x($i) }}" cy="{{ $y($v) }}" r="4.5" fill="{{ $s['color'] }}" stroke="#fff" stroke-width="2"/>
                @endif
            @endforeach
        @endforeach
        @foreach ($labels as $i => $label)
            <rect x="{{ $x($i) - max(6, $step / 2) }}" y="0" width="{{ max(12, $step) }}" height="{{ $H }}" fill="transparent" @mouseenter="hover = {{ $i }}" @touchstart.passive="hover = {{ $i }}"/>
        @endforeach
    </svg>
    @foreach ($labels as $i => $label)
        <div x-show="hover === {{ $i }}" x-cloak class="absolute top-8 pointer-events-none z-10 rounded-[10px] bg-olive text-ivory text-xs px-3 py-2 shadow-lg whitespace-nowrap"
             style="left: {{ round(($x($i) / $W) * 100, 2) }}%; transform: translateX({{ $x($i) / $W > 0.6 ? '-105%' : '5%' }})">
            <div class="font-bold mb-1 font-num">{{ $label }}</div>
            @foreach ($series as $s)
                <div class="flex items-center gap-2"><span class="inline-block w-2.5 h-2.5 rounded-full" style="background: {{ $s['color'] }}; box-shadow: 0 0 0 1px #fff"></span>{{ $s['name'] }}: <b class="font-num">{{ $s['values'][$i] ?? '—' }}</b> {{ $unit }}</div>
            @endforeach
        </div>
    @endforeach
</figure>
