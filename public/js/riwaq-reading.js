/**
 * رِواق المعرفة — مؤقت القراءة العائم (يستمر بين الصفحات عبر localStorage)
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('rq-reading');
        if (!root) return;

        const $ = (id) => document.getElementById(id);
        const panel = $('rw-panel');
        const fab = $('rw-fab');
        const label = $('rw-fab-label');
        const timeEl = $('rw-time');
        const startBtn = $('rw-start');
        const pauseBtn = $('rw-pause');
        const finishBtn = $('rw-finish');
        const form = $('rw-form');
        const controls = $('rw-controls');
        const KEY = 'rq-reading-timer';
        const bookId = root.dataset.bookId || '';

        const load = () => {
            try { return JSON.parse(localStorage.getItem(KEY)) || null; } catch (e) { return null; }
        };
        const save = (s) => {
            try { s ? localStorage.setItem(KEY, JSON.stringify(s)) : localStorage.removeItem(KEY); } catch (e) { /* التخزين غير متاح */ }
        };

        let state = load();
        if (state && String(state.bookId) !== String(bookId)) { state = null; save(null); }

        const elapsed = () => (state ? state.acc + (state.running ? Date.now() - state.lastStart : 0) : 0);
        const fmt = (ms) => {
            const s = Math.floor(ms / 1000);
            const p = (n) => String(n).padStart(2, '0');
            return p(Math.floor(s / 3600)) + ':' + p(Math.floor((s % 3600) / 60)) + ':' + p(s % 60);
        };

        function render() {
            const running = !!(state && state.running);
            const started = !!state;
            if (timeEl) timeEl.textContent = fmt(elapsed());
            fab.classList.toggle('running', running);
            label.textContent = started ? fmt(elapsed()) : 'ابدأ القراءة';
            if (startBtn) {
                startBtn.hidden = running;
                startBtn.querySelector('span').textContent = started ? 'متابعة' : 'ابدأ القراءة';
            }
            if (pauseBtn) pauseBtn.hidden = !running;
            if (finishBtn) finishBtn.hidden = !started;
        }

        const setOpen = (open) => {
            panel.hidden = !open;
            fab.setAttribute('aria-expanded', String(open));
            if (open && window.lucide) window.lucide.createIcons();
        };

        fab.addEventListener('click', () => setOpen(panel.hidden));
        $('rw-close').addEventListener('click', () => setOpen(false));

        if (startBtn) {
            startBtn.addEventListener('click', () => {
                state = state || { bookId, acc: 0, running: false, lastStart: 0 };
                state.running = true;
                state.lastStart = Date.now();
                save(state);
                render();
            });
            pauseBtn.addEventListener('click', () => {
                state.acc = elapsed();
                state.running = false;
                save(state);
                render();
            });
            finishBtn.addEventListener('click', () => {
                state.acc = elapsed();
                state.running = false;
                save(state);
                const minutes = Math.max(1, Math.round(state.acc / 60000));
                $('rw-minutes').value = minutes;
                $('rw-sum-min').textContent = minutes;
                controls.hidden = true;
                form.hidden = false;
                render();
            });
            $('rw-discard').addEventListener('click', () => {
                state = null;
                save(null);
                form.hidden = true;
                controls.hidden = false;
                render();
            });
            form.addEventListener('submit', () => save(null));
        }

        render();
        try {
            if (sessionStorage.getItem('rq-open-reading')) {
                sessionStorage.removeItem('rq-open-reading');
                setOpen(true);
            }
        } catch (e) { /* غير متاح */ }
        // إن كان هناك مؤقت جارٍ، نُظهر الوقت على الزر مباشرة
        setInterval(() => { if (state) render(); }, 1000);
    });
})();
