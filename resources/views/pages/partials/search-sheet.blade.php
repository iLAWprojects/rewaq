<div class="ss" id="rq-search-sheet" role="dialog" aria-modal="true" aria-label="البحث الشامل" hidden>
    <div class="ss-backdrop" data-ss-close></div>
    <div class="ss-panel">
        <form action="{{ route('search.index') }}" method="GET" class="ss-form" role="search">
            <i data-lucide="search"></i>
            <input type="search" name="q" id="rq-search-input" placeholder="ابحث عن كتاب، نادٍ، مكتبة أو قارئ…" autocomplete="off" enterkeyhint="search" aria-label="بحث">
            <button type="button" class="ss-x" data-ss-close aria-label="إغلاق"><i data-lucide="x"></i></button>
        </form>

        <p class="ss-label">ابحث في</p>
        <div class="ss-types">
            <a href="{{ route('search.index', ['type' => 'books']) }}"><i data-lucide="book"></i><span>الكتب</span></a>
            <a href="{{ route('search.index', ['type' => 'communities']) }}"><i data-lucide="users"></i><span>نوادي القراءة</span></a>
            <a href="{{ route('search.index', ['type' => 'libraries']) }}"><i data-lucide="library-big"></i><span>المكتبات</span></a>
            <a href="{{ route('search.index', ['type' => 'readers']) }}"><i data-lucide="user-check"></i><span>القرّاء</span></a>
        </div>
    </div>
</div>
