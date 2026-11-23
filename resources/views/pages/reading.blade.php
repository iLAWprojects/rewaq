@extends('layouts.app')

@section('title', 'سجل القراءة')
@section('crumb', 'سجل القراءة والأثر')

@push('styles')
<style>
.reading-nav-pills {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--line);
}
.reading-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 24px;
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    color: #4A564A;
    background: #F3EFE6;
    border: 1px solid #DFD7C5;
    transition: all 0.25s ease;
    white-space: nowrap;
}
.reading-pill-btn:hover {
    background: #E8E2D4;
    color: var(--green);
}
.reading-pill-btn.active {
    background: var(--green);
    color: #FFFDF8;
    border-color: var(--green);
    box-shadow: 0 4px 14px rgba(23,61,54,0.2);
}
.reading-pill-btn.active i {
    color: var(--gold);
}
.star-rating-display {
    color: #E2AC37;
    font-size: 15px;
    letter-spacing: 2px;
}
.quote-card {
    background: #FAF7EF;
    border: 1px solid #E5DFCF;
    border-right: 4px solid var(--gold);
    border-radius: 12px;
    padding: 20px;
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
}
.quote-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}
.review-card {
    background: #FFFDF8;
    border: 1px solid #E8E2D2;
    border-radius: 14px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: all 0.25s ease;
}
.review-card:hover {
    border-color: #D5C7AA;
    box-shadow: 0 6px 18px rgba(23,61,54,0.06);
}
.spoiler-container {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
}
.spoiler-revealed .spoiler-blur {
    filter: none !important;
    user-select: auto !important;
}
.spoiler-revealed .spoiler-overlay-msg {
    display: none !important;
}
.series-card {
    background: #FFFDF8;
    border: 1px solid #E5DFCF;
    border-radius: 14px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s ease;
}
.series-card:hover {
    border-color: var(--gold);
    box-shadow: 0 8px 24px rgba(23,61,54,0.08);
}
.streak-banner {
    background: linear-gradient(135deg, #173D36 0%, #102B26 100%);
    color: #FFFDF8;
    border-radius: 16px;
    padding: 22px;
    border: 1px solid rgba(195,161,93,0.3);
    box-shadow: 0 8px 24px rgba(23,61,54,0.15);
}
.badge-unlocked {
    border: 1.5px solid var(--gold);
    background: #FFFDF8;
    box-shadow: 0 4px 14px rgba(195,161,93,0.15);
}
.badge-locked {
    opacity: 0.6;
    background: #F4EFE6;
    border: 1px dashed #D0C7B5;
    filter: grayscale(0.85);
}
/* Reading Modals Polish */
.reading-modal-dialog {
    width: 560px;
    max-width: 95vw;
    max-height: min(90vh, 780px);
    overflow-y: auto;
    box-sizing: border-box;
}
.reading-modal-dialog input,
.reading-modal-dialog select,
.reading-modal-dialog textarea {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}
.reading-modal-dialog [style*="grid-template-columns"] {
    min-width: 0 !important;
}
.reading-modal-dialog [style*="grid-template-columns"] > * {
    min-width: 0 !important;
}
@media (max-width: 540px) {
    .reading-modal-dialog {
        padding: 20px 16px !important;
        width: 100% !important;
    }
    .reading-modal-dialog [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
@endpush

@section('content')
<div class="ch-head">
    <div>
        <h1>سجل القراءة</h1>
        <p>احتفظ بما قرأت، ونمِّ حصيلتك، وشارك تأمّلاتك ومراجعاتك مع مجتمع القرّاء.</p>
    </div>
    @if($currentBookRecord && $currentBookRecord->book)
        <button type="button" class="rq-btn rq-btn-primary" onclick="openModal('session-modal')">
            <i data-lucide="timer"></i><span>تسجيل جلسة قراءة</span>
        </button>
    @endif
</div>

<div class="ch-toolbar">
    <div class="rq-tabs" role="tablist">
        <a href="{{ route('reading.index', array_filter(['tab' => 'journey', 'book' => $bookFilterId ?: null])) }}" class="{{ $tab === 'journey' ? 'on' : '' }}"><i data-lucide="compass"></i><span>الجلسات</span></a>
        <a href="{{ route('reading.index', array_filter(['tab' => 'shelves', 'book' => $bookFilterId ?: null])) }}" class="{{ $tab === 'shelves' ? 'on' : '' }}"><i data-lucide="book-marked"></i><span>الرفوف ({{ $userBooks->count() }})</span></a>
        <a href="{{ route('reading.index', array_filter(['tab' => 'diversity', 'book' => $bookFilterId ?: null])) }}" class="{{ $tab === 'diversity' ? 'on' : '' }}"><i data-lucide="pie-chart"></i><span>التنوع المعرفي ({{ $kdi['score'] }}%)</span></a>
        <a href="{{ route('reading.index', array_filter(['tab' => 'reviews', 'book' => $bookFilterId ?: null])) }}" class="{{ $tab === 'reviews' ? 'on' : '' }}"><i data-lucide="star"></i><span>المراجعات ({{ $reviews->count() }})</span></a>
        <a href="{{ route('reading.index', array_filter(['tab' => 'quotes', 'book' => $bookFilterId ?: null])) }}" class="{{ $tab === 'quotes' ? 'on' : '' }}"><i data-lucide="quote"></i><span>الاقتباسات ({{ $reflections->count() }})</span></a>
        <a href="{{ route('reading.index', array_filter(['tab' => 'series', 'book' => $bookFilterId ?: null])) }}" class="{{ $tab === 'series' ? 'on' : '' }}"><i data-lucide="layers"></i><span>السلاسل ({{ $series->count() }})</span></a>
        <a href="{{ route('reading.index', array_filter(['tab' => 'achievements', 'book' => $bookFilterId ?: null])) }}" class="{{ $tab === 'achievements' ? 'on' : '' }}"><i data-lucide="award"></i><span>الإنجازات</span></a>
    </div>
</div>

{{-- فلتر الكتاب: يسري على كل التبويبات --}}
<form method="GET" action="{{ route('reading.index') }}" class="rd-filter" id="reading-book-filter">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <label for="reading-book-select"><i data-lucide="filter"></i><span>تصفية حسب الكتاب</span></label>
    <select name="book" id="reading-book-select" onchange="onReadingBookFilter(this)">
        <option value="">{{ $myBooks->count() ? 'كل كتبي' : 'لا توجد كتب في مكتبتك بعد' }}</option>
        @foreach($myBooks as $mb)
            <option value="{{ $mb->id }}" {{ $bookFilterId === $mb->id ? 'selected' : '' }}>{{ $mb->title }} — {{ $mb->author }}</option>
        @endforeach
        <option value="__add__">＋ إضافة كتاب…</option>
    </select>
    @if($bookFilterId)
        <a href="{{ route('reading.index', ['tab' => $tab]) }}" class="rd-filter-clear"><i data-lucide="x"></i><span>إزالة التصفية</span></a>
    @endif
    @if($bookFilterId && in_array($tab, ['diversity', 'achievements']))
        <small class="muted">هذا التبويب يعرض حصيلتك الكاملة ولا يتأثر بتصفية الكتاب.</small>
    @endif
</form>

<!-- Reading Overview & Annual Goals Bar -->
<section class="panel animate-fade-in" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;margin-bottom:24px;padding:20px;background:#FAF8F3;border:1px solid #E8E2D2;">
    <!-- Annual Goal Card -->
    <div style="border-inline-end:1px solid var(--line);padding:4px 12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="muted" style="font-weight:600;">الهدف السنوي للقراءة</small>
            <button type="button" onclick="openModal('goal-modal')" style="background:none;border:none;color:var(--gold);cursor:pointer;font-size:12px;display:flex;align-items:center;gap:3px;" title="تعديل الهدف">
                <i data-lucide="edit-3" style="width:12px;height:12px;"></i>
                <span>تعديل</span>
            </button>
        </div>
        <strong style="font-family:'Noto Naskh Arabic';font-size:24px;display:block;font-weight:700;color:var(--green);margin:4px 0;">
            {{ $finished }} / {{ $goal }}
        </strong>
        <div class="progress" style="height:6px;margin:6px 0;">
            <span style="width: {{ $goalPercentage }}%;"></span>
        </div>
        <small class="muted" style="font-size:11.5px;">أنجزت <b>{{ $goalPercentage }}٪</b> من مسارك لعام {{ date('Y') }}</small>
    </div>

    <!-- Daily Streak Card (Anti-Tamper verified) -->
    <div style="border-inline-end:1px solid var(--line);padding:4px 12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="muted" style="font-weight:600;">سلسلة القراءة الموثقة</small>
            <span style="font-size:14px;" title="محسوبة وفق قاعدة عدم التلاعب">🔥</span>
        </div>
        <strong style="font-family:'Noto Naskh Arabic';font-size:24px;display:block;font-weight:700;color:#C86D2B;margin:4px 0;">
            {{ $streak }} أيام متتالية
        </strong>
        <small class="muted" style="font-size:11px;">قراءة متصلة (جلسات ٥ دقائق فأكثر)</small>
    </div>

    <!-- Knowledge Diversity Index Mini Card -->
    <div style="border-inline-end:1px solid var(--line);padding:4px 12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="muted" style="font-weight:600;">مؤشر التنوع (KDI)</small>
            <a href="{{ route('reading.index', array_filter(['tab' => 'diversity', 'book' => $bookFilterId ?: null])) }}" style="color:var(--gold);font-size:11.5px;text-decoration:none;font-weight:600;">التفاصيل ↗</a>
        </div>
        <strong style="font-family:'Noto Naskh Arabic';font-size:24px;display:block;font-weight:700;color:var(--green);margin:4px 0;">
            {{ $kdi['score'] }}%
        </strong>
        <small class="muted" style="font-size:11px;">{{ $kdi['categories_count'] }} تصنيفات · {{ $kdi['authors_count'] }} مؤلفين</small>
    </div>

    <!-- Recorded Sessions -->
    <div style="border-inline-end:1px solid var(--line);padding:4px 12px;">
        <small class="muted" style="font-weight:600;">جلسات القراءة المسجلة</small>
        <strong style="font-family:'Noto Naskh Arabic';font-size:24px;display:block;font-weight:700;color:var(--green);margin:4px 0;">
            {{ $totalSessionsCount }}
        </strong>
        <small class="muted" style="font-size:11px;">جلسات موثقة بالوقت والصفحات</small>
    </div>

    <!-- Reading Minutes & Hours -->
    <div style="padding:4px 12px;">
        <small class="muted" style="font-weight:600;">إجمالي وقت القراءة</small>
        <strong style="font-family:'Noto Naskh Arabic';font-size:24px;display:block;font-weight:700;color:var(--gold);margin:4px 0;">
            {{ $totalMinutes }} <span style="font-size:14px;color:#78694B;">دقيقة</span>
        </strong>
        <small class="muted" style="font-size:11px;">قرابة <b>{{ round($totalMinutes / 60, 1) }}</b> ساعة موثقة</small>
    </div>
</section>

<!-- Current Book Hero (بين يديك الآن) -->
@if($currentBookRecord && $currentBookRecord->book)
@php $book = $currentBookRecord->book; @endphp
<section class="hero animate-fade-in delay-1" style="background:#FFFDF8;border:1px solid #E5DFCF;box-shadow:0 6px 20px rgba(0,0,0,0.04);">
    <div class="cover" style="background: {{ $book->cover_color }};box-shadow:0 8px 18px rgba(0,0,0,0.18);">
        <strong>{{ $book->title }}</strong>
        <small>{{ $book->author }}</small>
    </div>
    <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
            <span class="eyebrow" style="color:var(--gold);margin:0;">بين يديك الآن · {{ $book->category }}</span>
            <button type="button" onclick="openModal('switch-book-modal')" class="secondary btn-sm" style="font-size:12px;padding:4px 10px;">
                <i data-lucide="refresh-cw" style="width:12px;height:12px;"></i>
                <span>تبديل الكتاب الحالي</span>
            </button>
        </div>
        <h2 style="margin:6px 0 4px;font-size:22px;color:var(--green);">{{ $book->title }}</h2>
        <p class="muted" style="margin-bottom:12px;">{{ $book->author }}</p>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:13px;">
            <span>الصفحة <b>{{ $currentBookRecord->current_page }}</b> من {{ $book->total_pages }}</span>
            <span style="font-weight:700;color:var(--green);">{{ $percentage }}٪</span>
        </div>

        <div class="progress" style="margin-bottom:16px;">
            <span style="width: {{ $percentage }}%;"></span>
        </div>

        <div class="actions" style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="primary" onclick="openModal('session-modal')">
                <i data-lucide="timer"></i>
                <span>{{ $currentBookRecord->current_page >= $book->total_pages ? 'مراجعة قراءتك' : 'سجّل جلسة قراءة' }}</span>
            </button>
            <button class="secondary" onclick="openEditStatusModal({{ $book->id }}, '{{ $currentBookRecord->status }}', {{ $currentBookRecord->current_page }}, '{{ $currentBookRecord->start_date ? $currentBookRecord->start_date->format('Y-m-d') : '' }}', '{{ $currentBookRecord->finish_date ? $currentBookRecord->finish_date->format('Y-m-d') : '' }}', {{ $currentBookRecord->rating ?? 0 }}, '{{ addslashes($currentBookRecord->notes ?? '') }}')">
                <i data-lucide="sliders"></i>
                <span>تعديل الحالة والتقدم</span>
            </button>
            <button class="secondary" onclick="openModal('reflection-modal')">
                <i data-lucide="feather"></i>
                <span>أضف تأمّلًا</span>
            </button>
            <button class="secondary" onclick="openModal('review-modal')">
                <i data-lucide="star"></i>
                <span>اكتب مراجعة وتقييم</span>
            </button>
        </div>
    </div>
</section>
@endif

<!-- Tab 1: Journey & Reading Sessions (سجلّ الأثر / جلسات وتأمّلات) -->
@if($tab === 'journey')
<section class="panel animate-fade-in delay-2" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h2 style="margin:0;">سجلّ الأثر</h2>
        <span class="tag">جلسات وتأمّلات</span>
    </div>

    <div class="timeline">
        @forelse($sessions as $session)
            <article class="timeline-item timeline-item-snap" style="animation-delay: {{ $loop->index * 0.08 }}s;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                    <span class="eyebrow" style="margin:0;">
                        @if($session->minutes > 0)
                            جلسة قراءة · {{ $session->minutes }} دقيقة
                        @else
                            تأمّل وخاطرة خاصة
                        @endif
                        · {{ $session->created_at->diffForHumans() }}
                        @if(! $session->is_valid)
                            <span class="tag" style="background:#FBF1EF;color:#A33A2E;font-size:11px;margin-inline-start:6px;" title="{{ $session->invalid_reason }}">غير موثّقة · لم تُحتسب</span>
                        @endif
                    </span>
                    @if($session->device)
                        <span class="tag" style="background:#FAF8F3;border:1px solid #DFD7C5;font-size:11px;color:#556055;">
                            {{ $session->device }}
                        </span>
                    @endif
                </div>
                <h3 style="margin:4px 0 6px;">
                    @if($session->minutes > 0)
                        قرأت {{ $session->pages_read }} صفحة من «{{ $session->book->title ?? 'كتابك' }}» (توقفت عند ص {{ $session->current_page }})
                    @else
                        فكرة وتأمّل يستحق أن يبقى
                    @endif
                </h3>
                @if($session->note)
                    <p class="muted" style="line-height:1.7;color:#556055;margin:0;">
                        {{ $session->note }}
                    </p>
                @endif
            </article>
        @empty
            <article class="timeline-item timeline-item-snap">
                <h3>هنا يبدأ سجلّك المعرفي</h3>
                <p class="muted">سجّل أول جلسة قراءة، أو احتفظ بتأمّل من كتابك الحالي، ليبقى أثر القراءة قريباً ومحفوظاً.</p>
            </article>
        @endforelse
    </div>
</section>
@endif

<!-- Tab: Reading Shelves & Book Statuses (الرفوف وحالات الكتب) -->
@if($tab === 'shelves')
<section class="panel animate-fade-in delay-2" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0;font-size:20px;">الرفوف وحالات الكتب الخمسة</h2>
            <div class="muted" style="font-size:13px;">توزيع كتبك وسجل قراءتك وفق الحالات المنهجية الخمس المعتمدة في رِواق.</div>
        </div>
        <button type="button" class="primary btn-sm" onclick="openAddStatusModal()">
            <i data-lucide="plus"></i>
            <span>إضافة كتاب إلى رفوفك</span>
        </button>
    </div>

    @php
        $shelvesList = [
            [
                'status' => 'currently_reading',
                'title' => 'أقرأ الآن (قراءة نشطة)',
                'icon' => 'book-open',
                'badge' => 'tag-active',
                'color' => '#1F5E3B',
                'books' => $currentlyReading,
                'desc' => 'الكتب التي تقرؤها حالياً وتتابع تسجيل جلساتك وصفحاتك فيها بانتظام.'
            ],
            [
                'status' => 'want_to_read',
                'title' => 'أرغب بقراءته (قائمة الأمنيات)',
                'icon' => 'bookmark',
                'badge' => 'tag-info',
                'color' => '#2A6F97',
                'books' => $wantToRead,
                'desc' => 'عناوين اخترتها لتكون في خطتك القرائية القادمة وتنتظر الشروع فيها.'
            ],
            [
                'status' => 'completed',
                'title' => 'مكتمل (أتممت قراءته)',
                'icon' => 'check-circle-2',
                'badge' => 'tag-success',
                'color' => '#C3A15D',
                'books' => $completedBooks,
                'desc' => 'الكتب التي طويت آخر صفحاتها ودخلت في رصيدك وإنجازك السنوي.'
            ],
            [
                'status' => 'on_hold',
                'title' => 'متوقف / معلّق مؤقتاً',
                'icon' => 'pause-circle',
                'badge' => 'tag-warning',
                'color' => '#D97706',
                'books' => $onHoldBooks,
                'desc' => 'كتب بدأت بها ولكن أوقفتها مؤقتاً للانشغال أو الانتقال لكتاب آخر وستعود إليها.'
            ],
            [
                'status' => 'did_not_finish',
                'title' => 'لم أكمله (DNF)',
                'icon' => 'x-circle',
                'badge' => 'tag-muted',
                'color' => '#9B1C1C',
                'books' => $dnfBooks,
                'desc' => 'كتب قررت التوقف عن إتمامها لعدم ملاءمتها، مع حفظ ما قُرئ منها في السجل.'
            ],
        ];
    @endphp

    <div style="display:grid;gap:24px;">
        @foreach($shelvesList as $sh)
            <div style="background:#FAF8F3;border:1px solid #E5DFCF;border-radius:14px;padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="width:36px;height:36px;border-radius:10px;background:{{ $sh['color'] }}15;color:{{ $sh['color'] }};display:grid;place-items:center;font-size:18px;">
                            <i data-lucide="{{ $sh['icon'] }}" style="width:20px;height:20px;"></i>
                        </span>
                        <div>
                            <h3 style="margin:0;font-size:17px;color:var(--ink);">{{ $sh['title'] }}</h3>
                            <small class="muted" style="font-size:12px;">{{ $sh['desc'] }}</small>
                        </div>
                    </div>
                    <span class="tag" style="background:#FFFDF8;border:1px solid #DFD7C5;color:{{ $sh['color'] }};font-weight:700;">
                        {{ $sh['books']->count() }} كتب
                    </span>
                </div>

                @if($sh['books']->count() > 0)
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:16px;">
                        @foreach($sh['books'] as $ucb)
                            @php $b = $ucb->book; @endphp
                            @if($b)
                            <div style="background:#FFFDF8;border:1px solid #EAE3D2;border-radius:12px;padding:16px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                                <div>
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
                                        <span class="tag" style="background:#F2EDE4;color:#6A604F;font-size:11px;">
                                            {{ $b->category ?? 'فكر ومعرفة' }}
                                        </span>
                                        @if($ucb->rating)
                                            <span style="color:#E2AC37;font-size:12px;font-weight:700;">
                                                ★ {{ $ucb->rating }}/5
                                            </span>
                                        @endif
                                    </div>
                                    <h4 style="margin:0 0 4px;font-size:16px;color:var(--green);">
                                        <a href="{{ route('books.show', $b->id) }}" style="color:inherit;text-decoration:none;">{{ $b->title }}</a>
                                    </h4>
                                    <p class="muted" style="font-size:12.5px;margin:0 0 10px;">{{ $b->author }} · {{ $b->total_pages }} صفحة</p>

                                    <!-- Progress Bar -->
                                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:#556055;">
                                        <span>ص {{ $ucb->current_page }} من {{ $b->total_pages }}</span>
                                        <b style="color:var(--green);">{{ $ucb->percentage() }}%</b>
                                    </div>
                                    <div class="progress" style="height:6px;margin:0 0 10px;">
                                        <span style="width: {{ $ucb->percentage() }}%;"></span>
                                    </div>

                                    <!-- Timestamps info -->
                                    <div style="font-size:11.5px;color:#786E5E;display:grid;gap:2px;margin-bottom:8px;">
                                        @if($ucb->start_date)
                                            <div>البداية: <b>{{ $ucb->start_date->format('Y-m-d') }}</b></div>
                                        @endif
                                        @if($ucb->finish_date)
                                            <div>الإنهاء: <b>{{ $ucb->finish_date->format('Y-m-d') }}</b></div>
                                        @endif
                                        @if($ucb->notes)
                                            <div style="background:#FAF8F3;padding:6px 8px;border-radius:6px;border-right:2px solid var(--gold);margin-top:4px;color:#4F5E4F;">
                                                «{{ $ucb->notes }}»
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #EFEAE0;padding-top:10px;margin-top:8px;">
                                    <button type="button" onclick="openEditStatusModal({{ $b->id }}, '{{ $ucb->status }}', {{ $ucb->current_page }}, '{{ $ucb->start_date ? $ucb->start_date->format('Y-m-d') : '' }}', '{{ $ucb->finish_date ? $ucb->finish_date->format('Y-m-d') : '' }}', {{ $ucb->rating ?? 0 }}, '{{ addslashes($ucb->notes ?? '') }}')" class="secondary btn-sm" style="font-size:11.5px;padding:4px 8px;">
                                        <i data-lucide="sliders" style="width:12px;height:12px;"></i>
                                        <span>تعديل الحالة والتقدم</span>
                                    </button>
                                    @if($ucb->status !== 'currently_reading')
                                        <form method="POST" action="{{ route('reading.switchBook') }}" style="margin:0;">
                                            @csrf
                                            <input type="hidden" name="book_id" value="{{ $b->id }}">
                                            <button type="submit" style="background:none;border:none;color:var(--green);cursor:pointer;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:3px;" title="تعيين كقراءة حالية نشطة">
                                                <i data-lucide="play" style="width:11px;height:11px;"></i>
                                                <span>اجعله الحالي</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div style="text-align:center;padding:16px;background:#FFFDF8;border-radius:10px;border:1px dashed #DFD7C5;color:#8A8070;font-size:12.5px;">
                        لا توجد كتب مضافة في هذا الرف حالياً.
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endif

<!-- Tab: Knowledge Diversity Index (مؤشر التنوع المعرفي KDI) -->
@if($tab === 'diversity')
<section class="panel animate-fade-in delay-2" style="margin-top:24px;display:grid;gap:24px;">
    <!-- Score Hero Widget -->
    <div style="background:linear-gradient(135deg, #173D36 0%, #0F2A25 100%);color:#FFFDF8;border-radius:16px;padding:26px;border:1px solid rgba(195,161,93,0.3);box-shadow:0 8px 24px rgba(23,61,54,0.15);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:18px;">
            <div>
                <span class="eyebrow" style="color:var(--gold);margin:0;">السجل المعرفي المتقدم</span>
                <h2 style="margin:6px 0 8px;font-size:26px;color:#FFFDF8;">
                    مؤشر التنوع المعرفي (KDI): {{ $kdi['score'] }}%
                </h2>
                <p style="color:#C6D4C8;font-size:14px;margin:0;max-width:620px;line-height:1.7;">
                    مؤشر علمي دقيق يقيس توازن حصيلتك القرائية وتنوع مصادرك الفكرية عبر ثلاثة محاور رئيسة: الموضوعات والتصنيفات (45%)، تعدد المؤلفين (35%)، واللغات والأصول (20%).
                </p>
            </div>
            <div style="background:rgba(255,255,255,0.1);padding:18px 26px;border-radius:16px;border:1.5px solid var(--gold);text-align:center;min-width:140px;">
                <span style="font-size:12px;color:#D8C49D;display:block;">تصنيف التنوع</span>
                <strong style="font-size:32px;color:#FFFDF8;font-family:'Noto Naskh Arabic';">{{ $kdi['score'] }}<span style="font-size:18px;">%</span></strong>
                <span class="tag" style="background:rgba(195,161,93,0.25);color:#FFE3A8;font-size:11.5px;display:block;margin-top:4px;">
                    @if($kdi['score'] >= 80) تنوع استثنائي ✦
                    @elseif($kdi['score'] >= 60) تنوع غني ومتوازن ✓
                    @elseif($kdi['score'] >= 40) تنوع متوسط ومبشر
                    @else تنوع في مرحلة التأسيس
                    @endif
                </span>
            </div>
        </div>
    </div>

    @php $kdiPct = fn (array $d) => collect($d)->map(fn ($c) => (int) round($c / max(1, array_sum($d)) * 100))->all(); @endphp
    <!-- 3 Pillars Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:18px;">
        <!-- Categories Pillar (45%) -->
        <div class="panel" style="background:#FFFDF8;border:1px solid #DFD5C2;border-radius:14px;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="tag" style="width:18px;height:18px;color:var(--green);"></i>
                    <strong style="font-size:16px;color:var(--ink);">1. تنوع التصنيفات (45%)</strong>
                </div>
                <span class="tag" style="background:#EBF3ED;color:var(--green);font-weight:700;">{{ $kdi['categories_count'] }} تصنيفات</span>
            </div>
            <p class="muted" style="font-size:12.5px;margin-bottom:14px;">يقيس اتساع اهتماماتك بين الفكر، الأدب، التاريخ، العلوم، وتطوير الذات.</p>
            <div style="display:grid;gap:8px;">
                @forelse($kdiPct($kdi['categories_distribution']) as $cat => $pct)
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;color:#4A564A;">
                            <span>{{ $cat }}</span>
                            <b>{{ $pct }}%</b>
                        </div>
                        <div class="progress" style="height:5px;margin:0;"><span style="width:{{ $pct }}%;"></span></div>
                    </div>
                @empty
                    <small class="muted">أضف كتباً إلى سجل قراءتك لحساب توزيع التصنيفات.</small>
                @endforelse
            </div>
        </div>

        <!-- Authors Pillar (35%) -->
        <div class="panel" style="background:#FFFDF8;border:1px solid #DFD5C2;border-radius:14px;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="users" style="width:18px;height:18px;color:var(--gold);"></i>
                    <strong style="font-size:16px;color:var(--ink);">2. تعدد المؤلفين (35%)</strong>
                </div>
                <span class="tag" style="background:#FAF0D6;color:#785419;font-weight:700;">{{ $kdi['authors_count'] }} مؤلفين</span>
            </div>
            <p class="muted" style="font-size:12.5px;margin-bottom:14px;">يضمن عدم حصر التلقي في كاتب واحد والانفتاح على مختلف المدارس الفكرية.</p>
            <div style="display:grid;gap:8px;">
                @forelse($kdiPct($kdi['authors_distribution']) as $author => $pct)
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;color:#4A564A;">
                            <span>{{ $author }}</span>
                            <b>{{ $pct }}%</b>
                        </div>
                        <div class="progress" style="height:5px;margin:0;"><span style="width:{{ $pct }}%;background:var(--gold);"></span></div>
                    </div>
                @empty
                    <small class="muted">أضف كتباً إلى سجل قراءتك لحساب تعدد المؤلفين.</small>
                @endforelse
            </div>
        </div>

        <!-- Languages Pillar (20%) -->
        <div class="panel" style="background:#FFFDF8;border:1px solid #DFD5C2;border-radius:14px;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="globe" style="width:18px;height:18px;color:#2A6F97;"></i>
                    <strong style="font-size:16px;color:var(--ink);">3. تنوع اللغات (20%)</strong>
                </div>
                <span class="tag" style="background:#E6EFF8;color:#2A6F97;font-weight:700;">{{ $kdi['languages_count'] }} لغات</span>
            </div>
            <p class="muted" style="font-size:12.5px;margin-bottom:14px;">يقيس قراءتك بين المصادر العربية والترجمات أو اللغات الأصلية الأخرى.</p>
            <div style="display:grid;gap:8px;">
                @forelse($kdiPct($kdi['languages_distribution']) as $lang => $pct)
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;color:#4A564A;">
                            <span>{{ $lang }}</span>
                            <b>{{ $pct }}%</b>
                        </div>
                        <div class="progress" style="height:5px;margin:0;"><span style="width:{{ $pct }}%;background:#2A6F97;"></span></div>
                    </div>
                @empty
                    <small class="muted">أضف كتباً لحساب توزيع لغات القراءة.</small>
                @endforelse
            </div>
        </div>
    </div>

    <!-- مخططات التنوع المعرفي من أرقام القارئ -->
    @php
        $kdiScores = [
            ['label' => 'التصنيفات', 'value' => (int) $kdi['category_score']],
            ['label' => 'المؤلفون', 'value' => (int) $kdi['author_score']],
            ['label' => 'اللغات', 'value' => (int) $kdi['language_score']],
        ];
        $cx = 120; $cy = 120; $R = 84;
        $angles = [-90, 30, 150];
        $pt = fn ($i, $v) => [round($cx + $R * ($v / 100) * cos(deg2rad($angles[$i])), 1), round($cy + $R * ($v / 100) * sin(deg2rad($angles[$i])), 1)];
        $poly = collect($kdiScores)->map(fn ($s, $i) => implode(',', $pt($i, max(4, $s['value']))))->implode(' ');
        $ringPoly = fn ($lvl) => collect($angles)->keys()->map(fn ($i) => implode(',', $pt($i, $lvl)))->implode(' ');
        $palette = ['#173D36', '#C3A15D', '#2A6F97', '#A5654D', '#6B8F71', '#795B44', '#8A8A8A'];
        $catDist = collect($kdi['categories_distribution'])->sortDesc();
        $catTotal = max(1, $catDist->sum());
        $circ = 2 * M_PI * 15.9155;
        $offset = 0;
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:18px;">
        <div class="panel" style="background:#FFFDF8;border:1px solid #DFD5C2;border-radius:14px;padding:20px;">
            <strong style="font-size:16px;color:var(--ink);display:block;margin-bottom:4px;">بصمة تنوعك المعرفي</strong>
            <small class="muted">كل ضلع يمثل درجتك من 100 في محور من محاور المؤشر.</small>
            <svg viewBox="0 0 240 240" style="width:100%;max-width:300px;display:block;margin:10px auto 0;" role="img" aria-label="مخطط راداري لتنوع التصنيفات والمؤلفين واللغات">
                @foreach([25, 50, 75, 100] as $lvl)
                    <polygon points="{{ $ringPoly($lvl) }}" fill="none" stroke="#E3DAC6" stroke-width="1"/>
                @endforeach
                @foreach($angles as $i => $a)
                    @php [$ex, $ey] = $pt($i, 100); @endphp
                    <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $ex }}" y2="{{ $ey }}" stroke="#E3DAC6" stroke-width="1"/>
                @endforeach
                <polygon points="{{ $poly }}" fill="rgba(195,161,93,.32)" stroke="#C3A15D" stroke-width="2.5" stroke-linejoin="round"/>
                @foreach($kdiScores as $i => $sc)
                    @php [$vx, $vy] = $pt($i, max(4, $sc['value'])); [$lx, $ly] = $pt($i, 118); @endphp
                    <circle cx="{{ $vx }}" cy="{{ $vy }}" r="4.5" fill="#173D36"/>
                    <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="middle" font-size="11" font-weight="700" fill="#173D36">{{ $sc['label'] }}</text>
                    <text x="{{ $lx }}" y="{{ $ly + 13 }}" text-anchor="middle" font-size="11" fill="#7a6a3f">{{ $sc['value'] }}</text>
                @endforeach
            </svg>
        </div>

        <div class="panel" style="background:#FFFDF8;border:1px solid #DFD5C2;border-radius:14px;padding:20px;">
            <strong style="font-size:16px;color:var(--ink);display:block;margin-bottom:4px;">توزيع قراءاتك حسب التصنيف</strong>
            <small class="muted">محسوبة من {{ $kdi['books_count'] }} {{ $kdi['books_count'] === 1 ? 'كتاب' : 'كتب' }} بدأت قراءتها أو أنهيتها من أصل {{ count($myBookIds) }} في مكتبتك ورفوفك. كتب «أريد قراءتها» لا تُحتسب حتى تبدأها.</small>
            @if($catDist->isEmpty())
                <div style="padding:40px 10px;text-align:center;" class="muted">لا توجد بيانات بعد. ابدأ قراءة كتاب ليظهر المخطط.</div>
            @else
                <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;justify-content:center;margin-top:12px;">
                    <svg viewBox="0 0 42 42" style="width:170px;height:170px;transform:rotate(-90deg);" role="img" aria-label="مخطط دائري لتوزيع التصنيفات">
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#EEE6D4" stroke-width="6"/>
                        @foreach($catDist as $name => $count)
                            @php $len = $circ * ($count / $catTotal); @endphp
                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $palette[$loop->index % count($palette)] }}" stroke-width="6"
                                    stroke-dasharray="{{ round($len, 2) }} {{ round($circ - $len, 2) }}" stroke-dashoffset="{{ round(-$offset, 2) }}"/>
                            @php $offset += $len; @endphp
                        @endforeach
                    </svg>
                    <div style="display:grid;gap:7px;font-size:13px;">
                        @foreach($catDist as $name => $count)
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="width:11px;height:11px;border-radius:3px;background:{{ $palette[$loop->index % count($palette)] }};flex:none;"></span>
                                <span>{{ $name }}</span>
                                <b style="margin-inline-start:auto;">{{ round($count / $catTotal * 100) }}%</b>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

