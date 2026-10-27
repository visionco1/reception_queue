@extends('layouts.admin')
@section('title', __('نظرة اليوم'))

@php
    use App\Support\Duration;
    $t = $today['totals'];
@endphp

@section('admin')
<x-page-header :title="__('نظرة اليوم')" :subtitle="now()->translatedFormat('l j F Y')">
    <a href="{{ route('admin.reports') }}" class="btn btn-quiet">{{ __('التقارير التفصيلية') }}</a>
</x-page-header>

@if ($waFailed)
    <a href="{{ route('admin.whatsapp', ['status' => 'failed']) }}" class="flash flash-error block mb-6 hover:underline">
        {{ __('تعذر إرسال :n رسالة واتساب خلال آخر 7 أيام. راجع سجل الرسائل لإعادة الإرسال.', ['n' => $waFailed]) }}
    </a>
@endif

<dl class="grid grid-cols-2 lg:grid-cols-5 gap-px bg-line border border-line rounded-xl overflow-hidden mb-6">
    @foreach ([
        [__('في الانتظار الآن'), $waiting->count()],
        [__('مع الموظفين الآن'), $active->count()],
        [__('عملاء اليوم'), $t['visits']],
        [__('متوسط الانتظار اليوم'), Duration::human($t['avg_wait'])],
        [__('متوسط الخدمة اليوم'), Duration::human($t['avg_service'])],
    ] as [$label, $value])
        <div class="bg-surface px-5 py-4">
            <dt class="text-sm text-ink-faint">{{ $label }}</dt>
            <dd class="font-semibold text-[1.65rem] leading-tight mt-1">{{ $value }}</dd>
        </div>
    @endforeach
</dl>

<div class="grid xl:grid-cols-3 gap-6">
    <section class="panel xl:col-span-2 min-w-0">
        <h2 class="px-5 py-3.5 border-b border-line font-semibold">{{ __('المكاتب الآن') }}</h2>
        <table class="data-table">
            <thead><tr><th>{{ __('المكتب') }}</th><th>{{ __('الموظف') }}</th><th>{{ __('العميل') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('منذ') }}</th></tr></thead>
            <tbody>
                @forelse ($active->sortBy(fn ($v) => $v->desk?->number, SORT_NATURAL) as $v)
                    <tr>
                        <td class="font-display font-bold ltr-nums text-start">{{ $v->desk?->number }}</td>
                        <td>{{ $v->employee?->name }}</td>
                        <td><span class="inline-flex items-baseline gap-2"><span class="ticket-code {{ $v->customer_type->value === 'buyer' ? 'text-buyer' : 'text-seller' }}">{{ $v->ticket?->ticket_no }}</span>{{ $v->customer->name }}</span></td>
                        <td><x-status-pill :status="$v->status" /></td>
                        <td class="text-ink-soft">{{ Duration::human((int) $v->called_at?->diffInSeconds(now())) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-ink-faint py-8">{{ __('لا يخدم أي موظف عميلًا حضوريًا الآن.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2 class="px-5 py-3.5 border-y border-line font-semibold mt-2">{{ __('قائمة الانتظار') }}</h2>
        <table class="data-table">
            <thead><tr><th>{{ __('الرقم') }}</th><th>{{ __('العميل') }}</th><th>{{ __('النوع') }}</th><th>{{ __('مدة الانتظار') }}</th></tr></thead>
            <tbody>
                @forelse ($waiting as $v)
                    <tr>
                        <td><span class="ticket-code {{ $v->customer_type->value === 'buyer' ? 'text-buyer' : 'text-seller' }}">{{ $v->ticket->ticket_no }}</span></td>
                        <td>{{ $v->customer->name }}</td>
                        <td><x-type-tag :type="$v->customer_type" /></td>
                        <td>{{ Duration::human((int) $v->ticket->issued_at->diffInSeconds(now())) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-ink-faint py-8">{{ __('لا يوجد عملاء في الانتظار.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="space-y-6">
        <section class="panel">
            <h2 class="px-5 py-3.5 border-b border-line font-semibold">{{ __('آخر النداءات على الشاشة') }}</h2>
            <ul class="px-5 py-2 divide-y divide-line-soft">
                @forelse ($calls as $c)
                    <li class="flex items-center justify-between py-2.5">
                        <span><span class="ticket-code text-lg">{{ $c->ticket_no }}</span> <span class="text-ink-faint text-sm">{{ __('مكتب :n', ['n' => $c->desk_label]) }}</span></span>
                        <span class="text-ink-faint text-sm ltr-nums">{{ $c->created_at->format('H:i') }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-ink-faint">{{ __('لا توجد نداءات اليوم.') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="panel p-5 text-sm space-y-2">
            <h2 class="font-semibold mb-2">{{ __('اليوم حسب النوع') }}</h2>
            <p class="flex justify-between"><span>{{ __('مشتري') }}</span><b class="ltr-nums">{{ $t['buyers'] }}</b></p>
            <p class="flex justify-between"><span>{{ __('بائع') }}</span><b class="ltr-nums">{{ $t['sellers'] }}</b></p>
            <p class="flex justify-between"><span>{{ __('حضوري') }}</span><b class="ltr-nums">{{ $t['in_person'] }}</b></p>
            <p class="flex justify-between"><span>{{ __('عن بُعد') }}</span><b class="ltr-nums">{{ $t['remote'] }}</b></p>
            <p class="flex justify-between"><span>{{ __('لم يحضر') }}</span><b class="ltr-nums">{{ $t['no_show'] }}</b></p>
        </section>
    </div>
</div>
@endsection
