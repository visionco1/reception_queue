@extends('layouts.base')

@section('title', __('تسجيل الدخول'))
@section('body_class', 'min-h-screen antialiased bg-ivory')

@section('body')
<main class="min-h-screen flex flex-col-reverse lg:flex-row-reverse">
    {{-- النموذج على الخلفية العاجية --}}
    <section class="flex-1 flex flex-col">
        <div class="flex-1 flex items-center justify-center px-6 py-10">
            <form method="POST" action="{{ url('login') }}" class="w-full max-w-[470px]" x-data="{ show: false }" novalidate>
                @csrf
                <h1 class="text-[34px] leading-tight font-bold">{{ __('تسجيل الدخول') }}</h1>
                <p class="text-ink-faint mt-1.5">{{ __('أدخل بيانات حسابك للمتابعة') }}</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-[12px] border-[1.5px] border-[#ecc3bc] bg-danger-soft text-danger px-4 py-3 text-sm font-medium" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="mt-7 space-y-5">
                    <div>
                        <label for="username" class="field-label">{{ __('اسم المستخدم') }}</label>
                        <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" dir="ltr"
                               class="field !h-[58px] !text-[17px] text-start" style="text-align: right">
                    </div>
                    <div>
                        <label for="password" class="field-label">{{ __('كلمة المرور') }}</label>
                        <div class="relative">
                            <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" required autocomplete="current-password"
                                   class="field !h-[58px] !text-[17px] !ps-4 !pe-20">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 end-0 px-4 text-sm font-medium text-gold-dark"
                                    :aria-pressed="show" x-text="show ? '{{ __('إخفاء') }}' : '{{ __('إظهار') }}'">{{ __('إظهار') }}</button>
                        </div>
                    </div>
                    <label class="flex items-center gap-2.5 text-sm text-ink-faint select-none">
                        <input type="checkbox" name="remember" value="1" class="size-[18px] accent-olive" @checked(old('remember'))>
                        {{ __('تذكرني على هذا الجهاز') }}
                    </label>
                </div>

                <button class="mt-6 w-full h-[62px] rounded-[16px] bg-olive text-ivory text-lg font-bold flex items-center justify-center gap-4 hover:bg-deed-dark">
                    <span>{{ __('دخول') }}</span><span class="gold-rule"></span>
                </button>
                <p class="mt-4 text-center text-sm text-ink-faint">
                    {{ __('نسيت كلمة المرور؟') }} <span class="font-bold text-gold-dark">{{ __('تواصل مع مدير النظام') }}</span>
                </p>
            </form>
        </div>
        <p class="text-center text-xs text-[#a19c90] pb-6 font-num" dir="ltr">Sultan Alhaydal Co. · Staff Portal v1.0</p>
    </section>

    {{-- اللوحة الزيتية: الشعار، العنوان، الساعة --}}
    <aside class="olive lg:w-[43%] lg:min-h-screen px-8 lg:px-[60px] py-10 lg:py-14 flex flex-col">
        <span class="hidden lg:block absolute top-11 bottom-11 left-9 w-36 border-l-[1.5px] border-b-[1.5px] border-gold/80 rounded-bl-[34px] pointer-events-none" aria-hidden="true"></span>
        <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="brand-logo w-[260px] lg:w-[340px] ms-0 self-start">
        <div class="mt-10 lg:mt-auto">
            <h2 class="text-[34px] font-bold text-white leading-tight">{{ __('بوابة الموظفين') }}</h2>
        </div>
        <div class="mt-8 lg:mt-14 hidden sm:block" data-clock>
            <div class="text-[44px] font-semibold text-ivory leading-none">
                <span class="ltr-nums" data-clock-time></span> <span class="text-gold text-[34px]" data-clock-suffix></span>
            </div>
            <div class="mt-2 text-[#d6cfc0] text-[0.95rem]"><span data-clock-date></span> · <span data-clock-hijri></span></div>
        </div>
    </aside>
</main>
@endsection
