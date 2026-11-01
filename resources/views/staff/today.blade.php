@extends('layouts.panel')
@section('title', __('قائمة انتظار اليوم'))

@section('content')
<x-page-header :title="__('قائمة انتظار اليوم')" :subtitle="today()->translatedFormat('l j F Y').' — '.__('كل أرقام اليوم بحالاتها. يمكن حذف العميل الذي لم يكمل طلبه.')">
    <form class="flex items-center gap-2" role="search">
        <input type="hidden" name="status" value="{{ $status }}">
        @if ($mine)<input type="hidden" name="mine" value="1">@endif
        <label for="tq" class="sr-only">{{ __('بحث') }}</label>
        <input id="tq" name="q" value="{{ $q }}" class="field !w-60" placeholder="{{ __('بحث بالاسم أو الجوال أو الرقم') }}" type="search">
        <button class="btn btn-quiet">{{ __('بحث') }}</button>
    </form>
</x-page-header>

<div class="flex flex-wrap items-center gap-3 mb-4">
    <nav class="seg" aria-label="{{ __('الحالة') }}">
        @foreach ([
            'all' => __('الكل'),
            'waiting' => __('في الانتظار'),
            'active' => __('مع موظف'),
            'completed' => __('تمت الخدمة'),
            'no_show' => __('لم يحضر'),
            'cancelled' => __('ملغي'),
        ] as $key => $label)
            <a href="{{ route('staff.today', array_filter(['status' => $key, 'mine' => $mine ? 1 : null, 'q' => $q ?: null])) }}" @class(['is-on' => $status === $key])>
                {{ $label }} <span class="n">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>
    <a href="{{ route('staff.today', array_filter(['status' => $status, 'mine' => $mine ? null : 1, 'q' => $q ?: null])) }}"
       @class(['btn btn-sm', 'btn-primary' => $mine, 'btn-quiet' => ! $mine])>{{ __('عملائي فقط') }}</a>
</div>

<div class="panel overflow-x-auto" x-data="{ confirming: null }">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('الرقم') }}</th>
                <th>{{ __('العميل') }}</th>
                <th>{{ __('النوع') }}</th>
                <th class="hidden lg:table-cell">{{ __('الجهة') }}</th>
                <th>{{ __('الحالة') }}</th>
                <th class="hidden md:table-cell">{{ __('الموظف') }}</th>
                <th class="hidden md:table-cell">{{ __('التسجيل') }}</th>
                <th class="hidden lg:table-cell">{{ __('الانتظار') }}</th>
                <th class="hidden lg:table-cell">{{ __('الخدمة') }}</th>
                <th class="w-1"><span class="sr-only">{{ __('إجراء') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($visits as $v)
                <tr>
                    <td><span class="ticket-code text-lg">{{ $v->ticketNo() }}</span></td>
                    <td>
                        <a href="{{ route('staff.customers.show', $v->customer_id) }}" class="font-bold hover:underline underline-offset-4">{{ $v->customer->name }}</a>
                        <div class="text-xs text-ink-faint font-num" dir="ltr" style="text-align: right">{{ \App\Support\Phone::mask($v->customer->mobile) }}</div>
                    </td>
                    <td><x-type-tag :type="$v->customer_type" :label="$v->typeLabel()" /></td>
                    <td class="hidden lg:table-cell text-sm text-ink-soft">
                        {{ $v->viaLabel() }}
                        @if ($v->agent)<div class="text-xs text-ink-faint">{{ __('الوكيل') }}: {{ $v->agent->name }}</div>@endif
                    </td>
                    <td>
                        <x-status-pill :status="$v->status" />
                        @if ($v->outcome && $v->outcome !== \App\Enums\Outcome::Completed)
                            <span class="status-pill outcome-{{ $v->outcome->value }}">{{ $v->outcome->label() }}</span>
                        @endif
                        @if ($v->assignedDesk)<div class="text-xs text-gold-dark mt-1">{{ __('محوّل لمكتب :n', ['n' => $v->assignedDesk->number]) }}</div>@endif
                    </td>
                    <td class="hidden md:table-cell text-sm">
                        @if ($v->employee){{ $v->employee->name }} <span class="text-ink-faint">· {{ __('مكتب') }} <span class="font-num">{{ $v->desk?->number }}</span></span>@else<span class="text-ink-faint">—</span>@endif
                    </td>
                    <td class="hidden md:table-cell font-num text-sm">{{ $v->ticket->issued_at->format('g:i') }} <span class="text-ink-faint">{{ $v->ticket->issued_at->format('A') === 'AM' ? __('ص') : __('م') }}</span></td>
                    <td class="hidden lg:table-cell text-sm">{{ $v->wait_seconds !== null ? round($v->wait_seconds / 60).' '.__('د') : '—' }}</td>
                    <td class="hidden lg:table-cell text-sm">{{ $v->service_seconds !== null ? round($v->service_seconds / 60).' '.__('د') : '—' }}</td>
                    <td>
                        @if ($v->status !== \App\Enums\VisitStatus::Completed)
                            <form method="POST" action="{{ route('staff.visits.destroy', $v) }}" class="whitespace-nowrap"
                                  @submit="if (confirming !== {{ $v->id }}) { $event.preventDefault(); confirming = {{ $v->id }} }">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm" :class="confirming === {{ $v->id }} ? 'btn-danger' : 'text-ink-faint hover:text-danger'"
                                        x-text="confirming === {{ $v->id }} ? @js(__('تأكيد الحذف')) : @js(__('حذف'))">{{ __('حذف') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="py-14 text-center text-ink-faint">{{ __('لا توجد أرقام مطابقة اليوم.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
