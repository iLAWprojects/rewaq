/**
 * رِواق المعرفة — سلوك الواجهة الجديدة: نافذة تفاصيل الكتاب + سلايدر الغلاف (Coverflow)
 */
(function () {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const refreshIcons = () => window.lucide && window.lucide.createIcons();

    // ---------- Sidebar (mobile) ----------
    document.addEventListener('DOMContentLoaded', () => {
        const side = $('riwaq-sidebar');
        const scrim = $('rq-scrim');
        if (side && scrim) {
            scrim.addEventListener('click', () => {
                side.classList.remove('open');
                scrim.classList.remove('show');
            });
            const toggle = $('menu-toggle');
            if (toggle) {
                toggle.addEventListener('click', () => {
                    scrim.classList.toggle('show', side.classList.contains('open'));
                });
            }
        }

        // إغلاق قائمة المستخدم عند النقر خارجها
        document.addEventListener('click', (e) => {
            document.querySelectorAll('details.rq-user[open]').forEach((d) => {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
    });


    // ---------- Bottom nav (الدائرة المنزلقة) ----------
    document.addEventListener('DOMContentLoaded', () => {
        const bn = $('rq-bottom-nav');
        if (!bn) return;
        const items = Array.from(bn.querySelectorAll('.bn-item'));
        const target = parseInt(getComputedStyle(bn).getPropertyValue('--i'), 10) || 0;

        // عند فتح صفحة جديدة: نبدأ من موضع الصفحة السابقة لتنزلق الدائرة إلى مكانها
        try {
            const prev = sessionStorage.getItem('rq-bn-prev');
            if (prev !== null && !bn.classList.contains('bn-none') && parseInt(prev, 10) !== target) {
                bn.style.transition = 'none';
                bn.style.setProperty('--i', prev);
                bn.getBoundingClientRect();
                bn.style.transition = '';
                requestAnimationFrame(() => bn.style.setProperty('--i', String(target)));
            }
            sessionStorage.setItem('rq-bn-prev', String(target));
        } catch (e) { /* غير متاح */ }

        items.forEach((a, idx) => {
            a.addEventListener('click', (e) => {
                if (a.hasAttribute('data-notif-open') || a.classList.contains('on') || e.metaKey || e.ctrlKey) return;
                e.preventDefault();
                bn.classList.remove('bn-none');
                bn.style.setProperty('--i', String(idx));
                items.forEach((x) => x.classList.toggle('on', x === a));
                try { sessionStorage.setItem('rq-bn-prev', String(idx)); } catch (err) { /* غير متاح */ }
                setTimeout(() => { window.location.href = a.href; }, 280);
            });
        });
    });

    // ---------- Smart notifications panel (قائمة الإشعارات الذكية) ----------
    document.addEventListener('DOMContentLoaded', () => {
        const panel = $('rq-notif-panel');
        if (!panel) return;
        const scrim = $('np-scrim');
        const list = $('np-list');
        const triggers = document.querySelectorAll('[data-notif-open]');
        const csrf = (window.RQ || {}).csrf;
        let loading = false;

        const setCount = (n) => {
            document.querySelectorAll('[data-notif-count]').forEach((el) => {
                el.hidden = n < 1;
                el.textContent = n > 99 ? '99+' : String(n);
            });
        };
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        const render = (items) => {
            if (!items.length) {
                list.innerHTML = '<p class="np-empty">لا توجد إشعارات بعد.</p>';
                return;
            }
            list.innerHTML = items.map((n) => {
                const readClass = n.is_read ? ' is-read' : ' new';
                let actionsHtml = '';
                if (n.actions && n.actions.type === 'join_request') {
                    actionsHtml = '<div class="np-actions" id="notif-actions-' + n.id + '">' +
                        '<button type="button" class="np-btn-approve" data-url="' + esc(n.actions.approve_url) + '" data-notif-id="' + n.id + '">' +
                            '<i data-lucide="check" style="width:12px;height:12px;"></i><span>قبول</span>' +
                        '</button>' +
                        '<button type="button" class="np-btn-reject" data-url="' + esc(n.actions.reject_url) + '" data-notif-id="' + n.id + '">' +
                            '<i data-lucide="x" style="width:12px;height:12px;"></i><span>رفض</span>' +
                        '</button>' +
                    '</div>';
                }

                return '<div class="np-item' + readClass + '" data-notif-id="' + n.id + '" data-link="' + esc(n.link || '') + '">' +
                    '<span class="np-ico"><i data-lucide="' + esc(n.icon) + '"></i></span>' +
                    '<div class="np-body">' +
                        '<b>' + esc(n.title) + '</b>' +
                        '<small>' + esc(n.body) + '</small>' +
                        actionsHtml +
                        '<em>' + esc(n.time) + '</em>' +
                    '</div>' +
                '</div>';
            }).join('');
            refreshIcons();
            attachItemEvents();
        };

        const markSingleRead = async (id, itemEl) => {
            if (!itemEl || !itemEl.classList.contains('new')) return;
            itemEl.classList.remove('new');
            itemEl.classList.add('is-read');

            // Decrement unread counter in DOM
            document.querySelectorAll('[data-notif-count]').forEach((el) => {
                let current = parseInt(el.textContent.replace('+', '') || '0');
                if (current > 0) {
                    const next = current - 1;
                    el.hidden = next < 1;
                    el.textContent = next > 99 ? '99+' : String(next);
                }
            });

            try {
                const res = await fetch('/notifications/' + id + '/read', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                const data = await res.json();
                if (typeof data.unread_count === 'number') {
                    setCount(data.unread_count);
                }
            } catch (err) {
                console.warn('Mark read failed', err);
            }
        };

        const attachItemEvents = () => {
            // Action buttons (Approve / Reject)
            list.querySelectorAll('.np-btn-approve, .np-btn-reject').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    const url = btn.dataset.url;
                    const notifId = btn.dataset.notifId;
                    const isApprove = btn.classList.contains('np-btn-approve');
                    const actionsBox = document.getElementById('notif-actions-' + notifId);
                    const itemEl = btn.closest('.np-item');

                    btn.disabled = true;
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        });
                        const data = await res.json();
                        if (data.success) {
                            if (actionsBox) {
                                actionsBox.innerHTML = isApprove
                                    ? '<span class="np-status-tag approved"><i data-lucide="check"></i> تم قبول العضوية</span>'
                                    : '<span class="np-status-tag rejected"><i data-lucide="x"></i> تم رفض الطلب</span>';
                                refreshIcons();
                            }
                            if (itemEl) markSingleRead(notifId, itemEl);
                            if (window.showToast) window.showToast(data.message || (isApprove ? 'تم قبول الطلب بنجاح' : 'تم رفض الطلب'));
                        } else {
                            alert(data.message || 'تعذر معالجة الطلب.');
                            btn.disabled = false;
                        }
                    } catch (err) {
                        console.error('Request failed', err);
                        alert('حدث خطأ أثناء معالجة الطلب.');
                        btn.disabled = false;
                    }
                });
            });

            // Card click (Navigate & Mark Read)
            list.querySelectorAll('.np-item').forEach((item) => {
                item.addEventListener('click', (e) => {
                    if (e.target.closest('button, a')) return;
                    const notifId = item.dataset.notifId;
                    const link = item.dataset.link;
                    markSingleRead(notifId, item);
                    if (link && link !== '#') {
                        close();
                        window.location.href = link;
                    }
                });
            });
        };

        const open = async () => {
            panel.hidden = false;
            scrim.hidden = false;
            triggers.forEach((t) => t.setAttribute('aria-expanded', 'true'));
            if (loading) return;
            loading = true;
            try {
                const res = await fetch(panel.dataset.feedUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await res.json();
                render(data.items);
                setCount(data.unread_count);
            } catch (e) {
                list.innerHTML = '<p class="np-empty">تعذر تحميل الإشعارات الآن.</p>';
            } finally {
                loading = false;
            }
        };
        const close = () => {
            panel.hidden = true;
            scrim.hidden = true;
            triggers.forEach((t) => t.setAttribute('aria-expanded', 'false'));
        };

        triggers.forEach((t) => t.addEventListener('click', (e) => {
            e.preventDefault();
            panel.hidden ? open() : close();
        }));
        scrim.addEventListener('click', close);
        panel.querySelectorAll('[data-notif-close]').forEach((b) => b.addEventListener('click', close));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) close(); });
    });

    // ---------- Search sheet (البحث الشامل على الموبايل) ----------
    document.addEventListener('DOMContentLoaded', () => {
        const sheet = $('rq-search-sheet');
        const openBtn = $('rq-search-open');
        if (!sheet || !openBtn) return;
        const input = $('rq-search-input');
        const setOpen = (open) => {
            sheet.hidden = !open;
            document.body.style.overflow = open ? 'hidden' : '';
            if (open) {
                refreshIcons();
                setTimeout(() => input.focus(), 60);
            }
        };
        openBtn.addEventListener('click', () => setOpen(true));
        sheet.querySelectorAll('[data-ss-close]').forEach((el) => el.addEventListener('click', () => setOpen(false)));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !sheet.hidden) setOpen(false); });
    });

    // ---------- Report modal (الإبلاغ عن محتوى مخالف) ----------
    const REPORT_REASONS = {
        book: [['inappropriate', 'محتوى غير لائق أو مخالف'], ['copyright', 'انتهاك حقوق النشر'], ['misinformation', 'معلومات مضللة أو خاطئة'], ['spam', 'إعلان أو محتوى متكرر (Spam)'], ['other', 'سبب آخر']],
        default: [['inappropriate', 'محتوى غير لائق أو مخالف'], ['spoiler', 'حرق أحداث غير معلن (Spoiler)'], ['disrespect', 'إساءة أو تنمر'], ['spam', 'إعلان أو محتوى متكرر (Spam)'], ['other', 'سبب آخر']],
    };
    const TYPE_LABEL = { book: 'الكتاب', review: 'المراجعة', reflection: 'الاقتباس', user: 'الحساب', discussion: 'المناقشة', comment: 'التعليق' };

    window.openReportModal = function (type, id, title) {
        const RQ = window.RQ || {};
        if (!RQ.auth) { window.location.href = RQ.loginUrl; return; }
        const box = $('rq-report-modal');
        if (!box) return;
        $('rq-rp-type').value = type;
        $('rq-rp-id').value = id;
        $('rq-rp-title').textContent = 'الإبلاغ عن ' + (TYPE_LABEL[type] || 'محتوى');
        $('rq-rp-target').textContent = title || '';
        const list = REPORT_REASONS[type] || REPORT_REASONS.default;
        $('rq-rp-reasons').innerHTML = '<legend>سبب البلاغ</legend>' + list.map((r, i) =>
            '<label><input type="radio" name="reason" value="' + r[0] + '"' + (i === 0 ? ' required' : '') + '><span>' + r[1] + '</span></label>').join('');
        box.classList.add('open');
        document.body.style.overflow = 'hidden';
        refreshIcons();
    };
    document.addEventListener('DOMContentLoaded', () => {
        const box = $('rq-report-modal');
        if (box) {
            const close = () => { box.classList.remove('open'); document.body.style.overflow = ''; };
            box.querySelectorAll('[data-rq-report-close]').forEach((el) => el.addEventListener('click', close));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
        }
        document.querySelectorAll('[data-report-type]').forEach((btn) => {
            btn.addEventListener('click', () => window.openReportModal(btn.dataset.reportType, btn.dataset.reportId, btn.dataset.reportTitle));
        });
    });

    // ---------- Book modal ----------
    const modal = $('rq-book-modal');

    function setCover(color, title, author, cover) {
        const box = $('rq-bm-cover');
        box.style.setProperty('--c', color || '#315749');
        $('rq-bm-cover-title').textContent = title || '';
        $('rq-bm-cover-author').textContent = author || '';
        const img = $('rq-bm-cover-img');
        const valid = cover && cover !== 'null' && cover !== 'undefined';
        img.hidden = !valid;
        if (valid) img.src = cover; else img.removeAttribute('src');
    }

    // riwaq.js يعرّف النسخة القديمة داخل DOMContentLoaded، لذا نستبدلها بعده
    const definePreview = () => { window.previewBook = function (bookId, title, author, cat, pages, color, desc, copies, loanUrl, isBorrowed, coverImage, inLibrary) {
        if (!modal) return;
        const RQ = window.RQ || {};

        setCover(color, title, author, coverImage);
        $('rq-bm-title').textContent = title || '';
        $('rq-bm-author').textContent = author ? 'تأليف: ' + author : '';
        $('rq-bm-cat').querySelector('span').textContent = cat || 'عام';
        $('rq-bm-pages').querySelector('span').textContent = (pages || '—') + ' صفحة';
        $('rq-bm-copies').querySelector('span').textContent = (copies || 0) + ' نسخة مادية';
        $('rq-bm-desc').textContent = desc && desc !== 'null' ? desc : 'لا يوجد وصف متاح لهذا الكتاب بعد.';
        $('rq-bm-book-id').value = bookId || '';
        $('rq-bm-more').href = (RQ.bookUrl || '/books') + '/' + bookId;

        const addBtn = $('rq-bm-add-btn');
        const addForm = $('rq-bm-add-form');
        const note = $('rq-bm-guest-note');
        const loanForm = $('rq-bm-loan-form');
        const readForm = $('rq-bm-read-form');
        const reportBtn = $('rq-bm-report');
        reportBtn.onclick = () => window.openReportModal('book', bookId, title);
        readForm.hidden = !(RQ.auth && inLibrary);
        $('rq-bm-read-book-id').value = bookId || '';
        readForm.onsubmit = () => {
            try { sessionStorage.setItem('rq-open-reading', '1'); } catch (e) { /* غير متاح */ }
        };

        addBtn.disabled = false;
        addBtn.querySelector('span').textContent = 'أضف إلى مكتبتي';
        addForm.hidden = false;
        note.hidden = true;

        if (!RQ.auth) {
            // الزائر: زر الإضافة يحوّله إلى تسجيل الدخول
            addForm.onsubmit = (e) => {
                e.preventDefault();
                window.location.href = RQ.loginUrl;
            };
            note.hidden = false;
        } else {
            addForm.onsubmit = null;
            // الكتاب في مكتبتك أصلًا: لا داعي لزر الإضافة (ويظهر بدلًا منه "اقرأ الآن")
            addForm.hidden = !!inLibrary;
        }

        if (RQ.auth && loanUrl && !isBorrowed) {
            loanForm.action = loanUrl;
            loanForm.hidden = false;
        } else {
            loanForm.hidden = true;
        }

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        refreshIcons();
    }; };
    definePreview();
    document.addEventListener('DOMContentLoaded', definePreview);

    const defineClose = () => { window.closeBookModal = function () {
        if (!modal) return;
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }; };
    defineClose();
    document.addEventListener('DOMContentLoaded', defineClose);

    if (modal) {
        modal.querySelectorAll('[data-rq-close]').forEach((el) => el.addEventListener('click', window.closeBookModal));
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') window.closeBookModal();
    });

    document.addEventListener('DOMContentLoaded', () => refreshIcons());
})();

/* الإشعارات المؤقتة القادمة من الخادم تختفي تلقائيًا بعد 4 ثوانٍ */
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("#toast-container .toast").forEach(function (t) {
        setTimeout(function () {
            t.style.transition = "opacity .3s ease, transform .3s ease";
            t.style.opacity = "0";
            t.style.transform = "translateY(-10px)";
            setTimeout(function () { t.remove(); }, 300);
        }, 4000);
    });
});
