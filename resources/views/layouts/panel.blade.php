@extends('layouts.base')

@section('body')
@php($user = auth()->user())
<div class="min-h-screen flex flex-col">
    <header class="bg-surface border-b border-line">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 min-h-16 py-2 lg:py-0 flex flex-wrap lg:flex-nowrap items-center gap-x-6 gap-y-2">
            <a href="{{ route('staff.dashboard') }}" class="flex items-center gap-3 shrink-0">
                @if (setting('logo_path'))
                    <img src="{{ asset(setting('logo_path')) }}" alt="" class="h-9 w-auto">
                @else
                    <span class="grid place-items-center size-9 rounded-md bg-deed text-white font-display font-bold text-sm">{{ mb_substr(setting('company_name'), 0, 1) }}</span>
                @endif
                <span class="hidden md:block font-display font-semibold text-[0.95rem] leading-tight max-w-52 truncate">{{ setting('company_name') }}</span>
            </a>

            <nav class="order-last lg:order-none w-full lg:w-auto flex items-center gap-1 text-[0.9375rem] overflow-x-auto" aria-label="{{ __('التنقل الرئيسي') }}">
                @php($links = [
                    ['staff.dashboard', __('الطابور'), 'staff.dashboard'],
                    ['staff.customers.index', __('العملاء'), 'staff.customers.*'],
                    ['staff.today', __('عملائي اليوم'), 'staff.today'],
                ])
                @if ($user->isManager())
                    @php($links[] = ['admin.overview', __('الإدارة'), 'admin.*'])
                @endif
                @foreach ($links as [$route, $label, $pattern])
                    <a href="{{ route($route) }}"
                       @class([
                           'px-3 py-2 rounded-md whitespace-nowrap',
                           'bg-deed-soft text-deed-dark font-semibold' => request()->routeIs($pattern),
                           'text-ink-soft hover:text-ink' => ! request()->routeIs($pattern),
                       ])>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="ms-auto flex items-center gap-3 shrink-0">
                @php($desk = $user->workingDesk())
                <form method="POST" action="{{ route('staff.desk') }}" class="flex items-center gap-2" x-data>
                    @csrf
                    <label for="desk-switch" class="text-sm text-ink-faint hidden sm:inline">{{ __('مكتبي') }}</label>
                    <select id="desk-switch" name="desk_id" class="field !py-1.5 !w-auto !text-sm font-semibold" @change="$el.form.submit()">
                        @unless ($desk)
                            <option value="">{{ __('اختر') }}</option>
                        @endunless
                        @foreach (\App\Models\Desk::query()->active()->ordered()->get() as $d)
                            <option value="{{ $d->id }}" @selected($desk?->id === $d->id)>{{ __('مكتب :n', ['n' => $d->number]) }}</option>
                        @endforeach
                    </select>
                </form>
                <div class="text-end leading-tight hidden lg:block">
                    <div class="text-sm font-semibold">{{ $user->name }}</div>
                    <div class="text-xs text-ink-faint">{{ $user->role->label() }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-quiet btn-sm">{{ __('خروج') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1 w-full mx-auto max-w-[1440px] px-4 sm:px-6 py-6">
        <x-flash />
        @yield('content')
    </main>
</div>
@endsection
