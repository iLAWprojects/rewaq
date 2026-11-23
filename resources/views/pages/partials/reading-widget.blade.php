@php
    $rwRecord = \App\Models\UserCurrentBook::where('user_id', Auth::id())
        ->where('is_active', true)
        ->with('book')
        ->first();
    $rwBook = $rwRecord?->book;
@endphp

<div class="rw" id="rq-reading" data-book-id="{{ $rwBook?->id }}">
    <div class="rw-panel" id="rw-panel" hidden role="dialog" aria-label="جلسة القراءة">
        <div class="rw-head">
            <b>جلسة القراءة</b>
            <button type="button" class="rw-x" id="rw-close" aria-label="إغلاق"><i data-lucide="x"></i></button>
        </div>

        @if($rwBook)
            <p class="rw-book"><i data-lucide="book-open"></i><span>{{ $rwBook->title }}</span><button type="button" class="rw-change" data-rw-change><i data-lucide="repeat"></i>تغيير</button></p>

            <div class="rw-time" id="rw-time" aria-live="off">00:00:00</div>

            <div class="rw-controls" id="rw-controls">
                <button type="button" class="rq-btn rq-btn-primary" id="rw-start"><i data-lucide="play"></i><span>ابدأ القراءة</span></button>
                <button type="button" class="rq-btn rq-btn-ghost" id="rw-pause" hidden><i data-lucide="pause"></i><span>إيقاف مؤقت</span></button>
                <button type="button" class="rq-btn rq-btn-gold" id="rw-finish" hidden><i data-lucide="square"></i><span>إنهاء وحفظ</span></button>
            </div>

            <form method="POST" action="{{ route('reading.storeSession') }}" id="rw-form" hidden>
                @csrf
                <input type="hidden" name="book_id" value="{{ $rwBook->id }}">
                <input type="hidden" name="start_page" value="{{ $rwRecord->current_page }}">
                <input type="hidden" name="minutes" id="rw-minutes" value="1">
                <input type="hidden" name="device" value="كتاب ورقي">
                <p class="rw-sum">قرأت <b id="rw-sum-min">0</b> دقيقة. في أي صفحة توقفت؟</p>
                <label class="rw-page">
                    <span>وصلت إلى صفحة</span>
                    <input type="number" name="page" min="1" max="{{ $rwBook->total_pages }}" value="{{ min($rwBook->total_pages, $rwRecord->current_page + 5) }}" required>
                </label>
                <label class="rw-page">
                    <span>ملاحظة (اختياري)</span>
                    <input type="text" name="note" maxlength="1000" placeholder="فكرة أو اقتباس استوقفك…">
                </label>
                <div class="rw-controls">
                    <button type="submit" class="rq-btn rq-btn-primary"><i data-lucide="save"></i><span>حفظ الجلسة</span></button>
                    <button type="button" class="rq-btn rq-btn-ghost" id="rw-discard">تجاهل</button>
                </div>
            </form>
        @else
            <p class="rw-empty">لم تحدد كتابًا لتقرأه بعد. اختر كتابًا هنا أو من تصفح الكتب.</p>
            <button type="button" class="rq-btn rq-btn-primary" data-rw-change><i data-lucide="search"></i><span>اختيار كتاب</span></button>
        @endif

        <form method="POST" action="{{ route('reading.switchBook') }}" class="rw-switch" id="rw-switch" hidden>
            @csrf
            <input type="hidden" name="stay" value="1">
            <div class="rw-switch-head">
                <label>اختر الكتاب الذي ستقرأه</label>
                <button type="button" class="rw-back" data-rw-back><i data-lucide="arrow-right"></i>رجوع</button>
            </div>
            <select name="book_id" data-book-picker data-book-required data-placeholder="ابحث بعنوان الكتاب أو المؤلف…">
                <option value="">اختر الكتاب…</option>
            </select>
            <div class="rw-warn" data-rw-warn hidden>
                <p>تغيير الكتاب سيُلغي المؤقّت الحالي لهذه الجلسة.</p>
                <div>
                    <button type="button" class="rq-btn rq-btn-gold rq-btn-sm" data-rw-confirm>تأكيد التغيير</button>
                    <button type="button" class="rq-btn rq-btn-ghost rq-btn-sm" data-rw-cancel>إلغاء</button>
                </div>
            </div>
        </form>

        <a href="{{ route('reading.index') }}" class="rw-log"><i data-lucide="history"></i><span>سجل القراءة</span></a>
    </div>

    <button type="button" class="rw-fab" id="rw-fab" aria-expanded="false" aria-controls="rw-panel">
        <i data-lucide="book-open"></i>
        <span id="rw-fab-label">ابدأ القراءة</span>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('rw-switch');
    if (!form) return;
    document.querySelectorAll('[data-rw-change]').forEach(function (btn) {
        btn.addEventListener('click', function () { setSwitching(true); });
    });
    form.querySelector('[data-rw-back]').addEventListener('click', function () { setSwitching(false); });

    function setSwitching(on) {
        var panel = document.getElementById('rw-panel');
        panel.classList.toggle('is-switching', on);
        form.hidden = !on;
        if (!on) {
            form.querySelector('[data-rw-warn]').hidden = true;
            return;
        }
        var input = form.querySelector('.rq-bp input');
        if (input) { input.focus(); }
        if (window.lucide) { window.lucide.createIcons(); }
    }
    var select = form.querySelector('select');
    var warn = form.querySelector('[data-rw-warn]');

    function go() {
        try { localStorage.removeItem('rq-reading-timer'); } catch (e) {}
        form.submit();
    }

    select.addEventListener('change', function () {
        if (!/^[0-9]+$/.test(this.value)) { warn.hidden = true; return; }
        var hasTime = false;
        try { var st = JSON.parse(localStorage.getItem('rq-reading-timer')); hasTime = !!(st && (st.running || st.acc > 0)); } catch (e) {}
        if (hasTime) { warn.hidden = false; return; }
        go();
    });
    warn.querySelector('[data-rw-confirm]').addEventListener('click', go);
    warn.querySelector('[data-rw-cancel]').addEventListener('click', function () {
        warn.hidden = true;
        select.value = '';
        if (select._bookPickerRefresh) { select._bookPickerRefresh(); }
        var input = form.querySelector('.rq-bp input');
        if (input) { input.focus(); }
    });
});
</script>
