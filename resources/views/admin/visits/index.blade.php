@extends('layouts.admin')
@section('title', __('الزيارات'))

@section('admin')
<x-page-header :title="__('جميع الزيارات والتفاعلات')" :subtitle="__(':n نتيجة', ['n' => number_format($visits->total())])">
    <a class="btn btn-quiet" href="{{ route('admin.export.visits', array_filter($filters) + ['format' => 'xlsx']) }}">{{ __('تصدير Excel') }}</a>
    <a class="btn btn-quiet" href="{{ route('admin.export.visits', array_filter($filters) + ['format' => 'csv']) }}">{{ __('تصدير CSV') }}</a>
</x-page-header>

<form class="panel p-3 mb-4 flex flex-wrap gap-3 items-end text-sm">
    <div class="w-40"><label class="field-label !text-xs" for="f-from">{{ __('من') }}</label><input id="f-from" type="date" name="from" value="{{ $filters['from'] }}" class="field !py-1.5"></div>
    <div class="w-40"><label class="field-label !text-xs" for="f-to">{{ __('إلى') }}</label><input id="f-to" type="date" name="to" value="{{ $filters['to'] }}" class="field !py-1.5"></div>
    @foreach ([
        ['channel', __('طريقة الدخول'), $channels],
        ['type', __('النوع'), $types],
        ['status', __('الحالة'), $statuses],
        ['bank', __('البنك'), $banks->pluck('name', 'id')->all()],
        ['agent', __('الوكيل'), $agents->mapWithKeys(fn ($a) => [$a->id => $a->name.' ('.$a->bank?->name.')'])->all()],
        ['employee', __('الموظف'), $employees->pluck('name', 'id')->all()],
    ] as [$name, $label, $options])
        <div class="w-36">
            <label class="field-label !text-xs" for="f-{{ $name }}">{{ $label }}</label>
            <select id="f-{{ $name }}" name="{{ $name }}" class="field !py-1.5">
                <option value="">{{ __('الكل') }}</option>
                @foreach ($options as $value => $text)
                    <option value="{{ $value }}" @selected((string) $filters[$name] === (string) $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
    <div class="flex-1 min-w-40"><label class="field-label !text-xs" for="f-q">{{ __('بحث') }}</label><input id="f-q" name="q" value="{{ $filters['q'] }}" class="field !py-1.5" placeholder="{{ __('اسم، جوال، رقم') }}"></div>
    <button class="btn btn-primary !py-1.5">{{ __('تطبيق') }}</button>
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('التاريخ') }}</th><th>{{ __('العميل') }}</th><th>{{ __('الدخول') }}</th><th>{{ __('النوع') }}</th>
                <th>{{ __('البنك') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('الموظف') }}</th><th>{{ __('الانتظار') }}</th><th>{{ __('الخدمة') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($visits as $v)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('admin.visits.show', $v) }}" class="hover:text-deed">{{ $v->created_at->translatedFormat('j M') }} <span class="ltr-nums text-ink-faint">{{ $v->created_at->format('H:i') }}</span></a></td>
                    <td><a href="{{ route('admin.visits.show', $v) }}" class="font-semibold hover:text-deed">{{ $v->customer->name }}</a></td>
                    <td class="whitespace-nowrap">
                        @if ($v->ticket)
                            <span class="ticket-code {{ $v->customer_type->value === 'buyer' ? 'text-buyer' : 'text-seller' }}">{{ $v->ticket->ticket_no }}</span>
                        @else
                            <span class="text-ink-soft">{{ __('عن بُعد') }}</span>
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
                    <td class="ltr-nums text-start">{{ \App\Support\Duration::clock($v->wait_seconds) }}</td>
                    <td class="ltr-nums text-start">{{ \App\Support\Duration::clock($v->service_seconds) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-ink-faint py-10">{{ __('لا توجد زيارات مطابقة للمرشحات.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $visits->links() }}
@endsection