<!-- Tab 2: Book Reviews & Ratings (المراجعات والتقييمات) -->
@if($tab === 'reviews')
<section class="panel animate-fade-in delay-2" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <div>
            <h2 style="margin:0;font-size:20px;">المراجعات والتقييمات النقدية</h2>
            <div class="muted" style="font-size:13px;">تجارب القراء وقراءاتهم النقدية للكتب مع تقييم النجوم.</div>
        </div>
        <button type="button" class="primary btn-sm" onclick="openModal('review-modal')">
            <i data-lucide="plus"></i>
            <span>إضافة مراجعة جديدة</span>
        </button>
    </div>

    <!-- Reviews vs Discussions Distinction Note -->
    <div style="background:#FAF8F3;border:1px solid #DFD7C5;border-right:4px solid var(--green);border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <strong style="color:var(--green);font-size:14px;display:block;">تنبيه منهجي حول المراجعات والنقاشات:</strong>
            <span class="muted" style="font-size:12.5px;">المراجعات والتقييمات النقدية بالنجوم في هذه الصفحة عامة وموحدة لكل كتاب عبر المنصة ككل، بينما نقاشات الكتب والأسئلة الحوارية متوفرة ومنفصلة داخل المجتمعات القرائية المتخصصة.</span>
        </div>
        <a href="{{ route('communities.index') }}" class="secondary btn-sm" style="font-size:12px;text-decoration:none;">
            <i data-lucide="messages-square"></i>
            <span>استعراض نقاشات المجالس</span>
        </a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:18px;">
        @forelse($reviews as $rev)
            <div class="review-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                    <div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span class="star-rating-display">
                                @for($i = 1; $i <= 5; $i++)
                                    {!! $i <= $rev->rating ? '★' : '<span style="color:#DCD4C0;">☆</span>' !!}
                                @endfor
                            </span>
                            <span style="font-weight:700;font-size:13px;color:var(--green);">{{ $rev->rating }}/5</span>
                        </div>
                        <h4 style="margin:6px 0 2px;font-size:16px;color:var(--green);">
                            «{{ $rev->book->title ?? 'كتاب' }}»
                        </h4>
                        <small class="muted">{{ $rev->book->author ?? '' }}</small>
                    </div>

                    @if($rev->is_spoiler)
                        <span class="tag" style="background:#FFF1D6;color:#8F5910;font-size:11px;font-weight:700;">
                            <i data-lucide="alert-triangle" style="width:11px;height:11px;"></i>
                            حرق للأحداث
                        </span>
                    @endif
                </div>

                @if($rev->title)
                    <strong style="color:#2C392C;font-size:14.5px;">{{ $rev->title }}</strong>
                @endif

                <div class="spoiler-container {{ $rev->is_spoiler ? '' : 'spoiler-revealed' }}" id="rev-content-{{ $rev->id }}">
                    @if($rev->is_spoiler)
                        <div class="spoiler-overlay-msg" onclick="document.getElementById('rev-content-{{ $rev->id }}').classList.add('spoiler-revealed')" style="position:absolute;inset:0;background:rgba(250,248,243,0.92);backdrop-filter:blur(5px);display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;z-index:2;border-radius:6px;border:1px dashed #C3A15D;padding:12px;text-align:center;">
                            <i data-lucide="eye-off" style="color:var(--gold);margin-bottom:4px;"></i>
                            <span style="font-size:12.5px;font-weight:700;color:var(--green);">هذه المراجعة تتضمن حرقاً للأحداث</span>
                            <small class="muted" style="font-size:11px;">انقر لإظهار نص المراجعة</small>
                        </div>
                    @endif
                    <p class="muted spoiler-blur" style="line-height:1.75;font-size:13.5px;color:#455245;margin:0;{{ $rev->is_spoiler ? 'filter:blur(5px);user-select:none;' : '' }}">
                        {{ $rev->content }}
                    </p>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #EFEAE0;padding-top:12px;margin-top:auto;font-size:12.5px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:24px;height:24px;border-radius:50%;background:var(--green);color:#FFFDF8;display:grid;place-items:center;font-size:11px;font-weight:700;">
                            {{ mb_substr($rev->user->name ?? 'ق', 0, 1) }}
                        </div>
                        <span style="font-weight:600;color:#354435;">{{ $rev->user->name ?? 'قارئ مجهول' }}</span>
                        <span class="muted">· {{ $rev->created_at->diffForHumans() }}</span>
                    </div>

                    <form method="POST" action="{{ route('reading.likeReview', $rev->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" style="background:none;border:none;cursor:pointer;color:#C86D2B;display:flex;align-items:center;gap:4px;font-size:12.5px;padding:2px 6px;border-radius:4px;" title="مراجعة مفيدة">
                            <i data-lucide="heart" style="width:13px;height:13px;"></i>
                            <span>{{ $rev->likes_count }}</span>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1;text-align:center;padding:40px 20px;background:#FAF8F3;border-radius:12px;border:1px dashed #D5C7AA;">
                <i data-lucide="star" style="width:36px;height:36px;color:var(--gold);margin-bottom:10px;"></i>
                <h3 style="margin:0 0 6px;">لا توجد مراجعات نقدية حتى الآن</h3>
                <p class="muted" style="margin-bottom:14px;">كن أول من يكتب مراجعة وتقييماً شاملاً لكتاب قرأته.</p>
                <button type="button" class="primary btn-sm" onclick="openModal('review-modal')">
                    <i data-lucide="plus"></i>
                    <span>كتابة أول مراجعة</span>
                </button>
            </div>
        @endforelse
    </div>
