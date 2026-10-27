@extends('layouts.panel')
@section('title', __('عملائي اليوم'))

@section('content')
<x-page-header :title="$date->isToday() ? __('عملائي اليوم') : __('سجل خدماتي')" :subtitle="$date->translatedFormat('l j F Y')">
    <form class="flex items-center gap-2">
        <label for="d" class="sr-only">{{ __('التاريخ') }}</label>
        <input id="d" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="field !w-auto" onchange="this.form.submit()">
        @unless ($date->isToday())
            <a href="{{ route('staff.today') }}" class="btn btn-quiet">{{ __('اليوم') }}</a>
        @endunless
    </form>
</x-page-header>

<dl class="grid grid-cols-2 md:grid-cols-4 gap-px bg-line border border-line rounded-xl overflow-hidden mb-6">
    @foreach ([
        [__('عملاء خدمتهم حضوريًا'), $summary['served']],
        [__('تفاعلات عن بُعد'), $summary['remote']],
        [__('لم يحضروا'), $summary['no_show']],
        [__('متوسط مدة الخدمة'), \App\Support\Duration::human($summary['avg_service'] ? (int) $summary['avg_service'] : null)],
    ] as [$label, $value])
        <div class="bg-surface px-5 py-4">
            <dt class="text-sm text-ink-faint">{{ $label }}</dt>
            <dd class="font-display font-bold text-2xl mt-1 ltr-nums text-start">{{ $value }}</dd>
        </div>
    @endforeach
</dl>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('الوقت') }}</th>
                <th>{{ __('الرقم') }}</th>
                <th>{{ __('العميل') }}</th>
                <th>{{ __('النوع') }}</th>
                <th>{{ __('البنك') }}</th>
                <th>{{ __('الحالة') }}</th>
                <th>{{ __('الانتظار') }}</th>
                <th>{{ __('مدة الخدمة') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($visits as $v)
                <tr>
                    <td class="ltr-nums text-ink-soft">{{ ($v->called_at ?? $v->created_at)->format('H:i') }}</td>
                    <td>
                        @if ($v->ticket)
                            <span class="ticket-code {{ $v->customer_type->value === 'buyer' ? 'text-buyer' : 'text-seller' }}">{{ $v->ticket->ticket_no }}</span>
                        @else
                            <span class="text-ink-faint text-sm">{{ __('عن بُعد') }} ({{ $v->source?->label() }})</span>
                        @endif
                    </td>
                    <td><a href="{{ route('staff.customers.show', $v->customer_id) }}" class="font-semibold hover:text-deed">{{ $v->customer->name }}</a></td>
                    <td><x-type-tag :type="$v->customer_type" :label="$v->typeLabel()" /></td>
                    <td class="text-ink-soft">
                        {{ $v->bank?->name ?? '—' }}
                        @if ($v->agent)<span class="block text-xs text-ink-faint">{{ __('الوكيل') }}: {{ $v->agent->name }}</span>@endif
                        <x-deal-with :visit="$v" class="mt-1" />
                    </td>
                    <td><x-status-pill :status="$v->status" /></td>
                    <td class="ltr-nums text-start">{{ \App\Support\Duration::clock($v->wait_seconds) }}</td>
                    <td class="ltr-nums text-start">{{ \App\Support\Duration::clock($v->service_seconds) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-ink-faint">{{ __('لا توجد خدمات مسجلة لك في هذا اليوم.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
