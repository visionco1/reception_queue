@extends('layouts.admin')
@section('title', __('وكلاء البنوك'))

@section('admin')
<x-page-header :title="__('وكلاء البنوك')" :subtitle="__('مندوب البنك الذي يتابع أكثر من بائع ومشتري. يظهر الوكلاء الفعّالون على الآيباد بعد اختيار البنك.')" />

<form method="POST" action="{{ route('admin.agents.store') }}" class="panel p-4 mb-6 flex flex-wrap items-end gap-3 max-w-4xl">
    @csrf
    <div class="w-56">
        <label for="n-bank" class="field-label">{{ __('البنك') }}</label>
        <select id="n-bank" name="bank_id" class="field" required>
            <option value="">{{ __('اختر') }}</option>
            @foreach ($banks as $b)
                <option value="{{ $b->id }}" @selected((string) old('bank_id', $bankFilter) === (string) $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex-1 min-w-48">
        <label for="n-name" class="field-label">{{ __('اسم الوكيل') }}</label>
        <input id="n-name" name="name" value="{{ old('name') }}" class="field" required>
    </div>
    <div class="w-44">
        <label for="n-mobile" class="field-label">{{ __('جواله (اختياري)') }}</label>
        <input id="n-mobile" name="mobile" value="{{ old('mobile') }}" class="field ltr-nums" dir="ltr" placeholder="05XXXXXXXX">
    </div>
    <button class="btn btn-primary">{{ __('إضافة وكيل') }}</button>
</form>
@if ($errors->any())
    <div class="flash flash-error mb-4 max-w-4xl">{{ $errors->first() }}</div>
@endif

<form class="flex items-center gap-2 mb-3 text-sm">
    <label for="f-bank" class="text-ink-soft">{{ __('عرض وكلاء') }}</label>
    <select id="f-bank" name="bank" class="field !w-auto !py-1.5" onchange="this.form.submit()">
        <option value="">{{ __('كل البنوك') }}</option>
        @foreach ($banks as $b)
            <option value="{{ $b->id }}" @selected((string) $bankFilter === (string) $b->id)>{{ $b->name }}</option>
        @endforeach
    </select>
</form>

<div class="panel overflow-x-auto max-w-5xl">
    <table class="data-table">
        <thead><tr><th>{{ __('البنك') }}</th><th>{{ __('اسم الوكيل') }}</th><th>{{ __('الجوال') }}</th><th>{{ __('بائعون') }}</th><th>{{ __('مشترون') }}</th><th>{{ __('الترتيب') }}</th><th>{{ __('فعّال') }}</th><th class="w-1"></th></tr></thead>
        <tbody>
            @forelse ($agents as $agent)
                <tr>
                    <td>
                        <select form="agent-{{ $agent->id }}" name="bank_id" class="field !py-1.5 !w-48" aria-label="{{ __('البنك') }}">
                            @foreach ($banks as $b)
                                <option value="{{ $b->id }}" @selected($agent->bank_id === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input form="agent-{{ $agent->id }}" name="name" value="{{ $agent->name }}" class="field !py-1.5" required aria-label="{{ __('اسم الوكيل') }}"></td>
                    <td><input form="agent-{{ $agent->id }}" name="mobile" value="{{ \App\Support\Phone::format($agent->mobile) }}" class="field !py-1.5 !w-36 ltr-nums" dir="ltr" aria-label="{{ __('الجوال') }}"></td>
                    <td class="ltr-nums text-start">{{ $agent->sellers_count }}</td>
                    <td class="ltr-nums text-start">{{ $agent->buyers_count }}</td>
                    <td><input form="agent-{{ $agent->id }}" name="sort_order" type="number" min="0" value="{{ $agent->sort_order }}" class="field !py-1.5 !w-20" aria-label="{{ __('الترتيب') }}"></td>
                    <td><span class="switch"><input form="agent-{{ $agent->id }}" type="checkbox" name="is_active" value="1" @checked($agent->is_active) aria-label="{{ __('فعّال') }}"><span></span></span></td>
                    <td><form id="agent-{{ $agent->id }}" method="POST" action="{{ route('admin.agents.update', $agent) }}">@csrf @method('PUT')<button class="btn btn-quiet btn-sm">{{ __('حفظ') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-ink-faint py-10">{{ __('لا يوجد وكلاء بعد. أضف أول وكيل من النموذج أعلاه.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p class="text-sm text-ink-faint mt-3 max-w-3xl">{{ __('تعطيل الوكيل يخفيه من الآيباد ونافذة العميل عن بُعد، وتبقى زياراته السابقة في التقارير.') }}</p>
@endsection