</section>
@endif

<!-- Tab 3: Quotes & Reflections (الاقتباسات والتأملات) -->
@if($tab === 'quotes')
<section class="panel animate-fade-in delay-2" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <div>
            <h2 style="margin:0;font-size:20px;">الاقتباسات والتأملات الملهمة</h2>
            <div class="muted" style="font-size:13px;">ما استوقفك من أفكار ودرر السطور أثناء رحلتك المعرفية.</div>
        </div>
        <button type="button" class="primary btn-sm" onclick="openModal('reflection-modal')">
            <i data-lucide="feather"></i>
            <span>إضافة تأمل أو اقتباس</span>
        </button>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:18px;">
        @forelse($reflections as $ref)
            <div class="quote-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                    <i data-lucide="quote" style="width:20px;height:20px;color:var(--gold);opacity:0.6;"></i>
                    @if($ref->isPrivate())
                        <span class="tag" style="background:#FDE8E8;color:#9B1C1C;font-size:10.5px;font-weight:700;">
                            <i data-lucide="lock" style="width:10px;height:10px;"></i>
                            خاص بك فقط 🔒
                        </span>
                    @else
                        <span class="tag" style="background:#E1ECE2;color:#1B5E20;font-size:10.5px;font-weight:700;">
                            <i data-lucide="globe" style="width:10px;height:10px;"></i>
                            عام في الرواق 🌍
                        </span>
                    @endif
                </div>
                <p style="font-family:'Noto Naskh Arabic';font-size:15px;line-height:1.9;color:#243224;margin:0 0 14px;">
                    «{{ $ref->content }}»
                </p>

                <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #ECE4D4;padding-top:10px;font-size:12px;">
                    <div>
                        <strong style="color:var(--green);display:block;">{{ $ref->book->title ?? 'تأمل حر' }}</strong>
                        <span class="muted">
                            {{ $ref->book ? $ref->book->author : '' }}
                            @if($ref->page_number) · ص {{ $ref->page_number }} @endif
                        </span>
                    </div>
                    <button type="button" onclick="navigator.clipboard.writeText('{{ addslashes($ref->content) }}'); showToast('تم نسخ الاقتباس بنجاح!');" class="secondary btn-sm" style="padding:3px 8px;font-size:11px;" title="نسخ الاقتباس">
                        <i data-lucide="copy" style="width:11px;height:11px;"></i>
                        <span>نسخ</span>
                    </button>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1;text-align:center;padding:40px 20px;background:#FAF8F3;border-radius:12px;border:1px dashed #D5C7AA;">
                <i data-lucide="feather" style="width:36px;height:36px;color:var(--gold);margin-bottom:10px;"></i>
                <h3 style="margin:0 0 6px;">سجل الاقتباسات فارغ</h3>
                <p class="muted" style="margin-bottom:14px;">سجل ما يلفت انتباهك من درر الكلمات أثناء القراءة لترجع إليها دائماً.</p>
                <button type="button" class="primary btn-sm" onclick="openModal('reflection-modal')">
                    <i data-lucide="feather"></i>
                    <span>تدوين أول تأمل</span>
                </button>
            </div>
        @endforelse
    </div>
