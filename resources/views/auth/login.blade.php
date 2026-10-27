@extends('layouts.base')
@section('title', __('تسجيل الدخول'))

@section('body')
<main class="min-h-screen grid place-items-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="flex items-center gap-3 mb-8">
            @if (setting('logo_path'))
                <img src="{{ asset(setting('logo_path')) }}" alt="" class="h-12 w-auto">
            @else
                <span class="grid place-items-center size-12 rounded-lg bg-deed text-white font-display font-bold text-lg">{{ mb_substr(setting('company_name'), 0, 1) }}</span>
            @endif
            <div>
                <div class="font-display font-bold text-lg leading-tight">{{ setting('company_name') }}</div>
                <div class="text-ink-faint text-sm">{{ __('لوحة الموظفين والإدارة') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('login') }}" class="panel p-6 space-y-4">
            @csrf
            <div>
                <label for="username" class="field-label">{{ __('اسم المستخدم') }}</label>
                <input id="username" name="username" value="{{ old('username') }}" class="field" dir="ltr" autocomplete="username" autocapitalize="none" required autofocus>
                @error('username')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="field-label">{{ __('كلمة المرور') }}</label>
                <input id="password" type="password" name="password" class="field" dir="ltr" autocomplete="current-password" required>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="remember" value="1" class="size-4 accent-deed">
                {{ __('تذكرني على هذا الجهاز') }}
            </label>
            <button class="btn btn-primary w-full !py-2.5">{{ __('دخول') }}</button>
        </form>
    </div>
</main>
@endsection
