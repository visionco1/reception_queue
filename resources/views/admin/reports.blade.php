@extends('layouts.admin')
@section('title', __('التقارير'))

@php
    use App\Support\Duration;
    $t = $report['totals'];
    $series = $report['series'];
    $maxSeries = max(1, collect($series)->max('total'));
    $tickTop = $maxSeries <= 4 ? 4 : ($maxSeries <= 10 ? 10 : (int) (ceil($maxSeries / 10) * 10));
    $labelEvery = max(1, (int) ceil(count($series) / 15));
    $pct = fn ($a, $b) => $b > 0 ? round($a / $b * 100) : 0;
    $hours = $report['hours'];
    $maxHour = max(1, collect($hours)->max('count'));
    $peak = collect($hours)->sortByDesc('count')->first();
    $banks = collect($report['banks']);
    $maxBank = max(1, $banks->max('count'));
    $range = $report['from']->isSameDay($report['to'])
        ? $report['from']->translatedFormat('j F Y')
        : $report['from']->translatedFormat('j F').' – '.$report['to']->translatedFormat('j F Y');
@endphp

@section('admin')
<x-page-header :title="__('التقارير')" :subtitle="$range">
    <a class="btn btn-quiet" href="{{ route('admin.export.visits', ['from' => $report['from']->toDateString(), 'to' => $report['to']->toDateString()]) }}">{{ __('تصدير زيارات الفترة (Excel)') }}</a>
</x-page-header>