</section>
@endif

<!-- Tab 4: Reading Series & Tracks (السلاسل والمسارات القرائية) -->
@if($tab === 'series')
<section class="panel animate-fade-in delay-2" style="margin-top:24px;">
    <div style="margin-bottom:20px;">
        <h2 style="margin:0;font-size:20px;">السلاسل والمسارات القرائية المنسقة</h2>
        <div class="muted" style="font-size:13px;">مسارات موضوعية متدرجة صُممت لتبني معرفتك خطوة بخطوة في مختلف الفنون.</div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));gap:20px;">
        @forelse($series as $s)
            <div class="series-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span class="tag" style="background:#E6EFE8;color:var(--green);font-weight:700;">
                            {{ $s->category }}
                        </span>
                        <span class="tag" style="background:#FAF0D6;color:#785419;">
                            المستوى: {{ $s->difficulty }}
                        </span>
                    </div>

                    <h3 style="margin:4px 0 8px;font-size:18px;color:var(--green);">{{ $s->title }}</h3>
                    <p class="muted" style="font-size:13.5px;line-height:1.7;margin-bottom:16px;">
                        {{ $s->description }}
                    </p>

                    <h4 style="font-size:13px;color:#394539;margin-bottom:8px;">الكتب المندرجة في المسار:</h4>
                    <ol style="margin:0 0 16px;padding-right:20px;font-size:13px;color:#4A564A;line-height:1.8;">
                        @foreach($s->books as $sb)
                            <li>
                                <strong>{{ $sb->title }}</strong>
                                <span class="muted">· {{ $sb->author }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div style="border-top:1px solid #ECE4D4;padding-top:14px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12.5px;color:var(--green);font-weight:600;">
                        <i data-lucide="book-check" style="width:13px;height:13px;vertical-align:middle;"></i>
                        {{ $s->books->count() }} كتب متدرجة
                    </span>
                    <button type="button" class="primary btn-sm" onclick="showToast('تم اعتماد المسار «{{ addslashes($s->title) }}» ضمن خطتك القرائية.')">
                        <i data-lucide="play-circle"></i>
                        <span>ابدأ المسار</span>
                    </button>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1;text-align:center;padding:40px 20px;">
                <p class="muted">لا توجد مسارات قرائية حالياً.</p>
            </div>
        @endforelse
    </div>
</section>
@endif

<!-- Tab 5: Achievements & Streaks (الإنجازات وسلاسل الأيام) -->
@if($tab === 'achievements')
<section class="animate-fade-in delay-2" style="margin-top:24px;display:grid;gap:24px;">
    <!-- Streak Banner Widget -->
    <div class="streak-banner">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
            <div>
                <span class="eyebrow" style="color:var(--gold);margin:0;">سلسلة الاستمرار والانضباط</span>
                <h2 style="margin:4px 0 6px;color:#FFFDF8;font-size:24px;">
                    🔥 {{ $streak }} أيام متتالية من القراءة
                </h2>
                <p style="color:#C6D4C8;font-size:14px;margin:0;max-width:560px;line-height:1.7;">
                    القراءة اليومية المنتظمة ولو لـ 15 دقيقة تصنع فارقاً تراكمياً هائلاً. حافظ على توقد شعلتك المعرفية!
                </p>
                <div style="margin-top:12px;display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.08);padding:6px 14px;border-radius:20px;border:1px dashed rgba(195,161,93,0.4);font-size:12px;color:#FFE3A8;">
                    <i data-lucide="shield-check" style="width:14px;height:14px;color:var(--gold);"></i>
                    <span><b>قاعدة التحقق ومنع التلاعب:</b> تشترط الجلسة $\ge 5$ دقائق أو $\ge 2$ صفحة، بدون احتساب تواريخ مستقبلية أو تكرار يومي.</span>
                </div>
            </div>
            <div style="background:rgba(255,255,255,0.12);padding:14px 22px;border-radius:14px;border:1px solid rgba(195,161,93,0.3);text-align:center;">
                <span style="font-size:12px;color:#D8C49D;display:block;">رصيد نقاط المعرفة</span>
                <strong style="font-size:28px;color:#FFFDF8;font-family:'Noto Naskh Arabic';">{{ Auth::user()->points ?? 120 }}</strong>
                <span style="font-size:11px;color:#A9C0AF;display:block;">نقطة تميز</span>
            </div>
        </div>

        <!-- 7-Day Flame Tracker -->
        <div style="display:flex;gap:10px;margin-top:20px;justify-content:center;flex-wrap:wrap;">
            @php
                $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
            @endphp
            @foreach($days as $idx => $d)
                @php $isActiveDay = $idx < min(7, $streak); @endphp
                <div style="background:{{ $isActiveDay ? 'rgba(195,161,93,0.2)' : 'rgba(255,255,255,0.05)' }};border:1px solid {{ $isActiveDay ? 'var(--gold)' : 'rgba(255,255,255,0.1)' }};border-radius:10px;padding:8px 14px;text-align:center;min-width:65px;">
                    <span style="font-size:18px;display:block;">{{ $isActiveDay ? '🔥' : '⚪' }}</span>
                    <small style="color:{{ $isActiveDay ? '#FFE3A8' : '#8CA392' }};font-size:11.5px;font-weight:600;">{{ $d }}</small>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Reader Level & Progression Card (المستويات والمراتب القرائية) -->
    <div class="panel animate-fade-in" style="background:#FFFDF9;border:1px solid #DFD5C2;border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg, #FAF4E6 0%, #E8DFCB 100%);color:var(--gold);display:flex;align-items:center;justify-content:center;border:1px solid var(--gold);box-shadow:0 3px 10px rgba(195,161,93,0.2);">
                    <i data-lucide="{{ $levelData['icon'] ?? 'award' }}" style="width:26px;height:26px;color:var(--gold);"></i>
                </div>
                <div>
                    <span class="eyebrow" style="color:var(--gold);margin:0;">المرتبة القرائية الحالية</span>
                    <h2 style="margin:0;font-size:22px;color:var(--ink);">{{ $levelData['title'] }}</h2>
                </div>
            </div>
            <div style="text-align:left;">
                <span class="tag" style="background:rgba(35,87,56,0.12);color:var(--green);font-size:12.5px;font-weight:700;padding:5px 14px;">
                    المستوى {{ $levelData['number'] }} من 5
                </span>
            </div>
        </div>

        <div style="background:#FAF8F3;border:1px solid #EAE3D2;padding:16px 20px;border-radius:12px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;color:#554E3F;margin-bottom:8px;">
                <span>التقدم نحو الرتبة التالية: <b>{{ $levelData['next_points'] ? ($levelData['next_points'] . ' نقطة') : 'القمة المعرفية ✦' }}</b></span>
                <b style="color:var(--green);font-size:14px;">{{ $levelData['progress_pct'] }}%</b>
            </div>
            <div class="progress liquid-progress" style="--pct:{{ $levelData['progress_pct'] }}%;height:10px;margin:0;">
                <span style="width: {{ $levelData['progress_pct'] }}%;">
                    <span class="liquid-wave-effect"></span>
                </span>
            </div>
            @if($levelData['points_needed'] > 0)
                <small class="muted" style="display:block;margin-top:8px;font-size:11.5px;">
                    متبقي <b>{{ $levelData['points_needed'] }}</b> نقطة لبلوغ المستوى المعرفي القادم. استمر في جلسات القراءة ومناقشة الكتب!
                </small>
            @else
                <small class="muted" style="display:block;margin-top:8px;font-size:11.5px;color:var(--green);font-weight:600;">
                    وصلت إلى أعلى مراتب الرواق القرائية! أنت مصدر إلهام لكل قراء الرواق.
                </small>
            @endif
        </div>

        <!-- 5 Tiers Ladder -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:10px;">
            @php
                $levelsLadder = [
                    ['num' => 1, 'name' => 'قارئ ناشئ', 'pts' => '0 - 99', 'icon' => 'seedling'],
                    ['num' => 2, 'name' => 'قارئ مطلع', 'pts' => '100 - 299', 'icon' => 'book-open'],
                    ['num' => 3, 'name' => 'قارئ متمرس', 'pts' => '300 - 599', 'icon' => 'landmark'],
                    ['num' => 4, 'name' => 'باحث معرفي', 'pts' => '600 - 999', 'icon' => 'scroll'],
                    ['num' => 5, 'name' => 'حكيم الرواق', 'pts' => '1000+', 'icon' => 'crown'],
                ];
            @endphp
            @foreach($levelsLadder as $lvl)
                @php $isCurrentOrPast = $levelData['number'] >= $lvl['num']; @endphp
                <div style="border-radius:10px;padding:10px;text-align:center;border:1px solid {{ $isCurrentOrPast ? 'var(--gold)' : '#E8E2D2' }};background:{{ $isCurrentOrPast ? 'linear-gradient(180deg, #FFFDF8 0%, #F5EFE0 100%)' : '#FAF8F3' }};opacity:{{ $isCurrentOrPast ? '1' : '0.6' }};">
                    <span style="font-size:11px;font-weight:700;color:{{ $isCurrentOrPast ? 'var(--green)' : '#888' }};display:block;">مستوى {{ $lvl['num'] }}</span>
                    <strong style="display:block;font-size:12.5px;color:var(--ink);margin:2px 0;">{{ $lvl['name'] }}</strong>
                    <small style="font-size:10.5px;color:#786E58;">{{ $lvl['pts'] }} نقطة</small>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Annual Knowledge Archive & Immutable Records -->
    <div class="panel animate-fade-in" style="background:#FFFDF9;border:1px solid #DFD5C2;border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="archive" style="width:22px;height:22px;color:var(--gold);"></i>
                    <h2 style="margin:0;font-size:20px;color:var(--ink);">السجل السنوي والأرشيف المعرفي المحفوظ</h2>
                </div>
                <p class="muted" style="margin:4px 0 0;font-size:13px;">إنجازاتك السنوية تبقى محفوظة وثابتة في أرشيفك حتى عند تعديل أو تغيير أهدافك المستقبلية.</p>
            </div>
            <button type="button" onclick="openModal('goal-modal')" class="primary btn-sm">
                <i data-lucide="edit-3"></i>
                <span>تعديل أهداف عام {{ date('Y') }}</span>
            </button>
        </div>

        <!-- Current Year Card -->
        <div style="background:#FAF8F3;border:1.5px solid var(--gold);border-radius:14px;padding:18px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                <span class="tag" style="background:var(--green);color:#FFFDF8;font-weight:700;">العام الجاري · {{ date('Y') }}</span>
                <span style="font-size:12.5px;color:#78694B;font-weight:600;">مؤشر التنوع المعرفي المسجل: <b>{{ $yearlyGoal->knowledge_diversity_score }}%</b></span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:14px;">
                <div style="background:#FFFDF9;padding:12px;border-radius:10px;border:1px solid #EBE4D5;">
                    <small class="muted" style="display:block;font-size:11.5px;">الكتب المنجزة</small>
                    <strong style="font-size:20px;color:var(--green);font-family:'Noto Naskh Arabic';">{{ $yearlyGoal->achieved_books }} / {{ $yearlyGoal->target_books }}</strong>
                    <div class="progress" style="height:5px;margin-top:6px;"><span style="width: {{ $yearlyGoal->percentage_books }}%;"></span></div>
                </div>
                <div style="background:#FFFDF9;padding:12px;border-radius:10px;border:1px solid #EBE4D5;">
                    <small class="muted" style="display:block;font-size:11.5px;">الصفحات المقروءة</small>
                    <strong style="font-size:20px;color:var(--gold);font-family:'Noto Naskh Arabic';">{{ $yearlyGoal->achieved_pages }} {{ $yearlyGoal->target_pages ? '/ ' . $yearlyGoal->target_pages : 'صفحة' }}</strong>
                    @if($yearlyGoal->target_pages)
                        <div class="progress" style="height:5px;margin-top:6px;"><span style="width: {{ $yearlyGoal->percentage_pages }}%;"></span></div>
                    @endif
                </div>
                <div style="background:#FFFDF9;padding:12px;border-radius:10px;border:1px solid #EBE4D5;">
                    <small class="muted" style="display:block;font-size:11.5px;">ساعات القراءة</small>
                    <strong style="font-size:20px;color:var(--ink);font-family:'Noto Naskh Arabic';">{{ round($yearlyGoal->achieved_minutes / 60, 1) }} {{ $yearlyGoal->target_hours ? '/ ' . $yearlyGoal->target_hours : 'ساعة' }}</strong>
                    @if($yearlyGoal->target_hours)
                        <div class="progress" style="height:5px;margin-top:6px;"><span style="width: {{ $yearlyGoal->percentage_hours }}%;"></span></div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Historical Archive -->
        @if(isset($historicalGoals) && $historicalGoals->count() > 0)
            <h3 style="font-size:16px;margin:0 0 12px;color:var(--green);">الأعوام السابقة المؤرشفة</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:14px;">
                @foreach($historicalGoals as $hg)
                    <div style="background:#FAF8F3;border:1px solid #E5DFCF;border-radius:12px;padding:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <strong style="font-size:16px;color:var(--green);">عام {{ $hg->year }}</strong>
                            <span class="tag" style="background:#E8E2D4;color:#5D5340;font-size:11px;">مؤرشف ومحفوظ قطعي ✓</span>
                        </div>
                        <div style="font-size:13px;line-height:1.7;color:#4A564A;">
                            <div>الكتب المقروءة: <b>{{ $hg->achieved_books }}</b> من أصل مستهدف {{ $hg->target_books }}</div>
                            <div>إجمالي الصفحات: <b>{{ $hg->achieved_pages }}</b> صفحة</div>
                            <div>الوقت: <b>{{ round($hg->achieved_minutes / 60, 1) }}</b> ساعة</div>
                            <div>مؤشر التنوع المعرفي: <b>{{ $hg->knowledge_diversity_score }}%</b></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align:center;padding:16px;background:#FAF8F3;border-radius:10px;border:1px dashed #D5C7AA;font-size:13px;" class="muted">
                سجل عام {{ date('Y') }} هو عامك الأول النشط. في نهاية العام سيتم أرشفة إنجازاتك تلقائياً لتبقى مرجعاً راسخاً في تاريخك المعرفي.
            </div>
        @endif
    </div>

    <!-- Community Badges Showcase (أوسمة المجالس الشرفية) -->
    @if(isset($communityBadges) && $communityBadges->count() > 0)
    <div class="panel animate-fade-in" style="background:#FFFDF9;border:1px solid #DFD5C2;border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(195,161,93,0.15);color:var(--gold);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="crown" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:19px;">أوسمة المجالس الشرفية</h2>
            </div>
            <span class="tag" style="background:#EBF3ED;color:var(--green);font-weight:700;">{{ $communityBadges->count() }} أوسمة مجتمعية</span>
        </div>
        <p class="muted" style="margin-bottom:16px;font-size:13px;">أوسمة وتكريمات خاصة منحتها لك إدارات المجالس تقديراً لإسهاماتك وحواراتك الراقية.</p>

        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:14px;">
            @foreach($communityBadges as $cb)
                <div style="background:#FAF8F3;border:1px solid {{ $cb->color ?? '#C3A15D' }};border-radius:12px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                        <span class="tag" style="background:rgba(195,161,93,0.15);color:#785419;font-size:11px;">
                            {{ $cb->community->name ?? 'مجلس الرواق' }}
                        </span>
                        <small class="muted" style="font-size:11px;">{{ \Carbon\Carbon::parse($cb->pivot->awarded_at)->diffForHumans() }}</small>
                    </div>
                    <strong style="display:block;font-size:15px;color:var(--green);margin-bottom:4px;">{{ $cb->name }}</strong>
                    <p style="margin:0 0 8px;font-size:12px;color:#554E3F;line-height:1.5;">{{ $cb->description }}</p>
                    @if($cb->pivot->reason)
                        <div style="background:#FFFDF9;border-right:3px solid var(--gold);padding:6px 10px;font-size:11.5px;color:#6A604F;border-radius:0 6px 6px 0;">
                            <b>سبب التتويج:</b> {{ $cb->pivot->reason }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Global Badges Showcase (الأوسمة العامة لمنصة رِواق) -->
    <div class="panel">
        <h2 style="margin:0 0 4px;font-size:20px;">الأوسمة والإنجازات الشرفية</h2>
        <p class="muted" style="margin-bottom:18px;font-size:13px;">تُمنح هذه الأوسمة تكريماً لالتزامك وتعمقك القرائي.</p>

        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:16px;">
            @foreach($allBadges as $b)
                @php
                    $isEarned = $earnedBadges->contains('id', $b->id);
                @endphp
                <div class="panel {{ $isEarned ? 'badge-unlocked' : 'badge-locked' }}" style="padding:16px;text-align:center;">
                    <div style="font-size:32px;margin-bottom:6px;">
                        @if($b->icon === 'crown') 👑
                        @elseif($b->icon === 'flame') 🔥
                        @elseif($b->icon === 'feather') 🪶
                        @elseif($b->icon === 'book-open') 📖
                        @elseif($b->icon === 'message-square') 💬
                        @elseif($b->icon === 'calendar-check') 📅
                        @elseif($b->icon === 'library') 🏛️
                        @elseif($b->icon === 'share-2') 🌿
                        @else 🏅
                        @endif
                    </div>
                    <strong style="display:block;font-size:15px;color:var(--green);margin-bottom:4px;">{{ $b->name }}</strong>
                    <p class="muted" style="font-size:12px;margin:0 0 10px;line-height:1.5;">{{ $b->description }}</p>
                    @if($isEarned)
                        <span class="tag" style="background:#E1ECE2;color:#235738;font-size:11px;font-weight:700;">
                            <i data-lucide="check" style="width:11px;height:11px;"></i>
                            وسام محقق ✓
                        </span>
                    @else
                        <span class="tag" style="background:#EFECE4;color:#786E5E;font-size:11px;">
                            <i data-lucide="lock" style="width:11px;height:11px;"></i>
                            يتطلب {{ $b->points_required }} نقطة
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Points Ledger & Transactions (سجل حركات ونقاط الأثر) -->
    @if(isset($pointTransactions) && $pointTransactions->count() > 0)
    <div class="panel animate-fade-in" style="background:#FAF8F3;border:1px solid #DFD5C2;border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(35,87,56,0.1);color:var(--green);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="history" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">سجل حركات ونقاط الأثر القرائي</h2>
            </div>
            <span class="tag" style="background:#FFFDF8;border:1px solid var(--gold);color:#785419;font-weight:700;">
                إجمالي: {{ Auth::user()->points }} نقطة
            </span>
        </div>
        <div style="display:grid;gap:8px;">
            @foreach($pointTransactions as $pt)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#FFFDF9;border:1px solid #EAE3D2;border-radius:10px;">
                    <div>
                        <strong style="font-size:13px;color:var(--ink);display:block;">{{ $pt->description }}</strong>
                        <small class="muted" style="font-size:11px;">{{ $pt->activityLabel() }} · {{ $pt->created_at->diffForHumans() }}</small>
                    </div>
                    <span class="tag" style="background:#E1ECE2;color:#1B5E20;font-weight:700;font-size:12.5px;">
                        +{{ $pt->points }} أثر
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</section>
@endif
@endsection

@section('modals')
<!-- Session Recorder Modal (Required for test: stopwatch-box, breath-focus-ring, triggerPointsCelebration) -->
@if($currentBookRecord && $currentBookRecord->book)
<div id="session-modal" class="overlay" style="display:none;">
    <section class="dialog reading-modal-dialog" role="dialog" aria-modal="true" style="width:560px;max-width:95vw;max-height:min(90vh, 780px);overflow-y:auto;">
        <div class="heading" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h2 style="margin:0;font-size:20px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">سجّل لحظتك مع الكتاب</h2>
                <p class="muted" style="margin:4px 0 0;font-size:12.5px;">
                    {{ $currentBookRecord->book->title }} · توقّفت عند الصفحة {{ $currentBookRecord->current_page }}
                </p>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('session-modal')" title="إغلاق">&times;</button>
        </div>

        <!-- Live Stopwatch Animation Widget with Breath Focus Ring -->
        <div class="stopwatch-box" style="margin-bottom:18px;padding:18px;background:linear-gradient(180deg, #FAF8F2 0%, #F5EFE1 100%);border:1px dashed #D6C9B2;border-radius:14px;position:relative;">
            <div class="breath-focus-ring" aria-hidden="true"></div>
            
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;position:relative;z-index:2;flex-wrap:wrap;gap:8px;">
                <span class="eyebrow" style="font-size:11px;color:var(--gold);margin:0;display:inline-flex;align-items:center;gap:4px;">
                    ⏱️ المؤقت التفاعلي للجلسة
                </span>
                <!-- Mode Selector -->
                <div class="timer-mode-toggle" style="display:inline-flex;background:rgba(23,61,54,0.06);padding:2px;border-radius:8px;font-size:11.5px;font-weight:600;">
                    <button type="button" id="timer-mode-countdown" class="timer-mode-btn active" style="padding:3px 10px;border-radius:6px;border:none;background:var(--green);color:#fff;cursor:pointer;font-family:inherit;font-size:11px;transition:all 0.2s;">
                        ⏳ مؤقت تنازلي (Focus)
                    </button>
                    <button type="button" id="timer-mode-stopwatch" class="timer-mode-btn" style="padding:3px 10px;border-radius:6px;border:none;background:transparent;color:var(--ink);cursor:pointer;font-family:inherit;font-size:11px;transition:all 0.2s;">
                        ⏱️ ساعة إيقاف
                    </button>
                </div>
            </div>

            <!-- Quick Duration Presets -->
            <div class="timer-presets" style="display:flex;gap:6px;justify-content:center;margin-bottom:8px;position:relative;z-index:2;flex-wrap:wrap;">
                <button type="button" class="timer-preset-btn" data-minutes="15" style="padding:4px 10px;border-radius:6px;border:1px solid #D0C5B0;background:#FFFDF8;font-size:11.5px;cursor:pointer;color:var(--ink);font-family:inherit;transition:all 0.15s;">15 د</button>
                <button type="button" class="timer-preset-btn" data-minutes="20" style="padding:4px 10px;border-radius:6px;border:1px solid #D0C5B0;background:#FFFDF8;font-size:11.5px;cursor:pointer;color:var(--ink);font-family:inherit;transition:all 0.15s;">20 د</button>
                <button type="button" class="timer-preset-btn active" data-minutes="25" style="padding:4px 10px;border-radius:6px;border:1px solid var(--green);background:rgba(23,61,54,0.09);font-size:11.5px;cursor:pointer;color:var(--green);font-weight:700;font-family:inherit;transition:all 0.15s;">25 د (بومودورو)</button>
                <button type="button" class="timer-preset-btn" data-minutes="30" style="padding:4px 10px;border-radius:6px;border:1px solid #D0C5B0;background:#FFFDF8;font-size:11.5px;cursor:pointer;color:var(--ink);font-family:inherit;transition:all 0.15s;">30 د</button>
                <button type="button" class="timer-preset-btn" data-minutes="45" style="padding:4px 10px;border-radius:6px;border:1px solid #D0C5B0;background:#FFFDF8;font-size:11.5px;cursor:pointer;color:var(--ink);font-family:inherit;transition:all 0.15s;">45 د</button>
            </div>

            <div class="stopwatch-digits" id="stopwatch-digits" style="font-size:38px;font-family:'Courier New', monospace;font-weight:800;color:var(--green);letter-spacing:3px;margin:2px 0 10px;position:relative;z-index:2;">25:00</div>
            
            <div style="display:flex;gap:10px;justify-content:center;position:relative;z-index:2;">
                <button type="button" class="secondary" id="stopwatch-toggle" style="min-height:36px;padding:6px 18px;background:#FFFDF8;border-color:#C3A15D;color:#173D36;font-weight:700;border-radius:8px;display:inline-flex;align-items:center;gap:6px;">
                    <i data-lucide="play" style="width:14px;height:14px;"></i>
                    <span>تشغيل المؤقت</span>
                </button>
                <button type="button" class="secondary" id="stopwatch-reset" title="إعادة تعيين المؤقت" style="min-height:36px;padding:6px 12px;background:#FFFDF8;border-color:#D0C5B0;border-radius:8px;">
                    <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                </button>
            </div>
            <div id="timer-status-hint" style="font-size:11.5px;color:var(--ink-light,#777);margin-top:8px;position:relative;z-index:2;">
                مدة الجلسة المحددة: <span id="timer-hint-minutes" style="font-weight:700;color:var(--green);">25</span> دقيقة
            </div>
        </div>

        <form action="{{ route('reading.storeSession') }}" method="POST" onsubmit="triggerPointsCelebration(25, 'أثر جلسة قراءة جديدة')">
            @csrf
            <div style="display:grid;gap:14px;">
                <div style="display:grid;grid-template-columns:minmax(0, 1fr) minmax(0, 1fr);gap:12px;">
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">من صفحة (البداية)</span>
                        <input name="start_page" type="number" min="0" max="{{ $currentBookRecord->book->total_pages }}" value="{{ $currentBookRecord->current_page }}" style="box-sizing:border-box;width:100%;">
                    </label>
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">وصلت إلى صفحة</span>
                        <input name="page" type="number" min="1" max="{{ $currentBookRecord->book->total_pages }}" value="{{ min($currentBookRecord->book->total_pages, $currentBookRecord->current_page + 16) }}" required style="box-sizing:border-box;width:100%;">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:minmax(0, 1fr) minmax(0, 1fr);gap:12px;">
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">مدة القراءة بالدقائق</span>
                        <input name="minutes" id="session-minutes-input" type="number" min="1" max="1440" value="25" required style="box-sizing:border-box;width:100%;">
                    </label>
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">وسيلة القراءة / الجهاز</span>
                        <select name="device" required style="box-sizing:border-box;width:100%;">
                            <option value="كتاب ورقي">📖 كتاب ورقي مطبوع</option>
                            <option value="قارئ إلكتروني Kindle">📱 قارئ إلكتروني Kindle</option>
                            <option value="جهاز لوحي">📟 جهاز لوحي (iPad / Tablet)</option>
                            <option value="هاتف ذكي">📱 هاتف ذكي</option>
                            <option value="حاسوب">💻 حاسوب</option>
                        </select>
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:minmax(0, 1fr) minmax(0, 1fr);gap:12px;">
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">وقت البدء (اختياري)</span>
                        <input name="started_at" type="datetime-local" style="box-sizing:border-box;width:100%;">
                    </label>
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">وقت الانتهاء (اختياري)</span>
                        <input name="ended_at" type="datetime-local" style="box-sizing:border-box;width:100%;">
                    </label>
                </div>

                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">ما الذي بقي معك من الجلسة؟</span>
                    <textarea name="note" placeholder="فكرة أو خاطرة أو اقتباس استوقفك…" rows="2" style="box-sizing:border-box;width:100%;"></textarea>
                </label>

                <button type="submit" class="primary" style="width:100%;margin-top:4px;padding:12px;font-weight:700;font-size:14px;border-radius:10px;background:var(--green);color:#FFFDF8;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(23,61,54,0.25);">
                    <i data-lucide="save" style="width:16px;height:16px;"></i>
                    <span>حفظ الجلسة وتحديث التقدّم (+25 أثر)</span>
                </button>
            </div>
        </form>
    </section>
</div>
@endif

<!-- Reflection Note Modal -->
<div id="reflection-modal" class="overlay" style="display:none;">
    <section class="dialog reading-modal-dialog" role="dialog" aria-modal="true" style="width:540px;max-width:95vw;max-height:min(90vh, 760px);overflow-y:auto;">
        <div class="heading" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h2 style="margin:0;font-size:20px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">تأمّل من قراءتك</h2>
                <p class="muted" style="margin:4px 0 0;font-size:12.5px;">دوّن أثر الكلمات وخاطرة ألهمتك في رحلتك المعرفية</p>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('reflection-modal')" title="إغلاق">&times;</button>
        </div>
        <form action="{{ route('reading.storeReflection') }}" method="POST" onsubmit="triggerPointsCelebration(15, 'تأمل فكري محفوظ')">
            @csrf
            <div style="display:grid;gap:14px;">
                @if(isset($allBooks) && $allBooks->count() > 0)
                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">الكتاب المرتبط بالتأمل (اختياري)</span>
                    <select name="book_id" data-book-picker style="box-sizing:border-box;width:100%;">
                        <option value="">-- تأمل حر عام --</option>
                        @if($currentBookRecord && $currentBookRecord->book)
                            <option value="{{ $currentBookRecord->book_id }}" selected>{{ $currentBookRecord->book->title }} — {{ $currentBookRecord->book->author }}</option>
                        @endif
                    </select>
                </label>
                @endif

                <div style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">خصوصية التأمل والاقتباس</span>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 12px;background:#FAF8F3;border:1px solid #DFD7C5;border-radius:8px;flex:1;min-width:160px;">
                            <input type="radio" name="privacy" value="public" checked style="width:auto;">
                            <span style="font-size:12.5px;">🌍 عام ومشترك في الرواق</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 12px;background:#FAF8F3;border:1px solid #DFD7C5;border-radius:8px;flex:1;min-width:160px;">
                            <input type="radio" name="privacy" value="private" style="width:auto;">
                            <span style="font-size:12.5px;">🔒 خاص بي وسري</span>
                        </label>
                    </div>
                    <small class="muted" style="font-size:11px;">التأمل الخاص لا يظهر لباقي القراء في الرواق ولا ينشر في شريط النشاط الاجتماعي.</small>
                </div>

                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">تأمّلك الخاص أو الاقتباس الملهم</span>
                    <textarea name="note" placeholder="اكتب ما تركته الكلمات في نفسك أو الاقتباس الذي استوقفك…" rows="4" required style="box-sizing:border-box;width:100%;"></textarea>
                </label>

                <button type="submit" class="primary" style="width:100%;padding:11px;font-weight:700;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <i data-lucide="check" style="width:16px;height:16px;"></i>
                    <span>حفظ في سجل الأثر (+15 نقطة)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Book Review Modal -->
<div id="review-modal" class="overlay" style="display:none;">
    <section class="dialog reading-modal-dialog" role="dialog" aria-modal="true" style="width:560px;max-width:95vw;max-height:min(90vh, 780px);overflow-y:auto;">
        <div class="heading" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h2 style="margin:0;font-size:20px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">اكتب مراجعة وتقييماً نقدياً</h2>
                <p class="muted" style="margin:4px 0 0;font-size:12.5px;">شارك رأيك القرائي وانطباعك لمساعدة الرفقاء في اختيار كتبهم</p>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('review-modal')" title="إغلاق">&times;</button>
        </div>
        <form action="{{ route('reading.storeReview') }}" method="POST" onsubmit="triggerPointsCelebration(25, 'مراجعة نقدية منشورة')">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">اختر الكتاب</span>
                    <select name="book_id" data-book-picker data-book-required style="box-sizing:border-box;width:100%;">
                        <option value="">اختر الكتاب…</option>
                        @if($currentBookRecord && $currentBookRecord->book)
                            <option value="{{ $currentBookRecord->book_id }}" selected>{{ $currentBookRecord->book->title }} — {{ $currentBookRecord->book->author }}</option>
                        @endif
                    </select>
                </label>

                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">التقييم بالنجوم (1 إلى 5)</span>
                    <select name="rating" required style="font-size:15px;box-sizing:border-box;width:100%;">
                        <option value="5">★★★★★ (5 من 5 - تحفة تستحق القراءة)</option>
                        <option value="4" selected>★★★★☆ (4 من 5 - كتاب ممتاز)</option>
                        <option value="3">★★★☆☆ (3 من 5 - جيد ومفيد)</option>
                        <option value="2">★★☆☆☆ (2 من 5 - دون المأمول)</option>
                        <option value="1">★☆☆☆☆ (1 من 5 - لم يناسبني)</option>
                    </select>
                </label>

                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">عنوان المراجعة (اختياري)</span>
                    <input name="title" type="text" placeholder="مثال: قراءة عميقة بين سطور البلاغة والفكر..." style="box-sizing:border-box;width:100%;">
                </label>

                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">نص المراجعة النقدية</span>
                    <textarea name="content" rows="4" placeholder="شارك تحليلك، أسلوب الكاتب، ما أضافه لك الكتاب، ونقاط القوة والضعف..." required style="box-sizing:border-box;width:100%;"></textarea>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;background:#FAF8F3;padding:10px 14px;border-radius:10px;border:1px dashed #C3A15D;min-width:0;">
                    <input type="checkbox" name="is_spoiler" value="1" style="width:16px;height:16px;margin-top:2px;accent-color:var(--gold);">
                    <div>
                        <strong style="display:block;font-size:12.5px;color:#8C6D2C;">⚠️ تحذير حرق الأحداث (Spoiler Alert)</strong>
                        <small class="muted" style="font-size:11px;display:block;margin-top:2px;">حدد هذا الخيار إذا كانت مراجعتك تكشف حبكة أو نهاية الكتاب لحماية القراء.</small>
                    </div>
                </label>

                <button type="submit" class="primary" style="width:100%;margin-top:4px;padding:11px;font-weight:700;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <i data-lucide="send" style="width:16px;height:16px;"></i>
                    <span>نشر المراجعة (+25 نقطة)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Annual Goal Modal -->
<div id="goal-modal" class="overlay" style="display:none;">
    <section class="dialog reading-modal-dialog" role="dialog" aria-modal="true" style="width:500px;max-width:95vw;max-height:min(90vh, 760px);overflow-y:auto;">
        <div class="heading" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h2 style="margin:0;font-size:20px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">تحديد هدفك القرائي السنوي</h2>
                <p class="muted" style="margin:4px 0 0;font-size:12.5px;">خطّتك المعرفية لعام {{ date('Y') }}</p>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('goal-modal')" title="إغلاق">&times;</button>
        </div>
        <form action="{{ route('reading.updateGoal') }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <p class="muted" style="font-size:13px;line-height:1.7;margin:0;">
                    حدد مستهدفاتك القرائية لعام {{ date('Y') }}. يمكنك تحديد مستهدف بالكتب، والصفحات، وساعات القراءة، مع ضمان حفظ إنجازاتك في الأرشيف حتى لو تغير الهدف لاحقاً.
                </p>
                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">عدد الكتب المستهدفة سنوياً</span>
                    <input name="goal" type="number" min="1" max="500" value="{{ $goal }}" required style="font-size:18px;font-weight:700;box-sizing:border-box;width:100%;">
                </label>

                <div style="display:grid;grid-template-columns:minmax(0, 1fr) minmax(0, 1fr);gap:12px;">
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">مستهدف الصفحات (اختياري)</span>
                        <input name="target_pages" type="number" min="1" max="100000" value="{{ $yearlyGoal->target_pages }}" placeholder="مثلاً: 5000" style="box-sizing:border-box;width:100%;">
                    </label>
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">مستهدف الساعات (اختياري)</span>
                        <input name="target_hours" type="number" min="1" max="5000" value="{{ $yearlyGoal->target_hours }}" placeholder="مثلاً: 120" style="box-sizing:border-box;width:100%;">
                    </label>
                </div>

                <div style="background:#FAF8F3;border:1px dashed #C3A15D;border-radius:10px;padding:12px;font-size:12px;color:#78694B;display:flex;align-items:flex-start;gap:8px;">
                    <i data-lucide="shield-check" style="width:16px;height:16px;color:var(--gold);flex-shrink:0;margin-top:2px;"></i>
                    <span><b>ضمان الأرشيف المعرفي:</b> تعديل الهدف السنوي يحدّث هدف العام الحالي فقط، ولا يمس أو يعيد تصفير أي إنجازات سابقة في سجلك المعرفي التاريخي.</span>
                </div>

                <button type="submit" class="primary" style="width:100%;padding:11px;font-weight:700;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <i data-lucide="check" style="width:16px;height:16px;"></i>
                    <span>حفظ وتحديث الهدف</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Switch Book Modal -->
<div id="switch-book-modal" class="overlay" style="display:none;">
    <section class="dialog reading-modal-dialog" role="dialog" aria-modal="true" style="width:500px;max-width:95vw;max-height:min(90vh, 760px);overflow-y:auto;">
        <div class="heading" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h2 style="margin:0;font-size:20px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">تبديل قراءتك الحالية</h2>
                <p class="muted" style="margin:4px 0 0;font-size:12.5px;">اختر الكتاب الذي تقرأه الآن لتوثيق الجلسات والصفحات في سجله</p>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('switch-book-modal')" title="إغلاق">&times;</button>
        </div>
        <form action="{{ route('reading.switchBook') }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">اختر من مكتبة رواق</span>
                    <select name="book_id" data-book-picker data-book-required style="box-sizing:border-box;width:100%;">
                        <option value="">اختر الكتاب…</option>
                        @if($currentBookRecord && $currentBookRecord->book)
                            <option value="{{ $currentBookRecord->book_id }}" selected>{{ $currentBookRecord->book->title }} — {{ $currentBookRecord->book->author }}</option>
                        @endif
                    </select>
                </label>
                <button type="submit" class="primary" style="width:100%;padding:11px;font-weight:700;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <i data-lucide="check" style="width:16px;height:16px;"></i>
                    <span>تعيين كقراءة حالية نشطة</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Edit / Add Book Status Modal -->
<div id="status-modal" class="overlay" style="display:none;">
    <section class="dialog reading-modal-dialog" role="dialog" aria-modal="true" style="width:520px;max-width:95vw;max-height:min(90vh, 760px);overflow-y:auto;">
        <div class="heading" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h2 style="margin:0;font-size:20px;color:var(--green);font-family:'Noto Naskh Arabic',serif;" id="status-modal-title">تحديث حالة الكتاب والتقدم</h2>
                <p class="muted" style="margin:4px 0 0;font-size:12.5px;">تعديل موقع الكتاب في رفوفك المعرفية وتحديث صفحات القراءة</p>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('status-modal')" title="إغلاق">&times;</button>
        </div>
        <form action="{{ route('reading.updateBookStatus') }}" method="POST">
            @csrf
            <input type="hidden" name="book_id" id="status-modal-book-id" value="">
            <div style="display:grid;gap:14px;">
                <div id="status-modal-book-picker" style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">الكتاب</span>
                    <select id="status-modal-book-select" data-book-picker onchange="onStatusBookChange(this)" style="box-sizing:border-box;width:100%;">
                        <option value="">اختر كتابًا…</option>
                        <option value="__new__">＋ كتاب جديد غير موجود في القائمة…</option>
                    </select>

                    <div id="status-modal-new-book" style="display:none;gap:10px;margin-top:6px;padding:12px;background:#F9F6EE;border:1px solid #E6DCC8;border-radius:10px;">
                        <input type="text" name="new_title" id="status-modal-new-title" placeholder="عنوان الكتاب *" maxlength="255" style="box-sizing:border-box;width:100%;">
                        <input type="text" name="new_author" placeholder="اسم المؤلف" maxlength="255" style="box-sizing:border-box;width:100%;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <input type="number" name="new_pages" min="1" max="5000" placeholder="عدد الصفحات" style="box-sizing:border-box;width:100%;">
                            <select name="new_category" style="box-sizing:border-box;width:100%;">
                                @foreach(['أدب', 'سيرة', 'تاريخ', 'فكر', 'فلسفة', 'شعر'] as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">حالة الكتاب في خطتك</span>
                    <select name="status" id="status-modal-status" required style="box-sizing:border-box;width:100%;">
                        <option value="currently_reading">📖 أقرأ الآن (قراءة نشطة)</option>
                        <option value="want_to_read">🔖 أرغب بقراءته (قائمة الأمنيات)</option>
                        <option value="completed">✅ تم الانتهاء من قراءته</option>
                        <option value="on_hold">⏸️ متوقف مؤقتاً</option>
                        <option value="did_not_finish">⏹️ لم أكمله (DNF)</option>
                    </select>
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:6px;min-width:0;" id="status-modal-page-field">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">الصفحة الحالية</span>
                        <input type="number" name="current_page" id="status-modal-page" min="0" value="0" style="box-sizing:border-box;width:100%;">
                    </label>
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">التقييم الشخصي (1-5)</span>
                        <select name="rating" id="status-modal-rating" style="box-sizing:border-box;width:100%;">
                            <option value="">-- بدون تقييم --</option>
                            <option value="5">★★★★★ (5/5)</option>
                            <option value="4">★★★★☆ (4/5)</option>
                            <option value="3">★★★☆☆ (3/5)</option>
                            <option value="2">★★☆☆☆ (2/5)</option>
                            <option value="1">★☆☆☆☆ (1/5)</option>
                        </select>
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">تاريخ البدء</span>
                        <input name="start_date" id="status-modal-start-date" type="date" style="box-sizing:border-box;width:100%;">
                    </label>
                    <label style="display:grid;gap:6px;min-width:0;">
                        <span style="font-size:12.5px;font-weight:600;color:var(--ink);">تاريخ الإنهاء</span>
                        <input name="finish_date" id="status-modal-finish-date" type="date" style="box-sizing:border-box;width:100%;">
                    </label>
                </div>

                <label style="display:grid;gap:6px;min-width:0;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--ink);">ملاحظات شخصية وخاطرة حول الكتاب</span>
                    <textarea name="notes" id="status-modal-notes" rows="2" placeholder="ملاحظات شخصية أو أسباب التوقف أو ملخص عام..." style="box-sizing:border-box;width:100%;"></textarea>
                </label>

                <button type="submit" class="primary" style="width:100%;margin-top:6px;padding:11px;font-weight:700;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <i data-lucide="check" style="width:16px;height:16px;"></i>
                    <span>حفظ وتحديث الرف</span>
                </button>
            </div>
        </form>
    </section>
</div>

<script>
function openEditStatusModal(bookId, status, page, startDate, finishDate, rating, notes) {
    document.getElementById('status-modal-title').innerText = 'تحديث حالة الكتاب والتقدم';
    document.getElementById('status-modal-book-id').value = bookId;
    if (document.getElementById('status-modal-book-select')) {
        document.getElementById('status-modal-book-select').value = bookId;
        document.getElementById('status-modal-book-picker').style.display = 'none';
    }
    if (document.getElementById('status-modal-status')) {
        document.getElementById('status-modal-status').value = status || 'currently_reading';
    }
    if (document.getElementById('status-modal-page')) {
        document.getElementById('status-modal-page').value = page || 0;
    }
    if (document.getElementById('status-modal-start-date')) {
        document.getElementById('status-modal-start-date').value = startDate || '';
    }
    if (document.getElementById('status-modal-finish-date')) {
        document.getElementById('status-modal-finish-date').value = finishDate || '';
    }
    if (document.getElementById('status-modal-rating')) {
        document.getElementById('status-modal-rating').value = rating || '';
    }
    if (document.getElementById('status-modal-notes')) {
        document.getElementById('status-modal-notes').value = notes || '';
    }
    openModal('status-modal');
}

function onStatusBookChange(select) {
    var isNew = select.value === '__new__';
    var box = document.getElementById('status-modal-new-book');
    var title = document.getElementById('status-modal-new-title');
    box.style.display = isNew ? 'grid' : 'none';
    title.required = isNew;
    document.getElementById('status-modal-book-id').value = isNew ? '' : select.value;
    if (isNew) { title.focus(); }
}

function onReadingBookFilter(select) {
    if (select.value === '__add__') {
        select.value = '{{ $bookFilterId ?: '' }}';
        openAddStatusModal();
        return;
    }
    select.form.submit();
}

function openAddStatusModal() {
    document.getElementById('status-modal-title').innerText = 'إضافة كتاب جديد إلى الرفوف';
    var select = document.getElementById('status-modal-book-select');
    if (select) {
        document.getElementById('status-modal-book-picker').style.display = 'grid';
        select.value = '';
        if (select._bookPickerRefresh) { select._bookPickerRefresh(); }
        onStatusBookChange(select);
    }
    document.getElementById('status-modal-status').value = 'want_to_read';
    document.getElementById('status-modal-page').value = 0;
    document.getElementById('status-modal-start-date').value = '';
    document.getElementById('status-modal-finish-date').value = '';
    document.getElementById('status-modal-rating').value = '';
    document.getElementById('status-modal-notes').value = '';
    openModal('status-modal');
}
</script>
@endsection
