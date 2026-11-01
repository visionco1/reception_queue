@extends('layouts.base')
@section('title', __('شاشة العرض'))
@section('body_class', 'antialiased overflow-hidden bg-[#141914]')

@push('head')
    <style>
        /* لوحة بمقاس 1920×1080 تُكبَّر وتُصغَّر لتملأ أي شاشة بنفس النسب */
        .stage { width: 1920px; height: 1080px; position: absolute; top: 50%; left: 50%; transform-origin: center; color: var(--color-ivory); }
        .num { font-family: var(--font-num); font-weight: 600; direction: ltr; unicode-bidi: isolate; letter-spacing: 0.035em; font-variant-numeric: tabular-nums; }
        .zoom-in { animation: zoomIn 900ms cubic-bezier(.2, .9, .25, 1.15) both; }
        @keyframes zoomIn { 0% { transform: scale(.82); opacity: 0; } 60% { transform: scale(1.04); opacity: 1; } 100% { transform: scale(1); } }
        .glow { animation: glow 2.4s ease-out both; }
        @keyframes glow { 0% { box-shadow: 0 0 0 0 rgb(187 152 96 / .55); } 100% { box-shadow: 0 0 0 46px rgb(187 152 96 / 0); } }
        .ticker { overflow: hidden; direction: ltr; white-space: nowrap; }
        .ticker .track { display: inline-block; padding-left: 100%; animation: tick var(--dur, 40s) linear infinite; direction: rtl; }
        @keyframes tick { from { transform: translateX(-100%); } to { transform: translateX(0); } }
        .fade { animation: fade 700ms ease both; }
        @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
    </style>
@endpush

