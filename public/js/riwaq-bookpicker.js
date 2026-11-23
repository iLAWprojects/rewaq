/**
 * اختيار الكتب بالبحث الفوري (AJAX) بدل القوائم الطويلة.
 * يحوّل أي <select data-book-picker> إلى مربع بحث، ويُبقي الـ select مخفيًا يحمل القيمة المختارة.
 * - خيار قيمته '' يظهر كعنصر «بدون كتاب».
 * - خيار قيمته '__new__' يظهر كعنصر إجراء في آخر القائمة.
 * - data-book-required: يُلزم باختيار كتاب قبل الإرسال.
 */
(function () {
    var URL_SEARCH = (window.RQ && window.RQ.bookSearchUrl) || '/books/search';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function init(select) {
        if (select._bookPicker) return;
        select._bookPicker = true;

        var required = select.hasAttribute('data-book-required') || select.required;
        select.required = false;

        var clearOpt = Array.prototype.find.call(select.options, function (o) { return o.value === ''; });
        var newOpt = Array.prototype.find.call(select.options, function (o) { return o.value === '__new__'; });

        var wrap = document.createElement('div');
        wrap.className = 'rq-bp';
        wrap.innerHTML =
            '<div class="rq-bp-field"><i data-lucide="search"></i>' +
            '<input type="text" autocomplete="off" placeholder="' + esc(select.getAttribute('data-placeholder') || 'ابحث بعنوان الكتاب أو المؤلف…') + '">' +
            '<button type="button" class="rq-bp-clear" aria-label="مسح" hidden>&times;</button></div>' +
            '<div class="rq-bp-list" role="listbox" hidden></div>';
        select.style.display = 'none';
        select.insertAdjacentElement('afterend', wrap);

        var input = wrap.querySelector('input');
        var list = wrap.querySelector('.rq-bp-list');
        var clearBtn = wrap.querySelector('.rq-bp-clear');
        var timer = null;
        var activeIndex = -1;
        var seq = 0;

        function hasBook() { return /^\d+$/.test(select.value); }

        function syncValidity() {
            var ok = !required || hasBook() || select.value === '__new__';
            input.setCustomValidity(ok ? '' : 'اختر كتابًا من القائمة');
            clearBtn.hidden = !input.value;
        }

        function labelOfSelected() {
            var o = select.options[select.selectedIndex];
            if (!o || o.value === '' ) return '';
            return o.textContent.trim();
        }

        function setValue(value, label) {
            if (/^\d+$/.test(value)) {
                var existing = Array.prototype.find.call(select.options, function (o) { return o.value === String(value); });
                if (!existing) {
                    existing = document.createElement('option');
                    existing.value = String(value);
                    select.appendChild(existing);
                }
                existing.textContent = label;
            }
            // إلغاء أي بحث جارٍ كي لا تُعاد فتح القائمة بعد الاختيار
            seq++;
            clearTimeout(timer);
            select.value = String(value);
            input.value = label || '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncValidity();
            close();
        }

        function open() { list.hidden = false; }
        function close() { list.hidden = true; activeIndex = -1; }

        function render(books) {
            var html = '';
            if (clearOpt) {
                html += '<button type="button" class="rq-bp-item rq-bp-extra" data-clear="1"><span>' + esc(clearOpt.textContent.trim()) + '</span></button>';
            }
            if (!books.length) {
                html += '<div class="rq-bp-empty">لا توجد كتب مطابقة</div>';
            }
            books.forEach(function (b) {
                var cover = b.cover
                    ? '<img src="' + esc(b.cover) + '" alt="" loading="lazy">'
                    : '<span class="rq-bp-ph" style="background:' + esc(b.color || '#315749') + '"></span>';
                html += '<button type="button" class="rq-bp-item" data-id="' + b.id + '" data-label="' + esc(b.title + ' — ' + b.author) + '">' +
                    cover + '<span class="rq-bp-txt"><b>' + esc(b.title) + '</b><small>' + esc(b.author) +
                    (b.mine ? ' · في مكتبتك' : '') + '</small></span></button>';
            });
            if (newOpt) {
                html += '<button type="button" class="rq-bp-item rq-bp-extra" data-new="1"><span>' + esc(newOpt.textContent.trim()) + '</span></button>';
            }
            list.innerHTML = html;
            if (window.lucide) { window.lucide.createIcons(); }
        }

        function search(q) {
            var mySeq = ++seq;
            list.classList.add('is-loading');
            fetch(URL_SEARCH + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (mySeq !== seq) return;
                    list.classList.remove('is-loading');
                    render(data.books || []);
                    open();
                })
                .catch(function () { list.classList.remove('is-loading'); });
        }

        input.addEventListener('focus', function () { search(hasBook() ? '' : input.value.trim()); });
        input.addEventListener('input', function () {
            // الكتابة بعد اختيار سابق تُلغي الاختيار
            if (hasBook() || select.value === '__new__') {
                select.value = clearOpt ? '' : '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            syncValidity();
            clearTimeout(timer);
            timer = setTimeout(function () { search(input.value.trim()); }, 250);
        });
        input.addEventListener('keydown', function (e) {
            var items = list.querySelectorAll('.rq-bp-item');
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (list.hidden) { search(input.value.trim()); return; }
                activeIndex = (activeIndex + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % Math.max(1, items.length);
                items.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIndex); });
                if (items[activeIndex]) items[activeIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && !list.hidden && activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                items[activeIndex].click();
            } else if (e.key === 'Escape') {
                close();
            }
        });
        clearBtn.addEventListener('click', function () {
            input.value = '';
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncValidity();
            input.focus();
        });
        list.addEventListener('mousedown', function (e) { e.preventDefault(); });
        list.addEventListener('click', function (e) {
            var item = e.target.closest('.rq-bp-item');
            if (!item) return;
            if (item.dataset.clear) { setValue('', ''); input.value = ''; return; }
            if (item.dataset.new) { setValue('__new__', newOpt.textContent.trim()); return; }
            setValue(item.dataset.id, item.dataset.label);
        });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });

        // عرض الاختيار المبدئي (إن وُجد) وإعادة الضبط عند إعادة تعيين النموذج
        input.value = labelOfSelected();
        syncValidity();
        if (select.form) {
            select.form.addEventListener('reset', function () { setTimeout(function () { input.value = labelOfSelected(); syncValidity(); }, 0); });
        }
        // تحديث العرض عندما تغيّر الصفحة قيمة الـ select برمجيًا
        select._bookPickerRefresh = function () { input.value = labelOfSelected(); syncValidity(); };
    }

    function boot() {
        document.querySelectorAll('select[data-book-picker]').forEach(init);
    }

    window.RiwaqBookPicker = { init: init, boot: boot };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
