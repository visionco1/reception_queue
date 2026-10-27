@extends('layouts.admin')
@section('title', __('تفاصيل الزيارة'))

@php use App\Support\Duration; @endphp

@section('admin')
<x-page-header :title="$visit->customer->name" :subtitle="$visit->created_at->translatedFormat('l j F Y، H:i')">
    <a href="{{ route('staff.customers.show', $visit->customer) }}" class="btn btn-quiet">{{ __('سجل العميل الكامل') }}</a>
</x-page-header>

<div class="grid lg:grid-cols-[20rem_minmax(0,1fr)] gap-6 items-start">
    <section class="panel p-5">
        @if ($visit->ticket)
            <div class="ticket-code text-5xl {{ $visit->customer_type->value === 'buyer' ? 'text-buyer' : 'text-seller' }}">{{ $visit->ticket->ticket_no }}</div>
        @endif
        <dl class="mt-4 text-[0.9375rem] divide-y divide-line-soft">
            <div class="flex justify-between gap-4 py-2"><dt class="text-ink-faint">{{ __('الحالة') }}</dt><dd><x-status-pill :status="$visit->status" /></dd></div>
            @foreach ([
                [__('طريقة الدخول'), $visit->channel->label().($visit->source ? ' ('.$visit->source->label().')' : '')],
                [__('النوع'), $visit->typeLabel()],
                [__('البنك'), $visit->bank?->name ?? '—'],
                [__('وكيل البنك'), $visit->agent?->name ?? '—'],
                [__('الموظف'), $visit->employee?->name ?? '—'],
                [__('المكتب'), $visit->desk?->number ?? '—'],
                [__('مدة الانتظار'), Duration::human($visit->wait_seconds)],
                [__('مدة الخدمة'), Duration::human($visit->service_seconds)],
                [__('عميل متكرر'), $visit->is_returning ? __('نعم') : __('لا')],
            ] as [$k, $v])
                <div class="flex justify-between gap-4 py-2"><dt class="text-ink-faint">{{ $k }}</dt><dd class="text-end">{{ $v }}</dd></div>
            @endforeach
            <div class="flex justify-between gap-4 py-2"><dt class="text-ink-faint">{{ __('الجوال') }}</dt><dd class="ltr-nums">{{ $visit->customer->displayMobile() }}</dd></div>
        </dl>
        @if ($visit->notes)
            <p class="mt-4 text-sm bg-paper rounded-lg p-3">{{ $visit->notes }}</p>
        @endif
    </section>

    <div class="space-y-6 min-w-0">
        @if ($visit->deal && $visit->deal->visits->count() > 1)
            <section class="panel">
                <h2 class="px-5 py-3.5 border-b border-line font-semibold">{{ __('أطراف الصفقة') }}</h2>
                <table class="data-table">
                    <thead><tr><th>{{ __('الرقم') }}</th><th>{{ __('العميل') }}</th><th>{{ __('النوع') }}</th><th>{{ __('الحالة') }}</th></tr></thead>
                    <tbody>
                        @foreach ($visit->deal->visits as $p)
                            <tr @class(['bg-deed-soft/40' => $p->id === $visit->id])>
                                <td>@if ($p->ticket)<span class="ticket-code">{{ $p->ticket->ticket_no }}</span>@else <span class="text-ink-faint">{{ __('عن بُعد') }}</span>@endif</td>
                                <td><a href="{{ route('admin.visits.show', $p) }}" class="font-semibold hover:text-deed">{{ $p->customer->name }}</a></td>
                                <td><x-type-tag :type="$p->customer_type" :label="$p->typeLabel()" /></td>
                                <td><x-status-pill :status="$p->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section class="panel">
            <h2 class="px-5 py-3.5 border-b border-line font-semibold">{{ __('تسلسل الحالات') }}</h2>
            <ol class="px-5 py-3">
                @foreach ($visit->statusLogs as $log)
                    <li class="grid grid-cols-[4.5rem_1fr] gap-4 py-2.5 border-b border-line-soft last:border-b-0">
                        <span class="ltr-nums text-ink-faint text-sm text-start">{{ $log->created_at->format('H:i:s') }}</span>
                        <span>
                            <x-status-pill :status="$log->to_status" />
                            <span class="text-sm text-ink-soft ms-2">{{ $log->user?->name ?? __('النظام / الآيباد') }}</span>
                            @if ($log->note)<span class="text-sm text-ink-faint ms-1">({{ $log->note }})</span>@endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="panel">
            <h2 class="px-5 py-3.5 border-b border-line font-semibold">{{ __('رسائل واتساب') }}</h2>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ __('الرسالة') }}</th><th>{{ __('النص') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('وقت الإرسال') }}</th></tr></thead>
                    <tbody>
                        @forelse ($visit->whatsappLogs as $log)
                            <tr>
                                <td class="whitespace-nowrap">{{ $log->message_key->label() }}</td>
                                <td class="text-sm text-ink-soft max-w-md">{{ $log->body }}</td>
                                <td class="whitespace-nowrap">{{ \App\Models\WhatsappLog::statusLabel($log->status) }}</td>
                                <td class="ltr-nums text-start text-sm">{{ $log->sent_at?->format('H:i:s') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-ink-faint py-6">{{ $visit->isInPerson() ? __('لم تُرسل رسائل لهذه الزيارة.') : __('لا تُرسل رسائل الطابور للعملاء عن بُعد.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
