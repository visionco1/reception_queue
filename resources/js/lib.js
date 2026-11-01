// أدوات مشتركة: طلبات JSON مع CSRF، وتنسيق المدد بالعربية

export async function api(url, { method = 'GET', body = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) headers['X-CSRF-TOKEN'] = token;
    if (body !== null) headers['Content-Type'] = 'application/json';

    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            body: body !== null ? JSON.stringify(body) : null,
            credentials: 'same-origin',
            cache: 'no-store',
        });
    } catch (e) {
        const err = new Error('تعذر الاتصال بالخادم. تحقق من الشبكة وحاول مرة أخرى.');
        err.network = true;
        throw err;
    }

    // انتهت الجلسة: نعيد تحميل الصفحة لتظهر شاشة الدخول
    if (response.status === 401 || response.status === 419) {
        window.location.reload();
        throw new Error('انتهت الجلسة');
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const err = new Error(data.message || 'حدث خطأ غير متوقع. حاول مرة أخرى.');
        err.status = response.status;
        err.errors = data.errors || {};
        throw err;
    }
    return data;
}

export function minutesLabel(m) {
    if (m === 1) return 'دقيقة';
    if (m === 2) return 'دقيقتان';
    if (m >= 3 && m <= 10) return `${m} دقائق`;
    return `${m} دقيقة`;
}

/** "8 دقائق" — مطابقة لتنسيق الخادم */
export function humanDuration(seconds) {
    if (seconds == null) return '—';
    if (seconds < 60) return 'أقل من دقيقة';
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours === 0) return minutesLabel(minutes);
    const h = hours === 1 ? 'ساعة' : hours === 2 ? 'ساعتان' : `${hours} ساعات`;
    return rest ? `${h} و ${minutesLabel(rest)}` : h;
}

/** عداد بصيغة 12:05 */
export function clockDuration(seconds) {
    if (seconds == null || seconds < 0) seconds = 0;
    const m = Math.floor(seconds / 60);
    const s = String(seconds % 60).padStart(2, '0');
    if (m >= 60) return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}:${s}`;
    return `${m}:${s}`;
}

/** تحويل الأرقام العربية/الفارسية إلى أرقام لاتينية أثناء الكتابة */
export function asciiDigits(value) {
    return String(value ?? '').replace(/[٠-٩]/g, (d) => d.charCodeAt(0) - 1632).replace(/[۰-۹]/g, (d) => d.charCodeAt(0) - 1776);
}

/** "زيارة واحدة"، "زيارتان"، "5 زيارات"، "12 زيارة" */
export function visitsLabel(n) {
    if (n === 1) return 'زيارة واحدة';
    if (n === 2) return 'زيارتان';
    if (n >= 3 && n <= 10) return `${n} زيارات`;
    return `${n} زيارة`;
}

const tz = () => document.querySelector('meta[name="app-tz"]')?.content || undefined;

/** الساعة «10:24» ولاحقتها «ص/م» بتوقيت النظام */
export function clockParts(date = new Date(), lang = 'ar') {
    const parts = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: tz() }).formatToParts(date);
    const get = (t) => parts.find((p) => p.type === t)?.value ?? '';
    const pm = get('dayPeriod').toUpperCase() === 'PM';
    return { time: `${get('hour')}:${get('minute')}`, suffix: lang === 'en' ? (pm ? 'PM' : 'AM') : pm ? 'م' : 'ص' };
}

/** «السبت 31 أكتوبر 2026» */
export function gregorianDate(date = new Date(), lang = 'ar') {
    if (lang === 'en') {
        return new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: tz() }).format(date);
    }
    return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: tz() })
        .format(date)
        .replace('،', '');
}

/** التاريخ الهجري بتقويم أم القرى: «20 جمادى الأولى 1448هـ» */
export function hijriDate(date = new Date(), lang = 'ar') {
    try {
        const locale = lang === 'en' ? 'en-u-ca-islamic-umalqura-nu-latn' : 'ar-SA-u-ca-islamic-umalqura-nu-latn';
        const parts = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', year: 'numeric', timeZone: tz() }).formatToParts(date);
        const get = (t) => parts.find((p) => p.type === t)?.value ?? '';
        return lang === 'en' ? `${get('day')} ${get('month')} ${get('year')} AH` : `${get('day')} ${get('month')} ${get('year')}هـ`;
    } catch (e) {
        return '';
    }
}

/** تحديث كل عناصر الساعة في الصفحة: data-clock-time / data-clock-suffix / data-clock-date / data-clock-hijri */
export function startClocks(root = document) {
    const tick = () => {
        const now = new Date();
        root.querySelectorAll('[data-clock]').forEach((el) => {
            const lang = el.dataset.clockLang || 'ar';
            const { time, suffix } = clockParts(now, lang);
            el.querySelectorAll('[data-clock-time]').forEach((n) => (n.textContent = time));
            el.querySelectorAll('[data-clock-suffix]').forEach((n) => (n.textContent = suffix));
            el.querySelectorAll('[data-clock-date]').forEach((n) => (n.textContent = gregorianDate(now, lang)));
            el.querySelectorAll('[data-clock-hijri]').forEach((n) => (n.textContent = hijriDate(now, lang)));
        });
    };
    tick();
    window.addEventListener('clock:tick', tick);
    // التحديث مع بداية كل دقيقة
    setTimeout(() => {
        tick();
        setInterval(tick, 60_000);
    }, (60 - new Date().getSeconds()) * 1000 + 50);
}

/** «د» مختصرة للدقائق في الجداول */
export function minutesSince(iso, now = Date.now()) {
    return Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60000));
}

/** لون مدة الانتظار حسب الدليل: أخضر < 10 د، أصفر 10–20 د، أحمر > 20 د */
export function waitClass(minutes) {
    if (minutes > 20) return 'wait-late';
    if (minutes >= 10) return 'wait-mid';
    return 'wait-ok';
}
