{{-- أداء موظف مقابل متوسط الفريق: البطاقات، المخططات، الجدول اليومي --}}
@php
    use App\Support\Duration;
    $m = fn ($s) => $s === null ? null : round($s / 60);
    $delta = function ($value, $teamValue, bool $lowerIsBetter = false, bool $neutral = false) {
        if ($value === null || ! $teamValue) {
            return null;
        }
        $pct = (int) round(($value - $teamValue) / $teamValue * 100);
        $good = $neutral ? null : ($lowerIsBetter ? $pct <= 0 : $pct >= 0);

        return ['pct' => $pct, 'good' => $good];
    };
    $tiles = $row ? [
        [__('عملاء خدمهم'), $row['served'], __('عميل'), $team['served'], $delta($row['served'], $team['served'])],
        [__('متوسط مدة الخدمة'), $m($row['avg_service']), __('دقيقة'), $m($team['avg_service']), $delta($row['avg_service'], $team['avg_service'], neutral: true)],
        [__('متوسط انتظار عملائه قبل النداء'), $m($row['avg_call_wait']), __('دقيقة'), $m($team['avg_call_wait']), $delta($row['avg_call_wait'], $team['avg_call_wait'], lowerIsBetter: true)],
        [__('متوسط الخمول بين العملاء'), $m($row['avg_idle']), __('دقيقة'), $m($team['avg_idle']), $delta($row['avg_idle'], $team['avg_idle'], lowerIsBetter: true)],
    ] : [];
    $labels = collect($trend)->pluck('label')->all();
@endphp

<form class="panel p-3 mb-5 flex flex-wrap items-end gap-3">
    <nav class="seg">
        @foreach (['today' => __('اليوم'), 'week' => __('هذا الأسبوع'), 'month' => __('هذا الشهر'), 'last_month' => __('الشهر الماضي')] as $key => $label)
            <a href="{{ route($routeName, array_merge($routeParams, ['preset' => $key])) }}" @class(['is-on' => $preset === $key])>{{ $label }}</a>
        @endforeach
    </nav>
    <div>
        <label for="from" class="field-label !text-xs">{{ __('من') }}</label>
        <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="field !py-1.5">
    </div>
    <div>
        <label for="to" class="field-label !text-xs">{{ __('إلى') }}</label>
        <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="field !py-1.5">
    </div>
    <button class="btn btn-primary !py-1.5">{{ __('عرض') }}</button>
    @isset($employees)
        <div class="ms-auto">
            <label for="emp" class="field-label !text-xs">{{ __('الموظف') }}</label>
            <select id="emp" class="field !py-1.5" onchange="location.href = this.value">
                @foreach ($employees as $e)
                    <option value="{{ route('admin.reports.employee', array_merge(['user' => $e->id], request()->only('preset', 'from', 'to'))) }}" @selected($e->id === $user->id)>{{ $e->name }}</option>
                @endforeach
            </select>
        </div>
    @endisset
</form>

@if (! $row)
    <div class="panel py-14 text-center text-ink-faint">{{ __('لا توجد خدمات مسجلة في هذه الفترة.') }}</div>
