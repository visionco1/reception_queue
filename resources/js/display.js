import { api } from './lib';

const LETTER_NAMES = {
    A: 'إيه', B: 'بي', C: 'سي', D: 'دي', E: 'إي', F: 'إف', G: 'جي', H: 'إتش', I: 'آي', J: 'جيه', K: 'كيه', L: 'إل', M: 'إم',
    N: 'إن', O: 'أو', P: 'بي', Q: 'كيو', R: 'آر', S: 'إس', T: 'تي', U: 'يو', V: 'في', W: 'دبليو', X: 'إكس', Y: 'واي', Z: 'زد',
};

/**
 * شاشة العرض (الشاشة 02) بثلاث حالات:
 * - idle: ترحيب بالشعار وخطوات الخدمة بدون جدول. عند التشغيل، وخارج الدوام،
 *   وبعد دقائق بدون نداء إذا لم يكن أحد في الانتظار.
 * - alert: نداء جديد أو إعادة نداء — جرس ونطق، الرقم يومض ويتوهج لعدة ثوانٍ.
 * - active: الرقم الحالي يمين وجدول النداءات السابقة يسار.
 * تعيد التحميل إذا غيّرت الإدارة الإعدادات.
 */
export default (config) => ({
    data: null,
    view: 'idle',
    offline: false,
    soundReady: false,
    lastId: null,
    audio: null,
    offset: 0,
    zoomKey: 0,
    alertUntil: 0,
    tickerKey: '',

    init() {
        this.poll();
        setInterval(() => this.poll(), config.pollMs);
        setInterval(() => this.updateView(), 1000);
        this.soundReady = false;
    },

    get current() {
        return this.data?.current ?? null;
    },

    get recent() {
        return this.data?.recent ?? [];
    },

    get waiting() {
        return this.data?.waiting ?? { total: 0, buyer: 0, seller: 0, minutes: 0 };
    },

    get offices() {
        return this.data?.offices ?? [];
    },

    get ticker() {
        return this.data?.ticker ?? [];
    },

    get needsTap() {
        return !this.soundReady && (this.data?.sound || this.data?.voice);
    },

    enableSound() {
        try {
            this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)();
            this.audio.resume();
        } catch (e) {}
        if ('speechSynthesis' in window) window.speechSynthesis.speak(new SpeechSynthesisUtterance(''));
        this.soundReady = true;
    },

    async poll() {
        try {
            const data = await api(config.feedUrl);
            this.offline = false;
            if (data.version !== config.version) {
                window.location.reload();
                return;
            }
            this.offset = new Date(data.server_time).getTime() - Date.now();
            const incoming = data.current;
            const firstLoad = this.lastId === null;
            const isNew = incoming && incoming.id !== this.lastId;
            this.data = data;
            const key = (data.ticker || []).join('|');
            if (key !== this.tickerKey) this.tickerKey = key;
            if (incoming) this.lastId = incoming.id;
            else if (firstLoad) this.lastId = 0;
            if (isNew && !firstLoad) this.announce(incoming);
            else this.updateView();
        } catch (e) {
            this.offline = true;
        }
    },

    /** idle | alert | active (أو offices في وضع لوحة المكاتب) */
    updateView() {
        if (!this.data) return;
        const call = this.current;
        const age = call ? (Date.now() + this.offset - new Date(call.at).getTime()) / 1000 : Infinity;
        const quiet = age > (this.data.idle_minutes || 10) * 60 && this.waiting.total === 0;
        if (!this.data.open || !call || quiet) {
            this.view = 'idle';
        } else if (this.data.mode === 'offices') {
            this.view = 'offices';
        } else {
            this.view = Date.now() < this.alertUntil ? 'alert' : 'active';
        }
    },

    get showTable() {
        return this.view === 'alert' || this.view === 'active';
    },

    announce(call) {
        // نداء جديد أثناء التنبيه يبدأ العد من جديد
        this.alertUntil = Date.now() + (this.data?.alert_seconds || 10) * 1000;
        this.updateView();
        this.zoomKey++;
        if (this.data?.sound) this.chime();
        if (this.data?.voice) setTimeout(() => this.speak(call), 1300);
    },

    chime() {
        try {
            this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)();
            const ctx = this.audio;
            const volume = (this.data?.volume ?? 80) / 100;
            if (volume <= 0) return;
            [[659.25, 0], [523.25, 0.55]].forEach(([freq, delay]) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                const t = ctx.currentTime + delay;
                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(Math.max(0.0002, 0.6 * volume), t + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + 1.1);
                osc.connect(gain).connect(ctx.destination);
                osc.start(t);
                osc.stop(t + 1.2);
            });
        } catch (e) {}
    },

    /** «M-014» تُنطق «إم 14» */
    spokenTicket(ticket) {
        const match = /^([A-Za-z]*)-?(\d+)$/.exec(ticket) || [];
        const letters = (match[1] || '').toUpperCase().split('').map((l) => LETTER_NAMES[l] || l).join(' ');
        const number = match[2] ? parseInt(match[2], 10) : ticket;
        return `${letters} ${number}`;
    },

    speak(call) {
        if (!('speechSynthesis' in window)) return;
        const tickets = (call.tickets || []).map((t) => this.spokenTicket(t));
        const text = tickets.length > 1
            ? `الأرقام ${tickets.join(' و ')}، تفضلوا إلى مكتب ${call.desk}`
            : `الرقم ${tickets[0]}، تفضل إلى مكتب ${call.desk}`;
        const u = new SpeechSynthesisUtterance(text);
        u.volume = Math.min(1, (this.data?.volume ?? 80) / 100);
        u.lang = 'ar-SA';
        u.rate = 0.9;
        const voice = window.speechSynthesis.getVoices().find((v) => v.lang?.startsWith('ar'));
        if (voice) u.voice = voice;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(u);
    },

    timeOf(iso) {
        return new Intl.DateTimeFormat('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
            timeZone: document.querySelector('meta[name="app-tz"]')?.content || undefined,
        })
            .format(new Date(iso))
            .replace(/\s?[ap]m/i, '');
    },
});
