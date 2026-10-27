@extends('layouts.base')
@section('title', __('شاشة الانتظار'))
@section('body_class', 'h-dvh overflow-hidden antialiased cursor-none')

@push('head')
    <style>
        :root {
            --d-bg: {{ setting('display_bg') }};
            --d-fg: {{ setting('display_primary') }};
            --d-accent: {{ setting('display_accent') }};
        }
        body { background: var(--d-bg); color: var(--d-fg); }
        .d-muted { color: color-mix(in oklab, var(--d-fg) 62%, var(--d-bg)); }
        .d-rule { border-color: color-mix(in oklab, var(--d-fg) 16%, var(--d-bg)); }
        .d-panel { background: color-mix(in oklab, var(--d-fg) 6%, var(--d-bg)); }

        /* رقم المكتب كختم رسمي */
        .stamp {
            display: inline-flex; align-items: center; gap: 0.35em;
            background: var(--d-accent);
            color: color-mix(in oklab, var(--d-bg) 85%, black);
            padding: 0.12em 0.55em 0.18em;
            border-radius: 0.22em;
            box-shadow: inset 0 0 0 0.07em color-mix(in oklab, var(--d-bg) 70%, transparent), inset 0 0 0 0.12em var(--d-accent), inset 0 0 0 0.15em color-mix(in oklab, var(--d-bg) 40%, transparent);
            transform: rotate(-2deg);
        }
        .call-in .ticket-big { animation: rise 700ms cubic-bezier(.2,.9,.2,1) both; }
        .call-in .stamp { animation: stamp 520ms 380ms cubic-bezier(.3,1.6,.5,1) both; }
        .call-in .call-ring { animation: ring 2.4s ease-out both; }
        @keyframes rise { from { opacity: 0; transform: translateY(0.12em) scale(0.94); } to { opacity: 1; transform: none; } }
        @keyframes stamp { from { opacity: 0; transform: scale(1.5) rotate(-9deg); } to { opacity: 1; transform: scale(1) rotate(-2deg); } }
        @keyframes ring { 0% { box-shadow: 0 0 0 0 color-mix(in oklab, var(--d-accent) 55%, transparent); } 100% { box-shadow: 0 0 0 2.5vw transparent; } }
    </style>
@endpush

