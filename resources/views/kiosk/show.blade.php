@extends('layouts.base')
@section('title', __('الاستقبال'))
@section('body_class', 'min-h-dvh antialiased overflow-x-hidden select-none bg-ivory')

@push('head')
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ route('kiosk.manifest', $token) }}">
    <style>
        .k-card { background: #fff; border: 1.5px solid var(--color-line); border-radius: 20px; padding: 18px; }
        .k-label { display: block; font-size: 16px; font-weight: 700; margin-bottom: 8px; color: var(--color-ink); }
        .k-input { height: 66px; width: 100%; border-radius: 14px; border: 1.5px solid var(--color-line); background: #fff; padding-inline: 20px; font-size: 20px; color: var(--color-ink); }
        .k-input:focus { outline: none; border-color: var(--color-gold); box-shadow: 0 0 0 3px rgb(187 152 96 / 0.18); }
        .k-input.has-value { border-color: var(--color-gold); }
        .k-input::placeholder { color: #a8a79f; letter-spacing: 0.04em; }
        .k-hint { font-size: 13.5px; color: var(--color-ink-faint); }
        .k-err { font-size: 13.5px; color: var(--color-danger); margin-top: 6px; }

        .k-seg { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; background: var(--color-shade); border-radius: 16px; padding: 4px; }
        .k-seg button { height: 66px; border-radius: 13px; font-size: 21px; font-weight: 700; color: #6f6a5f; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .k-seg button[aria-checked='true'] { background: var(--color-olive); color: var(--color-ivory); }
        .k-seg button[aria-checked='true'] svg { color: var(--color-gold); }

        .k-opt { height: 76px; border-radius: 16px; border: 1.5px solid var(--color-line); background: #fff; display: flex; align-items: center; justify-content: center; gap: 12px; font-size: 21px; font-weight: 700; color: var(--color-ink); }
        .k-opt svg { color: var(--color-gold-dark); }
        .k-opt[aria-checked='true'] { border-color: var(--color-gold); background: #FBF6EC; box-shadow: inset 0 0 0 1px var(--color-gold); }

        .k-bank { min-height: 60px; border-radius: 14px; border: 1.5px solid var(--color-line); background: #fff; font-size: 15px; padding: 6px 10px; line-height: 1.3; color: var(--color-ink); }
        .k-bank[aria-checked='true'] { background: var(--color-olive); border-color: var(--color-olive); color: var(--color-ivory); font-weight: 700; }
        .k-bank.more { color: var(--color-gold-dark); border-style: dashed; }

        .k-submit { height: 72px; border-radius: 16px; background: var(--color-olive); color: var(--color-ivory); font-size: 23px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 20px; box-shadow: 0 14px 30px rgb(31 38 31 / 0.18); transition: opacity 150ms; }
        .k-submit:disabled { opacity: 0.38; box-shadow: none; }

        .k-step { display: flex; align-items: center; gap: 14px; font-size: 17px; color: #e9e3d6; }
        .k-step b { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 999px; border: 1.5px solid var(--color-gold); color: var(--color-gold); font-family: var(--font-num); font-weight: 500; font-size: 16px; flex-shrink: 0; }

        /* تذكرة الرقم: زيتية برقم ذهبي وحواف مثقوبة */
        .k-ticket { position: relative; background: var(--color-olive); border-radius: 20px; color: var(--color-ivory); box-shadow: 0 22px 44px rgb(31 38 31 / 0.22); }
        .k-ticket .notch { position: absolute; top: 132px; width: 30px; height: 30px; border-radius: 999px; background: var(--color-ivory); }
        .k-ticket .num { font-family: var(--font-num); font-weight: 600; font-size: 96px; line-height: 1; color: var(--color-gold); letter-spacing: 0.06em; direction: ltr; }
        .k-pop { animation: kpop 520ms cubic-bezier(.2,.9,.3,1.2) both; }
        @keyframes kpop { from { transform: translateY(18px) scale(.96); opacity: 0; } to { transform: none; opacity: 1; } }

        @media (max-width: 1180px) {
            .k-input, .k-seg button { height: 58px; font-size: 18px; }
            .k-opt { height: 66px; font-size: 18px; }
            .k-submit { height: 64px; font-size: 20px; }
            .k-ticket .num { font-size: 80px; }
        }
    </style>
@endpush

@section('body')
<div x-data="kiosk({
        registerUrl: @js(route('kiosk.register', $token)),
        stateUrl: @js(route('kiosk.state', $token)),
        resetSeconds: {{ max(5, (int) setting('kiosk_reset_seconds', 10)) }},
        featured: @js($featured->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()),
        banks: @js($banks->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()),
        agents: @js($agents),
        status: @js($status),
     })"
     class="min-h-dvh lg:h-dvh grid lg:grid-cols-[minmax(340px,440px)_1fr]">

    {{-- اللوحة الزيتية: الشعار، الساعة، الترحيب، خطوات الاستخدام --}}
    <aside class="olive px-8 py-8 lg:px-[42px] lg:py-[46px] flex flex-col lg:h-dvh">
        <span class="hidden lg:block absolute top-10 bottom-10 w-[120px] border-b-[1.5px] border-gold/80 pointer-events-none
                     ltr:right-[30px] ltr:border-r-[1.5px] ltr:rounded-br-[34px] rtl:left-[30px] rtl:border-l-[1.5px] rtl:rounded-bl-[34px]" aria-hidden="true"></span>
        <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="brand-logo w-[230px] lg:w-[280px]">

        <div class="mt-7" data-clock>
            <div class="font-num font-semibold text-[40px] leading-none text-ivory">
                <span class="ltr-nums" data-clock-time></span> <span class="text-gold text-[24px]" data-clock-suffix></span>
            </div>
            <div class="mt-2 text-[14px] text-[#d6cfc0]"><span data-clock-date></span> · <span data-clock-hijri></span></div>
        </div>

        <h2 class="mt-8 text-[40px] font-bold text-white leading-tight" x-text="t('welcome')">{{ __('أهلًا بكم') }}</h2>
        <p class="mt-3 text-[17px] leading-[1.9] text-[#e2dccd] max-w-[19rem]" x-text="t('welcomeText')">{{ setting('welcome_message') }}</p>

        <ol class="mt-8 lg:mt-auto space-y-4 lg:pb-2">
            <template x-for="(s, i) in t('steps')" :key="i">
                <li class="k-step"><b x-text="i + 1"></b><span x-text="s"></span></li>
            </template>
        </ol>
    </aside>

    <main class="relative px-6 sm:px-10 lg:px-[60px] py-8 lg:py-[38px] lg:h-dvh lg:overflow-y-auto flex flex-col">
        {{-- النموذج --}}
        <section x-show="step !== 'ticket'" class="flex flex-col flex-1 max-w-[860px] w-full">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-[32px] lg:text-[38px] font-bold leading-tight" x-text="t('title')">{{ __('سجّل وخذ رقمك') }}</h1>
                    <p class="mt-1 text-[16px] text-ink-faint" x-show="status.open && status.waiting === 0" x-text="t('noQueue')"></p>
                    <p class="mt-1 text-[16px] text-ink-faint" x-show="status.open && status.waiting > 0">
                        <span x-text="t('waitingNow')"></span>
                        <b class="font-num text-gold-dark text-[18px]" x-text="status.waiting"></b>
                        · <span x-text="t('about')"></span>
                        <b class="font-num text-gold-dark text-[18px]" x-text="status.minutes"></b>
                        <span x-text="minUnit(status.minutes)"></span>
                    </p>
                    <p class="mt-1 text-[16px] text-danger font-medium" x-show="!status.open" x-cloak>
                        <span x-text="t('closed')"></span> <span class="font-num" x-text="status.hours"></span>
                    </p>
                </div>
                <button type="button" @click="toggleLang()" class="shrink-0 h-[38px] px-4 rounded-full border-[1.5px] border-line bg-white text-[15px] text-ink-soft font-num"
                        x-text="lang === 'ar' ? 'English' : 'العربية'">English</button>
            </div>

            <form @submit.prevent="submit()" class="mt-6 flex flex-col flex-1 gap-[16px]" novalidate>
                <div class="k-card space-y-[14px]">
                    <div>
                        <label for="k-name" class="k-label" x-text="t('name')">{{ __('الاسم') }}</label>
                        <input id="k-name" type="text" x-model="form.name" @input="delete errors.name" autocomplete="off" autocapitalize="words" maxlength="100"
                               class="k-input" :class="form.name.trim().length >= 2 && 'has-value'" :placeholder="t('namePh')" enterkeyhint="next">
                        <p class="k-err" x-show="errors.name" x-text="errors.name" x-cloak></p>
                    </div>
                    <div>
                        <label for="k-mobile" class="k-label" x-text="t('mobile')">{{ __('رقم الجوال') }}</label>
                        <div class="flex gap-[12px]">
                            <input id="k-mobile" type="tel" inputmode="numeric" :value="form.mobile" @input="onMobileInput($event)" autocomplete="off"
                                   class="k-input flex-1 font-num tracking-[0.06em]" style="direction: ltr" :class="/^5\d{8}$/.test(mobileDigits) && 'has-value'"
                                   :style="{ textAlign: lang === 'ar' ? 'right' : 'left' }" placeholder="05X XXX XXXX">
                            <span class="grid place-items-center w-[100px] h-[66px] max-[1180px]:h-[58px] rounded-[14px] bg-shade font-num text-[20px] text-ink-soft shrink-0" dir="ltr">+966</span>
                        </div>
                        <p class="k-hint mt-2 flex items-center gap-1.5" x-show="!errors.mobile">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3.5 20.5l1.3-4A8.5 8.5 0 1 1 8 19.6z" stroke-linejoin="round"/></svg>
                            <span x-text="t('mobileHint')"></span>
                        </p>
                        <p class="k-err" x-show="errors.mobile" x-text="errors.mobile" x-cloak></p>
                    </div>
                </div>

                <div class="k-card space-y-[14px]">
                    <div class="k-seg" role="radiogroup" :aria-label="t('buyer') + ' / ' + t('seller')">
                        <button type="button" role="radio" :aria-checked="form.customer_type === 'buyer'" @click="form.customer_type = 'buyer'; delete errors.customer_type">
                            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg>
                            <span x-text="t('buyer')">{{ __('مشتري') }}</span>
                        </button>
                        <button type="button" role="radio" :aria-checked="form.customer_type === 'seller'" @click="form.customer_type = 'seller'; delete errors.customer_type">
                            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M9 15h6"/></svg>
                            <span x-text="t('seller')">{{ __('بائع') }}</span>
                        </button>
                    </div>
                    <p class="k-err !mt-1" x-show="errors.customer_type" x-text="errors.customer_type" x-cloak></p>

                    <div class="grid grid-cols-2 gap-[14px]" role="radiogroup">
                        <button type="button" role="radio" class="k-opt" :aria-checked="form.via === 'bank'" @click="setVia('bank')">
                            <svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 9.5L12 4l9 5.5"/><path d="M4 21h16M5 18h14"/><path d="M6.5 10.5v6M10 10.5v6M14 10.5v6M17.5 10.5v6"/></svg>
                            <span x-text="t('viaBank')">{{ __('عن طريق بنك') }}</span>
                        </button>
                        <button type="button" role="radio" class="k-opt" :aria-checked="form.via === 'other'" @click="setVia('other')">
                            <span class="text-gold-dark tracking-[0.15em] text-[20px] leading-none" aria-hidden="true">•••</span>
                            <span x-text="t('other')">{{ __('أخرى') }}</span>
                            <span class="text-[14px] font-normal text-ink-faint hidden sm:inline" x-text="t('otherHint')"></span>
                        </button>
                    </div>
                    <p class="k-err !mt-1" x-show="errors.via" x-text="errors.via" x-cloak></p>

                    {{-- البنوك الأربعة من الإدارة + «بنك آخر» --}}
                    <div x-show="form.via === 'bank'" x-cloak class="grid grid-cols-5 gap-[10px]" role="radiogroup" :aria-label="t('chooseBank')">
                        <template x-for="b in featured" :key="b.id">
                            <button type="button" role="radio" class="k-bank" :aria-checked="String(form.bank_id) === String(b.id)" @click="pickBank(b.id)" x-text="b.name"></button>
                        </template>
                        <button type="button" class="k-bank more" :class="otherBankName && '!bg-olive !text-ivory !border-olive !border-solid font-bold'" @click="banksOpen = true">
                            <span x-text="otherBankName || t('otherBank')"></span> <span aria-hidden="true">▾</span>
                        </button>
                    </div>
                    <p class="k-err !mt-1" x-show="errors.bank_id" x-text="errors.bank_id" x-cloak></p>

                    {{-- وكيل البنك (اختياري): يظهر إن كان للبنك المختار وكلاء --}}
                    <div x-show="form.via === 'bank' && bankAgents.length" x-cloak>
                        <p class="text-[15px] font-semibold text-ink-soft mb-2" x-text="t('chooseAgent')"></p>
                        <div class="grid grid-cols-4 gap-[10px]" role="radiogroup" :aria-label="t('chooseAgent')">
                            <template x-for="a in bankAgents" :key="a.id">
                                <button type="button" role="radio" class="k-bank" :aria-checked="String(form.agent_id) === String(a.id)" @click="pickAgent(a.id)" x-text="a.name"></button>
                            </template>
                        </div>
                    </div>
                    <p class="k-err !mt-1" x-show="errors.agent_id" x-text="errors.agent_id" x-cloak></p>

                    {{-- تفصيل «أخرى» (اختياري) --}}
                    <div x-show="form.via === 'other'" x-cloak class="grid grid-cols-2 gap-[10px]" role="radiogroup">
                        <button type="button" role="radio" class="k-bank" :aria-checked="form.via_detail === 'company'" @click="form.via_detail = form.via_detail === 'company' ? '' : 'company'" x-text="t('company')"></button>
                        <button type="button" role="radio" class="k-bank" :aria-checked="form.via_detail === 'individuals'" @click="form.via_detail = form.via_detail === 'individuals' ? '' : 'individuals'" x-text="t('individuals')"></button>
                    </div>
                </div>

                <p class="flash flash-error" x-show="error" x-text="error" x-cloak role="alert"></p>

                <div class="mt-auto pt-3">
                    <button type="submit" class="k-submit w-full" :disabled="!ready || step === 'sending'">
                        <span x-text="step === 'sending' ? t('sending') : t('register')">{{ __('تسجيل وأخذ رقم') }}</span>
                        <span class="gold-rule !w-10" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </section>

        {{-- تذكرة الرقم بعد التسجيل --}}
        <section x-show="step === 'ticket'" x-cloak class="flex-1 grid place-items-center">
            <template x-if="result">
                <div class="w-full max-w-[470px] text-center k-pop" role="status" aria-live="polite">
                    <div class="mx-auto grid place-items-center size-[116px] rounded-full bg-shade">
                        <div class="grid place-items-center size-[96px] rounded-full bg-olive">
                            <svg viewBox="0 0 24 24" class="size-9 text-gold" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>
                    <h1 class="mt-5 text-[34px] lg:text-[38px] font-bold leading-tight">
                        <span x-text="result.existing ? t('existing') : t('done')"></span>
                        <span x-show="!result.existing" x-text="result.first_name"></span>
                    </h1>
                    <p class="text-ink-faint text-[17px]" x-text="t('yourNumber')"></p>

                    <div class="k-ticket mt-4 px-8 pt-8 pb-7">
                        <span class="notch -right-[15px]"></span><span class="notch -left-[15px]"></span>
                        <div class="num" x-text="result.ticket_no"></div>
                        <div class="mt-7 border-t border-dashed border-white/25"></div>
                        <div class="mt-5 text-[16px] text-[#e2dccd]">
                            <span x-text="lang === 'ar' ? result.type_label : (result.type === 'buyer' ? 'Buyer' : 'Seller')"></span>
                            <span class="mx-3 text-gold">·</span>
                            <span x-text="lang === 'ar' ? result.via : result.via_en"></span>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-[16px] border-[1.5px] border-line bg-white py-4">
                            <div class="font-num font-semibold text-[34px] leading-none" x-text="result.ahead"></div>
                            <div class="mt-2 text-[14px] text-ink-faint" x-text="t('ahead')"></div>
                        </div>
                        <div class="rounded-[16px] border-[1.5px] border-line bg-white py-4">
                            <div class="font-num font-semibold text-[34px] leading-none" dir="ltr"><span>~ </span><span x-text="result.minutes"></span></div>
                            <div class="mt-2 text-[14px] text-ink-faint" x-text="t('approx')"></div>
                        </div>
                    </div>

                    <p class="mt-5 text-[15px] text-ink-faint" x-show="result.whatsapp">
                        <span x-text="t('sentTo')"></span> <span class="font-num tracking-[0.06em]" dir="ltr" x-text="result.phone_masked"></span>
                    </p>

                    <button type="button" @click="reset()" class="mt-5 inline-flex items-center gap-3 h-[58px] px-7 rounded-[16px] border-[1.5px] border-olive bg-white">
                        <span class="text-[18px] font-bold" x-text="t('newCustomer')"></span>
                        <span class="text-[14px] text-ink-faint">· <span x-text="t('backIn')"></span> <b class="font-num text-ink" x-text="countdown"></b> <span x-text="t('seconds')"></span></span>
                    </button>
                </div>
            </template>
        </section>
    </main>

    {{-- قائمة البنوك الكاملة --}}
    <div x-show="banksOpen" x-cloak class="fixed inset-0 z-40 bg-olive/60 grid place-items-center p-6" @click.self="banksOpen = false" @keydown.escape.window="banksOpen = false">
        <div class="w-full max-w-[760px] max-h-[85dvh] overflow-y-auto rounded-[20px] bg-ivory p-7" role="dialog" aria-modal="true" :aria-label="t('chooseBank')" x-trap.noscroll="banksOpen">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[26px] font-bold" x-text="t('chooseBank')"></h2>
                <button type="button" class="btn btn-quiet" @click="banksOpen = false" x-text="t('close')"></button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach ($banks as $b)
                    <button type="button" class="k-bank !min-h-[64px] !text-[17px]" :aria-checked="String(form.bank_id) === '{{ $b->id }}'" @click="pickBank({{ $b->id }})">{{ $b->name }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
