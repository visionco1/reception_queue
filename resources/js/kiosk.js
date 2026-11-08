import { api, asciiDigits } from './lib';

/**
 * آيباد الاستقبال (الشاشة 01): نموذج واحد ثم تذكرة الرقم، ويعود تلقائيًا
 * لنموذج فارغ بعد 10 ثوانٍ حفاظًا على خصوصية العميل التالي.
 * الواجهة بالعربي، وزر English يبدّلها للإنجليزي واتجاهها لليسار.
 */
const T = {
    ar: {
        title: 'سجّل وخذ رقمك',
        waitingNow: 'في الانتظار الآن',
        noQueue: 'لا يوجد انتظار الآن، دورك مباشرة بعد التسجيل',
        about: 'قرابة',
        minutes: 'دقيقة',
        name: 'الاسم',
        namePh: 'الاسم الكامل',
        mobile: 'رقم الجوال',
        mobileHint: 'يوصلك رقم الانتظار على واتساب',
        buyer: 'مشتري',
        seller: 'بائع',
        viaBank: 'عن طريق بنك',
        other: 'أخرى',
        otherHint: 'شركة عقارية أو أفراد',
        company: 'شركة عقارية',
        individuals: 'أفراد',
        otherBank: 'بنك آخر',
        chooseBank: 'اختر البنك',
        chooseAgent: 'وكيل البنك (اختياري)',
        register: 'تسجيل وأخذ رقم',
        sending: 'جارٍ التسجيل…',
        done: 'تم تسجيلك،',
        yourNumber: 'رقم انتظارك',
        ahead: 'أمامك في الدور',
        approx: 'دقائق تقريبًا',
        sentTo: 'أرسلنا الرقم على واتساب',
        existing: 'لديك رقم انتظار اليوم بالفعل',
        newCustomer: 'تسجيل عميل جديد',
        backIn: 'تعود الشاشة تلقائيًا خلال',
        seconds: 'ثوانٍ',
        close: 'إغلاق',
        closed: 'التسجيل متاح في ساعات العمل',
        welcome: 'أهلًا بكم',
        welcomeText: 'سجّل بياناتك وخذ رقمك، وتابع دورك على الشاشة.',
        steps: ['سجّل بياناتك', 'يوصلك الرقم على واتساب', 'توجّه للمكتب عند النداء'],
        errName: 'اكتب اسمك (حرفين على الأقل)',
        errMobile: 'رقم الجوال 9 أرقام يبدأ بـ 5',
        errType: 'اختر مشتري أو بائع',
        errVia: 'اختر عن طريق بنك أو أخرى',
        errBank: 'اختر البنك',
    },
    en: {
        title: 'Register & get your number',
        waitingNow: 'Waiting now',
        noQueue: 'No queue right now, you will be served right after registering',
        about: 'about',
        minutes: 'min',
        name: 'Name',
        namePh: 'Full name',
        mobile: 'Mobile number',
        mobileHint: 'Your queue number will be sent on WhatsApp',
        buyer: 'Buyer',
        seller: 'Seller',
        viaBank: 'Through a bank',
        other: 'Other',
        otherHint: 'Real-estate company or individuals',
        company: 'Real-estate company',
        individuals: 'Individuals',
        otherBank: 'Other bank',
        chooseBank: 'Choose your bank',
        chooseAgent: 'Bank agent (optional)',
        register: 'Register & get a number',
        sending: 'Registering…',
        done: 'You are registered,',
        yourNumber: 'Your queue number',
        ahead: 'Ahead of you',
        approx: 'minutes approx.',
        sentTo: 'We sent your number on WhatsApp to',
        existing: 'You already have a number today',
        newCustomer: 'New registration',
        backIn: 'Screen resets in',
        seconds: 'seconds',
        close: 'Close',
        closed: 'Registration is available during working hours',
        welcome: 'Welcome',
        welcomeText: 'Register your details, get your number and follow your turn on the screen.',
        steps: ['Enter your details', 'Get your number on WhatsApp', 'Go to the desk when called'],
        errName: 'Enter your name (2 letters at least)',
        errMobile: 'Mobile is 9 digits starting with 5',
        errType: 'Choose buyer or seller',
        errVia: 'Choose bank or other',
        errBank: 'Choose your bank',
    },
};

const empty = () => ({ name: '', mobile: '', customer_type: '', via: '', bank_id: '', agent_id: '', via_detail: '' });

