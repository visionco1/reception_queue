@extends('layouts.base')
@section('title', __('شاشة العرض'))
@section('body_class', 'antialiased overflow-hidden bg-[#141914]')

@push('head')
    <style>
        /* لوحة بمقاس 1920×1080 تُكبَّر وتُصغَّر لتملأ أي شاشة بنفس النسب */
        .stage { width: 1920px; height: 1080px; position: absolute; top: 50%; left: 50%; transform-origin: center; color: var(--color-ivory); }
        .num { font-family: var(--font-num); font-weight: 600; direction: ltr; unicode-bidi: isolate; letter-spacing: 0.035em; font-variant-numeric: tabular-nums; }
        /* نداء جديد: الرقم يومض 3 مرات ثم يثبت، والإطار يتوهج ذهبيًا */
        .blink { animation: blink 520ms ease-in-out 3 both; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: .15; } }
        .call-frame { transition: border-color 600ms, box-shadow 600ms, background-color 600ms; }
        .call-frame.alerting { border-color: rgb(187 152 96 / .95); background-color: rgb(187 152 96 / .06); animation: frameGlow 2s ease-in-out infinite; }
        @keyframes frameGlow {
            0%, 100% { box-shadow: 0 0 0 6px rgb(187 152 96 / .14), 0 0 60px 4px rgb(187 152 96 / .22), inset 0 0 70px rgb(187 152 96 / .12); }
            50% { box-shadow: 0 0 0 10px rgb(187 152 96 / .2), 0 0 90px 10px rgb(187 152 96 / .32), inset 0 0 90px rgb(187 152 96 / .18); }
        }
        .ticker { overflow: hidden; direction: ltr; white-space: nowrap; }
        .ticker .track { display: inline-block; padding-left: 100%; animation: tick var(--dur, 40s) linear infinite; direction: rtl; }
        @keyframes tick { from { transform: translateX(-100%); } to { transform: translateX(0); } }
        .fade { animation: fade 700ms ease both; }
        @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
        .ready-dot { box-shadow: 0 0 0 6px rgb(74 167 96 / .22); animation: ready 2.4s ease-in-out infinite; }
        @keyframes ready { 50% { box-shadow: 0 0 0 10px rgb(74 167 96 / .08); } }
    </style>
@endpush

