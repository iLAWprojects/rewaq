@extends('layouts.app')

@section('title', 'الرئيسية')
@section('shell', 'public')
@section('bleed', 'rq-bleed')

@section('content')

{{-- ============ Hero: Timed Cards Slider (الخلفية = الكتاب النشط) ============ --}}
@php
    $thItems = $sliderBooks->map(function ($b) use ($libraryBookIds) {
        $cover = $b->cover_image
            ? (str_starts_with($b->cover_image, 'http') || str_starts_with($b->cover_image, 'data:') ? $b->cover_image : asset($b->cover_image))
            : '';

        return [
            'id' => $b->id,
            'title' => $b->title,
            'author' => $b->author,
            'category' => $b->category,
            'pages' => $b->total_pages,
            'color' => $b->cover_color ?: '#315749',
            'cover' => $cover,
            'desc' => $b->description,
            'copies' => $b->copies_count,
            'inLibrary' => in_array($b->id, $libraryBookIds),
        ];
    })->values();
    $first = $thItems->first();
@endphp
<section class="th" id="rq-hero" data-th data-coverflow style="--c: {{ $first['color'] ?? '#315749' }};">
    <div class="th-bg th-bg-a" aria-hidden="true"></div>
    <div class="th-bg th-bg-b" aria-hidden="true"></div>
    <div class="th-shade" aria-hidden="true"></div>
    <div class="th-progress" id="th-progress" aria-hidden="true"></div>

    <div class="th-content">
        <span class="th-tag"><i data-lucide="sparkles"></i>اقرأ على مهل. واترك للمعرفة أثرًا.</span>
        <span class="th-kicker" id="th-kicker">{{ $first['category'] ?? '' }} · {{ $first['pages'] ?? '' }} صفحة</span>
        <h1 class="th-title" id="th-title">{{ $first['title'] ?? 'رِواق المعرفة' }}</h1>
        <p class="th-author" id="th-author">{{ $first['author'] ?? '' }}</p>
        <p class="th-desc" id="th-desc">{{ $first['desc'] ?? 'اقرأ على مهل، واترك للمعرفة أثرًا.' }}</p>
        <div class="th-cta">
            <button type="button" class="rq-btn rq-btn-gold" id="cf-open"><i data-lucide="book-open"></i><span>تفاصيل الكتاب</span></button>
            <button type="button" class="rq-btn rq-btn-light" id="cf-add"><i data-lucide="library-big"></i><span>أضف للمكتبة</span></button>
        </div>
    </div>

    <div class="th-cards" id="th-cards" aria-label="كتب مختارة"></div>

    <div class="th-controls">
        <button type="button" class="th-arrow" id="cf-prev" aria-label="الكتاب السابق"><i data-lucide="chevron-right"></i></button>
        <button type="button" class="th-arrow" id="cf-next" aria-label="الكتاب التالي"><i data-lucide="chevron-left"></i></button>
        <span class="th-line" aria-hidden="true"><i id="th-line-fill"></i></span>
        <span class="th-count" id="th-count">01</span>
    </div>

    <script type="application/json" id="th-data">@json($thItems)</script>
    <noscript>
        @foreach($thItems as $item)<span class="cf-item">{{ $item['title'] }}</span>@endforeach
    </noscript>
</section>


{{-- ============ Stats ============ --}}
<div class="lp-stats">
    <div class="lp-stat"><b>{{ $booksCount }}</b><span>كتاب مفهرس</span></div>
    <div class="lp-stat"><b>{{ $librariesCount }}</b><span>مكتبة</span></div>
    <div class="lp-stat"><b>{{ $communitiesCount }}</b><span>نادي قراءة</span></div>
    <div class="lp-stat"><b>{{ $readersCount }}</b><span>قارئ</span></div>
</div>

