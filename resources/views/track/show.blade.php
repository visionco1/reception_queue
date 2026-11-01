@extends('layouts.base')
@section('title', __('متابعة دورك'))
@section('body_class', 'min-h-dvh antialiased bg-ivory')

@section('body')
<div x-data="{
        s: @js($state),
        async load() { try { const r = await fetch(@js(route('track.state', $token)), { headers: { Accept: 'application/json' }, cache: 'no-store' }); if (r.ok) this.s = await r.json(); } catch (e) {} },
        init() { setInterval(() => ['waiting', 'called', 'in_service'].includes(this.s.status) && this.load(), 15000); },
     }" class="min-h-dvh flex flex-col">
    <header class="olive px-6 pt-7 pb-16 text-center">
        <img src="{{ asset('brand/logo-gold.svg') }}" alt="{{ setting('company_name') }}" class="brand-logo w-[230px] mx-auto">
    </header>

    <main class="relative z-10 flex-1 px-5 -mt-10 pb-10">
        <div class="mx-auto max-w-md">
            <div class="rounded-[20px] bg-olive text-center px-6 pt-6 pb-5 shadow-[0_18px_40px_rgb(31_38_31/0.25)] border border-gold/30">
                <div class="text-[#d6cfc0] text-sm">{{ __('رقم انتظارك') }}، <span x-text="s.first_name"></span></div>
                <div class="ticket-code text-gold text-[72px] leading-none mt-2" x-text="s.ticket_no"></div>
                <div class="mt-4 border-t border-dashed border-white/25 pt-3 text-[#e2dccd] text-sm">
                    <span x-text="s.type_label"></span> <span class="text-gold mx-2">·</span> <span x-text="s.via"></span>
                </div>
            </div>

            <section class="mt-5 panel p-6 text-center" aria-live="polite">
                <template x-if="s.status === 'waiting'">
                    <div>
                        <p class="text-ink-faint" x-show="s.ahead > 0">{{ __('أمامك في الدور') }}</p>
                        <p class="font-num font-semibold text-[3rem] leading-none mt-1" x-show="s.ahead > 0" x-text="s.ahead"></p>
                        <p class="text-[1.4rem] font-bold" x-show="s.ahead === 0">{{ __('أنت التالي') }}</p>
                        <p class="mt-3 text-ink-soft">{{ __('الوقت التقريبي') }}: <b class="font-num" x-text="'~ ' + s.minutes"></b> {{ __('دقيقة') }}</p>
                        <p class="mt-4 text-sm text-ink-faint">{{ __('تتحدث هذه الصفحة تلقائيًا. سيصلك رقم المكتب على واتساب عند النداء.') }}</p>
                    </div>
                </template>
                <template x-if="s.status === 'called' || s.status === 'in_service'">
                    <div>
                        <p class="text-[1.5rem] font-bold">{{ __('حان دورك') }}</p>
                        <p class="mt-3 inline-flex items-center gap-3 rounded-[16px] bg-shade px-6 py-3">
                            <span>{{ __('تفضّل إلى') }}</span> <b class="text-[1.6rem]">{{ __('مكتب') }}</b> <b class="font-num text-[1.6rem] text-gold-dark" x-text="s.desk"></b>
                        </p>
                    </div>
                </template>
                <template x-if="s.status === 'completed'">
                    <div>
                        <p class="text-[1.3rem] font-bold">{{ __('تمت الخدمة') }}</p>
                        <p class="mt-2 text-ink-soft">{{ __('شكرًا لزيارتكم، سعدنا بخدمتكم.') }}</p>
                    </div>
                </template>
                <template x-if="s.status === 'no_show' || s.status === 'cancelled'">
                    <div>
                        <p class="text-[1.2rem] font-bold">{{ __('أُلغيت هذه التذكرة') }}</p>
                        <p class="mt-2 text-ink-soft">{{ __('يمكنك التسجيل من جديد من آيباد الاستقبال.') }}</p>
                    </div>
                </template>
                <template x-if="s.status === 'expired'">
                    <p class="text-ink-soft">{{ __('هذه التذكرة ليوم') }} <span x-text="s.date"></span>.</p>
                </template>
            </section>
        </div>
    </main>
</div>
@endsection