{{-- المرشحات في صف واحد --}}
<form class="panel p-3 mb-6 flex flex-wrap items-end gap-3">
    <div class="flex rounded-lg border border-line p-0.5 text-sm">
        @foreach (['today' => __('اليوم'), 'week' => __('هذا الأسبوع'), 'month' => __('هذا الشهر'), 'last_month' => __('الشهر الماضي')] as $key => $label)
            <a href="{{ route('admin.reports', ['preset' => $key]) }}" @class(['px-3 py-1.5 rounded-md', 'bg-ink text-white font-semibold' => $preset === $key, 'text-ink-soft hover:text-ink' => $preset !== $key])>{{ $label }}</a>
        @endforeach
    </div>
    <div>
        <label for="from" class="field-label !text-xs">{{ __('من') }}</label>
        <input id="from" type="date" name="from" value="{{ $report['from']->toDateString() }}" class="field !py-1.5">
    </div>
    <div>
        <label for="to" class="field-label !text-xs">{{ __('إلى') }}</label>
        <input id="to" type="date" name="to" value="{{ $report['to']->toDateString() }}" class="field !py-1.5">
    </div>
    <div>
        <label for="group" class="field-label !text-xs">{{ __('التجميع') }}</label>
        <select id="group" name="group" class="field !py-1.5">
            @foreach (['day' => __('يومي'), 'week' => __('أسبوعي'), 'month' => __('شهري')] as $key => $label)
                <option value="{{ $key }}" @selected($report['group'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary !py-1.5">{{ __('عرض') }}</button>
</form>

{{-- الأرقام الرئيسية --}}
<dl class="grid grid-cols-2 md:grid-cols-4 gap-px bg-line border border-line rounded-xl overflow-hidden mb-6">
    @foreach ([
        [__('إجمالي الزيارات والتفاعلات'), number_format($t['visits']), __(':n عميل مختلف', ['n' => number_format($t['customers'])])],
        [__('متوسط مدة الانتظار'), Duration::human($t['avg_wait']), $t['max_wait'] ? __('الأطول :d', ['d' => Duration::human($t['max_wait'])]) : null],
        [__('متوسط مدة الخدمة'), Duration::human($t['avg_service']), __('للعملاء الحضوريين')],
        [__('العملاء المتكررون'), number_format($report['repeat']['customers']), __(':p٪ من عملاء الفترة', ['p' => $report['repeat']['share']])],
    ] as [$label, $value, $note])
        <div class="bg-surface px-5 py-4">
            <dt class="text-sm text-ink-faint">{{ $label }}</dt>
            <dd class="font-semibold text-[1.65rem] leading-tight mt-1">{{ $value }}</dd>
            @if ($note)<dd class="text-xs text-ink-faint mt-1">{{ $note }}</dd>@endif
        </div>
    @endforeach
</dl>

{{-- عدد العملاء عبر الزمن --}}
<section class="panel p-5 mb-6">
    <div class="flex flex-wrap items-baseline justify-between gap-3 mb-5">
        <h2 class="font-semibold">{{ __('عدد العملاء') }} — {{ ['day' => __('يوميًا'), 'week' => __('أسبوعيًا'), 'month' => __('شهريًا')][$report['group']] }}</h2>
        <div class="flex gap-4 text-sm text-ink-soft">
            <span><span class="key bg-chart-inperson"></span> {{ __('حضوري') }} <b class="text-ink ltr-nums">{{ $t['in_person'] }}</b></span>
            <span><span class="key bg-chart-remote"></span> {{ __('عن بُعد') }} <b class="text-ink ltr-nums">{{ $t['remote'] }}</b></span>
        </div>
    </div>
    @if ($t['visits'] === 0)
        <p class="py-12 text-center text-ink-faint">{{ __('لا توجد زيارات في هذه الفترة.') }}</p>
    @else
        <div class="grid grid-cols-[2.25rem_1fr] gap-2">
            <div class="relative h-56 text-xs text-ink-faint ltr-nums">
                <span class="absolute top-0 -translate-y-1/2 end-0">{{ $tickTop }}</span>
                <span class="absolute top-1/2 -translate-y-1/2 end-0">{{ $tickTop / 2 }}</span>
                <span class="absolute bottom-0 translate-y-1/2 end-0">0</span>
            </div>
            <div class="relative h-56 border-b border-line">
                <div class="absolute inset-x-0 top-0 border-t border-line-soft"></div>
                <div class="absolute inset-x-0 top-1/2 border-t border-line-soft"></div>
                <div class="relative h-full flex items-end gap-px">
                    @foreach ($series as $b)
                        <div class="chart-col" tabindex="0" aria-label="{{ $b['label'] }}: {{ $b['total'] }}">
                            @if ($b['in_person'])<div class="seg bg-chart-inperson" style="height: {{ $b['in_person'] / $tickTop * 100 }}%"></div>@endif
                            @if ($b['remote'])<div class="seg bg-chart-remote" style="height: {{ $b['remote'] / $tickTop * 100 }}%"></div>@endif
                            <span class="chart-tip">{{ $b['label'] }}<br>{{ __('حضوري') }} {{ $b['in_person'] }}، {{ __('عن بُعد') }} {{ $b['remote'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div></div>
            <div class="flex gap-px text-[0.7rem] text-ink-faint">
                @foreach ($series as $i => $b)
                    <div class="flex-1 min-w-0 flex justify-center"><span class="whitespace-nowrap ltr-nums">{{ $i % $labelEvery === 0 ? $b['short'] : '' }}</span></div>
                @endforeach
            </div>
        </div>
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer text-ink-soft">{{ __('عرض كجدول') }}</summary>
            <table class="data-table mt-2">
                <thead><tr><th>{{ __('الفترة') }}</th><th>{{ __('حضوري') }}</th><th>{{ __('عن بُعد') }}</th><th>{{ __('الإجمالي') }}</th></tr></thead>
                <tbody>
                    @foreach ($series as $b)
                        <tr><td>{{ $b['label'] }}</td><td class="ltr-nums text-start">{{ $b['in_person'] }}</td><td class="ltr-nums text-start">{{ $b['remote'] }}</td><td class="ltr-nums text-start font-semibold">{{ $b['total'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</section>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    {{-- بائع مقابل مشتري، حضوري مقابل عن بعد --}}
    <section class="panel p-5 space-y-7">
        @foreach ([
            [__('نوع العميل'), array_values(array_filter([[__('مشتري'), $t['buyers'], 'bg-chart-buyer'], [__('بائع'), $t['sellers'], 'bg-chart-seller'], $t['others'] ? [__('أنواع أخرى'), $t['others'], 'bg-chart-other'] : null]))],
            [__('الحضوري مقابل عن بُعد'), [[__('حضوري'), $t['in_person'], 'bg-chart-inperson'], [__('عن بُعد'), $t['remote'], 'bg-chart-remote']]],
        ] as [$title, $parts])
            @php($sum = collect($parts)->sum(1))
            <div>
                <h2 class="font-semibold mb-3">{{ $title }}</h2>
                <div class="flex h-6 gap-[2px] rounded overflow-hidden bg-line-soft">
                    @foreach ($parts as [$label, $n, $color])
                        @if ($n)<div class="{{ $color }} h-full" style="width: {{ $pct($n, $sum) }}%" title="{{ $label }}: {{ $n }}"></div>@endif
                    @endforeach
                </div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 mt-2.5 text-sm">
                    @foreach ($parts as [$label, $n, $color])
                        <span><span class="key {{ $color }}"></span> {{ $label }} <b class="ltr-nums">{{ number_format($n) }}</b> <span class="text-ink-faint">({{ $pct($n, $sum) }}٪)</span></span>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="text-sm text-ink-soft border-t border-line-soft pt-4 flex flex-wrap gap-x-6 gap-y-1">
            <span>{{ __('انتهت الخدمة') }}: <b class="text-ink ltr-nums">{{ $t['completed'] }}</b></span>
            <span>{{ __('لم يحضر') }}: <b class="text-ink ltr-nums">{{ $t['no_show'] }}</b></span>
            <span>{{ __('ملغي') }}: <b class="text-ink ltr-nums">{{ $t['cancelled'] }}</b></span>
            <span>{{ __('صفقات مربوطة') }}: <b class="text-ink ltr-nums">{{ $t['deals'] }}</b></span>
        </div>
        @if ($report['other_types'])
            <div class="text-sm border-t border-line-soft pt-4">
                <h3 class="font-semibold mb-2">{{ __('الأنواع الأخرى (عن بُعد)') }}</h3>
                <ul class="flex flex-wrap gap-x-5 gap-y-1 text-ink-soft">
                    @foreach ($report['other_types'] as $ot)
                        <li>{{ $ot['name'] }}: <b class="text-ink ltr-nums">{{ $ot['count'] }}</b></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- أوقات الذروة --}}
    <section class="panel p-5">
        <div class="flex items-baseline justify-between gap-3 mb-5">
            <h2 class="font-semibold">{{ __('أوقات الذروة خلال اليوم') }}</h2>
            @if ($peak && $peak['count'])
                <span class="text-sm text-ink-soft">{{ __('الأعلى عند :h', ['h' => $peak['label']]) }}</span>
            @endif
        </div>
        <div class="h-44 flex items-end gap-px border-b border-line">
            @foreach ($hours as $h)
                <div class="chart-col" tabindex="0" aria-label="{{ $h['label'] }}: {{ $h['count'] }}">
                    @if ($h['count'])<div class="seg bg-chart-inperson" style="height: {{ $h['count'] / $maxHour * 100 }}%"></div>@endif
                    <span class="chart-tip">{{ $h['label'] }}: {{ $h['count'] }} {{ __('تسجيل حضوري') }}</span>
                </div>
            @endforeach
        </div>
        <div class="flex gap-px text-[0.7rem] text-ink-faint mt-1.5">
            @foreach ($hours as $i => $h)
                <div class="flex-1 text-center">{{ $i % 2 === 0 ? $h['label'] : '' }}</div>
            @endforeach
        </div>
        <p class="text-xs text-ink-faint mt-3">{{ __('حسب وقت تسجيل العميل الحضوري من آيباد الاستقبال.') }}</p>
    </section>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- أكثر البنوك ورودًا --}}
    <section class="panel p-5">
        <h2 class="font-semibold mb-4">{{ __('أكثر البنوك ورودًا') }}</h2>
        <ul class="space-y-2.5">
            @forelse ($banks->take(10) as $bank)
                <li class="hbar relative grid grid-cols-[9.5rem_1fr] items-center gap-3 text-sm">
                    <span class="truncate" title="{{ $bank['name'] }}">{{ $bank['name'] }}</span>
                    <span class="flex items-center gap-2">
                        <span class="h-4 rounded-e bg-chart-inperson" style="width: {{ max(1, $bank['count'] / $maxBank * 85) }}%"></span>
                        <b class="ltr-nums">{{ $bank['count'] }}</b>
                    </span>
                    <span class="chart-tip">{{ __('مشتري') }} {{ $bank['buyers'] }}، {{ __('بائع') }} {{ $bank['sellers'] }}</span>
                </li>
            @empty
                <li class="text-ink-faint">{{ __('لا توجد بيانات.') }}</li>
            @endforelse
        </ul>
    </section>

    {{-- أداء الموظفين --}}
    <section class="panel">
        <h2 class="font-semibold px-5 pt-5 pb-3">{{ __('العملاء الذين خدمهم كل موظف') }}</h2>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>{{ __('الموظف') }}</th><th>{{ __('حضوري') }}</th><th>{{ __('عن بُعد') }}</th><th>{{ __('لم يحضر') }}</th><th>{{ __('متوسط الخدمة') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($report['employees'] as $e)
                        <tr>
                            <td class="font-semibold">{{ $e['name'] }}</td>
                            <td class="ltr-nums text-start">{{ $e['served'] }}</td>
                            <td class="ltr-nums text-start">{{ $e['remote'] }}</td>
                            <td class="ltr-nums text-start">{{ $e['no_show'] }}</td>
                            <td>{{ Duration::human($e['avg_service']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-ink-faint py-8">{{ __('لا توجد بيانات.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel mt-6">
    <h2 class="font-semibold px-5 pt-5 pb-3">{{ __('حسب وكيل البنك') }}</h2>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('الوكيل') }}</th><th>{{ __('البنك') }}</th><th>{{ __('بائعون') }}</th><th>{{ __('مشترون') }}</th><th>{{ __('صفقات مربوطة') }}</th><th>{{ __('الإجمالي') }}</th></tr></thead>
            <tbody>
                @forelse ($report['agents'] as $a)
                    <tr>
                        <td class="font-semibold">{{ $a['name'] }}</td>
                        <td class="text-ink-soft">{{ $a['bank'] }}</td>
                        <td class="ltr-nums text-start">{{ $a['sellers'] }}</td>
                        <td class="ltr-nums text-start">{{ $a['buyers'] }}</td>
                        <td class="ltr-nums text-start">{{ $a['deals'] }}</td>
                        <td class="ltr-nums text-start font-semibold">{{ $a['count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-ink-faint py-8">{{ __('لا توجد زيارات مسجلة بوكيل في هذه الفترة.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