{{-- ============ Features ============ --}}
<section class="lp-section" id="features">
    <div class="lp-wrap">
        <div class="lp-head">
            <div class="lp-kicker">ماذا يقدّم رِواق؟</div>
            <h2>كل ما يحتاجه القارئ في مكان واحد</h2>
            <p>من تنظيم رفوفك إلى مشاركة القراءة مع الآخرين.</p>
        </div>
        <div class="lp-grid-3">
            <div class="lp-card">
                <div class="lp-ico"><i data-lucide="library-big"></i></div>
                <h3>مكتبات خاصة وعامة</h3>
                <p>أنشئ مكتبة تحفظ فيها كتبك لنفسك، أو افتحها للجميع. أضف كتبًا وأدِر النسخ والأعضاء بسهولة.</p>
            </div>
            <div class="lp-card">
                <div class="lp-ico"><i data-lucide="users"></i></div>
                <h3>نوادي قراءة</h3>
                <p>انضم إلى نوادٍ تقرأ الكتاب نفسه، وتناقش فصوله، وتصوّت على الكتاب القادم.</p>
            </div>
            <div class="lp-card">
                <div class="lp-ico"><i data-lucide="flag"></i></div>
                <h3>تحديات القراءة</h3>
                <p>حدّد هدفك وتابع تقدّمك، وتنافس بودّ مع أصدقائك واجمع النقاط والشارات.</p>
            </div>
            <div class="lp-card">
                <div class="lp-ico"><i data-lucide="repeat"></i></div>
                <h3>استعارة الكتب</h3>
                <p>استعر نسخًا ورقية من المكتبات العامة وتابع مواعيد الإرجاع وحالة كل نسخة.</p>
            </div>
            <div class="lp-card">
                <div class="lp-ico"><i data-lucide="calendar-days"></i></div>
                <h3>تجمّعات ولقاءات</h3>
                <p>جدول لقاءات نوادي القراءة في تقويم واحد، وأضفها إلى تقويم جوجل بنقرة.</p>
            </div>
            <div class="lp-card">
                <div class="lp-ico"><i data-lucide="notebook-pen"></i></div>
                <h3>تأملات ومراجعات</h3>
                <p>دوّن اقتباساتك وأفكارك، وشارك مراجعاتك دون حرق الأحداث على غيرك.</p>
            </div>
        </div>
    </div>
</section>

{{-- ============ How it works ============ --}}
<section class="lp-section alt">
    <div class="lp-wrap">
        <div class="lp-head">
            <div class="lp-kicker">ابدأ في دقائق</div>
            <h2>ثلاث خطوات فقط</h2>
        </div>
        <div class="lp-steps">
            <div class="lp-step"><h3>أنشئ حسابك</h3><p>سجّل بالبريد الإلكتروني وادخل إلى مساحتك الخاصة.</p></div>
            <div class="lp-step"><h3>أسّس مكتبتك</h3><p>اختر نوعها خاصة أو عامة، ثم أضف كتبك إلى الرفوف.</p></div>
            <div class="lp-step"><h3>شارك وتفاعل</h3><p>انضم للنوادي والتحديات وتابع تقدّمك في القراءة.</p></div>
        </div>
    </div>
</section>

{{-- ============ Communities ============ --}}
@if($featuredCommunities->isNotEmpty())
<section class="lp-section" id="communities">
    <div class="lp-wrap">
        <div class="lp-head">
            <div class="lp-kicker">نوادي القراءة</div>
            <h2>قرّاء يقرؤون معًا</h2>
            <p>بعض النوادي النشطة التي يمكنك الانضمام إليها.</p>
        </div>
        <div class="lp-grid-3">
            @foreach($featuredCommunities as $c)
                <a href="{{ route('communities.index') }}" class="lp-card lp-comm" style="text-decoration:none;">
                    <div class="lp-ico"><i data-lucide="users"></i></div>
                    <h3>{{ $c->name }}</h3>
                    <p>{{ \Illuminate\Support\Str::limit($c->description, 110) }}</p>
                    <div class="lp-meta">
                        <span><i data-lucide="user"></i>{{ $c->members_count }} عضو</span>
                        @if($c->currentBook)<span><i data-lucide="book-open"></i>{{ $c->currentBook->title }}</span>@endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============ CTA ============ --}}
<section class="lp-cta">
    <h2>مكتبتك تنتظرك</h2>
    <p>أنشئ حسابك المجاني وابدأ بتنظيم كتبك ومشاركة قراءتك اليوم.</p>
    @auth
        <a href="{{ route('libraries.index') }}" class="rq-btn rq-btn-gold"><i data-lucide="library-big"></i><span>اذهب إلى مكتباتي</span></a>
    @else
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="{{ route('register') }}" class="rq-btn rq-btn-gold"><i data-lucide="user-plus"></i><span>إنشاء حساب</span></a>
            <a href="{{ route('login') }}" class="rq-btn rq-btn-light"><i data-lucide="log-in"></i><span>تسجيل الدخول</span></a>
        </div>
    @endauth
</section>
@endsection
