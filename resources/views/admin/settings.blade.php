@extends('layouts.admin')
@section('title', __('الإعدادات والأجهزة'))

@section('admin')
<x-page-header :title="__('الإعدادات والأجهزة')" />

{{-- روابط الأجهزة --}}
<section class="panel p-6 mb-6">
    <h2 class="font-display font-bold text-lg">{{ __('روابط الأجهزة') }}</h2>
    <p class="text-ink-soft text-sm mt-1 mb-5">{{ __('افتح كل رابط على جهازه مرة واحدة واحفظه. الروابط سرية: من يملك الرابط يستطيع فتح الشاشة، لذا غيّره إن تسرب.') }}</p>
    <div class="space-y-5">
        @foreach ([
            ['kiosk', __('آيباد الاستقبال'), route('kiosk.show', $s['kiosk_token'] ?? 'x'), __('على الآيباد: افتح الرابط في Safari ثم «مشاركة» ← «إضافة إلى الشاشة الرئيسية» ليعمل بملء الشاشة، وفعّل «الوصول الموجّه» لقفله على هذه الصفحة.')],
            ['display', __('شاشة الانتظار'), route('display.show', $s['display_token'] ?? 'x'), __('على التلفزيون: افتح الرابط في المتصفح بوضع ملء الشاشة، والمس الشاشة مرة لتفعيل صوت التنبيه.')],
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

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf @method('PUT')

    <section class="panel p-6 grid md:grid-cols-2 gap-5">
        <h2 class="md:col-span-2 font-display font-bold text-lg">{{ __('الهوية') }}</h2>
        <div>
            <label for="company_name" class="field-label">{{ __('اسم الشركة') }}</label>
            <input id="company_name" name="company_name" value="{{ old('company_name', $s['company_name']) }}" class="field" required>
            @error('company_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="logo" class="field-label">{{ __('الشعار') }}</label>
            <div class="flex items-center gap-3">
                @if ($s['logo_path'])
                    <img src="{{ asset($s['logo_path']) }}" alt="" class="h-11 w-auto rounded border border-line p-1 bg-white">
                @endif
                <label class="btn btn-quiet cursor-pointer" x-data="{ name: '' }">
                    <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="sr-only" @change="name = $event.target.files[0]?.name || ''">
                    <span x-text="name || @js(__('اختيار صورة'))"></span>
                </label>
                <span class="text-xs text-ink-faint">{{ __('PNG أو SVG بخلفية شفافة، حتى 2 ميجابايت') }}</span>
            </div>
            @if ($s['logo_path'])
                <label class="flex items-center gap-2 text-sm mt-2"><input type="checkbox" name="remove_logo" value="1" class="accent-danger"> {{ __('إزالة الشعار') }}</label>
            @endif
            @error('logo')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="welcome_message" class="field-label">{{ __('رسالة تعريفية قصيرة (الآيباد وشاشة العرض)') }}</label>
            <input id="welcome_message" name="welcome_message" value="{{ old('welcome_message', $s['welcome_message']) }}" class="field" maxlength="300">
        </div>
    </section>

    <section class="panel p-6 grid md:grid-cols-3 gap-5">
        <div class="md:col-span-3">
            <h2 class="font-display font-bold text-lg">{{ __('ترقيم الطابور') }}</h2>
            <p class="text-sm text-ink-soft mt-1">{{ __('رمز يميز نوع العميل ثم رقم تسلسلي يبدأ من جديد كل يوم. مثال بالإعداد الحالي:') }}
                <span class="ticket-code text-buyer">{{ $s['ticket_prefix_buyer'] }}{{ str_pad('23', $s['ticket_digits'], '0', STR_PAD_LEFT) }}</span>
                <span class="ticket-code text-seller ms-2">{{ $s['ticket_prefix_seller'] }}{{ str_pad('14', $s['ticket_digits'], '0', STR_PAD_LEFT) }}</span>
            </p>
        </div>
        <div>
            <label for="pb" class="field-label">{{ __('رمز المشتري') }}</label>
            <input id="pb" name="ticket_prefix_buyer" value="{{ old('ticket_prefix_buyer', $s['ticket_prefix_buyer']) }}" class="field ticket-code uppercase" maxlength="3" dir="ltr" required>
            @error('ticket_prefix_buyer')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="ps" class="field-label">{{ __('رمز البائع') }}</label>
            <input id="ps" name="ticket_prefix_seller" value="{{ old('ticket_prefix_seller', $s['ticket_prefix_seller']) }}" class="field ticket-code uppercase" maxlength="3" dir="ltr" required>
            @error('ticket_prefix_seller')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="td" class="field-label">{{ __('عدد خانات الرقم') }}</label>
            <input id="td" type="number" min="2" max="5" name="ticket_digits" value="{{ old('ticket_digits', $s['ticket_digits']) }}" class="field" required>
        </div>
        <div>
            <label for="krs" class="field-label">{{ __('مدة عرض الرقم على الآيباد (ثانية)') }}</label>
            <input id="krs" type="number" min="5" max="120" name="kiosk_reset_seconds" value="{{ old('kiosk_reset_seconds', $s['kiosk_reset_seconds']) }}" class="field" required>
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

    <section class="panel p-6">
        <h2 class="font-display font-bold text-lg mb-5">{{ __('شاشة الانتظار') }}</h2>
        <div class="grid lg:grid-cols-[1fr_22rem] gap-8">
            <div class="grid sm:grid-cols-3 gap-5 content-start" x-data="{ bg: @js($s['display_bg']), fg: @js($s['display_primary']), accent: @js($s['display_accent']) }">
                @foreach ([['display_bg', __('الخلفية'), 'bg'], ['display_primary', __('النص والأرقام'), 'fg'], ['display_accent', __('ختم رقم المكتب'), 'accent']] as [$name, $label, $model])
                    <div>
                        <label for="{{ $name }}" class="field-label">{{ $label }}</label>
                        <div class="flex items-center gap-2">
                            <input id="{{ $name }}" type="color" name="{{ $name }}" x-model="{{ $model }}" class="h-10 w-14 rounded border border-line bg-white p-1">
                            <span class="ltr-nums text-sm text-ink-soft" x-text="{{ $model }}"></span>
                        </div>
                    </div>
                @endforeach
                <div class="sm:col-span-3 rounded-lg p-5 flex items-center justify-between" :style="`background:${bg};color:${fg}`" aria-hidden="true">
                    <span class="ticket-code text-4xl">M023</span>
                    <span class="font-display font-bold text-lg rounded px-3 py-1 -rotate-2" :style="`background:${accent};color:${bg}`">{{ __('مكتب') }} 3</span>
                </div>
                <div>
                    <label for="drc" class="field-label">{{ __('عدد الأرقام السابقة') }}</label>
                    <input id="drc" type="number" min="1" max="10" name="display_recent_count" value="{{ $s['display_recent_count'] }}" class="field">
                </div>
            </div>
            <div class="space-y-4">
                @foreach ([
                    ['display_sound', __('صوت تنبيه عند نداء رقم جديد')],
                    ['display_voice', __('نطق الرقم ورقم المكتب صوتيًا (حسب دعم المتصفح للعربية)')],
                    ['display_show_employee', __('إظهار اسم الموظف تحت رقم المكتب')],
                ] as [$name, $label])
                    <label class="flex items-start gap-3">
                        <span class="switch mt-0.5"><input type="checkbox" name="{{ $name }}" value="1" @checked($s[$name])><span></span></span>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </section>

    <div class="flex justify-end">
        <button class="btn btn-primary !px-8 !py-2.5">{{ __('حفظ الإعدادات') }}</button>
    </div>
</form>
@endsection
