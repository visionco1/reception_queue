@extends('layouts.base')
@section('title', __('الاستقبال'))
@section('body_class', 'min-h-dvh antialiased overflow-x-hidden select-none')

@push('head')
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0f5c4d">
    <link rel="manifest" href="{{ route('kiosk.manifest', $token) }}">
    <style>
        .kiosk-input { height: 3.5rem; font-size: 1.25rem; border-radius: 0.75rem; padding-inline: 1.1rem; }
        .choice { border: 1.5px solid var(--color-line); background: var(--color-surface); border-radius: 0.75rem; transition: border-color 120ms, background 120ms, box-shadow 120ms; }
        .choice[aria-checked='true'] { border-color: currentColor; box-shadow: inset 0 0 0 1.5px currentColor; }
        .choice-buyer[aria-checked='true'] { background: var(--color-buyer-soft); color: var(--color-buyer); }
        .choice-seller[aria-checked='true'] { background: var(--color-seller-soft); color: var(--color-seller); }
        .choice-bank[aria-checked='true'] { background: var(--color-deed-soft); color: var(--color-deed-dark); }

        /* قسيمة الرقم: حواف مثقبة كتذكرة مطبوعة */
        .slip {
            --notch: 10px;
            background:
                radial-gradient(circle at 50% 0, transparent var(--notch), var(--color-surface) calc(var(--notch) + 0.5px)) top / 28px 51% repeat-x,
                radial-gradient(circle at 50% 100%, transparent var(--notch), var(--color-surface) calc(var(--notch) + 0.5px)) bottom / 28px 51% repeat-x;
            filter: drop-shadow(0 18px 30px rgb(25 34 47 / 0.12)) drop-shadow(0 2px 3px rgb(25 34 47 / 0.08));
        }
        .slip-rule { border-top: 2px dashed var(--color-line); }
        .slot { height: 10px; border-radius: 999px; background: var(--color-ink); opacity: 0.85; }
        .slip-print { animation: print 900ms cubic-bezier(.2,.8,.2,1) both; }
        @keyframes print { from { transform: translateY(-62%); clip-path: inset(62% 0 0 0); } to { transform: translateY(0); clip-path: inset(0 0 0 0); } }
    </style>
@endpush

