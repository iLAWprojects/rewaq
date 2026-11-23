@extends('layouts.app')

@section('title', 'تصفح الكتب')
@section('crumb', 'تصفح الكتب')

@section('content')
<div class="ex-head">
    <div>
        <h1>تصفح الكتب</h1>
        <p>اتّسع بكتاب جديد: {{ $totalBooks }} عنوانًا في فهرس رِواق، من الأدب إلى العمران.</p>
    </div>
    <span class="muted" id="ex-count" style="font-size:13px;">{{ $totalBooks }} كتابًا</span>
</div>

<form method="GET" action="{{ route('explore') }}" class="ex-search ex-filter" id="ex-filter" role="search" autocomplete="off">
    <label><i data-lucide="book-open"></i><input type="text" name="title" value="{{ $titleFilter }}" placeholder="اسم الكتاب" aria-label="اسم الكتاب"></label>
    <label><i data-lucide="pen-line"></i><input type="text" name="author" value="{{ $authorFilter }}" placeholder="اسم الكاتب" aria-label="اسم الكاتب"></label>
    <label><i data-lucide="building-2"></i><input type="text" name="publisher" value="{{ $publisherFilter }}" placeholder="دار النشر" list="ex-publishers" aria-label="دار النشر"></label>
    <datalist id="ex-publishers">
        @foreach($publishers as $pub)<option value="{{ $pub }}">@endforeach
    </datalist>
    <input type="hidden" name="category" id="ex-category" value="{{ $category }}">
    <button type="button" class="ex-filter-reset" id="ex-reset"><i data-lucide="rotate-ccw"></i><span>مسح</span></button>
</form>

<div class="ex-pills" role="tablist" aria-label="التصنيفات">
    @foreach($categories as $cat)
        <a href="{{ route('explore', array_filter(['category' => $cat === 'الكل' ? null : $cat, 'q' => $query])) }}"
           class="{{ $category === $cat ? 'on' : '' }}" data-category="{{ $cat }}">{{ $cat }}</a>
    @endforeach
</div>

<div class="ex-grid" id="exploreBooksGrid">
    @include('pages.partials.explore-grid')
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('ex-filter');
    var grid = document.getElementById('exploreBooksGrid');
    var count = document.getElementById('ex-count');
    var catInput = document.getElementById('ex-category');
    var pills = document.querySelectorAll('.ex-pills a[data-category]');
    var timer = null, seq = 0;
    if (!form || !grid) return;

    function params() {
        var p = new URLSearchParams();
        ['title', 'author', 'publisher'].forEach(function (n) {
            var v = form.elements[n].value.trim();
            if (v) p.set(n, v);
        });
        if (catInput.value && catInput.value !== 'الكل') p.set('category', catInput.value);
        return p;
    }

    function run() {
        var p = params(), my = ++seq;
        grid.classList.add('is-loading');
        fetch(form.action + '?' + p.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (my !== seq) return;
                grid.innerHTML = data.html;
                if (count) count.textContent = data.count + ' كتابًا';
                grid.classList.remove('is-loading');
                history.replaceState(null, '', p.toString() ? form.action + '?' + p.toString() : form.action);
                if (window.lucide) window.lucide.createIcons();
            })
            .catch(function () { grid.classList.remove('is-loading'); });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
    form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 300); });
    pills.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            catInput.value = a.dataset.category;
            pills.forEach(function (x) { x.classList.toggle('on', x === a); });
            run();
        });
    });
    document.getElementById('ex-reset').addEventListener('click', function () {
        ['title', 'author', 'publisher'].forEach(function (n) { form.elements[n].value = ''; });
        catInput.value = 'الكل';
        pills.forEach(function (x) { x.classList.toggle('on', x.dataset.category === 'الكل'); });
        run();
    });
})();
</script>
@endpush
