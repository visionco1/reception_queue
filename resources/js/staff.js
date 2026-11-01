import { api, clockDuration, visitsLabel, waitClass } from './lib';

/**
 * لوحة الموظفين (الشاشة 03): قائمة انتظار مشتركة تتحدث تلقائيًا بفلاتر
 * وبحث، نداء عميل معيّن، وإدارة «عميلي الحالي» حتى إنهاء الخدمة بنتيجتها.
 */
export default (config) => ({
    state: config.initial,
    filter: 'all',
    search: '',
    busy: null,
    toast: null,
    toastTimer: null,
    tick: Date.now(),
    offset: 0,
    fresh: {},
    confirming: null,
    finishing: false,
    outcome: 'completed',
    notes: '',
    offline: false,
    linkOpen: false,
    candidates: [],
    candLoading: false,
    candFilter: '',
    transferOpen: false,
    bankChoice: '',
    agentChoice: '',
    editingAgent: false,
    remoteMobile: '',

    visitsLabel,
    waitClass,

    init() {
        this.syncClock(this.state.server_time);
        this.markFresh([], this.state.waiting);
        setInterval(() => (this.tick = Date.now()), 1000);
        const loop = async () => {
            await this.refresh();
            setTimeout(loop, document.hidden ? config.pollMs * 4 : config.pollMs);
        };
        setTimeout(loop, config.pollMs);
        document.addEventListener('visibilitychange', () => !document.hidden && this.refresh());
        window.addEventListener('remote-saved', () => this.refresh());
    },

    syncClock(serverIso) {
        this.offset = new Date(serverIso).getTime() - Date.now();
    },

    async refresh() {
        try {
            const next = await api(config.stateUrl);
            this.offline = false;
            this.markFresh(this.state.waiting, next.waiting);
            this.syncClock(next.server_time);
            if (this.state.current?.id !== next.current?.id) {
                this.notes = '';
                this.finishing = false;
                this.outcome = 'completed';
                this.editingAgent = false;
                this.transferOpen = false;
            }
            this.state = next;
        } catch (e) {
            if (e.network) this.offline = true;
        }
    },

    markFresh(before, after) {
        const known = new Set(before.map((v) => v.id));
        after.forEach((v) => {
            if (before.length && !known.has(v.id)) {
                this.fresh[v.id] = true;
                setTimeout(() => delete this.fresh[v.id], 6000);
            }
        });
    },

    get waiting() {
        const q = this.search.trim().replace(/\s/g, '').toLowerCase();
        return this.state.waiting.filter((v) => {
            if (this.filter === 'buyer' || this.filter === 'seller') {
                if (v.type !== this.filter) return false;
            } else if (this.filter === 'bank' || this.filter === 'other') {
                if (v.via_kind !== this.filter) return false;
            }
            if (!q) return true;
            return [v.name, v.ticket_no, v.phone].some((f) => String(f || '').replace(/\s/g, '').toLowerCase().includes(q.replace(/^0/, '')));
        });
    },

    /** أول من يمكنني ندائه: المحوَّل لمكتبي أولًا ثم الأقدم */
    get nextUp() {
        return this.state.waiting.find((v) => !v.assigned_desk || v.assigned_to_me) || null;
    },

    elapsed(iso) {
        if (!iso) return 0;
        return Math.max(0, Math.floor((this.tick + this.offset - new Date(iso).getTime()) / 1000));
    },

    waitMinutes(v) {
        return Math.floor(this.elapsed(v.issued_at) / 60);
    },

    minutes(seconds) {
        return seconds == null ? '—' : Math.round(seconds / 60);
    },

    get oldestMinutes() {
        return this.state.oldest ? Math.floor(this.elapsed(this.state.oldest.since) / 60) : 0;
    },

    serviceClock() {
        return clockDuration(this.elapsed(this.state.current?.started_at));
    },

    canCall(v) {
        return !this.state.current && this.state.desk && !this.busy && (!v.assigned_desk || v.assigned_to_me);
    },

    get otherDesks() {
        return (this.state.desks || []).filter((d) => d.id !== this.state.desk?.id);
    },

    get currentAgents() {
        const bank = this.bankChoice || this.state.current?.bank_id;
        return (bank && config.agentsByBank[bank]) || [];
    },

    startAgentEdit() {
        this.bankChoice = this.state.current?.bank_id || '';
        this.agentChoice = this.state.current?.agent_id || '';
        this.editingAgent = true;
    },

    async saveAgent() {
        const id = this.state.current?.id;
        if (!id) return;
        await this.act(config.urls.agent.replace('__ID__', id), { bank_id: this.bankChoice || null, agent_id: this.agentChoice || null });
        this.editingAgent = false;
    },

    get withMe() {
        return (this.state.current?.linked || []).filter((p) => p.mine).length;
    },

    get filteredCandidates() {
        const q = this.candFilter.trim().toLowerCase();
        if (!q) return this.candidates;
        return this.candidates.filter((c) =>
            [c.name, c.ticket_no, c.mobile, c.agent, c.bank].some((f) => String(f || '').toLowerCase().replace(/\s/g, '').includes(q.replace(/\s/g, ''))),
        );
    },

    async openLink() {
        const id = this.state.current?.id;
        if (!id) return;
        this.linkOpen = true;
        this.candFilter = '';
        this.candidates = [];
        this.candLoading = true;
        try {
            const res = await api(config.urls.candidates.replace('__ID__', id));
            this.candidates = res.candidates;
        } catch (e) {
            this.notify(e.message, 'error');
            this.linkOpen = false;
        } finally {
            this.candLoading = false;
        }
    },

    async linkTo(c) {
        const id = this.state.current?.id;
        if (!id) return;
        await this.act(config.urls.link.replace('__ID__', id), { other_id: c.id });
        this.linkOpen = false;
    },

    partnerAction(name, p) {
        return this.act(config.urls[name].replace('__ID__', p.id));
    },

    notify(message, kind = 'ok') {
        this.toast = { message, kind };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (this.toast = null), kind === 'error' ? 6000 : 3500);
    },

    async act(url, body = {}, method = 'POST') {
        if (this.busy) return;
        this.busy = url;
        try {
            const res = await api(url, { method, body: method === 'DELETE' ? null : body });
            if (res.message) this.notify(res.message);
            this.confirming = null;
        } catch (e) {
            this.notify(e.message, 'error');
        } finally {
            this.busy = null;
            await this.refresh();
        }
    },

    call(v) {
        return this.act(config.urls.take.replace('__ID__', v.id));
    },

    remove(v) {
        return this.act(config.urls.destroy.replace('__ID__', v.id), {}, 'DELETE');
    },

    visitAction(name, body = {}) {
        const id = this.state.current?.id;
        if (!id) return;
        return this.act(config.urls[name].replace('__ID__', id), body);
    },

    finish() {
        return this.visitAction('complete', { outcome: this.outcome, notes: this.notes });
    },

    transferTo(d) {
        this.transferOpen = false;
        return this.visitAction('transfer', { desk_id: d.id });
    },

    openRemote() {
        this.$dispatch('open-remote', this.remoteMobile ? { mobile: this.remoteMobile } : {});
        this.remoteMobile = '';
    },
});
