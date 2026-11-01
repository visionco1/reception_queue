@extends('layouts.admin')
@section('title', __('الإعدادات والأجهزة'))

@section('admin')
<x-page-header :title="__('الإعدادات والأجهزة')" />

{{-- روابط الأجهزة --}}
<section class="panel p-6 mb-6">
    <h2 class="font-bold text-lg">{{ __('روابط الأجهزة') }}</h2>
    <p class="text-ink-soft text-sm mt-1 mb-5">{{ __('افتح كل رابط على جهازه مرة واحدة واحفظه. الروابط سرية: من يملك الرابط يستطيع فتح الشاشة، لذا غيّره إن تسرب.') }}</p>
    <div class="space-y-5">
        @foreach ([
            ['kiosk', __('آيباد الاستقبال'), route('kiosk.show', $s['kiosk_token'] ?? 'x'), __('على الآيباد: افتح الرابط في Safari ثم «مشاركة» ← «إضافة إلى الشاشة الرئيسية» ليعمل بملء الشاشة، وفعّل «الوصول الموجّه» لقفله على هذه الصفحة.')],
            ['display', __('شاشة العرض'), route('display.show', $s['display_token'] ?? 'x'), __('على التلفزيون: افتح الرابط في المتصفح بوضع ملء الشاشة، واضغط «تفعيل صوت النداء» مرة واحدة.')],
        ] as [$device, $label, $url, $hint])
            <div x-data="{ copied: false }">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                    <span class="font-semibold">{{ $label }}</span>
                    <form method="POST" action="{{ route('admin.settings.token', $device) }}" onsubmit="return confirm(@js(__('سيتوقف الرابط الحالي عن العمل فورًا. متابعة؟')))">
                        @csrf
                        <button class="text-sm text-danger hover:underline">{{ __('إنشاء رابط جديد') }}</button>
                    </form>
                </div>
                <div class="flex gap-2">
                    <input readonly value="{{ $url }}" class="field !bg-paper text-sm" dir="ltr" @focus="$el.select()" aria-label="{{ $label }}">
                    <button type="button" class="btn btn-quiet" @click="navigator.clipboard.writeText(@js($url)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? @js(__('نُسخ')) : @js(__('نسخ'))"></button>
                    <a href="{{ $url }}" target="_blank" class="btn btn-quiet">{{ __('فتح') }}</a>
                </div>
                <p class="field-hint">{{ $hint }}</p>
            </div>
        @endforeach
    </div>
</section>

