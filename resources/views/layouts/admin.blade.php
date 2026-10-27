@extends('layouts.panel')

@section('content')
@php($admin = auth()->user()->isAdmin())
<div class="grid lg:grid-cols-[13rem_1fr] gap-6 items-start">
    <aside class="lg:sticky lg:top-6">
        <nav class="flex lg:flex-col gap-0.5 overflow-x-auto text-[0.9375rem]" aria-label="{{ __('أقسام الإدارة') }}">
            @php($groups = [
                __('المتابعة') => [
                    ['admin.overview', __('نظرة اليوم'), 'admin.overview', true],
                    ['admin.reports', __('التقارير'), 'admin.reports', true],
                    ['admin.visits', __('الزيارات'), 'admin.visits*', true],
                    ['admin.customers', __('العملاء'), 'admin.customers', true],
                    ['admin.whatsapp', __('سجل رسائل واتساب'), 'admin.whatsapp', true],
                ],
                __('الإعداد') => [
                    ['admin.users.index', __('الموظفون'), 'admin.users.*', $admin],
                    ['admin.desks', __('المكاتب'), 'admin.desks', $admin],
                    ['admin.banks', __('البنوك'), 'admin.banks', $admin],
                    ['admin.agents', __('وكلاء البنوك'), 'admin.agents', $admin],
                    ['admin.client-types', __('أنواع العملاء'), 'admin.client-types', $admin],
                    ['admin.messages', __('رسائل واتساب'), 'admin.messages', $admin],
                    ['admin.settings', __('الإعدادات والأجهزة'), 'admin.settings', $admin],
                    ['admin.audit', __('سجل التدقيق'), 'admin.audit', $admin],
                ],
            ])
            @foreach ($groups as $group => $items)
                @php($visible = collect($items)->filter(fn ($i) => $i[3]))
                @if ($visible->isNotEmpty())
                    <div class="hidden lg:block text-xs text-ink-faint px-3 pt-4 pb-1.5 first:pt-0">{{ $group }}</div>
                    @foreach ($visible as [$route, $label, $pattern])
                        <a href="{{ route($route) }}" @class([
                            'px-3 py-2 rounded-md whitespace-nowrap border-s-2',
                            'border-deed bg-surface text-ink font-semibold' => request()->routeIs($pattern),
                            'border-transparent text-ink-soft hover:text-ink' => ! request()->routeIs($pattern),
                        ])>{{ $label }}</a>
                    @endforeach
                @endif
            @endforeach
        </nav>
    </aside>
    <section class="min-w-0">
        @yield('admin')
    </section>
</div>
@endsection