export default (config) => ({
    lang: 'ar',
    step: 'form',
    form: empty(),
    errors: {},
    error: '',
    result: null,
    countdown: 0,
    timer: null,
    idleTimer: null,
    banksOpen: false,
    status: config.status,

    init() {
        ['pointerdown', 'keydown'].forEach((evt) => window.addEventListener(evt, () => this.bumpIdle(), { passive: true }));
        setInterval(() => this.refreshStatus(), 20000);
        // تحديث الصفحة دوريًا وهي فارغة لتظهر تعديلات الإدارة (البنوك...)
        setInterval(() => {
            if (this.step === 'form' && !this.form.name && !this.form.mobile) window.location.reload();
        }, 30 * 60 * 1000);
        this.$watch('lang', (l) => {
            document.documentElement.lang = l;
            document.documentElement.dir = l === 'ar' ? 'rtl' : 'ltr';
            document.querySelectorAll('[data-clock]').forEach((el) => (el.dataset.clockLang = l));
            window.dispatchEvent(new Event('clock:tick'));
        });
    },

    t(key) {
        return T[this.lang][key] ?? T.ar[key] ?? key;
    },

    /** «6 دقائق» و«12 دقيقة» */
    minUnit(n) {
        if (this.lang === 'en') return 'min';
        return n >= 3 && n <= 10 ? 'دقائق' : 'دقيقة';
    },

    toggleLang() {
        this.lang = this.lang === 'ar' ? 'en' : 'ar';
    },

    get featured() {
        return config.featured;
    },

    get featuredIds() {
        return config.featured.map((b) => String(b.id));
    },

    /** البنك المختار من «بنك آخر» يظهر مكان الزر */
    get otherBankName() {
        if (!this.form.bank_id || this.featuredIds.includes(String(this.form.bank_id))) return '';
        return config.banks.find((b) => String(b.id) === String(this.form.bank_id))?.name ?? '';
    },

    /** وكلاء البنك المختار */
    get bankAgents() {
        return (config.agents || {})[this.form.bank_id] || [];
    },

    get mobileDigits() {
        return this.form.mobile.replace(/\D/g, '').replace(/^0/, '');
    },

    get ready() {
        return (
            this.status.open &&
            this.form.name.trim().length >= 2 &&
            /^5\d{8}$/.test(this.mobileDigits) &&
            this.form.customer_type !== '' &&
            (this.form.via === 'other' || (this.form.via === 'bank' && this.form.bank_id !== ''))
        );
    },

    setVia(v) {
        this.form.via = v;
        if (v === 'other') {
            this.form.bank_id = '';
            this.form.agent_id = '';
        } else {
            this.form.via_detail = '';
        }
        delete this.errors.via;
        delete this.errors.bank_id;
    },

    pickBank(id) {
        if (String(id) !== String(this.form.bank_id)) this.form.agent_id = '';
        this.form.bank_id = String(id);
        this.banksOpen = false;
        delete this.errors.bank_id;
    },

    /** الضغط مرة ثانية على الوكيل نفسه يلغي اختياره */
    pickAgent(id) {
        this.form.agent_id = String(this.form.agent_id) === String(id) ? '' : String(id);
        delete this.errors.agent_id;
    },

    onMobileInput(e) {
        let v = asciiDigits(e.target.value).replace(/\D/g, '').slice(0, 10);
        // عرض بالمسافات كما في التصميم: 05X XXX XXXX
        const groups = v.startsWith('0') ? [3, 3, 4] : [2, 3, 4];
        let out = '';
        let i = 0;
        for (const g of groups) {
            if (i >= v.length) break;
            out += (out ? ' ' : '') + v.slice(i, i + g);
            i += g;
        }
        this.form.mobile = out;
        e.target.value = out;
        delete this.errors.mobile;
    },

    async refreshStatus() {
        try {
            this.status = await api(config.stateUrl);
        } catch (e) {
            /* يبقى آخر رقم معروض */
        }
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
            this.result = await api(config.registerUrl, {
                method: 'POST',
                body: { ...this.form, mobile: this.mobileDigits },
            });
            this.step = 'ticket';
            this.startCountdown();
            this.refreshStatus();
        } catch (e) {
            this.step = 'form';
            this.errors = Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k, v[0]]));
            if (!Object.keys(this.errors).length) this.error = e.message;
        }
    },

    validateLocally() {
        if (!this.status.open) this.error = this.t('closed');
        if (this.form.name.trim().length < 2) this.errors.name = this.t('errName');
        if (!/^5\d{8}$/.test(this.mobileDigits)) this.errors.mobile = this.t('errMobile');
        if (!this.form.customer_type) this.errors.customer_type = this.t('errType');
        if (!this.form.via) this.errors.via = this.t('errVia');
        else if (this.form.via === 'bank' && !this.form.bank_id) this.errors.bank_id = this.t('errBank');
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
        // نموذج متروك نصف ممتلئ يُمسح بعد دقيقتين، وتعود الواجهة للعربي
        this.idleTimer = setTimeout(() => {
            if (this.step === 'form' && (this.form.name || this.form.mobile || this.lang !== 'ar')) this.reset();
        }, 120000);
    },

    reset() {
        clearInterval(this.timer);
        this.form = empty();
        this.errors = {};
        this.error = '';
        this.result = null;
        this.banksOpen = false;
        this.step = 'form';
        this.lang = 'ar';
        window.scrollTo({ top: 0 });
    },
});
