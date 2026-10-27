import { api, asciiDigits, visitsLabel } from './lib';

/**
 * إضافة عميل عن بعد: يبدأ الموظف برقم الجوال، فإن كان العميل مسجلًا
 * يظهر تنبيه بسجله بدل إنشاء سجل مكرر، ثم يحفظ التفاعل بدون رقم انتظار.
 */
export default (config) => ({
    open: false,
    form: { mobile: '', name: '', customer_type: '', client_type_id: '', bank_id: '', agent_id: '', source: '', notes: '' },
    found: null,
    looked: false,
    looking: false,
    saving: false,
    errors: {},
    error: '',
    saved: null,

    visitsLabel,

    get bankAgents() {
        return (config.agents || {})[this.form.bank_id] || [];
    },

    show(prefill = {}) {
        this.reset();
        Object.assign(this.form, prefill);
        this.open = true;
        if (this.form.mobile) this.lookup();
        this.$nextTick(() => this.$refs.mobile?.focus());
    },

    close() {
        this.open = false;
    },

    reset() {
        this.form = { mobile: '', name: '', customer_type: '', client_type_id: '', bank_id: '', agent_id: '', source: '', notes: '' };
        this.found = null;
        this.looked = false;
        this.errors = {};
        this.error = '';
        this.saved = null;
    },

    onMobileInput(e) {
        this.form.mobile = asciiDigits(e.target.value).replace(/[^\d+]/g, '');
        e.target.value = this.form.mobile;
        this.looked = false;
        this.found = null;
        delete this.errors.mobile;
    },

    async lookup() {
        if (this.form.mobile.length < 9 || this.looking) return;
        this.looking = true;
        try {
            const res = await api(config.lookupUrl + '?mobile=' + encodeURIComponent(this.form.mobile));
            this.found = res.customer;
            this.looked = true;
            if (res.customer && !this.form.name) this.form.name = res.customer.name;
        } catch (e) {
            this.errors.mobile = e.errors?.mobile?.[0] || e.message;
        } finally {
            this.looking = false;
        }
    },

    async save() {
        if (this.saving) return;
        this.saving = true;
        this.errors = {};
        this.error = '';
        try {
            const res = await api(config.storeUrl, { method: 'POST', body: this.form });
            this.saved = res;
            window.dispatchEvent(new CustomEvent('remote-saved'));
        } catch (e) {
            this.errors = Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k, v[0]]));
            if (!Object.keys(this.errors).length) this.error = e.message;
        } finally {
            this.saving = false;
        }
    },
});
