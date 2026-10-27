@extends('layouts.admin')
@section('title', __('المكاتب'))

@section('admin')
<x-page-header :title="__('المكاتب')" :subtitle="__('رقم المكتب هو ما يظهر للعميل على شاشة الانتظار: «توجّه إلى مكتب 3».')" />

<div class="panel overflow-x-auto max-w-3xl">
    <table class="data-table">
        <thead><tr><th>{{ __('رقم المكتب') }}</th><th>{{ __('وصف داخلي') }}</th><th>{{ __('الترتيب') }}</th><th>{{ __('فعّال') }}</th><th class="w-1"></th></tr></thead>
        <tbody>
            @foreach ($desks as $desk)
                <tr>
                    <td><input form="desk-{{ $desk->id }}" name="number" value="{{ $desk->number }}" class="field !py-1.5 !w-24 font-display font-bold" required aria-label="{{ __('رقم المكتب') }}"></td>
                    <td><input form="desk-{{ $desk->id }}" name="name" value="{{ $desk->name }}" class="field !py-1.5" placeholder="{{ __('مثل: الدور الأول') }}" aria-label="{{ __('وصف داخلي') }}"></td>
                    <td><input form="desk-{{ $desk->id }}" name="sort_order" type="number" min="0" value="{{ $desk->sort_order }}" class="field !py-1.5 !w-20" aria-label="{{ __('الترتيب') }}"></td>
                    <td><span class="switch"><input form="desk-{{ $desk->id }}" type="checkbox" name="is_active" value="1" @checked($desk->is_active) aria-label="{{ __('فعّال') }}"><span></span></span></td>
                    <td><form id="desk-{{ $desk->id }}" method="POST" action="{{ route('admin.desks.update', $desk) }}">@csrf @method('PUT')<button class="btn btn-quiet btn-sm">{{ __('حفظ') }}</button></form></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <form method="POST" action="{{ route('admin.desks.store') }}" class="flex flex-wrap items-end gap-3 p-4 border-t border-line bg-paper/60">
        @csrf
        <div><label for="n-number" class="field-label">{{ __('مكتب جديد') }}</label><input id="n-number" name="number" class="field !w-28" placeholder="5" required></div>
        <div class="flex-1 min-w-48"><label for="n-name" class="field-label">{{ __('وصف داخلي') }}</label><input id="n-name" name="name" class="field"></div>
        <button class="btn btn-primary">{{ __('إضافة مكتب') }}</button>
    </form>
</div>
@error('number')<p class="field-error mt-2">{{ $message }}</p>@enderror
@endsection
