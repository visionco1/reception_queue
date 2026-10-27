@extends('layouts.panel')
@section('title', __('الطابور'))

@section('content')
<div x-data="staffDashboard({
        initial: @js($initial),
        stateUrl: @js(route('staff.state')),
        pollMs: {{ (int) config('qms.queue.poll_ms') }},
        longWaitMinutes: 20,
        urls: {
            take: @js(route('staff.visits.take', '__ID__')),
            recall: @js(route('staff.visits.recall', '__ID__')),
            start: @js(route('staff.visits.start', '__ID__')),
            complete: @js(route('staff.visits.complete', '__ID__')),
            noShow: @js(route('staff.visits.no-show', '__ID__')),
            release: @js(route('staff.visits.release', '__ID__')),
            cancel: @js(route('staff.visits.cancel', '__ID__')),
            candidates: @js(route('staff.visits.candidates', '__ID__')),
            link: @js(route('staff.visits.link', '__ID__')),
            unlink: @js(route('staff.visits.unlink', '__ID__')),
            agent: @js(route('staff.visits.agent', '__ID__')),
        },
        agentsByBank: @js($agentsByBank),
     })">

    {{-- اختيار المكتب في بداية الدوام --}}
    <template x-if="!state.desk">
        <form method="POST" action="{{ route('staff.desk') }}" class="panel border-seal/60 bg-[#fffaf0] p-4 mb-6 flex flex-wrap items-center gap-3">
            @csrf
            <p class="font-semibold me-auto">{{ __('اختر رقم مكتبك قبل أخذ العملاء، ليظهر للعميل على شاشة الانتظار.') }}</p>
            <select name="desk_id" class="field !w-auto" required>
                @foreach ($desks as $d)
                    <option value="{{ $d->id }}">{{ __('مكتب :n', ['n' => $d->number]) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary">{{ __('بدء العمل على هذا المكتب') }}</button>
        </form>
    </template>

    <div class="grid lg:grid-cols-[22rem_minmax(0,1fr)] gap-6 items-start">

        {{-- العمود الجانبي: عميلي الحالي --}}
        <div class="space-y-6 lg:sticky lg:top-6">
            <section class="panel overflow-hidden" aria-labelledby="current-title">
                <div class="px-5 py-3.5 border-b border-line flex items-center justify-between">
                    <h2 id="current-title" class="font-semibold">{{ __('عميلي الحالي') }}</h2>
                    <span class="text-sm text-ink-faint" x-show="state.desk">{{ __('مكتب') }} <b class="text-ink ltr-nums" x-text="state.desk?.number"></b></span>
                </div>

                <template x-if="!state.current">
                    <div class="px-5 py-8 text-center">
                        <p class="text-ink-soft">{{ __('لا يوجد عميل معك الآن.') }}</p>
                        <p class="text-ink-faint text-sm mt-1">{{ __('اختر عميلًا من قائمة الانتظار، أو استدعِ الأقدم مباشرة.') }}</p>
                        <button class="btn btn-primary mt-5 w-full !py-2.5" :disabled="!waiting.length || !state.desk || busy" @click="take(waiting[0])">
                            {{ __('استدعاء التالي') }}
                            <span class="ticket-code font-normal opacity-80" x-show="waiting.length" x-text="waiting[0]?.ticket_no"></span>
                        </button>
                    </div>
                </template>

                <template x-if="state.current">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="ticket-code text-5xl leading-none" :class="state.current.type === 'buyer' ? 'text-buyer' : 'text-seller'" x-text="state.current.ticket_no"></div>
                                <div class="mt-2"><span class="type-tag" :class="'type-' + state.current.type" x-text="state.current.type_label"></span></div>
                            </div>
                            <div class="text-end">
                                <span class="status-pill" :class="'status-' + state.current.status" x-text="state.current.status_label"></span>
                                <div class="ltr-nums font-display font-semibold text-2xl mt-2" x-text="serviceClock()" :title="@js(__('مدة الخدمة'))"></div>
                            </div>
                        </div>

                        <dl class="mt-5 text-[0.9375rem] divide-y divide-line-soft">
                            <div class="flex justify-between gap-4 py-2"><dt class="text-ink-faint">{{ __('الاسم') }}</dt><dd class="font-semibold text-end" x-text="state.current.name"></dd></div>
                            <div class="flex justify-between gap-4 py-2"><dt class="text-ink-faint">{{ __('الجوال') }}</dt><dd class="ltr-nums" x-text="state.current.mobile"></dd></div>
                            <div class="flex justify-between gap-4 py-2"><dt class="text-ink-faint">{{ __('البنك') }}</dt><dd x-text="state.current.bank || @js(__('بدون بنك'))"></dd></div>
                            <div class="flex justify-between items-center gap-4 py-2" x-show="state.current.agent || currentAgents.length">
                                <dt class="text-ink-faint">{{ __('وكيل البنك') }}</dt>
                                <dd class="text-end">
                                    <template x-if="!editingAgent">
                                        <span>
                                            <span x-text="state.current.agent || @js(__('بدون وكيل'))"></span>
                                            <button type="button" class="text-xs text-deed font-semibold hover:underline ms-1" x-show="currentAgents.length" @click="editingAgent = true; agentChoice = state.current.agent_id">{{ __('تغيير') }}</button>
                                        </span>
                                    </template>
                                    <template x-if="editingAgent">
                                        <span class="flex items-center gap-1.5">
                                            <select x-model="agentChoice" class="field !py-1 !text-sm !w-40" aria-label="{{ __('وكيل البنك') }}">
                                                <option value="">{{ __('بدون وكيل') }}</option>
                                                <template x-for="a in currentAgents" :key="a.id"><option :value="a.id" x-text="a.name" :selected="a.id === agentChoice"></option></template>
                                            </select>
                                            <button type="button" class="btn btn-primary btn-sm" :disabled="busy" @click="saveAgent()">{{ __('حفظ') }}</button>
                                        </span>
                                    </template>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4 py-2" x-show="state.current.returning">
                                <dt class="text-ink-faint">{{ __('السجل') }}</dt>
                                <dd><a :href="state.current.customer_url" class="text-deed font-semibold underline-offset-4 hover:underline" target="_blank">
                                    {{ __('عميل سابق') }} (<span x-text="visitsLabel(state.current.visits_count)"></span>)</a></dd>
                            </div>
                        </dl>

                        {{-- الطرف الآخر في الصفقة (البائع ↔ المشتري) --}}
                        <div class="mt-4 rounded-lg border border-dashed border-deed/40 bg-deed-soft/30 p-3" x-show="state.current.linkable">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold">{{ __('الطرف الآخر') }}</span>
                                <button type="button" class="text-sm text-deed font-semibold hover:underline" @click="openLink()">
                                    <span x-text="state.current.linked.length ? @js(__('ربط طرف إضافي')) : @js(__('ربط بالطرف الآخر'))"></span>
                                </button>
                            </div>
                            <p class="text-sm text-ink-faint mt-1" x-show="!state.current.linked.length">{{ __('اربط البائع بالمشتري؛ إن كان الطرف الآخر ينتظر يُستدعى لمكتبك فورًا.') }}</p>
                            <ul class="mt-1 divide-y divide-line-soft">
                                <template x-for="p in state.current.linked" :key="p.id">
                                    <li class="py-2">
                                        <div class="flex items-center gap-2">
                                            <span class="ticket-code text-lg" :class="p.type === 'buyer' ? 'text-buyer' : 'text-seller'" x-text="p.ticket_no || '—'"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="font-semibold block truncate" x-text="p.name"></span>
                                                <span class="text-xs text-ink-faint" x-text="(p.type_label) + ' — ' + (p.mine ? @js(__('معك الآن')) + ' (' + p.status_label + ')' : (p.where || p.status_label))"></span>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                                            <button type="button" class="btn btn-danger btn-sm !py-0.5" x-show="p.mine && p.status === 'called'" :disabled="busy"
                                                    @click="confirming === 'pns-' + p.id ? partnerAction('noShow', p) : confirming = 'pns-' + p.id"
                                                    x-text="confirming === 'pns-' + p.id ? @js(__('تأكيد: لم يحضر')) : @js(__('لم يحضر'))"></button>
                                            <button type="button" class="btn btn-quiet btn-sm !py-0.5" x-show="p.mine" :disabled="busy"
                                                    @click="confirming === 'prl-' + p.id ? partnerAction('release', p) : confirming = 'prl-' + p.id"
                                                    x-text="confirming === 'prl-' + p.id ? @js(__('تأكيد الإرجاع')) : @js(__('إرجاع للطابور'))"></button>
                                            <button type="button" class="btn btn-sm !py-0.5 text-ink-faint hover:text-danger" :disabled="busy"
                                                    @click="confirming === 'pul-' + p.id ? partnerAction('unlink', p) : confirming = 'pul-' + p.id"
                                                    x-text="confirming === 'pul-' + p.id ? @js(__('تأكيد فك الربط')) : @js(__('فك الربط'))"></button>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="mt-5 space-y-2.5">
                            <template x-if="confirming !== 'complete'">
                                <button class="btn btn-primary w-full !py-3 !text-base" @click="confirming = 'complete'"
                                        x-text="withMe ? @js(__('إنهاء الخدمة للطرفين')) : @js(__('إنهاء الخدمة'))"></button>
                            </template>
                            <template x-if="confirming === 'complete'">
                                <div class="rounded-lg border border-line p-3 space-y-2.5 bg-paper">
                                    <label for="svc-notes" class="field-label !mb-0">{{ __('ملاحظة على الخدمة (اختياري)') }}</label>
                                    <textarea id="svc-notes" x-model="notes" rows="2" class="field" maxlength="2000"></textarea>
                                    <div class="flex gap-2">
                                        <button class="btn btn-primary flex-1" :disabled="busy" @click="visitAction('complete', { notes })">{{ __('تأكيد الإنهاء') }}</button>
                                        <button class="btn btn-quiet" @click="confirming = null">{{ __('رجوع') }}</button>
                                    </div>
                                </div>
                            </template>

                            <button class="btn btn-quiet w-full" x-show="state.current.status === 'called'" :disabled="busy" @click="visitAction('start')">{{ __('حضر العميل — بدء الخدمة') }}</button>

                            <div class="grid grid-cols-2 gap-2" x-show="confirming !== 'complete'">
                                <button class="btn btn-quiet btn-sm" :disabled="busy" @click="visitAction('recall')">
                                    {{ __('إعادة النداء') }}<span class="text-ink-faint ltr-nums" x-show="state.current.recalls" x-text="'(' + state.current.recalls + ')'"></span>
                                </button>
                                <button class="btn btn-quiet btn-sm" :disabled="busy" @click="confirming === 'release' ? visitAction('release') : confirming = 'release'"
                                        x-text="confirming === 'release' ? @js(__('تأكيد الإرجاع')) : @js(__('إرجاع للطابور'))"></button>
                                <button class="btn btn-danger btn-sm col-span-2" :disabled="busy" x-show="state.current.status === 'called'"
                                        @click="confirming === 'noshow' ? visitAction('noShow') : confirming = 'noshow'"
                                        x-text="confirming === 'noshow' ? @js(__('تأكيد: لم يحضر')) : @js(__('لم يحضر — تخطي'))"></button>
                            </div>
                        </div>
                    </div>
                </template>
            </section>

            <section class="panel p-5 space-y-3">
                <button type="button" class="btn btn-quiet w-full" @click="$dispatch('open-remote')">{{ __('إضافة عميل عن بُعد') }}</button>
                <form action="{{ route('staff.customers.index') }}" class="flex gap-2" role="search">
                    <label for="quick-search" class="sr-only">{{ __('بحث برقم الجوال') }}</label>
                    <input id="quick-search" name="q" class="field ltr-nums" dir="ltr" inputmode="tel" placeholder="{{ __('بحث برقم الجوال') }}">
                    <button class="btn btn-quiet">{{ __('بحث') }}</button>
                </form>
            </section>

            <section class="panel hidden lg:block" x-show="state.busy.length">
                <h2 class="px-5 py-3 border-b border-line font-semibold text-sm">{{ __('المكاتب الآن') }}</h2>
                <ul class="px-5 py-2 text-sm divide-y divide-line-soft">
                    <template x-for="b in state.busy" :key="b.desk + b.ticket_no">
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span><b class="ltr-nums" x-text="b.desk"></b> <span class="text-ink-soft" x-text="b.employee"></span></span>
                            <span class="ticket-code" x-text="b.ticket_no"></span>
                        </li>
                    </template>
                </ul>
            </section>
        </div>

        {{-- قائمة الانتظار المشتركة --}}
        <section class="panel min-w-0" aria-labelledby="queue-title">
            <div class="px-5 py-3.5 border-b border-line flex flex-wrap items-center gap-x-6 gap-y-3">
                <h2 id="queue-title" class="font-display font-bold text-lg">
                    {{ __('قائمة الانتظار') }} <span class="ltr-nums text-ink-faint font-sans font-semibold" x-text="state.counts.waiting"></span>
                </h2>
                <div class="flex rounded-lg border border-line p-0.5 text-sm" role="tablist">
                    <button role="tab" class="px-3 py-1.5 rounded-md" :class="filter === 'all' ? 'bg-ink text-white font-semibold' : 'text-ink-soft'" :aria-selected="filter === 'all'" @click="filter = 'all'">{{ __('الكل') }}</button>
                    <button role="tab" class="px-3 py-1.5 rounded-md" :class="filter === 'buyer' ? 'bg-buyer text-white font-semibold' : 'text-ink-soft'" :aria-selected="filter === 'buyer'" @click="filter = 'buyer'">{{ __('مشتري') }} <span class="ltr-nums" x-text="state.counts.buyer"></span></button>
                    <button role="tab" class="px-3 py-1.5 rounded-md" :class="filter === 'seller' ? 'bg-seller text-white font-semibold' : 'text-ink-soft'" :aria-selected="filter === 'seller'" @click="filter = 'seller'">{{ __('بائع') }} <span class="ltr-nums" x-text="state.counts.seller"></span></button>
                </div>
                <div class="ms-auto text-sm text-ink-faint">
                    {{ __('خدمتَ اليوم') }} <b class="text-ink ltr-nums" x-text="state.counts.served_today"></b>
                    <span x-show="offline" x-cloak class="ms-3 text-danger font-semibold">{{ __('انقطع الاتصال') }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-24">{{ __('الرقم') }}</th>
                            <th>{{ __('الاسم') }}</th>
                            <th class="hidden sm:table-cell">{{ __('بائع / مشتري') }}</th>
                            <th class="hidden md:table-cell">{{ __('البنك') }}</th>
                            <th>{{ __('مدة الانتظار') }}</th>
                            <th class="w-1"><span class="sr-only">{{ __('إجراء') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="v in waiting" :key="v.id">
                            <tr :class="fresh[v.id] && 'bg-[#fff8e6] transition-colors duration-1000'">
                                <td><span class="ticket-code text-xl" :class="v.type === 'buyer' ? 'text-buyer' : 'text-seller'" x-text="v.ticket_no"></span></td>
                                <td>
                                    <span class="font-semibold" x-text="v.name" :title="v.full_name"></span>
                                    <span x-show="v.returning" class="ms-1.5 text-xs text-deed bg-deed-soft rounded px-1.5 py-0.5 whitespace-nowrap">{{ __('عميل سابق') }}</span>
                                    <template x-if="v.deal_with.length">
                                        <span class="deal-tag ms-1.5">{{ __('مرتبط بـ') }} <span class="ltr-nums" x-text="v.deal_with.join('، ')"></span></span>
                                    </template>
                                    <div class="sm:hidden mt-1"><span class="type-tag" :class="'type-' + v.type" x-text="v.type_label"></span></div>
                                </td>
                                <td class="hidden sm:table-cell"><span class="type-tag" :class="'type-' + v.type" x-text="v.type_label"></span></td>
                                <td class="text-ink-soft hidden md:table-cell">
                                    <span x-text="v.bank || '—'"></span>
                                    <span class="block text-xs text-ink-faint" x-show="v.agent" x-text="@js(__('الوكيل')) + ': ' + v.agent"></span>
                                </td>
                                <td :class="waitTone(v)" x-text="waitLabel(v)"></td>
                                <td>
                                    <div class="flex items-center gap-1.5 justify-end">
                                        <button class="btn btn-primary btn-sm !px-3 sm:!px-4" :disabled="!!state.current || !state.desk || busy" @click="take(v)">{{ __('أخذ') }}<span class="hidden sm:inline -ms-1">{{ __('العميل') }}</span></button>
                                        <button class="btn btn-sm text-ink-faint hover:text-danger !px-2" :disabled="busy"
                                                :title="@js(__('إلغاء من الطابور'))"
                                                @click="confirming === 'cancel-' + v.id ? cancel(v) : confirming = 'cancel-' + v.id"
                                                x-text="confirming === 'cancel-' + v.id ? @js(__('تأكيد الإلغاء')) : '✕'"></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="!waiting.length" class="px-5 py-16 text-center">
                <p class="font-semibold" x-text="filter === 'all' ? @js(__('لا يوجد عملاء في الانتظار')) : @js(__('لا يوجد عملاء من هذا النوع في الانتظار'))"></p>
                <p class="text-ink-faint text-sm mt-1">{{ __('يظهر العميل هنا لحظة تسجيله من آيباد الاستقبال.') }}</p>
            </div>
            <p x-show="state.current && waiting.length" class="px-5 py-3 border-t border-line text-sm text-ink-faint">{{ __('أنهِ خدمة عميلك الحالي لتتمكن من أخذ عميل جديد.') }}</p>
        </section>
    </div>

    {{-- نافذة ربط الطرف الآخر --}}
    <div x-show="linkOpen" x-cloak class="fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-ink/40 p-4 overflow-y-auto" @click.self="linkOpen = false" @keydown.escape.window="linkOpen = false">
        <div class="panel w-full max-w-xl shadow-xl" role="dialog" aria-modal="true" aria-labelledby="link-title" x-trap.noscroll="linkOpen">
            <div class="px-6 py-4 border-b border-line flex items-center justify-between">
                <div>
                    <h2 id="link-title" class="font-display font-bold text-lg">{{ __('ربط بالطرف الآخر') }}</h2>
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
                        <span class="ticket-code text-lg w-16 shrink-0" :class="c.type === 'buyer' ? 'text-buyer' : 'text-seller'" x-text="c.ticket_no || '—'"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold" x-text="c.name"></span>
                                <span class="type-tag" :class="'type-' + c.type" x-text="c.type_label"></span>
                                <span class="deal-tag" x-show="c.suggested">{{ __('مقترح: نفس الوكيل') }}</span>
                            </span>
                            <span class="block text-xs text-ink-faint mt-0.5">
                                <span x-text="c.bank || @js(__('بدون بنك'))"></span><span x-show="c.agent" x-text="' — ' + @js(__('الوكيل')) + ': ' + c.agent"></span>
                                <span x-text="' — ' + (c.with || c.status_label) + (c.ticket_no ? '' : ' (' + c.channel + ')')"></span>
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