@section('body')
<div x-data="display({
        feedUrl: @js(route('display.feed', $token)),
        pollMs: {{ (int) config('qms.queue.poll_ms') }},
        version: @js($version),
        sound: @js((bool) setting('display_sound')),
        voice: @js((bool) setting('display_voice')),
     })"
     @click="enableSound()"
     class="h-dvh grid grid-rows-[auto_1fr_auto] px-[3.2vw] py-[2.6vh]">

    <header class="flex items-center justify-between gap-6 pb-[2vh] border-b d-rule">
        <div class="flex items-center gap-[1.2vw] min-w-0">
            @if (setting('logo_path'))
                <img src="{{ asset(setting('logo_path')) }}" alt="" class="h-[6.5vh] w-auto">
            @endif
            <div class="font-display font-bold text-[2.3vw] leading-tight truncate">{{ setting('company_name') }}</div>
        </div>
        <div class="text-end shrink-0">
            <div class="font-display font-semibold text-[2.3vw] leading-none ltr-nums" x-text="clock"></div>
            <div class="d-muted text-[1.15vw] mt-[0.6vh]" x-text="date"></div>
        </div>
    </header>

    <main class="grid grid-cols-[minmax(0,1.9fr)_minmax(0,1fr)] gap-[3vw] py-[3vh] min-h-0">
        {{-- النداء الحالي --}}
        <section class="flex flex-col justify-center min-w-0" aria-live="assertive">
            <template x-if="current">
                <div :class="flash && 'call-in'" class="flex flex-col items-start">
                    <div class="d-muted text-[1.9vw] font-semibold">
                        <span x-show="!current.recall" x-text="current.tickets.length === 1 ? @js(__('الرقم')) : (current.tickets.length === 2 ? @js(__('الرقمان')) : @js(__('الأرقام')))"></span>
                        <span x-show="current.recall" x-cloak x-text="current.tickets.length > 1 ? @js(__('نداء مجدد للأرقام')) : @js(__('نداء مجدد للرقم'))"></span>
                    </div>
                    <div class="call-ring rounded-[2vw] -ms-[1vw] px-[1vw]">
                        <div class="ticket-big ticket-code leading-[0.95] whitespace-nowrap"
                             :class="current.tickets.length > 2 ? 'text-[min(5vw,14vh)]' : (current.tickets.length === 2 ? 'text-[min(7.5vw,20vh)]' : 'text-[min(17vw,36vh)]')"
                             x-text="current.tickets.join(' + ')"></div>
                    </div>
                    <div class="mt-[3vh] flex items-center gap-[1.4vw] font-display font-bold text-[3.6vw]">
                        <span>{{ __('توجّه إلى') }}</span>
                        <span class="stamp">{{ __('مكتب') }} <span class="ltr-nums" x-text="current.desk"></span></span>
                    </div>
                    <div class="d-muted text-[1.6vw] mt-[2.2vh]" x-show="current.employee" x-text="current.employee"></div>
                </div>
            </template>
            <template x-if="!current">
                <div>
                    <div class="font-display font-bold text-[4.4vw] leading-tight">{{ __('أهلًا بكم') }}</div>
                    <p class="d-muted text-[2vw] mt-[2vh] max-w-[48vw] leading-relaxed">{{ __('سجّل بياناتك من جهاز الاستقبال، وسيظهر رقمك هنا مع رقم المكتب.') }}</p>
                </div>
            </template>
        </section>

        {{-- آخر الأرقام --}}
        <aside class="d-panel rounded-[1.2vw] px-[2vw] py-[2.6vh] flex flex-col min-h-0">
            <h2 class="d-muted text-[1.45vw] font-semibold pb-[1.6vh] border-b d-rule">{{ __('النداءات السابقة') }}</h2>
            <ol class="flex-1 min-h-0">
                <template x-for="call in recent" :key="call.id">
                    <li class="flex items-center justify-between py-[1.7vh] border-b d-rule last:border-b-0">
                        <span class="ticket-code text-[3.1vw] leading-none" x-text="call.ticket_no"></span>
                        <span class="font-display font-semibold text-[1.9vw] d-muted">{{ __('مكتب') }} <span class="ltr-nums" style="color: var(--d-fg)" x-text="call.desk"></span></span>
                    </li>
                </template>
                <li x-show="!recent.length" class="d-muted text-[1.4vw] pt-[2vh]">{{ __('لا توجد نداءات سابقة اليوم') }}</li>
            </ol>
        </aside>
    </main>

    <footer class="flex items-center justify-between gap-6 pt-[2vh] border-t d-rule text-[1.45vw]">
        <p class="d-muted truncate">{{ setting('welcome_message') }}</p>
        <p class="shrink-0 font-semibold">
            {{ __('في الانتظار الآن') }}: <span class="ltr-nums font-display" x-text="waiting"></span>
        </p>
    </footer>

    <div x-show="!soundReady" x-cloak class="fixed bottom-[3vh] inset-x-0 flex justify-center cursor-pointer">
        <span class="d-panel rounded-full px-[1.6vw] py-[1vh] text-[1.2vw]" style="outline: 1px solid var(--d-accent)">{{ __('اضغط في أي مكان لتفعيل صوت التنبيه') }}</span>
    </div>
    <div x-show="offline" x-cloak class="fixed top-[2vh] inset-x-0 flex justify-center"><span class="rounded-full px-[1.2vw] py-[0.6vh] text-[1vw] bg-black/40">{{ __('انقطع الاتصال، تتم إعادة المحاولة…') }}</span></div>
</div>
@endsection
