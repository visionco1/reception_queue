@extends('layouts.panel')
@section('title', __('العملاء'))

@section('content')
<x-page-header :title="__('البحث عن عميل')" :subtitle="__('رقم الجوال هو المفتاح الأساسي لكل عميل. ابحث قبل الإضافة لتجنب تكرار السجل.')">
    <button type="button" class="btn btn-primary" x-data @click="$dispatch('open-remote')">{{ __('إضافة عميل عن بُعد') }}</button>
</x-page-header>

<form class="flex gap-2 max-w-xl mb-6" role="search">
    <label for="q" class="sr-only">{{ __('رقم الجوال أو الاسم') }}</label>
    <input id="q" name="q" value="{{ $q }}" class="field !py-2.5 !text-base" placeholder="{{ __('رقم الجوال أو الاسم') }}" autofocus>
    <button class="btn btn-primary !px-6">{{ __('بحث') }}</button>
</form>

@if ($results === null)
    <p class="text-ink-faint">{{ __('اكتب رقم الجوال (مثل 0551234567) أو جزءًا من الاسم.') }}</p>
@elseif ($results->isEmpty())
    <div class="panel px-6 py-10 text-center">
        <p class="font-semibold">{{ __('لا يوجد عميل مطابق لـ ":q"', ['q' => $q]) }}</p>
        @if ($newMobile)
            <p class="text-ink-faint mt-1">{{ __('الرقم غير مسجل. يمكنك إضافته الآن كعميل عن بُعد.') }}</p>
            <button type="button" class="btn btn-primary mt-4" x-data @click="$dispatch('open-remote', { mobile: @js($q) })">{{ __('إضافة هذا الرقم') }}</button>
        @endif
    </div>
@else
    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('الاسم') }}</th>
                    <th>{{ __('الجوال') }}</th>
                    <th>{{ __('الزيارات') }}</th>
                    <th>{{ __('آخر زيارة') }}</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $c)
                    <tr>
                        <td class="font-semibold">{{ $c->name }}</td>
                        <td class="ltr-nums text-start">{{ $c->displayMobile() }}</td>
                        <td class="ltr-nums text-start">{{ $c->visits_count }}</td>
                        <td class="text-ink-soft">{{ $c->last_visit_at?->translatedFormat('j F Y') ?? '—' }}</td>
                        <td><a href="{{ route('staff.customers.show', $c) }}" class="btn btn-quiet btn-sm">{{ __('السجل') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $results->links() }}
@endif

@include('staff.partials.remote-modal')
@endsection