<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf @method('PUT')

    <section class="panel p-6 grid md:grid-cols-2 gap-5">
        <h2 class="md:col-span-2 font-bold text-lg">{{ __('الهوية') }}</h2>
        <div>
            <label for="company_name" class="field-label">{{ __('اسم الشركة (يظهر في رسائل واتساب)') }}</label>
            <input id="company_name" name="company_name" value="{{ old('company_name', $s['company_name']) }}" class="field" required>
            @error('company_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="field-label">{{ __('الشعار') }}</span>
            <div class="olive rounded-[14px] px-5 py-3 inline-block"><img src="{{ asset('brand/logo-gold.svg') }}" alt="" class="h-10 w-auto"></div>
            <p class="field-hint">{{ __('الشعار الذهبي المعتمد في دليل الهوية، ولا يُغيّر من الإعدادات.') }}</p>
        </div>
        <div class="md:col-span-2">
            <label for="welcome_message" class="field-label">{{ __('جملة الترحيب على الآيباد') }}</label>
            <input id="welcome_message" name="welcome_message" value="{{ old('welcome_message', $s['welcome_message']) }}" class="field" maxlength="300">
        </div>
    </section>

    <section class="panel p-6 grid md:grid-cols-3 gap-5">
        <div class="md:col-span-3">
            <h2 class="font-bold text-lg">{{ __('ترقيم قائمة الانتظار') }}</h2>
            <p class="text-sm text-ink-soft mt-1">{{ __('رمز نوع العميل ثم رقم يومي يبدأ من 001 كل يوم. مثال بالإعداد الحالي:') }}
                <span class="ticket-code">{{ $s['ticket_prefix_buyer'] }}-{{ str_pad('14', $s['ticket_digits'], '0', STR_PAD_LEFT) }}</span>
                <span class="ticket-code ms-2">{{ $s['ticket_prefix_seller'] }}-{{ str_pad('6', $s['ticket_digits'], '0', STR_PAD_LEFT) }}</span>
            </p>
        </div>
        @foreach ([['ticket_prefix_buyer', __('رمز المشتري')], ['ticket_prefix_seller', __('رمز البائع')], ['ticket_prefix_other', __('رمز الأنواع الأخرى (عن بُعد)')]] as [$name, $label])
            <div>
                <label for="{{ $name }}" class="field-label">{{ $label }}</label>
                <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $s[$name]) }}" class="field ticket-code uppercase" maxlength="3" dir="ltr" required>
                @error($name)<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div>
            <label for="td" class="field-label">{{ __('عدد خانات الرقم') }}</label>
            <input id="td" type="number" min="2" max="5" name="ticket_digits" value="{{ old('ticket_digits', $s['ticket_digits']) }}" class="field" required>
        </div>
        <div>
            <label for="krs" class="field-label">{{ __('مدة عرض التذكرة على الآيباد (ثانية)') }}</label>
            <input id="krs" type="number" min="5" max="120" name="kiosk_reset_seconds" value="{{ old('kiosk_reset_seconds', $s['kiosk_reset_seconds']) }}" class="field" required>
        </div>
        <div>
            <label for="dsm" class="field-label">{{ __('متوسط الخدمة المبدئي (دقيقة)') }}</label>
            <input id="dsm" type="number" min="1" max="120" name="default_service_minutes" value="{{ old('default_service_minutes', $s['default_service_minutes']) }}" class="field" required>
            <p class="field-hint">{{ __('للوقت التقريبي حتى تتوفر بيانات اليوم.') }}</p>
        </div>
        <div>
            <label for="cc" class="field-label">{{ __('رمز الدولة لأرقام الجوال') }}</label>
            <input id="cc" name="country_code" value="{{ old('country_code', $s['country_code']) }}" class="field ltr-nums" dir="ltr" required>
            <p class="field-hint">{{ __('966 للسعودية. لا تغيّره بعد بدء التشغيل.') }}</p>
        </div>
        <div>
            <label for="ess" class="field-label">{{ __('نطاق بحث الموظفين في العملاء') }}</label>
            <select id="ess" name="employee_search_scope" class="field">
                <option value="all" @selected($s['employee_search_scope'] === 'all')>{{ __('جميع العملاء') }}</option>
                <option value="own" @selected($s['employee_search_scope'] === 'own')>{{ __('العملاء الذين خدمهم فقط') }}</option>
            </select>
        </div>
    </section>

    <section class="panel p-6 grid md:grid-cols-3 gap-5">
        <div class="md:col-span-3">
            <h2 class="font-bold text-lg">{{ __('ساعات العمل') }}</h2>
            <p class="text-sm text-ink-soft mt-1">{{ __('خارج ساعات العمل يعرض الآيباد رسالة ولا يسجل أرقامًا جديدة. اتركهما فارغين لإيقاف هذا القيد.') }}</p>
        </div>
        <div>
            <label for="ws" class="field-label">{{ __('من') }}</label>
            <input id="ws" type="time" name="work_start" value="{{ old('work_start', $s['work_start']) }}" class="field ltr-nums">
            @error('work_start')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="we" class="field-label">{{ __('إلى') }}</label>
            <input id="we" type="time" name="work_end" value="{{ old('work_end', $s['work_end']) }}" class="field ltr-nums">
            @error('work_end')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="panel p-6">
        <h2 class="font-bold text-lg mb-5">{{ __('شاشة العرض') }}</h2>
        <div class="grid lg:grid-cols-2 gap-8">
            <div class="space-y-5">
                <div>
                    <span class="field-label">{{ __('وضع الشاشة') }}</span>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['current' => [__('الرقم الحالي'), __('الرقم بخط كبير مع النداءات السابقة')], 'offices' => [__('المكاتب'), __('لوحة بكل المكاتب ورقم كل مكتب')]] as $value => [$label, $hint])
                            <label class="cursor-pointer">
                                <input type="radio" name="display_mode" value="{{ $value }}" class="peer sr-only" @checked($s['display_mode'] === $value)>
                                <span class="block rounded-[14px] border-[1.5px] border-line p-3 peer-checked:border-gold peer-checked:bg-gold-soft">
                                    <b class="block">{{ $label }}</b><span class="text-xs text-ink-faint">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="dis" class="field-label">{{ __('العودة للشعار بعد (ثانية)') }}</label>
                        <input id="dis" type="number" min="10" max="3600" name="display_idle_seconds" value="{{ old('display_idle_seconds', $s['display_idle_seconds']) }}" class="field">
                        <p class="field-hint">{{ __('بعد آخر نداء بدون نداء جديد تعرض الشاشة الشعار (وقت الهدوء).') }}</p>
                    </div>
                    <div>
                        <label for="drc" class="field-label">{{ __('عدد النداءات السابقة') }}</label>
                        <input id="drc" type="number" min="1" max="8" name="display_recent_count" value="{{ old('display_recent_count', $s['display_recent_count']) }}" class="field">
                    </div>
                </div>
                @foreach ([
                    ['display_sound', __('صوت تنبيه عند كل نداء')],
                    ['display_voice', __('نطق آلي بالعربي: «الرقم M-014، مكتب 2» (حسب دعم المتصفح)')],
                ] as [$name, $label])
                    <label class="flex items-start gap-3">
                        <span class="switch mt-0.5"><input type="checkbox" name="{{ $name }}" value="1" @checked($s[$name])><span></span></span>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <div>
                <label for="ticker" class="field-label">{{ __('رسائل الشريط المتحرك') }}</label>
                <textarea id="ticker" name="ticker_messages" rows="6" class="field" maxlength="2000">{{ old('ticker_messages', $s['ticker_messages']) }}</textarea>
                <p class="field-hint">{{ __('كل سطر رسالة. تظهر أسفل شاشة العرض بالتتابع.') }}</p>
            </div>
        </div>
    </section>

    <div class="flex justify-end">
        <button class="btn btn-primary !px-8 !py-2.5">{{ __('حفظ الإعدادات') }}</button>
    </div>
</form>
@endsection
