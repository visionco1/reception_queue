import { api, humanDuration, clockDuration, visitsLabel } from './lib';

/**
 * لوحة الموظف: قائمة انتظار مشتركة تتحدث تلقائيًا، أخذ العميل،
 * وإدارة العميل الحالي حتى إنهاء الخدمة.
 */
export default (config) => ({
    state: config.initial,
    filter: 'all',
    busy: null,
    toast: null,
    toastTimer: null,
    tick: Date.now(),
    offset: 0,
    fresh: {},
    confirming: null,
    notes: '',
    offline: false,
    linkOpen: false,
    candidates: [],
    candLoading: false,
    candFilter: '',
    editingAgent: false,
    agentChoice: '',

    visitsLabel,

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
            if (this.state.current?.id !== next.current?.id) this.notes = '';
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
        if (this.filter === 'all') return this.state.waiting;
        return this.state.waiting.filter((v) => v.type === this.filter);
    },

    elapsed(iso) {
        if (!iso) return 0;
        return Math.max(0, Math.floor((this.tick + this.offset - new Date(iso).getTime()) / 1000));
    },

    waitLabel(v) {
        return humanDuration(this.elapsed(v.issued_at));
    },

    waitTone(v) {
        const m = this.elapsed(v.issued_at) / 60;
        if (m >= config.longWaitMinutes) return 'text-danger font-semibold';
        if (m >= config.longWaitMinutes / 2) return 'text-warn';
        return 'text-ink-soft';
    },

    get currentAgents() {
        const bank = this.state.current?.bank_id;
        return (bank && config.agentsByBank[bank]) || [];
    },

    async saveAgent() {
        const id = this.state.current?.id;
        if (!id) return;
        await this.act(config.urls.agent.replace('__ID__', id), { agent_id: this.agentChoice || null });
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

    serviceClock() {
        return clockDuration(this.elapsed(this.state.current?.started_at));
    },

    notify(message, kind = 'ok') {
        this.toast = { message, kind };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (this.toast = null), kind === 'error' ? 6000 : 3500);
    },

    async act(url, body = {}, success = null) {
        if (this.busy) return;
        this.busy = url;
        try {
            const res = await api(url, { method: 'POST', body });
            if (success || res.message) this.notify(success || res.message);
            this.confirming = null;
        } catch (e) {
            this.notify(e.message, 'error');
        } finally {
            this.busy = null;
            await this.refresh();
        }
    },

    take(v) {
        return this.act(config.urls.take.replace('__ID__', v.id));
    },

    visitAction(name, body = {}) {
        const id = this.state.current?.id;
        if (!id) return;
        return this.act(config.urls[name].replace('__ID__', id), body);
    },

    cancel(v) {
        return this.act(config.urls.cancel.replace('__ID__', v.id));
    },
});
