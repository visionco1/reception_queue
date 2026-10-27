import { api } from './lib';

const LETTER_NAMES = {
    A: 'إيه', B: 'بي', C: 'سي', D: 'دي', E: 'إي', F: 'إف', G: 'جي', H: 'إتش', I: 'آي', J: 'جيه', K: 'كيه', L: 'إل', M: 'إم',
    N: 'إن', O: 'أو', P: 'بي', Q: 'كيو', R: 'آر', S: 'إس', T: 'تي', U: 'يو', V: 'في', W: 'دبليو', X: 'إكس', Y: 'واي', Z: 'زد',
};

/**
 * شاشة الانتظار: تستعلم عن آخر النداءات دوريًا، وعند نداء جديد تُظهر
 * حركة بسيطة وصوت تنبيه. تعيد التحميل تلقائيًا إذا غيّرت الإدارة إعدادات العرض.
 */
export default (config) => ({
    current: null,
    recent: [],
    waiting: 0,
    flash: false,
    offline: false,
    soundReady: false,
    now: new Date(),
    lastId: null,
    audio: null,

    init() {
        this.poll();
        setInterval(() => this.poll(), config.pollMs);
        setInterval(() => (this.now = new Date()), 1000);
        // بعض المتصفحات لا تسمح بالصوت قبل أول لمسة
        this.soundReady = !config.sound;
    },

    enableSound() {
        try {
            this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)();
            this.audio.resume();
        } catch (e) {}
        if (config.voice && 'speechSynthesis' in window) {
            window.speechSynthesis.speak(new SpeechSynthesisUtterance(''));
        }
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
            const incoming = data.current;
            const isNew = incoming && incoming.id !== this.lastId;
            const firstLoad = this.lastId === null;
            this.current = incoming;
            this.recent = data.recent;
            this.waiting = data.waiting;
            if (incoming) this.lastId = incoming.id;
            else if (firstLoad) this.lastId = 0;
            if (isNew && !firstLoad) this.announce(incoming);
        } catch (e) {
            this.offline = true;
        }
    },

    announce(call) {
        this.flash = false;
        requestAnimationFrame(() => (this.flash = true));
        setTimeout(() => (this.flash = false), 2600);
        if (config.sound) this.chime();
        if (config.voice) setTimeout(() => this.speak(call), 1300);
    },

    chime() {
        try {
            this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)();
            const ctx = this.audio;
            [[659.25, 0], [523.25, 0.55]].forEach(([freq, delay]) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                const t = ctx.currentTime + delay;
                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(0.5, t + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + 1.1);
                osc.connect(gain).connect(ctx.destination);
                osc.start(t);
                osc.stop(t + 1.2);
            });
        } catch (e) {}
    },

    spokenTicket(ticket) {
        const match = /^([A-Za-z]*)(\d+)$/.exec(ticket) || [];
        const letters = (match[1] || '').toUpperCase().split('').map((l) => LETTER_NAMES[l] || l).join(' ');
        const number = match[2] ? parseInt(match[2], 10) : ticket;
        return `${letters} ${number}`;
    },

    speak(call) {
        if (!('speechSynthesis' in window)) return;
        const tickets = (call.tickets || [call.ticket_no]).map((t) => this.spokenTicket(t));
        const label = tickets.length > 1 ? 'الأرقام' : 'الرقم';
        const u = new SpeechSynthesisUtterance(`${label} ${tickets.join(' و ')}، التوجه إلى مكتب ${call.desk}`);
        u.lang = 'ar-SA';
        u.rate = 0.9;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(u);
    },

    get clock() {
        return this.now.toLocaleTimeString('ar-SA-u-nu-latn', { hour: 'numeric', minute: '2-digit' });
    },

    get date() {
        return this.now.toLocaleDateString('ar-SA-u-nu-latn-ca-gregory', { weekday: 'long', day: 'numeric', month: 'long' });
    },
});
