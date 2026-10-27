@extends('layouts.admin')
@section('title', __('البنوك'))

@section('admin')
<x-page-header :title="__('قائمة البنوك')" :subtitle="__('تظهر البنوك الفعّالة على آيباد الاستقبال بنفس الترتيب. تعطيل البنك يخفيه دون حذف بياناته من التقارير.')" />

<div class="panel overflow-x-auto max-w-3xl">
    <table class="data-table">
        <thead><tr><th>{{ __('اسم البنك') }}</th><th>{{ __('الترتيب') }}</th><th>{{ __('الزيارات') }}</th><th>{{ __('فعّال') }}</th><th class="w-1"></th></tr></thead>
        <tbody>
            @foreach ($banks as $bank)
                <tr>
                    <td><input form="bank-{{ $bank->id }}" name="name" value="{{ $bank->name }}" class="field !py-1.5" required aria-label="{{ __('اسم البنك') }}"></td>
                    <td><input form="bank-{{ $bank->id }}" name="sort_order" type="number" min="0" value="{{ $bank->sort_order }}" class="field !py-1.5 !w-20" aria-label="{{ __('الترتيب') }}"></td>
                        <td class="ltr-nums text-start text-ink-soft">{{ $bank->visits_count }}</td>
                    <td><span class="switch"><input form="bank-{{ $bank->id }}" type="checkbox" name="is_active" value="1" @checked($bank->is_active) aria-label="{{ __('فعّال') }}"><span></span></span></td>
                    <td><form id="bank-{{ $bank->id }}" method="POST" action="{{ route('admin.banks.update', $bank) }}">@csrf @method('PUT')<button class="btn btn-quiet btn-sm">{{ __('حفظ') }}</button></form></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <form method="POST" action="{{ route('admin.banks.store') }}" class="flex flex-wrap items-end gap-3 p-4 border-t border-line bg-paper/60">
        @csrf
        <div class="flex-1 min-w-56"><label for="b-name" class="field-label">{{ __('بنك جديد') }}</label><input id="b-name" name="name" class="field" required></div>
        <button class="btn btn-primary">{{ __('إضافة بنك') }}</button>
    </form>
</div>
@error('name')<p class="field-error mt-2">{{ $message }}</p>@enderror
@endsection
