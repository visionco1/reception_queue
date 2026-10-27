@extends('layouts.admin')
@section('title', __('أنواع العملاء'))

@section('admin')
<x-page-header :title="__('أنواع العملاء الأخرى')" :subtitle="__('للعميل عن بُعد الذي ليس بائعًا ولا مشتريًا. يختار الموظف «أخرى» ثم النوع من هذه القائمة.')" />

<div class="panel overflow-x-auto max-w-3xl">
    <table class="data-table">
        <thead><tr><th>{{ __('النوع') }}</th><th>{{ __('الترتيب') }}</th><th>{{ __('التفاعلات') }}</th><th>{{ __('فعّال') }}</th><th class="w-1"></th></tr></thead>
        <tbody>
            @forelse ($types as $type)
                <tr>
                    <td><input form="ct-{{ $type->id }}" name="name" value="{{ $type->name }}" class="field !py-1.5" required aria-label="{{ __('النوع') }}"></td>
                    <td><input form="ct-{{ $type->id }}" name="sort_order" type="number" min="0" value="{{ $type->sort_order }}" class="field !py-1.5 !w-20" aria-label="{{ __('الترتيب') }}"></td>
                    <td class="ltr-nums text-start text-ink-soft">{{ $type->visits_count }}</td>
                    <td><span class="switch"><input form="ct-{{ $type->id }}" type="checkbox" name="is_active" value="1" @checked($type->is_active) aria-label="{{ __('فعّال') }}"><span></span></span></td>
                    <td><form id="ct-{{ $type->id }}" method="POST" action="{{ route('admin.client-types.update', $type) }}">@csrf @method('PUT')<button class="btn btn-quiet btn-sm">{{ __('حفظ') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-ink-faint py-10">{{ __('لا توجد أنواع بعد.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <form method="POST" action="{{ route('admin.client-types.store') }}" class="flex flex-wrap items-end gap-3 p-4 border-t border-line bg-paper/60">
        @csrf
        <div class="flex-1 min-w-56"><label for="ct-name" class="field-label">{{ __('نوع جديد') }}</label><input id="ct-name" name="name" class="field" placeholder="{{ __('مثل: استشارة قانونية') }}" required></div>
        <button class="btn btn-primary">{{ __('إضافة نوع') }}</button>
    </form>
</div>
@error('name')<p class="field-error mt-2">{{ $message }}</p>@enderror
@endsection
