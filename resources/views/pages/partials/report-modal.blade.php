<div class="rq-modal" id="rq-report-modal" role="dialog" aria-modal="true" aria-labelledby="rq-rp-title">
    <div class="rq-modal-backdrop" data-rq-report-close></div>
    <form class="rq-report-card" method="POST" action="{{ route('content.report') }}" id="rq-report-form">
        @csrf
        <input type="hidden" name="reportable_type" id="rq-rp-type">
        <input type="hidden" name="reportable_id" id="rq-rp-id">

        <header>
            <span class="rq-rp-ico"><i data-lucide="flag"></i></span>
            <div>
                <h3 id="rq-rp-title">الإبلاغ عن مخالفة</h3>
                <p id="rq-rp-target"></p>
            </div>
            <button type="button" class="rq-modal-close" data-rq-report-close aria-label="إغلاق"><i data-lucide="x"></i></button>
        </header>

        <fieldset class="rq-rp-reasons" id="rq-rp-reasons"></fieldset>

        <label class="rq-rp-details">
            <span>تفاصيل إضافية (اختياري)</span>
            <textarea name="details" rows="3" maxlength="500" placeholder="اشرح لنا المشكلة باختصار ليسهل على الإدارة المراجعة…"></textarea>
        </label>

        <p class="rq-rp-note"><i data-lucide="shield-check"></i>تصل بلاغاتك إلى إدارة المنصة بسرّية، وسنخبرك بالنتيجة عبر الإشعارات.</p>

        <div class="rq-rp-actions">
            <button type="submit" class="rq-btn rq-btn-primary"><i data-lucide="send"></i><span>إرسال البلاغ</span></button>
            <button type="button" class="rq-btn rq-btn-ghost" data-rq-report-close>إلغاء</button>
        </div>
    </form>
</div>
