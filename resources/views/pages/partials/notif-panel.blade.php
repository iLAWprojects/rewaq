<div class="np" id="rq-notif-panel" role="dialog" aria-label="الإشعارات" hidden
     data-feed-url="{{ route('notifications.feed') }}" data-seen-url="{{ route('notifications.seen') }}">
    <div class="np-head">
        <b>الإشعارات</b>
        <button type="button" class="rw-x" data-notif-close aria-label="إغلاق"><i data-lucide="x"></i></button>
    </div>

    <div class="np-list" id="np-list" aria-live="polite">
        <p class="np-empty">جارٍ التحميل…</p>
    </div>

    <a href="{{ route('notifications.index') }}" class="np-all"><i data-lucide="bell-ring"></i><span>كل الإشعارات</span></a>
</div>
<div class="np-scrim" id="np-scrim" hidden></div>
