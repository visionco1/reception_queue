{{-- إضافة عميل عن بعد: يبدأ برقم الجوال لمنع تكرار السجل --}}
<div x-data="remoteForm({ lookupUrl: @js(route('staff.lookup')), storeUrl: @js(route('staff.remote.store')), agents: @js($remoteAgents) })"
     @open-remote.window="show($event.detail || {})"
     @keydown.escape.window="open && close()">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-ink/40 p-4 overflow-y-auto" @click.self="close()">
        <div class="panel w-full max-w-lg shadow-xl" role="dialog" aria-modal="true" aria-labelledby="remote-title" x-trap.noscroll="open">
            <div class="px-6 py-4 border-b border-line flex items-center justify-between">
                <h2 id="remote-title" class="font-display font-bold text-lg">{{ __('إضافة عميل عن بُعد') }}</h2>
                <button type="button" class="text-ink-faint hover:text-ink text-xl leading-none" @click="close()" aria-label="{{ __('إغلاق') }}">✕</button>
            </div>

            <template x-if="saved">
                <div class="p-6 text-center space-y-4">
                    <p class="font-semibold text-lg" x-text="saved.message"></p>
                    <p class="text-ink-faint text-sm">{{ __('سُجّل كتفاعل عن بعد بدون رقم انتظار، ونُسب إليك.') }}</p>
                    <div class="flex justify-center gap-2">
                        <a :href="saved.customer_url" class="btn btn-quiet">{{ __('فتح سجل العميل') }}</a>
                        <button type="button" class="btn btn-primary" @click="reset(); $nextTick(() => $refs.mobile.focus())">{{ __('إضافة عميل آخر') }}</button>
                    </div>
                </div>
            </template>

            <form x-show="!saved" @submit.prevent="save" class="p-6 space-y-4" novalidate>
                <div>
                    <label for="r-mobile" class="field-label">{{ __('رقم الجوال') }}</label>
                    <div class="flex gap-2">
                        <input id="r-mobile" x-ref="mobile" :value="form.mobile" @input="onMobileInput" @blur="lookup" @keydown.enter.prevent="lookup"
                               class="field ltr-nums" dir="ltr" inputmode="tel" placeholder="05XXXXXXXX" maxlength="16">
                        <button type="button" class="btn btn-quiet" @click="lookup" :disabled="looking">{{ __('تحقق') }}</button>
                    </div>
                    <p class="field-error" x-show="errors.mobile" x-text="errors.mobile"></p>
                </div>

                <div x-show="found" class="rounded-lg border border-seal/70 bg-[#fffaf0] px-4 py-3 text-sm">
                    <p class="font-semibold">{{ __('هذا الرقم مسجل مسبقًا') }}: <span x-text="found?.name"></span></p>
                    <p class="text-ink-soft mt-0.5">
                        <span x-text="found ? visitsLabel(found.visits_count) : ''"></span><span x-show="found?.last_visit">، {{ __('آخرها') }} <span x-text="found?.last_visit"></span></span>.
                        {{ __('سيُضاف تفاعل جديد لنفس السجل بدل إنشاء عميل مكرر.') }}
                    </p>
                    <a x-show="found?.url" :href="found?.url" class="inline-block mt-1.5 text-deed font-semibold hover:underline">{{ __('عرض سجل العميل') }}</a>
                </div>
                <p x-show="looked && !found" class="text-sm text-ok">{{ __('رقم جديد — سيُنشأ سجل عميل جديد.') }}</p>

                <div>
                    <label for="r-name" class="field-label">{{ __('الاسم') }}</label>
                    <input id="r-name" x-model="form.name" class="field" maxlength="100">
                    <p class="field-error" x-show="errors.name" x-text="errors.name"></p>
                </div>

                <div>
                    <span class="field-label">{{ __('نوع العميل') }}</span>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($remoteTypes as $value => $label)
                            <label>
                                <input type="radio" x-model="form.customer_type" value="{{ $value }}" class="peer sr-only">
                                <span @class([
                                    'btn btn-quiet w-full peer-focus-visible:outline-2',
                                    'peer-checked:border-buyer peer-checked:bg-buyer-soft peer-checked:text-buyer' => $value === 'buyer',
                                    'peer-checked:border-seller peer-checked:bg-seller-soft peer-checked:text-seller' => $value === 'seller',
                                    'peer-checked:border-[#5a4fa3] peer-checked:bg-[#efedfb] peer-checked:text-[#5a4fa3]' => $value === 'other',
                                ])>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="field-error" x-show="errors.customer_type" x-text="errors.customer_type"></p>
                </div>

                <div x-show="form.customer_type === 'other'" x-cloak>
                    <label for="r-ctype" class="field-label">{{ __('ما نوع العميل؟') }}</label>
                    <select id="r-ctype" x-model="form.client_type_id" class="field">
                        <option value="">{{ __('اختر') }}</option>
                        @foreach ($clientTypes as $ct)
                            <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                        @endforeach
                    </select>
                    @if ($clientTypes->isEmpty())
                        <p class="field-hint">{{ __('لا توجد أنواع مسجلة. يضيفها مدير النظام من الإدارة ← أنواع العملاء.') }}</p>
                    @endif
                    <p class="field-error" x-show="errors.client_type_id" x-text="errors.client_type_id"></p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="r-source" class="field-label">{{ __('مصدر العميل') }}</label>
                        <select id="r-source" x-model="form.source" class="field">
                            <option value="">{{ __('اختر') }}</option>
                            @foreach ($remoteSources as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="field-error" x-show="errors.source" x-text="errors.source"></p>
                    </div>
                    <div>
                        <label for="r-bank" class="field-label">{{ __('البنك') }}</label>
                        <select id="r-bank" x-model="form.bank_id" @change="form.agent_id = ''" class="field">
                            <option value="">{{ __('بدون بنك') }}</option>
                            @foreach ($remoteBanks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div x-show="bankAgents.length" x-cloak>
                    <label for="r-agent" class="field-label">{{ __('وكيل البنك') }}</label>
                    <select id="r-agent" x-model="form.agent_id" class="field">
                        <option value="">{{ __('بدون وكيل') }}</option>
                        <template x-for="a in bankAgents" :key="a.id">
                            <option :value="a.id" x-text="a.name"></option>
                        </template>
                    </select>
                    <p class="field-error" x-show="errors.agent_id" x-text="errors.agent_id"></p>
                </div>

                <div>
                    <label for="r-notes" class="field-label">{{ __('ملاحظات') }}</label>
                    <textarea id="r-notes" x-model="form.notes" rows="2" class="field" maxlength="2000"></textarea>
                </div>

                <div class="flash flash-error" x-show="error" x-text="error"></div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-quiet" @click="close()">{{ __('إلغاء') }}</button>
                    <button class="btn btn-primary" :disabled="saving">{{ __('حفظ التفاعل') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
