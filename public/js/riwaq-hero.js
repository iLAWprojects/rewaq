/**
 * رِواق المعرفة — سلايدر الصفحة الرئيسية (Timed Cards):
 * الكتاب النشط يملأ الخلفية، وبطاقات الكتب التالية على الجانب.
 * عند الانتقال يظهر لون الكتاب الجديد مباشرة كخلفية من وراء البطاقات.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const hero = document.getElementById('rq-hero');
        const dataEl = document.getElementById('th-data');
        if (!hero || !dataEl) return;

        let items = [];
        try { items = JSON.parse(dataEl.textContent); } catch (e) { return; }
        if (!items.length) return;

        const $ = (id) => document.getElementById(id);
        const cardsBox = $('th-cards');
        const bgA = hero.querySelector('.th-bg-a');
        const bgB = hero.querySelector('.th-bg-b');
        const progress = $('th-progress');
        const lineFill = $('th-line-fill');
        const DURATION = 7000;
        const n = items.length;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        let current = 0;
        let busy = false;
        let activeBg = bgA;
        let cards = []; // [{el, idx}]
        let timer = null;
        let paused = false;
        let startedAt = 0;
        let remaining = DURATION;

        const pad = (i) => String(i + 1).padStart(2, '0');
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const ease = (t) => 1 - Math.pow(1 - t, 3);

        function bgStyle(it) {
            const base = it.color || '#315749';
            const img = it.cover ? 'url("' + it.cover + '") center/cover no-repeat, ' : '';
            return img + 'radial-gradient(ellipse 70% 80% at 22% 35%, rgba(255,255,255,.28), transparent 62%), linear-gradient(125deg, ' + base + ' 0%, color-mix(in srgb, ' + base + ' 62%, #081B18) 100%)';
        }

        function cardHTML(it) {
            return '<div class="bk-cover" style="--c:' + esc(it.color) + '"><span class="bk-frame"></span><span class="bk-knot">✦</span>' +
                '<strong>' + esc(it.title) + '</strong><small>' + esc(it.author) + '</small>' +
                (it.cover ? '<img src="' + esc(it.cover) + '" alt="">' : '') + '</div>';
        }

        // ---- الخلفية: لون الكتاب الجديد يظهر مباشرة من وراء البطاقات ----
        function setBg(it, instant) {
            const next = activeBg === bgA ? bgB : bgA;
            const prevLayer = activeBg;
            next.style.background = bgStyle(it);
            next.classList.toggle('has-img', !!it.cover);
            hero.style.setProperty('--c', it.color || '#315749');
            activeBg = next;

            if (instant || reduce) {
                next.classList.add('on');
                prevLayer.classList.remove('on', 'prev');
                return;
            }

            prevLayer.classList.remove('on');
            prevLayer.classList.add('prev');
            next.classList.add('on');
            setTimeout(() => prevLayer.classList.remove('prev'), 700);
        }

        function setText(it, animate) {
            $('th-kicker').textContent = (it.category || '') + ' · ' + (it.pages || '—') + ' صفحة';
            $('th-title').textContent = it.title || '';
            $('th-author').textContent = it.author || '';
            $('th-desc').textContent = it.desc || '';
            $('th-count').textContent = pad(current);
            if (animate && !reduce) {
                hero.classList.remove('th-in');
                void hero.offsetWidth;
                hero.classList.add('th-in');
            }
        }

        // ---- البطاقات: لا يُعاد بناؤها، بل تُزامَن (تُحذف المنتهية وتُضاف الجديدة) ----
        const nextIdxs = () => {
            const out = [];
            for (let k = 1; k < n; k++) out.push((current + k) % n);
            return out.slice(0, 5);
        };

        function makeCard(idx) {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'th-card';
            el.setAttribute('aria-label', items[idx].title);
            el.innerHTML = cardHTML(items[idx]);
            el.addEventListener('click', () => goTo(idx));
            cardsBox.appendChild(el);
            return { el, idx };
        }

        function syncCards(initial) {
            const wanted = nextIdxs();
            const byIdx = new Map(cards.map((c) => [c.idx, c]));
            cards.forEach((c) => {
                if (!wanted.includes(c.idx)) {
                    c.el.classList.add('leaving');
                    c.el.style.pointerEvents = 'none';
                    setTimeout(() => c.el.remove(), 600);
                }
            });
            cards = wanted.map((idx, pos) => {
                let c = byIdx.get(idx);
                if (!c) {
                    c = makeCard(idx);
                    c.el.style.animationDelay = (initial ? pos * 90 + 120 : 380) + 'ms';
                }
                c.el.style.setProperty('--p', pos);
                return c;
            });
        }

        // ---- التوقيت ----
        function runTimer(ms) {
            clearTimeout(timer);
            progress.style.transition = 'none';
            progress.style.transform = 'scaleX(' + (1 - ms / DURATION) + ')';
            lineFill.style.transition = 'none';
            lineFill.style.transform = 'scaleX(' + (1 - ms / DURATION) + ')';
            void progress.offsetWidth;
            if (reduce) return;
            progress.style.transition = 'transform ' + ms + 'ms linear';
            lineFill.style.transition = 'transform ' + ms + 'ms linear';
            progress.style.transform = 'scaleX(1)';
            lineFill.style.transform = 'scaleX(1)';
            startedAt = performance.now();
            remaining = ms;
            timer = setTimeout(() => next(), ms);
        }
        function pause() {
            if (paused || reduce) return;
            paused = true;
            clearTimeout(timer);
            const elapsed = performance.now() - startedAt;
            remaining = Math.max(300, remaining - elapsed);
            const frac = 1 - remaining / DURATION;
            progress.style.transition = 'none';
            lineFill.style.transition = 'none';
            progress.style.transform = 'scaleX(' + frac + ')';
            lineFill.style.transform = 'scaleX(' + frac + ')';
        }
        function resume() {
            if (!paused) return;
            paused = false;
            runTimer(remaining);
        }

        // ---- الانتقال ----
        function goTo(idx) {
            if (busy || idx === current) return;
            busy = true;
            clearTimeout(timer);
            const it = items[idx];

            current = idx;
            setBg(it, false);
            setText(it, true);
            syncCards(false);

            setTimeout(() => {
                busy = false;
                paused = false;
                runTimer(DURATION);
            }, reduce ? 0 : 650);
        }

        function next() { goTo((current + 1) % n); }
        function prev() { goTo((current - 1 + n) % n); }

        // ---- التهيئة ----
        setBg(items[0], true);
        setText(items[0], true);
        syncCards(true);

        $('cf-next').addEventListener('click', () => next());
        $('cf-prev').addEventListener('click', () => prev());

        const open = () => {
            const d = items[current];
            window.previewBook(d.id, d.title, d.author, d.category, d.pages, d.color, d.desc, d.copies, '', false, d.cover, !!d.inLibrary);
        };
        $('cf-open').addEventListener('click', open);
        $('cf-add').addEventListener('click', () => {
            const RQ = window.RQ || {};
            if (!RQ.auth) { window.location.href = RQ.loginUrl; return; }
            open();
        });

        hero.addEventListener('mouseenter', pause);
        hero.addEventListener('mouseleave', resume);
        hero.addEventListener('focusin', pause);
        hero.addEventListener('focusout', resume);
        document.addEventListener('visibilitychange', () => { document.hidden ? pause() : resume(); });

        // سحب باللمس
        let sx = null;
        hero.addEventListener('pointerdown', (e) => { sx = e.clientX; });
        hero.addEventListener('pointerup', (e) => {
            if (sx === null) return;
            const dx = e.clientX - sx;
            sx = null;
            if (Math.abs(dx) > 60) { dx < 0 ? next() : prev(); }
        });

        if (n > 1) runTimer(DURATION);
    });
})();
