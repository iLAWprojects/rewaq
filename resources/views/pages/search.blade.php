@extends('layouts.app')

@section('title', 'البحث الموحد والجامع')
@section('crumb', 'البحث الموحد في الرواق')

@section('content')
<div class="ch-head">
    <div>
        <h1>البحث</h1>
        <p>الفهرس المعرفي الشامل: الكتب ونوادي القراءة والخزائن ورفقاء القراءة في مكان واحد.</p>
    </div>
</div>

<form action="{{ route('search.index') }}" method="GET" class="sr-form" role="search">
    <i data-lucide="search"></i>
    <input type="text" name="q" value="{{ $query }}" placeholder="ابحث بعنوان الكتاب، المؤلف، النادي، الخزانة، أو رفيق القراءة…" aria-label="بحث" autofocus>
    <input type="hidden" name="type" value="{{ $type }}">
    <button type="submit" class="rq-btn rq-btn-primary">بحث</button>
</form>

<div class="ch-toolbar">
    <div class="rq-tabs" role="tablist">
        <a href="{{ route('search.index', ['q' => $query, 'type' => 'all']) }}" class="{{ $type === 'all' ? 'on' : '' }}">الكل ({{ $totalResults }})</a>
        <a href="{{ route('search.index', ['q' => $query, 'type' => 'books']) }}" class="{{ $type === 'books' ? 'on' : '' }}">الكتب ({{ $books->count() }})</a>
        <a href="{{ route('search.index', ['q' => $query, 'type' => 'communities']) }}" class="{{ $type === 'communities' ? 'on' : '' }}">نوادي القراءة ({{ $communities->count() }})</a>
        <a href="{{ route('search.index', ['q' => $query, 'type' => 'libraries']) }}" class="{{ $type === 'libraries' ? 'on' : '' }}">المكتبات ({{ $libraries->count() }})</a>
        <a href="{{ route('search.index', ['q' => $query, 'type' => 'readers']) }}" class="{{ $type === 'readers' ? 'on' : '' }}">القرّاء ({{ $readers->count() }})</a>
    </div>
</div>

@if(! empty($query))
    <p class="sr-status">نتائج البحث عن <b>«{{ $query }}»</b>: {{ $totalResults }} نتيجة · <a href="{{ route('search.index') }}">مسح البحث</a></p>
@endif

{{-- الكتب --}}
@if($books->isNotEmpty() && in_array($type, ['all', 'books']))
<section class="sr-section">
    <h2><i data-lucide="book"></i>فهرس الكتب والأعمال الأدبية <small>{{ $books->count() }}</small></h2>
    <div class="sr-grid">
        @foreach($books as $book)
            @php
                $bCover = $book->cover_image ? (str_starts_with($book->cover_image, 'http') ? $book->cover_image : asset($book->cover_image)) : '';
            @endphp
            <article class="sr-card sr-book">
                <button type="button" class="sr-mini open-book-btn" onclick="openBookFromCard(this)" aria-label="تفاصيل «{{ $book->title }}»"
                    data-id="{{ $book->id }}"
                    data-title="{{ $book->title }}"
                    data-author="{{ $book->author }}"
                    data-category="{{ $book->category }}"
                    data-pages="{{ $book->total_pages }}"
                    data-color="{{ $book->cover_color }}"
                    data-cover="{{ $bCover }}"
                    data-desc="{{ $book->description }}"
                    data-copies="{{ $book->copies_count }}">
                    <div class="bk-cover" style="--c: {{ $book->cover_color ?: '#315749' }};">
                        <span class="bk-frame"></span>
                        <strong>{{ $book->title }}</strong>
                        @if($bCover)<img src="{{ $bCover }}" alt="{{ $book->title }}" loading="lazy">@endif
                    </div>
                </button>
                <div class="sr-body">
                    <a href="{{ route('books.show', $book->id) }}"><h3>{{ $book->title }}</h3></a>
                    <p>{{ $book->author }}</p>
                    <div class="ex-tags">
                        <span class="rq-chip">{{ $book->category }}</span>
                        <span class="rq-chip green">{{ $book->copies_count }} نسخ</span>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

