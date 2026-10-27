@extends('layouts.admin')
@section('title', __('رسائل واتساب'))

@section('admin')
<x-page-header :title="__('رسائل واتساب')" :subtitle="__('تُرسل تلقائيًا للعميل الحضوري فقط. العميل عن بعد لا تصله رسائل الطابور.')" />

<div @class(['flash mb-6', 'flash-ok' => $configured && $driver === 'meta', 'flash-error' => ! $configured || $driver === 'log'])>
    @if ($driver === 'log')
        {{ __('وضع التجربة: الرسائل تُسجَّل في النظام ولا تُرسل فعليًا. لتفعيل الإرسال اضبط WHATSAPP_DRIVER=meta وبيانات الربط في ملف .env على الخادم.') }}
    @elseif (! $configured)
        {{ __('مزود واتساب مضبوط على Meta لكن بيانات الربط ناقصة في ملف .env (رقم الهاتف التعريفي ورمز الوصول).') }}
    @else
        {{ __('متصل بـ WhatsApp Business Platform (Meta Cloud API).') }}
    @endif
</div>

<form method="POST" action="{{ route('admin.messages.update') }}" class="space-y-6">
    @csrf @method('PUT')

    <section class="panel p-6 flex flex-wrap items-center gap-x-10 gap-y-4">
        <label class="flex items-center gap-3 font-semibold">
            <span class="switch"><input type="checkbox" name="whatsapp_enabled" value="1" @checked($s['whatsapp_enabled'])><span></span></span>
            {{ __('إرسال رسائل واتساب') }}
        </label>
        <div class="flex items-center gap-2">
            <label for="tl" class="text-sm text-ink-soft">{{ __('لغة قوالب Meta') }}</label>
            <input id="tl" name="whatsapp_template_lang" value="{{ $s['whatsapp_template_lang'] }}" class="field !w-24 !py-1.5" dir="ltr">
        </div>
        <p class="text-sm text-ink-faint basis-full">
            {{ __('المتغيرات المتاحة في النص:') }}
            @foreach ($placeholders as $p)<code class="ltr-nums bg-paper border border-line rounded px-1.5 py-0.5 mx-0.5 text-ink">{{ '{'.$p.'}' }}</code>@endforeach
        </p>
    </section>

    @foreach ($keys as $i => $key)
        @php($k = $key->value)
        <section class="panel p-6 grid lg:grid-cols-[1fr_18rem] gap-6">
            <div>
                <div class="flex items-center justify-between gap-4 mb-3">
                    <h2 class="font-display font-bold text-lg">{{ $i + 1 }}. {{ $key->label() }}</h2>
                    <label class="flex items-center gap-2 text-sm">
                        <span class="switch"><input type="checkbox" name="wa_{{ $k }}_enabled" value="1" @checked($s["wa_{$k}_enabled"])><span></span></span>
                        {{ __('مفعّلة') }}
                    </label>
                </div>
                <label for="t-{{ $k }}" class="sr-only">{{ __('نص الرسالة') }}</label>
                <textarea id="t-{{ $k }}" name="wa_{{ $k }}_text" rows="3" class="field leading-relaxed" maxlength="1000" required>{{ old("wa_{$k}_text", $s["wa_{$k}_text"]) }}</textarea>
                @error("wa_{$k}_text")<p class="field-error">{{ $message }}</p>@enderror

                @if ($k === 'near_turn')
                    <div class="flex items-center gap-2 mt-3 text-sm">
                        <label for="nt">{{ __('تُرسل عندما يكون أمام العميل') }}</label>
                        <input id="nt" type="number" min="1" max="10" name="wa_near_turn_threshold" value="{{ $s['wa_near_turn_threshold'] }}" class="field !w-20 !py-1">
                        <span>{{ __('عميل/عملاء') }}</span>
                    </div>
                @endif
                @if ($k === 'thanks')
                    <div class="mt-4 rounded-lg border border-line p-4 space-y-2">
                        <label class="flex items-center gap-2 text-sm font-semibold">
                            <span class="switch"><input type="checkbox" name="wa_services_line_enabled" value="1" @checked($s['wa_services_line_enabled'])><span></span></span>
                            {{ __('إضافة سطر تعريف بخدمات الشركة') }}
                        </label>
                        <label for="svc" class="sr-only">{{ __('سطر التعريف بالخدمات') }}</label>
                        <input id="svc" name="wa_services_line_text" value="{{ $s['wa_services_line_text'] }}" class="field" maxlength="500">
                    </div>
                @endif
            </div>
            <div class="space-y-3 text-sm lg:border-s lg:border-line lg:ps-6">
                <p class="text-ink-faint">{{ __('للإرسال عبر Meta خارج نافذة 24 ساعة يلزم قالب معتمد. اتركه فارغًا لإرسال النص أعلاه كما هو.') }}</p>
                <div>
                    <label for="tpl-{{ $k }}" class="field-label">{{ __('اسم القالب المعتمد') }}</label>
                    <input id="tpl-{{ $k }}" name="wa_{{ $k }}_template" value="{{ $s["wa_{$k}_template"] }}" class="field !py-1.5" dir="ltr" placeholder="queue_{{ $k }}">
                    @error("wa_{$k}_template")<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="prm-{{ $k }}" class="field-label">{{ __('ترتيب متغيرات القالب') }}</label>
                    <input id="prm-{{ $k }}" name="wa_{{ $k }}_params" value="{{ $s["wa_{$k}_params"] }}" class="field !py-1.5" dir="ltr" placeholder="name,ticket">
                    <p class="field-hint">{{ __('بنفس ترتيب المتغيرات في القالب') }} <span dir="ltr">@{{1}}, @{{2}}</span></p>
                </div>
            </div>
        </section>
    @endforeach

    <div class="flex justify-end">
        <button class="btn btn-primary !px-8 !py-2.5">{{ __('حفظ الرسائل') }}</button>
    </div>
</form>

<section class="panel p-6 mt-8 max-w-2xl">
    <h2 class="font-display font-bold text-lg">{{ __('إرسال رسالة تجريبية') }}</h2>
    <p class="text-sm text-ink-soft mt-1 mb-4">{{ __('يُرسل النص المحفوظ ببيانات تجريبية (الرقم M001، مكتب 1).') }}</p>
    <form method="POST" action="{{ route('admin.messages.test') }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label for="tp" class="field-label">{{ __('رقم الجوال') }}</label>
            <input id="tp" name="phone" value="{{ old('phone') }}" class="field ltr-nums" dir="ltr" placeholder="05XXXXXXXX" required>
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="tk" class="field-label">{{ __('الرسالة') }}</label>
            <select id="tk" name="key" class="field">
                @foreach ($keys as $key)
                    <option value="{{ $key->value }}">{{ $key->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-quiet">{{ __('إرسال') }}</button>
    </form>
</section>
@endsection
