@extends('layouts.panel')
@section('title', $customer->name)

@section('content')
<x-page-header :title="$customer->name" :subtitle="__('عميل منذ :date', ['date' => $customer->created_at->translatedFormat('j F Y')])">
    <button type="button" class="btn btn-primary" x-data
            @click="$dispatch('open-remote', { mobile: @js('0'.substr($customer->mobile, strlen(\App\Support\Phone::countryCode()))), name: @js($customer->name) })">
        {{ __('تسجيل تفاعل جديد') }}
    </button>
</x-page-header>

<div class="grid lg:grid-cols-[22rem_minmax(0,1fr)] gap-6 items-start">
    <section class="panel p-5">
        <h2 class="font-semibold mb-4">{{ __('البيانات الأساسية') }}</h2>
        <form method="POST" action="{{ route('staff.customers.update', $customer) }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label for="c-name" class="field-label">{{ __('الاسم') }}</label>
                <input id="c-name" name="name" value="{{ old('name', $customer->name) }}" class="field" required maxlength="100">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="c-mobile" class="field-label">{{ __('رقم الجوال') }}</label>
                <input id="c-mobile" name="mobile" value="{{ old('mobile', $customer->displayMobile()) }}" class="field ltr-nums" dir="ltr" required>
                @error('mobile')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="c-notes" class="field-label">{{ __('ملاحظات') }}</label>
                <textarea id="c-notes" name="notes" rows="3" class="field" maxlength="2000">{{ old('notes', $customer->notes) }}</textarea>
            </div>
            <button class="btn btn-quiet w-full">{{ __('حفظ البيانات') }}</button>
        </form>

        <dl class="grid grid-cols-2 gap-px bg-line border border-line rounded-lg overflow-hidden mt-6 text-center">
            <div class="bg-surface py-3"><dt class="text-xs text-ink-faint">{{ __('الزيارات والتفاعلات') }}</dt><dd class="font-display font-bold text-xl ltr-nums">{{ $customer->visits_count }}</dd></div>
            <div class="bg-surface py-3"><dt class="text-xs text-ink-faint">{{ __('آخر زيارة') }}</dt><dd class="font-semibold mt-1">{{ $customer->last_visit_at?->translatedFormat('j M Y') ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="panel min-w-0">
        <h2 class="px-5 py-3.5 border-b border-line font-semibold">{{ __('سجل الزيارات والتفاعلات') }}</h2>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('التاريخ') }}</th>
                        <th>{{ __('طريقة الدخول') }}</th>
                        <th>{{ __('النوع') }}</th>
                        <th>{{ __('البنك والوكيل') }}</th>
                        <th>{{ __('الحالة') }}</th>
                        <th>{{ __('الموظف') }}</th>
                        <th>{{ __('الانتظار / الخدمة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customer->visits as $v)
                        <tr>
                            <td class="whitespace-nowrap">
                                <div>{{ $v->created_at->translatedFormat('j M Y') }}</div>
                                <div class="text-xs text-ink-faint ltr-nums text-start">{{ $v->created_at->format('H:i') }}</div>
                            </td>
                            <td>
                                @if ($v->ticket)
                                    <span class="ticket-code {{ $v->customer_type->value === 'buyer' ? 'text-buyer' : 'text-seller' }}">{{ $v->ticket->ticket_no }}</span>
                                    <span class="text-xs text-ink-faint">{{ __('حضوري') }}</span>
                                @else
                                    {{ __('عن بُعد') }} <span class="text-ink-faint">({{ $v->source?->label() }})</span>
                                @endif
                            </td>
                            <td><x-type-tag :type="$v->customer_type" :label="$v->typeLabel()" /></td>
                            <td class="text-ink-soft">
                                {{ $v->bank?->name ?? '—' }}
                                @if ($v->agent)<span class="block text-xs text-ink-faint">{{ __('الوكيل') }}: {{ $v->agent->name }}</span>@endif
                                <x-deal-with :visit="$v" class="mt-1" />
                            </td>
                            <td><x-status-pill :status="$v->status" /></td>
                            <td>{{ $v->employee?->name ?? '—' }}</td>
                            <td class="ltr-nums text-start whitespace-nowrap">{{ \App\Support\Duration::clock($v->wait_seconds) }} / {{ \App\Support\Duration::clock($v->service_seconds) }}</td>
                        </tr>
                        @if ($v->notes)
                            <tr><td colspan="7" class="!pt-0 text-sm text-ink-soft">{{ __('ملاحظة') }}: {{ $v->notes }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>

@include('staff.partials.remote-modal')
@endsection