@section('body')
<div x-data="display({ feedUrl: @js(route('display.feed', $token)), version: @js($version), pollMs: {{ (int) config('qms.queue.poll_ms', 2500) }} })"
     x-init="const fit = () => { const s = Math.min(innerWidth / 1920, innerHeight / 1080); $refs.stage.style.transform = `translate(-50%, -50%) scale(${s})`; }; fit(); addEventListener('resize', fit)"
     class="fixed inset-0">
    <div x-ref="stage" class="stage olive" style="transform: translate(-50%, -50%)">

        {{-- الرأس: الشعار والساعة والتاريخ --}}
        <header class="absolute inset-x-0 top-0 h-[130px] px-[66px] flex items-center justify-between border-b border-white/10">
            <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="h-[74px] w-auto" x-show="view !== 'idle'">
            <span x-show="view === 'idle'"></span>
            <div class="flex items-center gap-6" data-clock>
                <div class="text-[24px] leading-[1.45] text-[#d6cfc0] text-start">
                    <div data-clock-date></div>
                    <div data-clock-hijri></div>
                </div>
                <div class="num text-[64px] leading-none text-ivory">
                    <span data-clock-time></span><span class="text-gold text-[34px] ms-2" style="font-family: var(--font-display)" data-clock-suffix></span>
                </div>
            </div>
        </header>

        {{-- النداءات السابقة --}}
        <aside class="absolute top-[130px] bottom-[90px] left-0 w-[622px] border-r border-white/10 bg-gradient-to-b from-black/15 to-black/5 px-[54px] pt-[48px]" x-show="view !== 'idle'">
            <h2 class="text-[32px] font-bold text-ivory pb-6 border-b border-white/10">{{ __('النداءات السابقة') }}</h2>
            <ol>
                <template x-for="(c, i) in recent.slice(0, 5)" :key="c.ticket_no + c.at">
                    <li class="h-[108px] flex items-center justify-between border-b border-white/10 fade">
                        <span class="num text-[60px] leading-none" :class="i === 0 ? 'text-gold' : 'text-ivory'" x-text="c.ticket_no"></span>
                        <span class="flex items-baseline gap-8">
                            <span class="text-[30px] text-ivory">{{ __('مكتب') }} <b class="num text-gold" x-text="c.desk"></b></span>
                            <span class="num text-[24px] text-[#a9a597]" x-text="timeOf(c.at)"></span>
                        </span>
                    </li>
                </template>
            </ol>
            <p x-show="!recent.length" class="mt-10 text-[26px] text-[#a9a597]">{{ __('لا توجد نداءات سابقة اليوم') }}</p>
        </aside>

        {{-- الرقم الحالي --}}
        <section x-show="view === 'current'" class="absolute top-[200px] bottom-[162px] left-[710px] right-[90px] rounded-[40px] border-[1.5px] border-gold/35 grid place-items-center text-center">
            <template x-if="current">
                <div class="w-full" x-effect="zoomKey; $el.classList.remove('zoom-in'); void $el.offsetWidth; $el.classList.add('zoom-in')">
                    <div class="text-[40px] text-[#e2dccd]" x-text="current.tickets.length > 1 ? '{{ __('الأرقام الحالية') }}' : '{{ __('الرقم الحالي') }}'"></div>
                    <div class="num text-gold leading-[1.02] mt-1 flex flex-wrap items-center justify-center gap-x-10"
                         :class="current.tickets.length > 1 ? 'text-[170px]' : 'text-[300px]'">
                        <template x-for="(t, i) in current.tickets" :key="t">
                            <span class="flex items-center gap-10"><span x-show="i > 0" class="text-[90px] text-gold/70">+</span><span x-text="t"></span></span>
                        </template>
                    </div>
                    <div class="mt-6 inline-flex items-center gap-7 rounded-[24px] bg-shade px-12 h-[118px] text-olive" x-effect="zoomKey; $el.classList.remove('glow'); void $el.offsetWidth; $el.classList.add('glow')">
                        <span class="text-[40px]">{{ __('تفضّل إلى') }}</span>
                        <span class="text-[72px] font-bold">{{ __('مكتب') }}</span>
                        <span class="num text-[72px] text-gold-dark" x-text="current.desk"></span>
                    </div>
                    <div class="mt-6 text-[38px] text-[#e2dccd] h-[48px]" x-text="current.names.join(' · ')"></div>
                </div>
            </template>
        </section>

        {{-- لوحة المكاتب --}}
        <section x-show="view === 'offices'" x-cloak class="absolute top-[200px] bottom-[162px] left-[710px] right-[90px]">
            <div class="h-full grid gap-6" :class="offices.length > 6 ? 'grid-cols-4' : (offices.length > 4 ? 'grid-cols-3' : 'grid-cols-2')">
                <template x-for="o in offices" :key="o.number">
                    <div class="rounded-[28px] border-[1.5px] flex flex-col items-center justify-center"
                         :class="o.tickets.length ? 'border-gold/60 bg-black/10' : 'border-white/10'">
                        <div class="text-[34px] text-[#e2dccd]">{{ __('مكتب') }} <b class="num text-gold" x-text="o.number"></b></div>
                        <div class="num mt-3 leading-none" :class="o.tickets.length ? 'text-gold text-[96px]' : 'text-[#7d8079] text-[48px]'"
                             x-text="o.tickets.length ? o.tickets.join(' + ') : '{{ __('متاح') }}'"></div>
                    </div>
                </template>
            </div>
        </section>

        {{-- وقت الهدوء: الشعار --}}
        <section x-show="view === 'idle'" x-cloak class="absolute top-[130px] bottom-[90px] inset-x-0 grid place-items-center text-center fade">
            <div>
                <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="w-[880px] h-auto mx-auto">
                <div class="mt-14 mx-auto w-[120px] h-[2px] bg-gold/70"></div>
                <p class="mt-10 text-[38px] text-[#e2dccd]">{{ __('أهلًا بكم، تابع رقمك على الشاشة أو على واتساب') }}</p>
            </div>
        </section>

        {{-- الشريط السفلي: رسائل الإدارة وعدد المنتظرين --}}
        <footer class="absolute inset-x-0 bottom-0 h-[90px] border-t border-white/10 bg-black/15 px-[66px] flex items-center gap-10">
            <div class="ticker flex-1 text-[30px] text-ivory" :style="`--dur: ${Math.max(28, ticker.join('').length * 0.32)}s`">
                <template x-if="ticker.length">
                    <span class="track">
                        <template x-for="(m, i) in ticker" :key="i">
                            <span><span x-text="m"></span><span class="text-gold text-[22px] mx-10" aria-hidden="true">◆</span></span>
                        </template>
                    </span>
                </template>
            </div>
            <div class="shrink-0 flex items-baseline gap-9 text-[28px] text-[#e2dccd]">
                <span>{{ __('مشتري') }} <b class="num text-gold text-[38px]" x-text="waiting.buyer"></b></span>
                <span>{{ __('بائع') }} <b class="num text-gold text-[38px]" x-text="waiting.seller"></b></span>
                <span>{{ __('الانتظار التقريبي') }} <b class="num text-gold text-[38px]" x-text="waiting.minutes"></b>{{ __('د') }}</span>
            </div>
        </footer>

        <div x-show="offline" x-cloak class="absolute top-[150px] left-1/2 -translate-x-1/2 rounded-full bg-danger text-white text-[24px] px-8 py-2">{{ __('انقطع الاتصال… جارٍ إعادة المحاولة') }}</div>
    </div>

    {{-- المتصفح لا يسمح بالصوت قبل أول لمسة --}}
    <button x-show="needsTap" x-cloak @click="enableSound()" class="fixed top-4 left-1/2 -translate-x-1/2 z-10 rounded-full bg-gold text-olive font-bold px-6 py-3 shadow-lg">
        {{ __('اضغط لتفعيل صوت النداء') }}
    </button>
</div>
@endsection
