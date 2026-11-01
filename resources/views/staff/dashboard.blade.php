@extends('layouts.panel')
@section('title', __('قائمة الانتظار'))

@section('content')
<div x-data="staffDashboard({
        initial: @js($initial),
        stateUrl: @js(route('staff.state')),
        pollMs: {{ (int) config('qms.queue.poll_ms') }},
        urls: {
            take: @js(route('staff.visits.take', '__ID__')),
            recall: @js(route('staff.visits.recall', '__ID__')),
            complete: @js(route('staff.visits.complete', '__ID__')),
            noShow: @js(route('staff.visits.no-show', '__ID__')),
            release: @js(route('staff.visits.release', '__ID__')),
            transfer: @js(route('staff.visits.transfer', '__ID__')),
            destroy: @js(route('staff.visits.destroy', '__ID__')),
            candidates: @js(route('staff.visits.candidates', '__ID__')),
            link: @js(route('staff.visits.link', '__ID__')),
            unlink: @js(route('staff.visits.unlink', '__ID__')),
            agent: @js(route('staff.visits.agent', '__ID__')),
        },
        agentsByBank: @js($agentsByBank),
     })">

    {{-- اختيار المكتب إن لم يُختر عند الدخول --}}
    <template x-if="!state.desk">
        <form method="POST" action="{{ route('staff.desk') }}" class="panel !border-gold bg-gold-soft p-4 mb-5 flex flex-wrap items-center gap-3">
            @csrf
            <p class="font-bold me-auto">{{ __('اختر رقم مكتبك قبل نداء العملاء، ليظهر للعميل على شاشة العرض.') }}</p>
            <select name="desk_id" class="field !w-auto" required>
                @foreach ($desks as $d)
                    <option value="{{ $d->id }}">{{ __('مكتب :n', ['n' => $d->number]) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary">{{ __('بدء العمل على هذا المكتب') }}</button>
        </form>
    </template>

    {{-- أرقام اليوم --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
        <div class="stat-card is-dark"><span class="label">{{ __('في الانتظار') }}</span><span class="value" x-text="state.stats.waiting"></span></div>
        <div class="stat-card"><span class="label">{{ __('خدمتهم اليوم') }}</span><span class="value" x-text="state.stats.served_today"></span></div>
        <div class="stat-card"><span class="label">{{ __('متوسط الانتظار') }}</span><span><span class="value" x-text="minutes(state.stats.avg_wait)"></span><span class="unit">{{ __('دقيقة') }}</span></span></div>
        <div class="stat-card"><span class="label">{{ __('متوسط مدة الخدمة') }}</span><span><span class="value" x-text="minutes(state.stats.avg_service)"></span><span class="unit">{{ __('دقيقة') }}</span></span></div>
    </div>

    <div class="grid xl:grid-cols-[390px_minmax(0,1fr)] gap-5 items-start">

        {{-- العمود الجانبي --}}
        <div class="space-y-5 xl:sticky xl:top-5">
            {{-- عميلي الحالي --}}
            <section class="panel" aria-labelledby="current-title">
                <div class="px-5 py-4 border-b-[1.5px] border-line flex items-center justify-between">
                    <h2 id="current-title" class="text-[1.15rem] font-bold">{{ __('عميلي الحالي') }}</h2>
                    <span class="text-sm text-ink-faint" x-show="state.desk">{{ __('مكتب') }} <b class="text-ink font-num" x-text="state.desk?.number"></b></span>
                </div>

                <template x-if="!state.current">
                    <div class="px-5 py-10 text-center">
                        <div class="mx-auto grid place-items-center size-16 rounded-full bg-shade text-gold-dark">
                            <svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                        </div>
                        <p class="mt-4 font-bold">{{ __('لا يوجد عميل معك الآن') }}</p>
                        <p class="text-ink-faint text-sm mt-1">{{ __('نادِ التالي، أو اختر عميلًا معيّنًا من القائمة بزر «نداء».') }}</p>
                        <button class="mt-6 w-full h-[54px] rounded-[16px] bg-olive text-ivory font-bold flex items-center justify-center gap-3 disabled:opacity-40"
                                :disabled="!nextUp || !state.desk || busy" @click="call(nextUp)">
                            <span>{{ __('نداء التالي') }}</span>
                            <span class="ticket-code text-gold" x-show="nextUp" x-text="nextUp?.ticket_no"></span>
                        </button>
                    </div>
                </template>

                <template x-if="state.current">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="ticket-code text-[58px] leading-none" x-text="state.current.ticket_no"></div>
                            <span class="status-pill mt-2" :class="'status-' + state.current.status" x-text="state.current.status_label"></span>
                        </div>
                        <div class="mt-3">
                            <div class="text-[1.2rem] font-bold" x-text="state.current.name"></div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="type-tag" :class="'type-' + state.current.type" x-text="state.current.type_label"></span>
                                <span class="soft-tag" x-text="state.current.via"></span>
                                <span class="soft-tag" x-show="state.current.remote">{{ __('عن بُعد') }}</span>
                            </div>
                        </div>

                        <div class="mt-4 rounded-[14px] border-[1.5px] border-line bg-[#fcfaf6] p-3.5 grid grid-cols-3 gap-2 text-center">
                            <div>
                                <div class="text-xs text-ink-faint">{{ __('الجوال') }}</div>
                                <div class="mt-1 font-num font-semibold text-[0.95rem] leading-snug" dir="ltr" x-text="state.current.mobile"></div>
                            </div>
                            <div>
                                <div class="text-xs text-ink-faint">{{ __('انتظر') }}</div>
                                <div class="mt-1 font-num font-semibold"><span x-text="minutes(state.current.wait)"></span> <span class="text-xs text-ink-faint font-sans">{{ __('د') }}</span></div>
                            </div>
                            <div>
                                <div class="text-xs text-ink-faint">{{ __('مدة الخدمة') }}</div>
                                <div class="mt-1 font-num font-semibold text-gold-dark text-[1.05rem]" x-text="serviceClock()"></div>
                            </div>
                        </div>

                        {{-- البنك ووكيل البنك: يحددهما الموظف --}}
                        <div class="mt-3 rounded-[14px] border-[1.5px] border-dashed border-line px-3.5 py-2.5 text-sm" x-show="state.current.linkable">
                            <template x-if="!editingAgent">
                                <div class="flex items-center justify-between gap-2">
                                    <span><span class="text-ink-faint">{{ __('وكيل البنك') }}:</span> <b x-text="state.current.agent || @js(__('لم يُحدد'))"></b></span>
                                    <button type="button" class="text-gold-dark font-bold hover:underline" @click="startAgentEdit()">{{ __('تحديد') }}</button>
                                </div>
                            </template>
                            <template x-if="editingAgent">
                                <div class="space-y-2">
                                    <select x-model="bankChoice" @change="agentChoice = ''" class="field !py-1.5 !text-sm" aria-label="{{ __('البنك') }}">
                                        <option value="">{{ __('أخرى (ليس عن طريق بنك)') }}</option>
                                        @foreach ($banks as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                    <select x-model="agentChoice" class="field !py-1.5 !text-sm" x-show="bankChoice" aria-label="{{ __('وكيل البنك') }}">
                                        <option value="">{{ __('بدون وكيل') }}</option>
                                        <template x-for="a in currentAgents" :key="a.id"><option :value="a.id" x-text="a.name" :selected="a.id === agentChoice"></option></template>
                                    </select>
                                    <div class="flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm flex-1" :disabled="busy" @click="saveAgent()">{{ __('حفظ') }}</button>
                                        <button type="button" class="btn btn-quiet btn-sm" @click="editingAgent = false">{{ __('إلغاء') }}</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- الطرف الآخر في الصفقة (البائع ↔ المشتري) --}}
                        <div class="mt-3 rounded-[14px] border-[1.5px] border-dashed border-gold/60 bg-gold-soft px-3.5 py-2.5" x-show="state.current.linkable">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="font-bold">{{ __('الطرف الآخر') }}</span>
                                <button type="button" class="text-gold-dark font-bold hover:underline" @click="openLink()"
                                        x-text="state.current.linked.length ? @js(__('ربط طرف إضافي')) : @js(__('ربط بالطرف الآخر'))"></button>
                            </div>
                            <ul class="divide-y divide-line-soft" x-show="state.current.linked.length">
                                <template x-for="p in state.current.linked" :key="p.id">
                                    <li class="py-2">
                                        <div class="flex items-center gap-2">
                                            <span class="ticket-code text-lg" x-text="p.ticket_no || '—'"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="font-bold block truncate text-sm" x-text="p.name"></span>
                                                <span class="text-xs text-ink-faint" x-text="p.type_label + ' — ' + (p.mine ? @js(__('معك الآن')) : (p.where || p.status_label))"></span>
                                            </span>
                                            <button type="button" class="text-xs text-ink-faint hover:text-danger" :disabled="busy"
                                                    @click="confirming === 'pul-' + p.id ? partnerAction('unlink', p) : confirming = 'pul-' + p.id"
                                                    x-text="confirming === 'pul-' + p.id ? @js(__('تأكيد فك الربط')) : @js(__('فك الربط'))"></button>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- إنهاء الخدمة مع النتيجة --}}
                        <div class="mt-4" x-show="!finishing">
                            <button class="w-full h-[56px] rounded-[16px] bg-olive text-ivory text-[1.05rem] font-bold flex items-center justify-center gap-4" :disabled="busy" @click="finishing = true">
                                <span class="gold-rule !w-8"></span>
                                <span x-text="withMe ? @js(__('إنهاء الخدمة للطرفين')) : @js(__('إنهاء الخدمة'))"></span>
                            </button>
                        </div>
                        <div class="mt-4 rounded-[16px] border-[1.5px] border-olive p-3.5 space-y-3" x-show="finishing" x-cloak>
                            <span class="field-label !mb-1">{{ __('نتيجة الصفقة') }}</span>
                            <div class="grid grid-cols-3 gap-1.5" role="radiogroup">
                                @foreach ($outcomes as $value => $label)
                                    <button type="button" role="radio" class="rounded-[10px] border-[1.5px] py-2 text-sm font-bold"
                                            :class="outcome === '{{ $value }}' ? 'outcome-{{ $value }} border-current' : 'border-line text-ink-soft'"
                                            :aria-checked="outcome === '{{ $value }}'" @click="outcome = '{{ $value }}'">{{ $label }}</button>
                                @endforeach
                            </div>
                            <label for="svc-notes" class="sr-only">{{ __('ملاحظة') }}</label>
                            <textarea id="svc-notes" x-model="notes" rows="2" class="field !text-sm" maxlength="2000" placeholder="{{ __('ملاحظة على الخدمة (اختياري)') }}"></textarea>
                            <div class="flex gap-2">
                                <button class="btn btn-primary flex-1" :disabled="busy" @click="finish()">{{ __('تأكيد الإنهاء') }}</button>
                                <button class="btn btn-quiet" @click="finishing = false">{{ __('رجوع') }}</button>
                            </div>
                        </div>

                        <div class="mt-2.5 grid grid-cols-2 gap-2.5" x-show="!finishing">
                            <button class="btn btn-quiet h-[46px]" :disabled="busy" @click="visitAction('recall')">
                                {{ __('إعادة النداء') }}<span class="text-ink-faint font-num text-xs" x-show="state.current.recalls" x-text="'(' + state.current.recalls + ')'"></span>
                            </button>
                            <button class="btn btn-quiet h-[46px]" :disabled="busy"
                                    @click="confirming === 'noshow' ? visitAction('noShow') : confirming = 'noshow'"
                                    x-text="confirming === 'noshow' ? (state.current.no_shows ? @js(__('تأكيد: إلغاء التذكرة')) : @js(__('تأكيد: لآخر الدور'))) : @js(__('لم يحضر'))"></button>
                        </div>
                        <div class="relative mt-2.5" x-show="!finishing" @click.outside="transferOpen = false">
                            <button class="btn btn-quiet w-full h-[46px]" :disabled="busy || !otherDesks.length" @click="transferOpen = !transferOpen" :aria-expanded="transferOpen">
                                {{ __('تحويل لمكتب آخر') }} <span class="text-gold-dark" aria-hidden="true">▾</span>
                            </button>
                            <ul x-show="transferOpen" x-cloak class="absolute z-20 inset-x-0 mt-1.5 panel shadow-xl py-1.5 max-h-64 overflow-y-auto">
                                <template x-for="d in otherDesks" :key="d.id">
                                    <li><button type="button" class="w-full px-4 py-2.5 flex items-center justify-between text-sm hover:bg-shade" @click="transferTo(d)">
                                        <span class="font-bold">{{ __('مكتب') }} <span class="font-num" x-text="d.number"></span></span>
                                        <span class="text-ink-faint" x-text="d.staff || @js(__('لا يوجد موظف الآن'))"></span>
                                    </button></li>
                                </template>
                            </ul>
                        </div>
                        <p class="mt-3 text-center text-xs text-ink-faint" x-show="!finishing">
                            <button type="button" class="hover:text-ink underline-offset-4 hover:underline" :disabled="busy"
                                    @click="confirming === 'release' ? visitAction('release') : confirming = 'release'"
                                    x-text="confirming === 'release' ? @js(__('اضغط للتأكيد: إرجاعه لمكانه في قائمة الانتظار')) : @js(__('أخذته بالخطأ؟ إرجاعه لقائمة الانتظار'))"></button>
                        </p>
                    </div>
                </template>
            </section>

            {{-- عميل عن بُعد --}}
            <section class="panel">
                <h2 class="px-5 py-4 border-b-[1.5px] border-line text-[1.15rem] font-bold">{{ __('عميل عن بُعد') }}</h2>
                <div class="p-5 space-y-3">
                    <form @submit.prevent="openRemote()" role="search">
                        <label for="remote-mobile" class="sr-only">{{ __('بحث برقم الجوال') }}</label>
                        <input id="remote-mobile" x-model="remoteMobile" class="field" inputmode="tel" placeholder="{{ __('بحث برقم الجوال') }}">
                    </form>
                    <button type="button" @click="openRemote()" class="w-full h-[46px] rounded-[12px] border-[1.5px] border-dashed border-gold text-gold-dark font-bold hover:bg-gold-soft">
                        + {{ __('إضافة عميل للطابور') }}
                    </button>
                </div>
            </section>
        </div>

        {{-- قائمة الانتظار --}}
        <section class="panel min-w-0 flex flex-col" aria-labelledby="queue-title">
            <div class="px-5 py-4 border-b-[1.5px] border-line flex flex-wrap items-center gap-x-5 gap-y-3">
                <h2 id="queue-title" class="text-[1.15rem] font-bold flex items-center gap-2">
                    {{ __('قائمة الانتظار') }}
                    <span class="rounded-full bg-olive text-ivory text-xs font-num px-2 py-0.5" x-text="state.counts.all"></span>
                </h2>
                <div class="seg" role="tablist" aria-label="{{ __('تصفية') }}">
                    @foreach (['all' => __('الكل'), 'buyer' => __('مشتري'), 'seller' => __('بائع'), 'bank' => __('بنك'), 'other' => __('أخرى')] as $key => $label)
                        <button type="button" role="tab" :class="filter === '{{ $key }}' && 'is-on'" :aria-selected="filter === '{{ $key }}'" @click="filter = '{{ $key }}'">
                            {{ $label }} <span class="n" x-text="state.counts.{{ $key }}"></span>
                        </button>
                    @endforeach
                </div>
                <div class="ms-auto w-full sm:w-64">
                    <label for="q-search" class="sr-only">{{ __('بحث بالاسم أو الجوال') }}</label>
                    <input id="q-search" x-model="search" class="field !py-2 !text-sm" placeholder="{{ __('بحث بالاسم أو الجوال') }}" type="search">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-24">{{ __('الرقم') }}</th>
                            <th>{{ __('العميل') }}</th>
                            <th class="hidden sm:table-cell">{{ __('النوع') }}</th>
                            <th class="hidden md:table-cell">{{ __('الجهة') }}</th>
                            <th>{{ __('مدة الانتظار') }}</th>
                            <th class="w-1"><span class="sr-only">{{ __('إجراء') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="v in waiting" :key="v.id">
                            <tr :class="[fresh[v.id] && 'bg-gold-soft transition-colors duration-1000', v.assigned_to_me && 'bg-gold-soft']">
                                <td><span class="ticket-code text-[1.2rem]" x-text="v.ticket_no"></span></td>
                                <td>
                                    <div class="font-bold" x-text="v.name"></div>
                                    <div class="text-xs text-ink-faint font-num mt-0.5" dir="ltr" style="text-align: right" x-text="v.phone"></div>
                                    <div class="flex flex-wrap gap-1 mt-1" x-show="v.deal_with.length || v.assigned_desk || v.requeued || v.remote || v.returning">
                                        <span class="deal-tag" x-show="v.deal_with.length">{{ __('مرتبط بـ') }} <span class="ltr-nums" x-text="v.deal_with.join('، ')"></span></span>
                                        <span class="deal-tag !border-solid" x-show="v.assigned_desk" x-text="v.assigned_to_me ? @js(__('محوّل إليك')) : @js(__('محوّل لمكتب')) + ' ' + v.assigned_desk"></span>
                                        <span class="soft-tag !py-0" x-show="v.requeued">{{ __('لم يحضر مرة') }}</span>
                                        <span class="soft-tag !py-0" x-show="v.remote">{{ __('عن بُعد') }}</span>
                                        <span class="soft-tag !py-0" x-show="v.returning">{{ __('عميل سابق') }}</span>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell"><span class="type-tag" :class="'type-' + v.type" x-text="v.type_label"></span></td>
                                <td class="hidden md:table-cell text-ink-soft text-sm">
                                    <span x-text="v.via"></span>
                                    <span class="block text-xs text-ink-faint" x-show="v.agent" x-text="@js(__('الوكيل')) + ': ' + v.agent"></span>
                                </td>
                                <td><span class="wait-chip" :class="waitClass(waitMinutes(v))"><span x-text="waitMinutes(v)"></span> {{ __('د') }}</span></td>
                                <td>
                                    <div class="flex items-center gap-1.5 justify-end">
                                        <button class="btn btn-outline btn-sm !px-4" :disabled="!canCall(v)" @click="call(v)">{{ __('نداء') }}</button>
                                        <button class="btn btn-sm text-ink-faint hover:text-danger !px-2" :disabled="busy"
                                                :title="@js(__('حذف العميل (لم يكمل طلبه)'))"
                                                @click="confirming === 'del-' + v.id ? remove(v) : confirming = 'del-' + v.id"
                                                x-text="confirming === 'del-' + v.id ? @js(__('تأكيد الحذف')) : '✕'"></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="!waiting.length" class="px-5 py-16 text-center">
                <p class="font-bold" x-text="filter === 'all' && !search ? @js(__('لا يوجد عملاء في الانتظار')) : @js(__('لا يوجد عملاء مطابقون'))"></p>
                <p class="text-ink-faint text-sm mt-1">{{ __('يظهر العميل هنا لحظة تسجيله من آيباد الاستقبال.') }}</p>
            </div>

            <div class="mt-auto px-5 py-3 border-t-[1.5px] border-line flex flex-wrap items-center justify-between gap-3 text-sm text-ink-faint">
                <span x-show="state.oldest">{{ __('الأقدم في الدور') }}: <b class="ticket-code text-ink" x-text="state.oldest?.ticket_no"></b> {{ __('منذ') }} <b class="font-num text-ink" x-text="oldestMinutes"></b> {{ __('دقيقة') }}</span>
                <span x-show="!state.oldest"></span>
                <span x-show="offline" x-cloak class="text-danger font-bold">{{ __('انقطع الاتصال… جارٍ إعادة المحاولة') }}</span>
                <span class="flex items-center gap-4 text-xs">
                    <span class="flex items-center gap-1.5"><span class="key bg-ok"></span>{{ __('أقل من 10 د') }}</span>
                    <span class="flex items-center gap-1.5"><span class="key bg-[#C8901F]"></span>{{ __('10–20 د') }}</span>
                    <span class="flex items-center gap-1.5"><span class="key bg-danger"></span>{{ __('أكثر من 20 د') }}</span>
                </span>
            </div>
        </section>
    </div>

    {{-- نافذة ربط الطرف الآخر --}}
    <div x-show="linkOpen" x-cloak class="fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-olive/50 p-4 overflow-y-auto" @click.self="linkOpen = false" @keydown.escape.window="linkOpen = false">
        <div class="panel w-full max-w-xl shadow-xl" role="dialog" aria-modal="true" aria-labelledby="link-title" x-trap.noscroll="linkOpen">
            <div class="px-6 py-4 border-b-[1.5px] border-line flex items-center justify-between">
                <div>
                    <h2 id="link-title" class="font-bold text-lg">{{ __('ربط بالطرف الآخر') }}</h2>
                    <p class="text-sm text-ink-faint">{{ __('عملاء اليوم. المقترح أولًا: نفس الوكيل والنوع المقابل.') }}</p>
                </div>
                <button type="button" class="text-ink-faint hover:text-ink text-xl leading-none" @click="linkOpen = false" aria-label="{{ __('إغلاق') }}">✕</button>
            </div>
            <div class="p-4 border-b border-line">
                <label for="cand-filter" class="sr-only">{{ __('بحث') }}</label>
                <input id="cand-filter" x-model="candFilter" class="field" placeholder="{{ __('بحث بالاسم أو الرقم أو الجوال أو الوكيل') }}">
            </div>
            <ul class="max-h-[55vh] overflow-y-auto divide-y divide-line-soft">
                <template x-for="c in filteredCandidates" :key="c.id">
                    <li class="px-5 py-3 flex items-center gap-3">
                        <span class="ticket-code text-lg w-20 shrink-0" x-text="c.ticket_no || '—'"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold" x-text="c.name"></span>
                                <span class="type-tag" :class="'type-' + c.type" x-text="c.type_label"></span>
                                <span class="deal-tag" x-show="c.suggested">{{ __('مقترح: نفس الوكيل') }}</span>
                            </span>
                            <span class="block text-xs text-ink-faint mt-0.5">
                                <span x-text="c.bank || @js(__('أخرى'))"></span><span x-show="c.agent" x-text="' — ' + @js(__('الوكيل')) + ': ' + c.agent"></span>
                                <span x-text="' — ' + (c.with || c.status_label)"></span>
                            </span>
                        </span>
                        <button type="button" class="btn btn-primary btn-sm" :disabled="busy" @click="linkTo(c)">{{ __('ربط') }}</button>
                    </li>
                </template>
            </ul>
            <p class="px-5 py-8 text-center text-ink-faint" x-show="!candLoading && !filteredCandidates.length">{{ __('لا يوجد عملاء مطابقون اليوم. سجّل الطرف الآخر أولًا من الآيباد أو كعميل عن بُعد.') }}</p>
            <p class="px-5 py-8 text-center text-ink-faint" x-show="candLoading">{{ __('جارٍ التحميل…') }}</p>
        </div>
    </div>

    {{-- تنبيهات --}}
    <div class="fixed bottom-5 inset-x-0 flex justify-center pointer-events-none z-40" aria-live="polite">
        <div x-show="toast" x-transition.opacity class="flash pointer-events-auto shadow-lg max-w-lg" :class="toast?.kind === 'error' ? 'flash-error' : 'flash-ok'" x-text="toast?.message"></div>
    </div>
</div>

@include('staff.partials.remote-modal')
@endsection