@section('body')
<div x-data="display({ feedUrl: @js(route('display.feed', $token)), version: @js($version), pollMs: {{ (int) config('qms.queue.poll_ms', 2500) }} })"
     x-init="const fit = () => { const s = Math.min(innerWidth / 1920, innerHeight / 1080); $refs.stage.style.transform = `translate(-50%, -50%) scale(${s})`; }; fit(); addEventListener('resize', fit)"
     class="fixed inset-0">
    <div x-ref="stage" class="stage olive" style="transform: translate(-50%, -50%)">

        {{-- الرأس: الشعار (مخفي في الخمول لأن الشعار الكبير في المنتصف) والساعة والتاريخ --}}
        <header class="absolute inset-x-0 top-0 h-[130px] px-[66px] flex items-center justify-between border-b border-white/10">
            <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="h-[74px] w-auto transition-opacity duration-[600ms]"
                 :class="view === 'idle' && 'opacity-0'">
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

        {{-- الحالة 1 · الخمول: ترحيب بالشعار وخطوات الخدمة، بدون جدول --}}
        <section x-show="view === 'idle'" x-transition.opacity.duration.600ms class="absolute top-[130px] bottom-[90px] inset-x-0">
            {{-- القوسان الذهبيان في الزاويتين --}}
            <span class="absolute top-0 left-0 w-[360px] h-[280px] border-r-[1.5px] border-b-[1.5px] border-gold/45 rounded-br-[200px] pointer-events-none" aria-hidden="true"></span>
            <span class="absolute bottom-0 right-0 w-[360px] h-[280px] border-l-[1.5px] border-t-[1.5px] border-gold/45 rounded-tl-[200px] pointer-events-none" aria-hidden="true"></span>

            <div class="h-full flex flex-col items-center justify-center text-center pb-6">
                <p class="text-[40px] font-bold text-gold">{{ __('أهلًا وسهلًا بكم') }}</p>
                <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="mt-8 w-[900px] h-auto">
                <div class="mt-12 w-[120px] h-[2px] bg-gold/70"></div>

                <ol class="mt-14 flex gap-8">
                    @foreach ([
                        [__('سجّل بياناتك'), __('من جهاز الاستقبال عند المدخل')],
                        [__('انتظر رقمك'), __('يظهر هنا وعلى واتساب')],
                        [__('توجّه للمكتب'), __('الظاهر بجانب رقمك')],
                    ] as $i => [$title, $hint])
                        <li class="w-[380px] h-[96px] rounded-[20px] border border-white/15 bg-black/20 px-7 flex items-center gap-5 text-start">
                            <b @class(['num shrink-0 size-[52px] rounded-full grid place-items-center text-[24px]',
                                       'bg-gold text-olive' => $i === 0, 'border-[1.5px] border-gold text-gold' => $i > 0])>{{ $i + 1 }}</b>
                            <span>
                                <span class="block text-[28px] font-bold text-ivory leading-tight">{{ $title }}</span>
                                <span class="block text-[19px] text-[#a9a597] mt-1">{{ $hint }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <p class="absolute bottom-[34px] left-[66px] flex items-center gap-4 text-[24px] text-[#d6cfc0]">
                <span class="ready-dot size-[14px] rounded-full bg-[#4aa760]"></span>{{ __('المكاتب جاهزة لاستقبالكم') }}
            </p>
        </section>

        {{-- الحالتان 2 و3 · اليسار: جدول النداءات السابقة (يدخل من اليسار) --}}
        <aside x-show="showTable" x-cloak
               x-transition:enter="transition duration-[600ms] ease-out" x-transition:enter-start="-translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
               x-transition:leave="transition duration-[600ms] ease-in" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="-translate-x-full opacity-0"
               class="absolute top-[130px] bottom-[90px] left-0 w-[622px] border-r border-white/10 bg-gradient-to-b from-black/15 to-black/5 px-[40px] pt-[44px]">
            <h2 class="text-[32px] font-bold text-ivory pb-5 px-[14px] border-b border-white/10">{{ __('النداءات السابقة') }}</h2>
            <ol class="mt-3">
                <template x-for="(c, i) in recent.slice(0, 7)" :key="c.ticket_no + c.at">
                    <li class="h-[104px] px-[14px] flex items-center justify-between border-b border-white/10 rounded-[14px] fade transition-colors duration-[600ms]"
                        :class="i === 0 && view === 'alert' && 'bg-gold/15 border-transparent'">
                        <span class="num text-[60px] leading-none" :class="i === 0 ? 'text-gold' : 'text-ivory'" x-text="c.ticket_no"></span>
                        <span class="flex items-baseline gap-8">
                            <span class="text-[30px] text-ivory">{{ __('مكتب') }} <b class="num text-gold" x-text="c.desk"></b></span>
                            <span class="num text-[24px] text-[#a9a597]" x-text="timeOf(c.at)"></span>
                        </span>
                    </li>
                </template>
            </ol>
            <p x-show="!recent.length" class="mt-10 px-[14px] text-[26px] text-[#a9a597]">{{ __('لا توجد نداءات سابقة اليوم') }}</p>
        </aside>

        {{-- الحالتان 2 و3 · اليمين: الرقم الحالي (في التنبيه: شارة، توهج ذهبي، وميض) --}}
        <section x-show="showTable" x-cloak x-transition.opacity.duration.600ms
                 class="call-frame absolute top-[200px] bottom-[162px] left-[710px] right-[90px] rounded-[40px] border-[1.5px] border-white/15 grid place-items-center text-center"
                 :class="view === 'alert' && 'alerting'">
            <template x-if="current">
                <div class="w-full">
                    <div class="h-[58px] flex items-end justify-center">
                        <span x-show="view === 'alert'" x-transition.opacity.duration.300ms
                              class="inline-flex items-center gap-3 rounded-full bg-gold text-olive text-[26px] font-bold px-7 h-[50px]">
                            <span aria-hidden="true">🔔</span>{{ __('نداء جديد') }}
                        </span>
                    </div>
                    <div class="mt-4 text-[40px]" :class="view === 'alert' ? 'text-gold' : 'text-[#e2dccd]'"
                         x-text="current.tickets.length > 1 ? '{{ __('الأرقام الحالية') }}' : '{{ __('الرقم الحالي') }}'"></div>
                    <div class="num text-gold leading-[1.02] mt-1 flex flex-wrap items-center justify-center gap-x-10"
                         :class="current.tickets.length > 1 ? 'text-[170px]' : 'text-[300px]'"
                         x-effect="zoomKey; $el.classList.remove('blink'); void $el.offsetWidth; $el.classList.add('blink')">
                        <template x-for="(t, i) in current.tickets" :key="t">
                            <span class="flex items-center gap-10"><span x-show="i > 0" class="text-[90px] text-gold/70">+</span><span x-text="t"></span></span>
                        </template>
                    </div>
                    <div class="mt-6 inline-flex items-center gap-7 rounded-[24px] bg-shade px-12 h-[118px] text-olive">
                        <span class="text-[40px]">{{ __('تفضّل إلى') }}</span>
                        <span class="text-[72px] font-bold">{{ __('مكتب') }}</span>
                        <span class="num text-[72px] text-gold-dark" x-text="current.desk"></span>
                    </div>
                    <div class="mt-6 text-[38px] text-[#e2dccd] h-[48px]" x-text="current.names.join(' · ')"></div>
                </div>
            </template>
        </section>

        {{-- لوحة المكاتب (وضع اختياري من الإعدادات بدل الرقم الحالي) --}}
        <section x-show="view === 'offices'" x-cloak x-transition.opacity.duration.600ms class="absolute top-[200px] bottom-[162px] left-[90px] right-[90px]">
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

        {{-- الشريط السفلي: رسائل الإدارة، وعدد المنتظرين حسب الفئة --}}
        <footer class="absolute inset-x-0 bottom-0 h-[90px] border-t border-white/10 bg-black/25 px-[66px] flex items-center gap-10">
            <div class="ticker flex-1 text-[28px] text-ivory" :style="`--dur: ${Math.max(28, ticker.join('').length * 0.32)}s`">
                <template x-if="ticker.length">
                    <span class="track">
                        <template x-for="(m, i) in ticker" :key="i">
                            <span><span x-text="m"></span><span class="text-gold text-[22px] mx-10" aria-hidden="true">◆</span></span>
                        </template>
                    </span>
                </template>
            </div>
            <div x-show="waiting.total > 0" class="shrink-0 flex items-baseline gap-9 text-[28px] text-[#e2dccd]">
                <span>{{ __('مشتري') }} <b class="num text-gold text-[38px]" x-text="waiting.buyer"></b></span>
                <span>{{ __('بائع') }} <b class="num text-gold text-[38px]" x-text="waiting.seller"></b></span>
                <span>{{ __('الانتظار التقريبي') }} <b class="num text-gold text-[38px]" x-text="waiting.minutes"></b>{{ __('د') }}</span>
            </div>
            <div x-show="waiting.total === 0" class="shrink-0 text-[26px] text-[#d6cfc0]">{{ __('لا يوجد انتظار حاليًا') }}</div>
        </footer>

        {{-- انقطاع الاتصال: تبقى آخر شاشة ظاهرة مع نقطة حمراء صغيرة --}}
        <span x-show="offline" x-cloak class="absolute bottom-[14px] left-[18px] size-[12px] rounded-full bg-danger" title="{{ __('انقطع الاتصال… جارٍ إعادة المحاولة') }}"></span>
    </div>

    {{-- المتصفح لا يسمح بالصوت قبل أول لمسة --}}
    <button x-show="needsTap" x-cloak @click="enableSound()" class="fixed top-4 left-1/2 -translate-x-1/2 z-10 rounded-full bg-gold text-olive font-bold px-6 py-3 shadow-lg">
        {{ __('اضغط لتفعيل صوت النداء') }}
    </button>
</div>
@endsection
