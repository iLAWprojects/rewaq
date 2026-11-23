<div class="rq-modal" id="rq-book-modal" role="dialog" aria-modal="true" aria-labelledby="rq-bm-title">
    <div class="rq-modal-backdrop" data-rq-close></div>
    <div class="rq-modal-card">
        <button type="button" class="rq-modal-close" data-rq-close aria-label="إغلاق"><i data-lucide="x"></i></button>

        <div class="rq-modal-side">
            <div class="bk-cover" id="rq-bm-cover">
                <span class="bk-frame"></span>
                <span class="bk-knot">✦</span>
                <strong id="rq-bm-cover-title"></strong>
                <small id="rq-bm-cover-author"></small>
                <span class="bk-brand">رِواق المعرفة</span>
                <img id="rq-bm-cover-img" alt="" hidden>
            </div>
        </div>

        <div class="rq-modal-body">
            <h2 id="rq-bm-title"></h2>
            <p class="rq-modal-author" id="rq-bm-author"></p>

            <div class="rq-chips">
                <span class="rq-chip" id="rq-bm-cat"><i data-lucide="tag"></i><span></span></span>
                <span class="rq-chip green" id="rq-bm-pages"><i data-lucide="file-text"></i><span></span></span>
                <span class="rq-chip green" id="rq-bm-copies"><i data-lucide="boxes"></i><span></span></span>
            </div>

            <p class="rq-modal-desc" id="rq-bm-desc"></p>

            <div class="rq-modal-actions">
                <form method="POST" action="{{ route('libraries.addToPersonal') }}" id="rq-bm-add-form">
                    @csrf
                    <input type="hidden" name="book_id" id="rq-bm-book-id">
                    <button type="submit" class="rq-btn rq-btn-primary" id="rq-bm-add-btn">
                        <i data-lucide="library-big"></i><span>أضف إلى مكتبتي</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('reading.switchBook') }}" id="rq-bm-read-form" hidden>
                    @csrf
                    <input type="hidden" name="book_id" id="rq-bm-read-book-id">
                    <input type="hidden" name="stay" value="1">
                    <button type="submit" class="rq-btn rq-btn-primary" id="rq-bm-read-btn"><i data-lucide="book-open-check"></i><span>اقرأ الآن</span></button>
                </form>

                <form method="POST" action="" id="rq-bm-loan-form" hidden>
                    @csrf
                    <button type="submit" class="rq-btn rq-btn-gold"><i data-lucide="book-copy"></i><span>طلب استعارة</span></button>
                </form>

                <a href="#" class="rq-btn rq-btn-ghost" id="rq-bm-more"><i data-lucide="arrow-left"></i><span>صفحة الكتاب</span></a>
            </div>

            <button type="button" class="rq-report-link" id="rq-bm-report"><i data-lucide="flag"></i><span>الإبلاغ عن هذا الكتاب</span></button>

            <div class="rq-modal-note" id="rq-bm-guest-note" hidden>
                <i data-lucide="info"></i><span>سجّل الدخول أولًا لتتمكّن من إضافة الكتاب إلى مكتبتك.</span>
            </div>
        </div>
    </div>
</div>
