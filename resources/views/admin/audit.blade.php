@extends('layouts.admin')
@section('title', __('سجل التدقيق'))

@section('admin')
<x-page-header :title="__('سجل التدقيق')" :subtitle="__('من أخذ العميل، من عدّل البيانات، ومتى.')" />

<form class="panel p-3 mb-4 flex flex-wrap items-end gap-3 text-sm">
    <div>
        <label for="a-user" class="field-label !text-xs">{{ __('المستخدم') }}</label>
        <select id="a-user" name="user" class="field !py-1.5">
            <option value="">{{ __('الكل') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected((string) $user === (string) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="a-action" class="field-label !text-xs">{{ __('نوع الحدث') }}</label>
        <select id="a-action" name="action" class="field !py-1.5">
            <option value="">{{ __('الكل') }}</option>
            @foreach ($actions as $value => $label)
                <option value="{{ $value }}" @selected($action === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary !py-1.5">{{ __('تطبيق') }}</button>
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead><tr><th>{{ __('الوقت') }}</th><th>{{ __('المستخدم') }}</th><th>{{ __('الحدث') }}</th><th>{{ __('التفاصيل') }}</th><th>{{ __('عنوان IP') }}</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-sm">{{ $log->created_at->translatedFormat('j M') }} <span class="ltr-nums text-ink-faint">{{ $log->created_at->format('H:i:s') }}</span></td>
                    <td class="whitespace-nowrap">{{ $log->user?->name ?? __('النظام') }}</td>
                    <td>
                        {{ $log->description ?? $log->action }}
                        @if ($log->subject_type === 'Visit')
                            <a href="{{ route('admin.visits.show', $log->subject_id) }}" class="text-deed text-sm hover:underline ms-1">{{ __('الزيارة') }}</a>
                        @endif
                    </td>
                    <td class="text-xs text-ink-soft max-w-sm">
                        @php($fmt = fn ($x) => $x === null || $x === '' ? '—' : (is_bool($x) ? ($x ? __('نعم') : __('لا')) : (is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE))))
                        @foreach ((array) $log->properties as $k => $v)
                            @php($attr = trans()->has('validation.attributes.'.$k) ? __('validation.attributes.'.$k) : $k)
                            <div>
                                <span class="text-ink-faint">{{ $attr }}:</span>
                                @if (is_array($v) && array_is_list($v) && count($v) === 2)
                                    {{ __('من') }} «<bdi>{{ $fmt($v[0]) }}</bdi>» {{ __('إلى') }} «<bdi>{{ $fmt($v[1]) }}</bdi>»
                                @else
                                    <bdi>{{ is_array($v) ? implode('، ', array_map($fmt, $v)) : $fmt($v) }}</bdi>
                                @endif
                            </div>
                        @endforeach
                    </td>
                    <td class="ltr-nums text-start text-xs text-ink-faint">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-ink-faint py-10">{{ __('لا توجد أحداث.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
@endsection
