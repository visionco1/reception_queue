@extends('layouts.base')

@section('body')
@php($user = auth()->user())
<div class="min-h-screen flex flex-col">
    {{-- الشريط العلوي الزيتي 72px: الشعار، الأقسام، الساعة، المكتب، المستخدم --}}
    <header class="olive topbar shrink-0 border-b border-gold/40">
        <div class="h-full mx-auto max-w-[1600px] px-4 sm:px-6 flex items-center gap-x-5">
            <a href="{{ route('staff.dashboard') }}" class="shrink-0" aria-label="{{ setting('company_name') }}">
                <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="brand-logo h-[42px] w-auto">
            </a>

            <nav class="topnav hidden lg:flex items-center gap-1 overflow-x-auto" aria-label="{{ __('التنقل الرئيسي') }}">
                @php($links = [
                    ['staff.dashboard', __('قائمة الانتظار'), 'staff.dashboard'],
                    ['staff.customers.index', __('العملاء'), 'staff.customers.*'],
                    ['staff.today', __('قائمة انتظار اليوم'), 'staff.today'],
                    [$user->isManager() ? 'admin.reports' : 'staff.performance', __('التقارير'), $user->isManager() ? 'admin.reports*' : 'staff.performance'],
                ])
                @if ($user->isManager())
                    @php($links[] = ['admin.overview', __('الإدارة'), ['admin.overview', 'admin.visits*', 'admin.customers', 'admin.whatsapp', 'admin.users.*', 'admin.desks', 'admin.banks', 'admin.agents', 'admin.client-types', 'admin.messages', 'admin.settings', 'admin.audit']])
                @endif
                @foreach ($links as [$route, $label, $pattern])
                    <a href="{{ route($route) }}" @class(['is-on' => request()->routeIs($pattern)])
                       @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="ms-auto flex items-center gap-4 shrink-0">
                <div class="hidden xl:block leading-tight text-end" data-clock>
                    <div class="text-ivory text-[1.15rem] font-semibold">
                        <span class="ltr-nums" data-clock-time></span>
                        <span class="text-gold text-sm" data-clock-suffix></span>
                    </div>
                    <div class="text-[0.78rem] text-[#cfc8b8]"><span data-clock-date></span> · <span data-clock-hijri></span></div>
                </div>

                @php($desk = $user->workingDesk())
                <form method="POST" action="{{ route('staff.desk') }}" x-data>
                    @csrf
                    <label for="desk-switch" class="sr-only">{{ __('مكتبي') }}</label>
                    <select id="desk-switch" name="desk_id" class="dark-select font-semibold" @change="$el.form.submit()">
                        @unless ($desk)
                            <option value="">{{ __('اختر مكتبك') }}</option>
                        @endunless
                        @foreach (\App\Models\Desk::query()->active()->ordered()->get() as $d)
                            <option value="{{ $d->id }}" @selected($desk?->id === $d->id)>{{ __('مكتبي :n', ['n' => $d->number]) }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="flex items-center gap-2.5">
                    <span class="grid place-items-center size-9 rounded-full bg-gold text-olive font-bold" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                    <div class="leading-tight hidden md:block">
                        <div class="text-sm font-bold text-ivory">{{ $user->name }}</div>
                        <div class="text-xs text-[#bfb8a8]">{{ $user->role->label() }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-ivory border border-[#4a524a] hover:border-gold rounded-[10px] px-3 py-1.5">{{ __('خروج') }}</button>
                </form>
            </div>
        </div>
    </header>

    {{-- الأقسام على الشاشات الصغيرة --}}
    <nav class="lg:hidden bg-olive border-b border-gold/30 px-3 py-2 flex gap-1 overflow-x-auto topnav" aria-label="{{ __('التنقل الرئيسي') }}">
        @foreach ($links as [$route, $label, $pattern])
            <a href="{{ route($route) }}" @class(['is-on' => request()->routeIs($pattern)])>{{ $label }}</a>
        @endforeach
    </nav>

    <main class="flex-1 w-full mx-auto max-w-[1600px] px-4 sm:px-6 py-6">
        <x-flash />
        @yield('content')
    </main>
</div>
@endsection