{{-- نوادي القراءة --}}
@if($communities->isNotEmpty() && in_array($type, ['all', 'communities']))
<section class="sr-section">
    <h2><i data-lucide="users"></i>نوادي القراءة <small>{{ $communities->count() }}</small></h2>
    <div class="sr-grid">
        @foreach($communities as $comm)
            <article class="sr-card sr-col">
                <div class="sr-row"><span class="rq-chip">{{ $comm->category ?? 'عام' }}</span><small>{{ $comm->members_count }} قارئاً</small></div>
                <h3>{{ $comm->name }}</h3>
                <p>{{ $comm->description ?? 'نادي قراءة لتبادل الرؤى وتعميق الأثر المعرفي.' }}</p>
                <a href="{{ route('communities.index', ['community_id' => $comm->id, 'scope' => 'all']) }}" class="rq-btn rq-btn-ghost rq-btn-sm"><i data-lucide="arrow-left"></i><span>زيارة النادي</span></a>
            </article>
        @endforeach
    </div>
</section>
@endif

{{-- القرّاء --}}
@if($readers->isNotEmpty() && in_array($type, ['all', 'readers']))
<section class="sr-section">
    <h2><i data-lucide="user-check"></i>رفقاء القراءة والقراء <small>{{ $readers->count() }}</small></h2>
    <div class="sr-grid">
        @foreach($readers as $reader)
            <article class="sr-card sr-reader">
                <span class="avatar" style="width:48px;height:48px;font-size:19px;flex-shrink:0;">{{ $reader->avatar ?? mb_substr($reader->name, 0, 1) }}</span>
                <div class="sr-body">
                    <a href="{{ route('profile.show', $reader->id) }}"><h3>{{ $reader->name }}</h3></a>
                    <p>{{ $reader->level_data['title'] ?? 'قارئ' }} · {{ $reader->points }} نقطة</p>
                    <div class="sr-actions">
                        <a href="{{ route('profile.show', $reader->id) }}" class="rq-btn rq-btn-ghost rq-btn-sm">الملف</a>
                        @auth
                            @if(Auth::id() !== $reader->id)
                                <form method="POST" action="{{ route('profile.toggleFollow', $reader->id) }}">
                                    @csrf
                                    <button type="submit" class="rq-btn {{ Auth::user()->isFollowing($reader) ? 'rq-btn-ghost' : 'rq-btn-primary' }} rq-btn-sm">{{ Auth::user()->isFollowing($reader) ? 'إلغاء المتابعة' : 'متابعة' }}</button>
                                </form>
                            @endif
                        @endauth
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

{{-- المكتبات --}}
@if($libraries->isNotEmpty() && in_array($type, ['all', 'libraries']))
<section class="sr-section">
    <h2><i data-lucide="library-big"></i>الخزائن والمكتبات المشتركة <small>{{ $libraries->count() }}</small></h2>
    <div class="sr-grid">
        @foreach($libraries as $lib)
            <article class="sr-card sr-col">
                <div class="sr-row"><span class="rq-chip green">{{ $lib->type_label }}</span><small>{{ $lib->copies_count ?? 0 }} كتاباً</small></div>
                <h3>{{ $lib->name }}</h3>
                <p>{{ $lib->description ?? 'خزانة كتب ورقية متاحة للإعارة بين القراء.' }}</p>
                <a href="{{ route('libraries.index', ['library_id' => $lib->id]) }}" class="rq-btn rq-btn-ghost rq-btn-sm"><i data-lucide="book-marked"></i><span>استكشاف الخزانة</span></a>
            </article>
        @endforeach
    </div>
</section>
@endif

@if($totalResults === 0)
    <div class="ex-empty">
        <i data-lucide="search-x"></i>
        <h3>{{ empty($query) ? 'ابدأ بكتابة ما تبحث عنه' : 'لم نعثر على نتائج مطابقة' }}</h3>
        <p>حاول البحث بكلمات أخرى أو تصفح الفهرس العام.</p>
        <a href="{{ route('explore') }}" class="rq-btn rq-btn-primary rq-btn-sm">تصفح الكتب</a>
    </div>
@endif
@endsection