@section('body')
<div x-data="kiosk({
        registerUrl: @js(route('kiosk.register', $token)),
        resetSeconds: {{ (int) setting('kiosk_reset_seconds', 15) }},
        agents: @js($banks->mapWithKeys(fn ($b) => [(string) $b->id => $b->agents->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name])->values()])),
     })"
     class="min-h-dvh grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">

    {{-- هوية الشركة وخطوات الرحلة --}}
    <aside class="bg-deed text-white px-8 py-8 lg:px-12 lg:py-14 flex flex-col">
        <div class="flex items-center gap-4">
            @if (setting('logo_path'))
                <img src="{{ asset(setting('logo_path')) }}" alt="" class="h-14 w-auto bg-white/95 rounded-lg p-1.5">
            @endif
            <div class="font-display font-bold text-2xl lg:text-[1.75rem] leading-snug">{{ setting('company_name') }}</div>
        </div>
        @if (setting('welcome_message'))
            <p class="mt-4 text-white/80 text-lg leading-relaxed max-w-md">{{ setting('welcome_message') }}</p>
        @endif

        <ol class="mt-8 lg:mt-auto grid sm:grid-cols-3 lg:grid-cols-1 gap-4 lg:gap-6">
            @foreach ([__('سجّل اسمك ورقم جوالك'), __('يصلك رقم انتظارك على واتساب'), __('تابع الشاشة وتوجّه للمكتب عند ظهور رقمك')] as $i => $step)
                <li class="flex items-center gap-4">
                    <span class="grid place-items-center size-10 shrink-0 rounded-full border border-white/40 font-display font-bold">{{ $i + 1 }}</span>
                    <span class="text-white/90 text-[1.05rem] leading-snug">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </aside>

    <main class="px-6 py-8 sm:px-10 lg:px-14 lg:py-9 flex flex-col">

        {{-- نموذج التسجيل --}}
        <form x-show="step !== 'ticket'" @submit.prevent="submit" class="w-full max-w-2xl mx-auto flex flex-col gap-6 lg:gap-5" novalidate>
            <h1 class="font-display font-bold text-3xl">{{ __('سجّل وخذ رقمك') }}</h1>

            <div>
                <label for="k-name" class="block text-lg font-semibold mb-2">{{ __('الاسم') }}</label>
                <input id="k-name" x-model="form.name" @input="delete errors.name" class="field kiosk-input" autocomplete="off" autocapitalize="words" maxlength="100" enterkeyhint="next" :aria-invalid="!!errors.name">
                <p class="field-error !text-base" x-show="errors.name" x-text="errors.name"></p>
            </div>

            <div>
                <label for="k-mobile" class="block text-lg font-semibold mb-2">{{ __('رقم الجوال') }}</label>
                <input id="k-mobile" :value="form.mobile" @input="onMobileInput" class="field kiosk-input ltr-nums tracking-wider" dir="ltr" inputmode="tel" autocomplete="off" maxlength="16" placeholder="05XXXXXXXX" :aria-invalid="!!errors.mobile">
                <p class="field-hint !text-base" x-show="!errors.mobile">{{ __('نرسل رقم انتظارك ونداءك على واتساب هذا الرقم') }}</p>
                <p class="field-error !text-base" x-show="errors.mobile" x-text="errors.mobile"></p>
            </div>

            <div role="radiogroup" aria-labelledby="k-type">
                <div id="k-type" class="text-lg font-semibold mb-2">{{ __('أنت') }}</div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (\App\Enums\CustomerType::queueTypes() as $type)
                        <button type="button" role="radio" :aria-checked="form.customer_type === '{{ $type->value }}'"
                                @click="form.customer_type = '{{ $type->value }}'; delete errors.customer_type"
                                class="choice choice-{{ $type->value }} h-[4.5rem] flex items-center justify-center gap-3 text-2xl font-display font-bold text-ink">
                            <span class="size-3.5 rounded-full {{ $type === \App\Enums\CustomerType::Buyer ? 'bg-buyer' : 'bg-seller' }}"></span>
                            {{ $type->label() }}
                        </button>
                    @endforeach
                </div>
                <p class="field-error !text-base" x-show="errors.customer_type" x-text="errors.customer_type"></p>
            </div>

            <div role="radiogroup" aria-labelledby="k-bank">
                <div id="k-bank" class="text-lg font-semibold mb-2">{{ __('البنك') }}</div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    @foreach ($banks as $bank)
                        <button type="button" role="radio" :aria-checked="form.bank_id === '{{ $bank->id }}'"
                                @click="selectBank('{{ $bank->id }}')"
                                class="choice choice-bank min-h-13 px-3 py-1.5 text-[1.02rem] font-semibold leading-snug text-ink">{{ $bank->name }}</button>
                    @endforeach
                    <button type="button" role="radio" :aria-checked="form.bank_id === 'none'"
                            @click="selectBank('none')"
                            class="choice choice-bank min-h-13 px-3 py-1.5 text-[1.02rem] text-ink-soft">{{ __('بدون بنك') }}</button>
                </div>
                <p class="field-error !text-base" x-show="errors.bank_id" x-text="errors.bank_id"></p>
            </div>

            {{-- وكيل البنك: يظهر فقط للبنك الذي له وكلاء مسجلون --}}
            <div role="radiogroup" aria-labelledby="k-agent" x-show="bankAgents.length" x-ref="agentBox" x-cloak>
                <div id="k-agent" class="text-lg font-semibold mb-2">{{ __('وكيل البنك') }}</div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    <template x-for="agent in bankAgents" :key="agent.id">
                        <button type="button" role="radio" :aria-checked="form.agent_id === agent.id"
                                @click="form.agent_id = agent.id; delete errors.agent_id"
                                class="choice choice-bank min-h-13 px-3 py-1.5 text-[1.02rem] font-semibold leading-snug text-ink" x-text="agent.name"></button>
                    </template>
                    <button type="button" role="radio" :aria-checked="form.agent_id === 'none'"
                            @click="form.agent_id = 'none'; delete errors.agent_id"
                            class="choice choice-bank min-h-13 px-3 py-1.5 text-[1.02rem] text-ink-soft">{{ __('لا أعرف / بدون وكيل') }}</button>
                </div>
                <p class="field-error !text-base" x-show="errors.agent_id" x-text="errors.agent_id"></p>
            </div>

            <div class="flash flash-error !text-base" x-show="error" x-text="error" role="alert"></div>

            <button type="submit" class="btn btn-primary h-16 !text-[1.4rem] !rounded-xl font-display" :disabled="step === 'sending'">
                <span x-show="step !== 'sending'">{{ __('تسجيل وأخذ رقم') }}</span>
                <span x-show="step === 'sending'" x-cloak>{{ __('جارٍ إصدار رقمك…') }}</span>
            </button>
        </form>

        {{-- قسيمة الرقم بعد التسجيل --}}
        <section x-show="step === 'ticket'" x-cloak class="w-full max-w-md mx-auto my-auto" aria-live="assertive">
            <template x-if="result">
                <div>
                    <div class="slot mx-6"></div>
                    <div class="overflow-hidden -mt-1 pb-8 px-1">
                        <div class="slip slip-print px-8 pt-10 pb-10 text-center">
                            <p class="text-ink-soft text-lg" x-text="result.existing ? @js(__('لديك رقم انتظار مسجل اليوم')) : @js(__('رقم انتظارك'))"></p>
                            <p class="ticket-code text-[clamp(5rem,16vw,8.5rem)] leading-none mt-3"
                               :class="result.type === 'buyer' ? 'text-buyer' : 'text-seller'" x-text="result.ticket_no"></p>
                            <p class="mt-4"><span class="type-tag !text-base" :class="'type-' + result.type" x-text="result.type_label"></span></p>
                            <p class="mt-3 text-ink-soft" x-show="result.agent">{{ __('وكيل البنك') }}: <span class="font-semibold text-ink" x-text="result.agent"></span></p>

                            <div class="slip-rule my-7"></div>

                            <p class="text-xl font-semibold" x-show="result.ahead > 0">
                                <span>{{ __('أمامك') }}</span>
                                <span class="ltr-nums font-display" x-text="result.ahead"></span>
                                <span x-text="result.ahead === 1 ? @js(__('عميل')) : (result.ahead === 2 ? @js(__('عميلان')) : (result.ahead <= 10 ? @js(__('عملاء')) : @js(__('عميلًا'))))"></span>
                            </p>
                            <p class="text-xl font-semibold" x-show="result.ahead === 0">{{ __('سيُنادى رقمك قريبًا') }}</p>
                            <p class="text-ink-soft mt-2 text-lg leading-relaxed">{{ __('تابع شاشة الانتظار، وسيظهر رقمك مع رقم المكتب.') }}</p>
                            <p class="text-ink-soft mt-4 text-base" x-show="result.whatsapp">
                                {{ __('أرسلنا الرقم على واتساب') }}
                                <span class="ltr-nums font-semibold text-ink" x-text="result.phone_masked"></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col items-center gap-3">
                        <button type="button" @click="reset" class="btn btn-quiet h-14 px-10 !text-lg !rounded-xl">{{ __('تم') }}</button>
                        <p class="text-ink-faint text-sm">
                            {{ __('تعود الشاشة للتسجيل خلال') }} <span class="ltr-nums" x-text="countdown"></span> {{ __('ثانية') }}
                        </p>
                    </div>
                </div>
            </template>
        </section>
    </main>
</div>
@endsection
