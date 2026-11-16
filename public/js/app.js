/* تله‌پنل — اسکریپت عمومی (بدون وابستگی و بدون مرحله‌ی build) */
(function () {
    'use strict';
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const csrf = () => ($('meta[name=csrf-token]') || {}).content || '';
    const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

    function toast(msg, type) {
        const box = $('#toasts');
        if (!box || !msg) return;
        const t = document.createElement('div');
        t.className = 'toast ' + (type || '');
        t.textContent = msg;
        box.appendChild(t);
        setTimeout(() => t.remove(), 4200);
    }

    async function post(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data || {}),
            credentials: 'same-origin',
        });
        let json = {};
        try { json = await res.json(); } catch (e) { json = { ok: false, message: 'پاسخ نامعتبر از سرور (' + res.status + ')' }; }
        if (res.status === 419) json = { ok: false, message: 'نشست منقضی شده؛ صفحه را دوباره باز کنید.' };
        if (json.errors) json.message = Object.values(json.errors)[0][0];
        json.status = res.status;
        return json;
    }

    // ---------- منوی موبایل / دیالوگ‌ها / تأیید ----------
    document.addEventListener('click', (e) => {
        const burger = e.target.closest('[data-burger]');
        if (burger) { $('#side').classList.toggle('open'); return; }
        if ($('#side') && $('#side').classList.contains('open') && !e.target.closest('#side')) $('#side').classList.remove('open');

        const open = e.target.closest('[data-open]');
        if (open) { const d = $(open.dataset.open); d && d.showModal(); }
        const close = e.target.closest('[data-close]');
        if (close) close.closest('dialog').close();

        const reveal = e.target.closest('[data-reveal]');
        if (reveal) { const i = reveal.previousElementSibling; i.type = i.type === 'password' ? 'text' : 'password'; }

        const conf = e.target.closest('button[data-confirm]:not([data-action])');
        if (conf && !confirm(conf.dataset.confirm)) e.preventDefault();
    });

    // ---------- دکمه‌ی روشن/خاموش ----------
    function bindPower(root) {
        $$('[data-power]', root).forEach((wrap) => {
            const btn = $('[data-power-btn]', wrap);
            btn.addEventListener('click', async () => {
                const on = wrap.dataset.on !== '1';
                btn.classList.add('is-loading');
                btn.disabled = true;
                const r = await post(wrap.dataset.url, { on });
                btn.classList.remove('is-loading');
                btn.disabled = false;
                if (!r.ok) return toast(r.message, 'err');
                setPower(wrap, r.active);
                toast(r.message, r.active ? 'ok' : '');
                sync.setActive(r.active);
                if (r.active) setTimeout(() => sync.run(true), 600);
            });
        });
    }
    function setPower(wrap, on) {
        const btn = $('[data-power-btn]', wrap);
        wrap.dataset.on = on ? '1' : '0';
        btn.classList.toggle('is-on', on);
        btn.classList.toggle('is-off', !on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        $('[data-power-label]', wrap).textContent = on ? 'روشن — در حال دریافت پیام‌ها' : 'خاموش';
        $('[data-power-sub]', wrap).textContent = on ? 'در انتظار اولین دریافت…' : 'با زدن دکمه، از همین لحظه پیام‌های جدید ذخیره می‌شوند.';
        $$('.nav .dot').forEach((d) => { d.classList.toggle('is-on', on); d.classList.toggle('is-off', !on); });
        $$('[data-sync-now]').forEach((b) => (b.disabled = !on));
    }

    // ---------- همگام‌سازی کوتاه از مرورگر (وقتی پنل باز است) ----------
    // هر درخواست یک اجرای کوتاه روی سرور است: وصل می‌شود، پیام‌های جدید را می‌گیرد، قطع می‌شود.
    const sync = (function () {
        const body = document.body;
        const url = body.dataset.syncUrl;
        const every = Math.max(15, parseInt(body.dataset.syncInterval || '30', 10)) * 1000;
        let active = body.dataset.syncActive === '1';
        let timer = null;
        let running = false;
        const pill = $('[data-live-pill]');

        function schedule(ms) {
            clearTimeout(timer);
            if (active && url) timer = setTimeout(() => run(false), ms);
        }
        async function run(manual) {
            if (!url || running || (!manual && document.hidden)) return schedule(every);
            running = true;
            if (pill) pill.hidden = false;
            const r = await post(url, {});
            running = false;
            if (pill) pill.hidden = true;
            if (r.total !== undefined) $$('[data-stat=total]').forEach((el) => (el.textContent = Number(r.total).toLocaleString('en')));
            if (r.filtered !== undefined) $$('[data-stat=filtered]').forEach((el) => (el.textContent = Number(r.filtered).toLocaleString('en')));
            $$('[data-power-sub]').forEach((el) => { if (r.active) el.textContent = 'آخرین دریافت: ' + (r.last_sync || 'هنوز نه'); });
            if (r.active === false && active) { active = false; $$('[data-power]').forEach((w) => setPower(w, false)); }
            if (manual) toast(r.message, r.ok ? 'ok' : 'err');
            else if (r.saved > 0) toast(fa(r.saved) + ' پیام جدید دریافت شد', 'ok');
            // اگر زمان دور کافی نبود (پیام‌های زیاد) زودتر ادامه بده
            schedule(r.complete === false ? 3000 : every);
        }
        function setActive(on) { active = on; schedule(every); }
        document.addEventListener('visibilitychange', () => { if (!document.hidden && active) schedule(1500); });
        schedule(4000);
        return { run, setActive };
    })();

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sync-now]')) sync.run(true);
    });

    // ---------- ویزارد اتصال ----------
    function wizard(root) {
        const urls = JSON.parse(root.dataset.urls);
        const busy = $('[data-busy]', root);
        let step = parseInt(root.dataset.step, 10) || 1;

        function go(n) {
            step = n;
            $$('.steps li', root).forEach((li) => {
                const s = parseInt(li.dataset.s, 10);
                li.classList.toggle('done', s < n || (n === 5 && s === 5));
                li.classList.toggle('cur', s === n && n !== 5);
            });
            $$('.pane', root).forEach((p) => p.classList.toggle('on', parseInt(p.dataset.pane, 10) === n));
            root.querySelector('.sky').style.setProperty('--p', ((n - 1) / 4 * 92 + 4) + '%');
            const first = $('.pane.on input:not([type=hidden])', root);
            if (first) setTimeout(() => first.focus(), 350);
        }
        async function call(name, data, text) {
            $('[data-busy-text]', busy).textContent = text || 'در حال ارتباط با تلگرام…';
            busy.hidden = false;
            try { return await post(urls[name], data); } finally { busy.hidden = true; }
        }

        // OTP: پر کردن خانه‌ها، جابه‌جایی خودکار، چسباندن کل کد
        const otp = $('[data-otp]', root);
        const cells = $$('input', otp);
        const toEn = (v) => v.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '');
        cells.forEach((c, i) => {
            c.addEventListener('input', () => {
                const v = toEn(c.value);
                if (v.length > 1) { fill(v); return; }
                c.value = v;
                if (v && cells[i + 1]) cells[i + 1].focus();
                if (cells.every((x) => x.value)) $('[data-form=verify]', root).requestSubmit();
            });
            c.addEventListener('keydown', (e) => { if (e.key === 'Backspace' && !c.value && cells[i - 1]) cells[i - 1].focus(); });
            c.addEventListener('paste', (e) => { e.preventDefault(); fill(toEn((e.clipboardData || window.clipboardData).getData('text'))); });
        });
        function fill(v) {
            cells.forEach((x, j) => (x.value = v[j] || ''));
            (cells[Math.min(v.length, cells.length - 1)]).focus();
            if (v.length >= cells.length) $('[data-form=verify]', root).requestSubmit();
        }

        root.addEventListener('submit', async (e) => {
            const f = e.target.closest('[data-form]');
            if (!f) return;
            e.preventDefault();
            const data = Object.fromEntries(new FormData(f));
            const name = f.dataset.form;
            let r;
            if (name === 'credentials') {
                r = await call('credentials', data, 'در حال ذخیره…');
                if (r.ok) { toast(r.message, 'ok'); go(r.step || 2); }
            } else if (name === 'code') {
                r = await call('code', data, 'در حال درخواست کد از تلگرام…');
                if (r.ok) {
                    toast(r.message, 'ok');
                    $('[data-phone-label]', root).textContent = r.phone || data.phone;
                    cells.forEach((x) => (x.value = ''));
                    go(3);
                }
            } else if (name === 'verify') {
                data.code = cells.map((x) => x.value).join('');
                r = await call('verify', data, 'در حال بررسی کد…');
                if (r.ok && r.step === 4) {
                    $('[data-hint]', root).textContent = r.hint ? 'راهنما: «' + r.hint + '»' : '';
                    go(4);
                } else if (r.ok) {
                    return finish(r);
                } else {
                    otp.classList.remove('shake'); void otp.offsetWidth; otp.classList.add('shake');
                    cells.forEach((x) => (x.value = '')); cells[0].focus();
                }
            } else if (name === 'password') {
                r = await call('password', data, 'در حال ورود…');
                if (r.ok) return finish(r);
            }
            if (r && !r.ok) toast(r.message, 'err');
        });

        function finish(r) {
            toast(r.message, 'ok');
            go(5);
            // صفحه دوباره بارگذاری می‌شود تا کلید روشن/خاموش با وضعیت واقعی نمایش داده شود
            setTimeout(() => location.reload(), 1600);
        }

        root.addEventListener('click', async (e) => {
            const g = e.target.closest('[data-go]');
            if (g) return go(parseInt(g.dataset.go, 10));
            const a = e.target.closest('[data-action]');
            if (!a) return;
            if (a.dataset.confirm && !confirm(a.dataset.confirm)) return;
            const r = await call(a.dataset.action, {}, 'در حال انجام…');
            toast(r.message, r.ok ? 'ok' : 'err');
            if (r.ok) setTimeout(() => location.reload(), 800);
        });

        go(step);
    }

    // ---------- چراغ اتصال به تلگرام (سایدبار) ----------
    (function () {
        const btn = $('[data-conn]');
        if (!btn) return;
        let timer = null;
        let busy = false;
        function show(r) {
            btn.className = 'conn is-' + (r.state || 'none');
            $('[data-conn-label]', btn).textContent = r.label || '';
            btn.title = r.title || '';
        }
        async function check(force) {
            if (busy) return;
            if (!force && document.hidden) { clearTimeout(timer); return; } // در تب پنهان بررسی نمی‌کنیم
            busy = true;
            if (force) show({ state: 'checking', label: 'بررسی اتصال…', title: btn.title });
            try {
                const res = await fetch(btn.dataset.url + (force ? '?force=1' : ''), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (res.ok) {
                    const r = await res.json();
                    show(r);
                    if (r.state === 'none') return; // بدون کلید، بررسی دوره‌ای لازم نیست
                    // اگر بررسی دیگری در جریان بود کمی بعد دوباره بپرس
                    clearTimeout(timer);
                    timer = setTimeout(() => check(false), r.checking || r.state === 'checking' ? 5000 : 60000);
                    return;
                }
            } catch (e) { /* شبکه‌ی مرورگر قطع است؛ دور بعد */ }
            finally { busy = false; }
            clearTimeout(timer);
            timer = setTimeout(() => check(false), 60000);
        }
        btn.addEventListener('click', () => check(true));
        document.addEventListener('visibilitychange', () => { if (!document.hidden) check(false); });
        check(false);
    })();

    bindPower(document);
    $$('[data-wizard]').forEach(wizard);
})();
