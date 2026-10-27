import { api, asciiDigits } from './lib';

/**
 * آيباد الاستقبال: نموذج من خطوة واحدة ثم شاشة التذكرة،
 * ويعود تلقائيًا لنموذج فارغ حفاظًا على خصوصية العميل التالي.
 */
export default (config) => ({
    step: 'form',
    form: { name: '', mobile: '', customer_type: '', bank_id: '', agent_id: '' },
    errors: {},
    error: '',
    result: null,
    countdown: 0,
    timer: null,
    idleTimer: null,

    init() {
        ['pointerdown', 'keydown'].forEach((evt) => window.addEventListener(evt, () => this.bumpIdle(), { passive: true }));
        // تحديث الصفحة دوريًا وهي فارغة لتظهر تعديلات الإدارة (البنوك، الشعار...)
        setInterval(() => {
            if (this.step === 'form' && !this.form.name && !this.form.mobile) window.location.reload();
        }, 30 * 60 * 1000);
    },

    get bankAgents() {
        return config.agents[this.form.bank_id] || [];
    },

    get ready() {
        return this.form.name.trim().length >= 2 && this.form.mobile.length >= 9 && this.form.customer_type && this.form.bank_id !== ''
            && (!this.bankAgents.length || this.form.agent_id !== '');
    },

    selectBank(id) {
        const changed = this.form.bank_id !== id;
        this.form.bank_id = id;
        if (changed) this.form.agent_id = '';
        delete this.errors.bank_id;
        delete this.errors.agent_id;
        if (this.bankAgents.length) this.$nextTick(() => this.$refs.agentBox?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    },

    onMobileInput(e) {
        this.form.mobile = asciiDigits(e.target.value).replace(/[^\d+]/g, '');
        e.target.value = this.form.mobile;
        delete this.errors.mobile;
    },

    async submit() {
        if (this.step === 'sending') return;
        this.errors = {};
        this.error = '';
        if (!this.ready) {
            this.validateLocally();
            return;
        }
        this.step = 'sending';
        try {
            this.result = await api(config.registerUrl, { method: 'POST', body: this.form });
            this.step = 'ticket';
            this.startCountdown();
        } catch (e) {
            this.step = 'form';
            this.errors = Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k, v[0]]));
            if (!Object.keys(this.errors).length) this.error = e.message;
        }
    },

    validateLocally() {
        if (this.form.name.trim().length < 2) this.errors.name = 'اكتب اسمك';
        if (this.form.mobile.length < 9) this.errors.mobile = 'اكتب رقم جوالك المرتبط بواتساب';
        if (!this.form.customer_type) this.errors.customer_type = 'اختر هل أنت بائع أم مشتري';
        if (this.form.bank_id === '') this.errors.bank_id = 'اختر البنك';
        else if (this.bankAgents.length && this.form.agent_id === '') this.errors.agent_id = 'اختر وكيل البنك، أو «لا أعرف / بدون وكيل»';
    },

    startCountdown() {
        this.countdown = config.resetSeconds;
        clearInterval(this.timer);
        this.timer = setInterval(() => {
            this.countdown--;
            if (this.countdown <= 0) this.reset();
        }, 1000);
    },

    bumpIdle() {
        clearTimeout(this.idleTimer);
        // نموذج متروك نصف ممتلئ يُمسح بعد دقيقتين
        this.idleTimer = setTimeout(() => {
            if (this.step === 'form' && (this.form.name || this.form.mobile)) this.reset();
        }, 120000);
    },

    reset() {
        clearInterval(this.timer);
        this.form = { name: '', mobile: '', customer_type: '', bank_id: '', agent_id: '' };
        this.errors = {};
        this.error = '';
        this.result = null;
        this.step = 'form';
        window.scrollTo({ top: 0 });
    },
});