@else
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <span class="rounded-full bg-olive text-gold font-bold px-4 py-1.5">{{ __('الترتيب') }} <span class="font-num">#{{ $row['rank'] }}</span> <span class="text-ivory/80 font-normal">{{ __('من :n موظفين', ['n' => $count]) }}</span></span>
        <span class="text-sm text-ink-faint">{{ __('الترتيب حسب عدد من خدمهم، ثم الأقصر في مدة الخدمة.') }}</span>
    </div>

    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
        @foreach ($tiles as [$label, $value, $unit, $teamValue, $d])
            <div class="panel !rounded-[16px] p-4">
                <div class="text-sm text-ink-faint">{{ $label }}</div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-num font-semibold text-[2rem] leading-none">{{ $value ?? '—' }}</span>
                    <span class="text-sm text-ink-faint">{{ $value !== null ? $unit : '' }}</span>
                    @if ($d)
                        <span @class([
                            'ms-auto text-xs font-bold rounded-full px-2 py-0.5 font-num',
                            'bg-ok-soft text-ok' => $d['good'] === true,
                            'bg-danger-soft text-danger' => $d['good'] === false,
                            'bg-shade text-ink-soft' => $d['good'] === null,
                        ]) dir="ltr">{{ $d['pct'] > 0 ? '+' : '' }}{{ $d['pct'] }}%</span>
                    @endif
                </div>
                <div class="mt-2 text-xs text-ink-faint">{{ __('متوسط الفريق') }}: <b class="font-num text-ink-soft">{{ $teamValue ?? '—' }}</b> {{ $teamValue !== null ? $unit : '' }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-5 mb-5">
        <section class="panel p-5">
            <h2 class="font-bold mb-3">{{ __('عملاء خدمهم :name مقابل متوسط الفريق', ['name' => $user->name]) }}</h2>
            <x-line-chart :labels="$labels" unit="{{ __('عميل') }}" :title="__('عدد العملاء')" :series="[
                ['name' => $user->name, 'color' => '#1F261F', 'values' => collect($trend)->pluck('served')->all()],
                ['name' => __('متوسط الفريق'), 'color' => '#BB9860', 'values' => collect($trend)->pluck('team_served')->all()],
            ]" />
        </section>
        <section class="panel p-5">
            <h2 class="font-bold mb-3">{{ __('متوسط مدة الخدمة بالدقائق') }}</h2>
            <x-line-chart :labels="$labels" unit="{{ __('د') }}" :title="__('متوسط مدة الخدمة')" :series="[
                ['name' => $user->name, 'color' => '#1F261F', 'values' => collect($trend)->pluck('avg_service')->all()],
                ['name' => __('متوسط الفريق'), 'color' => '#BB9860', 'values' => collect($trend)->pluck('team_avg_service')->all()],
            ]" />
        </section>
    </div>

    <div class="grid lg:grid-cols-[1fr_20rem] gap-5">
        <section class="panel overflow-x-auto">
            <h2 class="font-bold px-5 pt-5 pb-3">{{ __('التفاصيل اليومية') }}</h2>
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('اليوم') }}</th><th>{{ __('خدمهم') }}</th><th>{{ __('متوسط الفريق') }}</th>
                    <th>{{ __('مدة الخدمة') }}</th><th>{{ __('الفريق') }}</th><th>{{ __('الخمول') }}</th><th>{{ __('الفريق') }}</th>
                </tr></thead>
                <tbody>
                    @foreach (array_reverse($trend) as $p)
                        @continue($p['served'] === null)
                        <tr>
                            <td class="font-num">{{ $p['label'] }}</td>
                            <td class="ltr-nums font-bold">{{ $p['served'] }}</td>
                            <td class="ltr-nums text-ink-soft">{{ $p['team_served'] }}</td>
                            <td class="ltr-nums">{{ $p['avg_service'] ?? '—' }}</td>
                            <td class="ltr-nums text-ink-soft">{{ $p['team_avg_service'] ?? '—' }}</td>
                            <td class="ltr-nums">{{ $p['avg_idle'] ?? '—' }}</td>
                            <td class="ltr-nums text-ink-soft">{{ $p['team_avg_idle'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="px-5 py-3 text-xs text-ink-faint border-t border-line-soft">{{ __('المدد بالدقائق. الخمول: الفترة بين إنهاء عميل ونداء التالي في نفس اليوم.') }}</p>
        </section>
        <section class="panel p-5">
            <h2 class="font-bold mb-4">{{ __('نتائج صفقاته') }}</h2>
            @php($done = $row['served'] - $row['missing_docs'] - $row['cancelled'])
            <ul class="space-y-3 text-sm">
                @foreach ([[__('مكتملة'), $done, 'completed'], [__('ناقصة أوراق'), $row['missing_docs'], 'missing_docs'], [__('ملغاة'), $row['cancelled'], 'cancelled']] as [$label, $n, $key])
                    <li class="flex items-center justify-between"><span class="status-pill outcome-{{ $key }}">{{ $label }}</span><b class="font-num text-lg">{{ $n }}</b></li>
                @endforeach
                <li class="flex items-center justify-between pt-3 border-t border-line-soft"><span class="text-ink-faint">{{ __('لم يحضر') }}</span><b class="font-num">{{ $row['no_show'] }}</b></li>
            </ul>
        </section>
    </div>
@endif
