@extends('layouts.app')

@section('title', 'مساحة القارئ')
@section('crumb', 'مساحة القارئ')

@section('content')
<div class="home-sanctuary-header animate-fade-in">
    <div class="header-main-info">
        <div class="date-capsule">
            <span class="capsule-icon">🌿</span>
            <span>رِواقك اليوم · {{ \Carbon\Carbon::now()->translatedFormat('l، d F Y') }}</span>
            <span class="capsule-sep">·</span>
            <span class="capsule-sanctuary">مساحة القارئ الهادئة</span>
        </div>
        <h1 class="greeting-title">
            طاب يومك يا {{ $user->name }}
            <span class="gold-sparkle">✦</span>
        </h1>
        <div class="greeting-subtitle">أهلاً بك في رِواقك الخاص · لحظة هادئة، وصفحة أخرى في مسارك.</div>
    </div>

    <!-- Quick Action Launchers in Header -->
    <div class="header-quick-actions">
        <button class="primary quick-action-btn" onclick="openModal('session-modal')" title="بدء تسجيل جلسة قراءة">
            <i data-lucide="timer"></i>
            <span>جلسة قراءة جديدة</span>
        </button>
        <button class="secondary quick-action-btn" onclick="openModal('snap-note-modal')" title="تدوين خاطرة أو فكرة سريعة">
            <i data-lucide="feather"></i>
            <span>خاطرة سريعة</span>
        </button>
    </div>
</div>

<!-- Pulse Metrics Ribbon -->
<div class="pulse-metrics-grid animate-fade-in">
    <div class="pulse-metric-tile">
        <div class="tile-icon-box tile-flame">
            <i data-lucide="flame"></i>
        </div>
        <div class="tile-content">
            <div class="tile-value">{{ max(3, $user->books_finished * 2 + 1) }} يوم متتالي</div>
            <div class="tile-label">استمرارية في القراءة 🔥</div>
        </div>
    </div>

    <div class="pulse-metric-tile">
        <div class="tile-icon-box tile-pages">
            <i data-lucide="book-open-check"></i>
        </div>
        <div class="tile-content">
            <div class="tile-value">{{ number_format($totalPagesRead) }} صفحة</div>
            <div class="tile-label">مجموع الصفحات المنجزة 📖</div>
        </div>
    </div>

    <div class="pulse-metric-tile">
        <div class="tile-icon-box tile-points">
            <i data-lucide="sparkles"></i>
        </div>
        <div class="tile-content">
            <div class="tile-value">{{ $user->points }} نقطة أثر</div>
            <div class="tile-label">المستوى {{ $user->level }} · قارئ مواظب ⭐</div>
        </div>
    </div>

    @php
        $goalPct = min(100, round(($user->books_finished / max(1, $user->annual_goal)) * 100));
    @endphp
    <div class="pulse-metric-tile">
        <div class="tile-icon-box tile-trophy">
            <i data-lucide="trophy"></i>
        </div>
        <div class="tile-content">
            <div class="tile-value">{{ $user->books_finished }} من {{ $user->annual_goal }} كتاب</div>
            <div class="tile-label">{{ $goalPct }}٪ من هدفك السنوي 🏆</div>
        </div>
    </div>
</div>

<!-- Hero: Current Book -->
@if($currentBookRecord && $currentBookRecord->book)
@php $book = $currentBookRecord->book; @endphp

@if(($daysSinceLastSession ?? 0) >= 2)
<!-- Gentle Nudge Banner -->
<div class="gentle-nudge-banner animate-fade-in">
    <div class="nudge-icon">
        <i data-lucide="sparkles"></i>
    </div>
    <div class="nudge-text">
        <strong>كتابك «{{ $book->title }}» يفتقدك...</strong>
        <span>مرّت {{ $daysSinceLastSession }} أيام منذ آخر جلسة. 10 دقائق قراءة اليوم كافية لصنع الفرق واستعادة سكينتك 🌿</span>
    </div>
    <button class="primary btn-sm" onclick="startSmartSession(10)">
        <i data-lucide="clock"></i>
        <span>10 دقائق الآن</span>
    </button>
</div>
@endif

<section class="hero current-book-hero-card animate-fade-in" id="currentBookHero">
    <!-- 3D Realistic Book with Spine, Cursor Tilt & Dynamic Drop Shadow -->
    <div class="hero-cover-column">
        @php
            $homeCoverUrl = $book->cover_image ? (str_starts_with($book->cover_image, 'http') || str_starts_with($book->cover_image, 'data:') ? $book->cover_image : asset($book->cover_image)) : '';
        @endphp
        <div class="cover-3d-stage" id="currentBook3DStage">
            <div class="cover cover-3d-tilt-box" id="currentBook3DCover" style="background: {{ $book->cover_color }};cursor:pointer;" 
                onclick="previewBook(
                    {{ $book->id }},
                    '{{ addslashes($book->title) }}',
                    '{{ addslashes($book->author) }}',
                    '{{ $book->category }}',
                    {{ $book->total_pages }},
                    '{{ $book->cover_color }}',
                    '{{ addslashes(str_replace(["\r", "\n"], ' ', $book->description ?? '')) }}',
                    {{ $book->copies()->count() }},
                    '',
                    false,
                    '{{ $homeCoverUrl }}',
                    {{ (Auth::check() && Auth::user()->hasBookInPersonalLibrary($book)) ? 'true' : 'false' }}
                )" 
                title="انقر لتصفح وافتتاح الكتاب 3D"
            >
                <div class="book-spine-side">
                    <div class="spine-lines"></div>
                    <div class="spine-gold-emboss"></div>
                </div>
                <div class="book-cover-surface {{ $homeCoverUrl ? 'has-real-cover' : '' }}" style="{{ $homeCoverUrl ? 'padding:0;overflow:hidden;' : '' }}">
                    @if($homeCoverUrl)
                        <img src="{{ $homeCoverUrl }}" alt="{{ $book->title }}" style="width:100%;height:100%;object-fit:cover;border-radius:2px 6px 6px 2px;display:block;">
                    @else
                        <strong>{{ $book->title }}</strong>
                        <small>{{ $book->author }}</small>
                    @endif
                </div>
                <div class="book-glare-light"></div>
                <div class="book-dynamic-shadow" id="bookDynamicShadow"></div>
            </div>
            <!-- Ledge underneath book -->
            <div class="book-shelf-ledge"></div>
        </div>

        <button type="button" class="book-inspect-pill" onclick="document.getElementById('currentBook3DCover').click()">
            <i data-lucide="book-open" style="width:13px;height:13px;"></i>
            <span>تصفح المجلد 3D</span>
        </button>
    </div>

    <div class="hero-details-column">
        <div class="hero-tags-row">
            <span class="eyebrow hero-cat-tag"><i data-lucide="bookmark" style="width:12px;height:12px;"></i> {{ $book->category }}</span>
            <span class="hero-status-pill">قراءة نشطة حالياً 🌿</span>
        </div>

        <h2 class="hero-book-title">{{ $book->title }}</h2>
        <p class="muted hero-book-author"><i data-lucide="user" style="width:13px;height:13px;display:inline-block;vertical-align:middle;margin-left:4px;"></i> {{ $book->author }}</p>

        <!-- Progress Gauge Box -->
        <div class="hero-progress-box">
            <div class="progress-labels-row">
                <span class="pages-count-text">
                    الصفحة <b class="current-pg-highlight">{{ $currentBookRecord->current_page }}</b> من <span class="total-pg-text">{{ $book->total_pages }} صفحة</span>
                </span>
                <span class="pct-badge-highlight">{{ $percentage }}٪ منجز</span>
            </div>

            <!-- Progress Bar Liquid Fill (تعبئة الانجاز السلسة والموجية) -->
            <div class="progress liquid-progress" style="--pct:{{ $percentage }}%;">
                <span style="width: {{ $percentage }}%;">
                    <span class="liquid-wave-effect"></span>
                </span>
            </div>

            <div class="progress-insights-row">
                <span class="insight-item">
                    <i data-lucide="hourglass" style="width:12px;height:12px;color:var(--gold);"></i>
                    متبقي {{ max(0, $book->total_pages - $currentBookRecord->current_page) }} صفحة للإتمام
                </span>
                <span class="insight-dot">·</span>
                <span class="insight-item">
                    <i data-lucide="clock" style="width:12px;height:12px;color:var(--green);"></i>
                    ~{{ ceil(max(0, $book->total_pages - $currentBookRecord->current_page) * 1.5) }} دقيقة قراءة مقدّرة
                </span>
            </div>
        </div>

        <div class="actions hero-action-buttons">
            <button class="primary hero-primary-btn" onclick="openModal('session-modal')">
                <i data-lucide="timer"></i>
                <span>{{ $currentBookRecord->current_page >= $book->total_pages ? 'مراجعة قراءتك' : 'سجّل جلسة قراءة' }}</span>
            </button>
            <button class="secondary" onclick="openModal('reflection-modal')">
                <i data-lucide="feather"></i>
                <span>أضف تأمّلًا</span>
            </button>
            <a href="{{ route('explore') }}" class="secondary" style="border-style:dashed;" title="استكشف كتباً أخرى في رِواق">
                <i data-lucide="library"></i>
                <span>استكشف الفهرس</span>
            </a>
        </div>
    </div>
</section>

<!-- اقتراح الجلسة بناءً على الوقت المتاح (Smart Session Pitch) -->
<section class="panel smart-pitch-panel animate-fade-in">
    <div class="smart-pitch-header">
        <div>
            <span class="eyebrow" style="color:var(--gold);">اقتراح الجلسة الذكي (Smart Pitch)</span>
            <h3 style="margin:2px 0 0;font-size:17px;color:var(--ink);">كم لديك من الوقت الآن للهدوء والقراءة؟</h3>
        </div>
        <div class="pitch-time-chips">
            <button type="button" class="pitch-chip active" onclick="selectPitchTime(10, this)">10 دقائق</button>
            <button type="button" class="pitch-chip" onclick="selectPitchTime(20, this)">20 دقيقة</button>
            <button type="button" class="pitch-chip" onclick="selectPitchTime(30, this)">30 دقيقة</button>
        </div>
    </div>
    <div class="pitch-suggestion-box" id="pitch-suggestion-box">
        <div class="pitch-text">
            <i data-lucide="lightbulb" style="color:var(--gold);width:18px;height:18px;flex-shrink:0;"></i>
            <span id="pitch-recommendation">اقترحنا لك: قراءة 4 إلى 5 صفحات بتركيز وهدوء من «{{ $book->title }}» لتصل إلى صفحة {{ min($book->total_pages, $currentBookRecord->current_page + 5) }}.</span>
        </div>
        <button type="button" class="primary btn-sm" id="pitch-start-btn" onclick="startSmartSession(10)">
            <i data-lucide="play"></i>
            <span>ابدأ مؤقت الجلسة (10 د)</span>
        </button>
    </div>
</section>
@else
<section class="panel animate-fade-in" style="text-align:center;padding:48px 24px;border:1px dashed #D6CAB5;background:#FFFDF9;margin-bottom:20px;">
    <div style="font-size:42px;margin-bottom:12px;">📖</div>
    <h2 style="font-family:'Noto Naskh Arabic',serif;font-size:24px;color:var(--ink);margin:0 0 8px;">ليس لديك كتاب نشط حالياً</h2>
    <p class="muted" style="max-width:440px;margin:0 auto 20px;font-size:14.5px;">
        اختر كتاباً من الفهرس لتستمتع بجلسات قراءة هادئة وتتبع مسارك المعرفي يوماً بعد يوم.
    </p>
    <a href="{{ route('explore') }}" class="primary" style="display:inline-flex;align-items:center;gap:8px;">
        <i data-lucide="compass"></i>
        <span>استكشف فهرس الكتب</span>
    </a>
</section>
@endif

<div class="grid2">
    <!-- Annual Goal -->
    <section class="panel goal-sanctuary-card animate-fade-in delay-1">
        <div class="card-header-row">
            <div class="card-title-group">
                <div class="icon-avatar avatar-green">
                    <i data-lucide="target"></i>
                </div>
                <div>
                    <h2 class="card-heading">مسارك هذا العام</h2>
                    <div class="card-subheading">رحلة قراءتك لعام {{ date('Y') }}</div>
                </div>
            </div>
            <button class="link goal-edit-link" onclick="openModal('goal-modal')">
                <i data-lucide="edit-3"></i>
                <span>تعديل الهدف</span>
            </button>
        </div>

        <div class="goal-showcase-box">
            <div class="goal-main-stat">
                <div class="goal-number-combo">
                    <span class="goal-finished-number">{{ $user->books_finished }}</span>
                    <div class="goal-number-details">
                        <span class="goal-unit-text">كتب أُنجزت</span>
                        <span class="goal-total-text">من أصل <b>{{ $user->annual_goal }}</b> كتاباً</span>
                    </div>
                </div>
                <div class="goal-status-badge">
                    @if($goalPct >= 100)
                        <span class="badge-completed">أكملت الهدف السنوي! 🏆</span>
                    @elseif($goalPct >= 50)
                        <span class="badge-on-track">تجاوزت منتصف المسار ✦</span>
                    @else
                        <span class="badge-in-progress">في المسار الصحيح 🌿</span>
                    @endif
                </div>
            </div>

            <!-- Progress Bar with Track Markers -->
            <div class="goal-progress-wrapper">
                <div class="progress-track-labels">
                    <span>البداية (0)</span>
                    <span class="progress-pct-badge">{{ $goalPct }}٪ منجز</span>
                    <span>الهدف ({{ $user->annual_goal }} كتاب)</span>
                </div>
                <div class="progress liquid-progress" style="--pct:{{ $goalPct }}%;">
                    <span style="width: {{ $goalPct }}%;">
                        <span class="liquid-wave-effect"></span>
                    </span>
                </div>
            </div>

            <!-- 3 Milestone Stat Chips -->
            <div class="goal-milestones-grid">
                <div class="milestone-chip">
                    <span class="milestone-icon">📖</span>
                    <span class="milestone-val">{{ $user->books_finished }}</span>
                    <span class="milestone-lbl">كتاب منجز</span>
                </div>
                <div class="milestone-chip">
                    <span class="milestone-icon">⏳</span>
                    <span class="milestone-val">{{ max(0, $user->annual_goal - $user->books_finished) }}</span>
                    <span class="milestone-lbl">كتاب متبقٍ</span>
                </div>
                <div class="milestone-chip highlight">
                    <span class="milestone-icon">🏆</span>
                    <span class="milestone-val">{{ $goalPct }}٪</span>
                    <span class="milestone-lbl">نسبة الإنجاز</span>
                </div>
            </div>
        </div>

        <!-- Smart Reading Pace Callout -->
        <div class="goal-pace-callout">
            <div class="pace-icon-box">
                <i data-lucide="sparkles"></i>
            </div>
            <div class="pace-text">
                <strong>قراءة على مهل تصنع أثراً دائماً</strong>
                <span>معدلك الحالي يبقيك متصلاً برحلتك المعرفية، كل صفحة تقرؤها اليوم تُقربك من تمام مسارك السنوي.</span>
            </div>
        </div>

        <!-- Footer Action -->
        <div class="goal-card-footer">
            <small class="muted">كل قراءة تُضيف إلى رحلتك، على مهل.</small>
            <button type="button" class="secondary btn-sm share-progress-btn" onclick="openShareableCard()">
                <i data-lucide="share-2"></i>
                <span>توليد بطاقة مسارك للمشاركة 📸</span>
            </button>
        </div>
    </section>

    <!-- Knowledge Footprint (توهج البصمة المعرفية ورسم الخريطة الذهنية SVG) -->
    <section class="panel footprint-sanctuary-card animate-fade-in delay-1" id="knowledgeFootprintPanel">
        <div class="card-header-row">
            <div class="card-title-group">
                <div class="icon-avatar avatar-gold">
                    <i data-lucide="compass"></i>
                </div>
                <div>
                    <h2 class="card-heading">بصمتك المعرفية</h2>
                    <div class="card-subheading">توزيع اهتماماتك عبر مجالات القراءة والتأمل</div>
                </div>
            </div>
            <span class="tag" style="background:rgba(195,161,93,0.18);color:#927233;font-size:11px;">خريطة اهتمامات حية</span>
        </div>

        <!-- SVG Mind-Map / Radar Geometry with Path Drawing & Apex Glow -->
        <div class="footprint-radar-stage">
            <svg class="radar-svg-canvas" viewBox="0 0 240 180" aria-label="رسم بياني لتوزيع مجالات المعرفة">
                <!-- Background Polygon Grid Guides -->
                <polygon points="120,20 220,150 20,150" fill="none" stroke="#E2DAC8" stroke-width="1" stroke-dasharray="3,3"/>
                <polygon points="120,55 185,140 55,140" fill="none" stroke="#E2DAC8" stroke-width="1" stroke-dasharray="3,3"/>
                <line x1="120" y1="20" x2="120" y2="105" stroke="#E2DAC8" stroke-width="1"/>
                <line x1="220" y1="150" x2="120" y2="105" stroke="#E2DAC8" stroke-width="1"/>
                <line x1="20" y1="150" x2="120" y2="105" stroke="#E2DAC8" stroke-width="1"/>

                <!-- Animated Data Shape (SVG Path Drawing) -->
                <polygon class="radar-data-polygon" points="120,30 205,145 38,145" fill="rgba(35, 87, 56, 0.14)" stroke="var(--green)" stroke-width="2.5"/>

                <!-- Nodes & Glowing Apex -->
                <!-- Apex 1: الأدب (Highest 50% - Glowing Pulse) -->
                <g class="radar-node-group highest-apex" transform="translate(120, 30)">
                    <circle r="12" class="radar-pulse-ring"/>
                    <circle r="6" fill="var(--gold)" stroke="#FFFDF8" stroke-width="2"/>
                    <text x="0" y="-12" text-anchor="middle" font-family="'Noto Naskh Arabic', Arial, sans-serif" font-size="11" font-weight="700" fill="var(--green)">الأدب 50٪ ✦</text>
                </g>

                <!-- Apex 2: التاريخ (30%) -->
                <g class="radar-node-group" transform="translate(205, 145)">
                    <circle r="4.5" fill="var(--green)" stroke="#FFFDF8" stroke-width="1.5"/>
                    <text x="8" y="16" text-anchor="middle" font-family="'Noto Naskh Arabic', Arial, sans-serif" font-size="10.5" fill="#6A6250">تاريخ 30٪</text>
                </g>

                <!-- Apex 3: الفكر (20%) -->
                <g class="radar-node-group" transform="translate(38, 145)">
                    <circle r="4.5" fill="var(--green)" stroke="#FFFDF8" stroke-width="1.5"/>
                    <text x="-8" y="16" text-anchor="middle" font-family="'Noto Naskh Arabic', Arial, sans-serif" font-size="10.5" fill="#6A6250">فكر 20٪</text>
                </g>
            </svg>
        </div>

        <!-- Domain Categories Breakdown -->
        <div class="footprint-domains-list">
            @foreach($footprint as $item)
            @php
                $isTop = $item['percent'] == 50;
                $icon = $item['label'] == 'أدب' ? 'book-open' : ($item['label'] == 'تاريخ' ? 'landmark' : 'lightbulb');
            @endphp
            <div class="domain-item-row {{ $isTop ? 'is-top-domain' : '' }}">
                <div class="domain-info-line">
                    <div class="domain-name-wrap">
                        <i data-lucide="{{ $icon }}" class="domain-icon"></i>
                        <span class="domain-name">{{ $item['label'] }}</span>
                        @if($isTop)
                            <span class="top-domain-pill">الأعلى أثراً ✦</span>
                        @endif
                    </div>
                    <span class="domain-pct-value">{{ $item['percent'] }}٪</span>
                </div>
                <div class="progress liquid-progress" style="--pct:{{ $item['percent'] }}%;">
                    <span style="width: {{ $item['percent'] }}%;">
                        <span class="liquid-wave-effect"></span>
                    </span>
                </div>
            </div>
            @endforeach
        </div>

        <div class="footprint-card-footer">
            <div class="footprint-insight-line">
                <i data-lucide="sparkles" style="width:13px;height:13px;color:var(--gold);"></i>
                <span>اهتمامك بالأدب يقود بوصلتك المعرفية هذا الموسم بنسبة 50٪ 🌿</span>
            </div>
        </div>
    </section>

    <!-- Upcoming Commitments -->
    <section class="panel animate-fade-in delay-2">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(195,161,93,0.15);color:#927233;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="calendar" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">في الأيام المقبلة</h2>
            </div>
            <a href="{{ route('calendar.index') }}" class="link">كل المواعيد &larr;</a>
        </div>

        <div class="commitments-items-list">
            @foreach($upcomingMeetings as $meeting)
            <div class="commitment-item-card">
                <div class="commitment-date-badge">
                    <span class="commitment-date-num">{{ $meeting->meeting_date->format('d') }}</span>
                    <span class="commitment-date-month">{{ $meeting->meeting_date->translatedFormat('M') }}</span>
                </div>
                <div class="commitment-details">
                    <div class="commitment-title">{{ $meeting->title }}</div>
                    <div class="commitment-meta">
                        <span class="commitment-type-tag tag-meeting">
                            <i data-lucide="users" style="width:11px;height:11px;"></i> مجلس
                        </span>
                        <span>{{ $meeting->community->name ?? 'مجلس الأدب' }} · {{ $meeting->meeting_date->format('g:i') }} مساءً</span>
                    </div>
                </div>
                <a href="{{ route('calendar.index') }}" class="link" style="font-size:12.5px;font-weight:600;">التفاصيل</a>
            </div>
            @endforeach

            @if($bookLendingEnabled)
            @foreach($activeLoans as $loan)
            <div class="commitment-item-card">
                <div class="commitment-date-badge">
                    <span class="commitment-date-num">{{ $loan->due_date ? $loan->due_date->format('d') : '20' }}</span>
                    <span class="commitment-date-month">{{ $loan->due_date ? $loan->due_date->translatedFormat('M') : \Carbon\Carbon::now()->translatedFormat('M') }}</span>
                </div>
                <div class="commitment-details">
                    <div class="commitment-title">إعادة «{{ $loan->copy->book->title ?? 'الأيام' }}»</div>
                    <div class="commitment-meta">
                        <span class="commitment-type-tag tag-loan">
                            <i data-lucide="book-check" style="width:11px;height:11px;"></i> إعارة
                        </span>
                        <span>مكتبة العائلة · إعارة نشطة</span>
                    </div>
                </div>
            </div>
            @endforeach
            @endif

            @if($activeChallenge)
            <div class="commitment-item-card">
                <div class="commitment-date-badge">
                    <span class="commitment-date-num">{{ $activeChallenge->target_date ? \Carbon\Carbon::parse($activeChallenge->target_date)->format('d') : '30' }}</span>
                    <span class="commitment-date-month">{{ $activeChallenge->target_date ? \Carbon\Carbon::parse($activeChallenge->target_date)->translatedFormat('M') : \Carbon\Carbon::now()->translatedFormat('M') }}</span>
                </div>
                <div class="commitment-details">
                    <div class="commitment-title">ختام تحدّي {{ $activeChallenge->title }}</div>
                    <div class="commitment-meta">
                        <span class="commitment-type-tag tag-challenge">
                            <i data-lucide="award" style="width:11px;height:11px;"></i> تحدي
                        </span>
                        <span>{{ Str::limit($activeChallenge->description, 35) }}</span>
                    </div>
                </div>
            </div>
            @endif

            @if($upcomingMeetings->isEmpty() && (!$bookLendingEnabled || $activeLoans->isEmpty()) && !$activeChallenge)
            <div class="empty-commitments-box">
                <div class="empty-icon-leaf">🌿</div>
                <p style="margin:0;font-size:13px;">أيامك القادمة هادئة · خصّص وقتاً إضافياً لقراءتك واستمتع بسكينة الكلمات.</p>
            </div>
            @endif
        </div>
    </section>

    <!-- Community Question -->
    <section class="panel thought-sanctuary-card animate-fade-in delay-2">
        <span class="eyebrow" style="color:var(--gold);">من ناديك · سؤال يستحق التأمّل</span>
        <h2 style="margin:2px 0 10px;">رواق التأمل الأسبوعي</h2>
        <p class="thought-quote-text">
            « {{ $featuredQuestion }} »
        </p>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:12px;border-top:1px dashed #DFD3BC;">
            <small class="muted">نقاش هذا الأسبوع في {{ $community ? $community->name : 'مجلس الأدب' }}</small>
            <a href="{{ route('communities.index') }}" class="link" style="font-weight:600;">شارك في النقاش &larr;</a>
        </div>
    </section>
</div>
@endsection

@section('modals')
<!-- Session Recorder Modal with Live Stopwatch -->
<div id="session-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true">
        <div class="heading" style="margin-bottom:14px;">
            <h2 style="margin:0;">سجّل لحظتك مع الكتاب</h2>
            <button class="close-btn" onclick="closeModal('session-modal')">&times;</button>
        </div>
        <p class="muted" style="margin-top:-6px;font-size:13px;">
            {{ $currentBookRecord ? $currentBookRecord->book->title : 'كتابك المختار' }} · توقّفت عند الصفحة {{ $currentBookRecord ? $currentBookRecord->current_page : 0 }}
        </p>

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

        <form action="{{ route('reading.storeSession') }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>وصلت إلى الصفحة</span>
                    <input name="page" type="number" min="{{ $currentBookRecord->current_page }}" max="{{ $currentBookRecord->book->total_pages }}" value="{{ min($currentBookRecord->book->total_pages, $currentBookRecord->current_page + 16) }}" required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>مدة القراءة بالدقائق</span>
                    <input name="minutes" id="session-minutes-input" type="number" min="1" max="1440" value="25" required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>ما الذي بقي معك من الجلسة؟</span>
                    <textarea name="note" placeholder="فكرة أو خاطرة أو اقتباس استوقفك…" rows="2"></textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;margin-top:6px;">
                    <i data-lucide="save"></i>
                    <span>حفظ الجلسة وتحديث التقدّم</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Reflection Note Modal -->
<div id="reflection-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true">
        <div class="heading" style="margin-bottom:14px;">
            <h2 style="margin:0;">تأمّل من قراءتك</h2>
            <button class="close-btn" onclick="closeModal('reflection-modal')">&times;</button>
        </div>
        <form action="{{ route('reading.storeReflection') }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>تأمّلك الخاص</span>
                    <textarea name="note" placeholder="اكتب ما تركته الكلمات في نفسك…" rows="4" required></textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="check"></i>
                    <span>حفظ في سجل الأثر</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Quick Snap Note Modal -->
<div id="snap-note-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:480px;">
        <div class="heading" style="margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="badge-gold"><i data-lucide="feather" style="width:14px;height:14px;"></i></span>
                <h2 style="margin:0;font-size:18px;">خاطرة سريعة (Quick Snap Note)</h2>
            </div>
            <button class="close-btn" onclick="closeModal('snap-note-modal')">&times;</button>
        </div>
        <p class="muted" style="margin-top:-4px;font-size:13px;">
            دوّن فكرة طرأت لك الآن دون مغادرة صفحتك، وستُحفظ مباشرة في أثر قراءتك.
        </p>

        <form action="{{ route('reading.storeReflection') }}" method="POST" id="quick-snap-form">
            @csrf
            <div style="display:grid;gap:12px;">
                @if($currentBookRecord && $currentBookRecord->book)
                    <input type="hidden" name="book_id" value="{{ $currentBookRecord->book_id }}">
                    <div style="background:var(--cream);padding:8px 12px;border-radius:6px;font-size:12.5px;color:var(--green);font-weight:600;">
                        مرتبطة بكتاب: «{{ $currentBookRecord->book->title }}» · صفحة {{ $currentBookRecord->current_page }}
                    </div>
                @endif
                <label style="display:grid;gap:6px;">
                    <span style="font-size:13px;font-weight:600;">الخاطرة أو الاقتباس</span>
                    <textarea name="note" id="snap-note-text" placeholder="اكتب فكرتك أو اقتباسك السريع هنا…" rows="3" required style="font-size:14px;"></textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;margin-top:4px;">
                    <i data-lucide="send"></i>
                    <span>حفظ الخاطرة فوراً (+10 نقاط)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Shareable Progress Card Modal -->
<div id="shareable-card-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:540px;padding:24px;">
        <div class="heading" style="margin-bottom:16px;">
            <h2 style="margin:0;font-size:20px;">بطاقة مسارك السنوي للمشاركة</h2>
            <button class="close-btn" onclick="closeModal('shareable-card-modal')">&times;</button>
        </div>
        <p class="muted" style="margin-top:-8px;font-size:13px;margin-bottom:16px;">
            بطاقتك السينمائية المصممة بألوان رِواق، جاهزة للنشر ومشاركة إلهام القراءة مع أصدقائك.
        </p>

        <!-- Visual Sanctuary Card Container -->
        <div class="shareable-card-frame" id="shareable-card-target">
            <div class="card-inner-border">
                <div class="card-brand-header">
                    <div class="card-brand-pill">
                        <i data-lucide="book-marked" style="width:14px;height:14px;color:#C3A15D;"></i>
                        <span>رِواق المعرفة · مسار القارئ 2026</span>
                    </div>
                    <span class="card-tag-year">حصاد العام</span>
                </div>

                <div class="card-user-profile">
                    <div class="card-avatar">{{ mb_substr($user->name, 0, 1) }}</div>
                    <div>
                        <h3 class="card-user-name">{{ $user->name }}</h3>
                        <span class="card-user-role">قارئ في رِواق المعرفة · {{ $user->points }} نقطة أثر</span>
                    </div>
                </div>

                <div class="card-stats-grid">
                    <div class="card-stat-box">
                        <span class="card-stat-number">{{ $user->books_finished }}</span>
                        <span class="card-stat-label">كتب أُنجزت من أصل {{ $user->annual_goal }}</span>
                    </div>
                    <div class="card-stat-box">
                        <span class="card-stat-number">{{ number_format($totalPagesRead ?? 2450) }}</span>
                        <span class="card-stat-label">صفحة تأمّل وقراءة</span>
                    </div>
                    <div class="card-stat-box">
                        <span class="card-stat-number">{{ $user->userBadges()->count() > 0 ? $user->userBadges()->count() : 3 }}</span>
                        <span class="card-stat-label">أوسمة شرفية معرفية</span>
                    </div>
                </div>

                <div class="card-quote-banner">
                    <p class="card-quote-text">«ما تقرؤه على مهل، يَبني في نفسك أثراً لا يمحوه الزمن.»</p>
                    <small class="card-watermark">riwaq.test · منصة القراءة والمعرفة العربية</small>
                </div>
            </div>
        </div>

        <!-- Download & Social Share Buttons -->
        <div class="card-actions-row" style="margin-top:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <button type="button" class="primary" onclick="downloadProgressCard()">
                <i data-lucide="download"></i>
                <span>تحميل الصورة (PNG)</span>
            </button>
            <button type="button" class="secondary" onclick="shareCardTwitter()">
                <i data-lucide="share"></i>
                <span>مشاركة على X (تويتر)</span>
            </button>
            <button type="button" class="secondary" onclick="copyCardInfo()">
                <i data-lucide="copy"></i>
                <span>نسخ ملخص المسار</span>
            </button>
        </div>
    </section>
</div>

<!-- Floating Action Button (Quick Snap Note FAB) -->
<button class="quick-snap-fab animate-pulse-gentle" id="quick-snap-fab" onclick="openModal('snap-note-modal')" title="تدوين خاطرة سريعة" aria-label="تدوين خاطرة سريعة">
    <i data-lucide="feather"></i>
    <span class="fab-tooltip">خاطرة سريعة</span>
</button>
@endsection

@push('scripts')
<script>
function selectPitchTime(minutes, btn) {
    document.querySelectorAll('.pitch-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    const recSpan = document.getElementById('pitch-recommendation');
    const startBtn = document.getElementById('pitch-start-btn');
    const bookTitle = "{{ $currentBookRecord && $currentBookRecord->book ? $currentBookRecord->book->title : 'كتابك الحالي' }}";
    const curPage = {{ $currentBookRecord ? $currentBookRecord->current_page : 1 }};

    if (minutes === 10) {
        recSpan.textContent = `اقترحنا لك: قراءة 4 إلى 5 صفحات بتركيز وهدوء من «${bookTitle}» لتصل إلى صفحة ${curPage + 5}. قراءة يسيرة تصنع أثراً كبيراً.`;
        startBtn.innerHTML = '<i data-lucide="play"></i><span>ابدأ مؤقت الجلسة (10 د)</span>';
        startBtn.setAttribute('onclick', 'startSmartSession(10)');
    } else if (minutes === 20) {
        recSpan.textContent = `اقترحنا لك: قراءة 8 إلى 10 صفحات مع تدوين اقتباس ملهم يستوقفك في «${bookTitle}» لتصل إلى صفحة ${curPage + 9}.`;
        startBtn.innerHTML = '<i data-lucide="play"></i><span>ابدأ مؤقت الجلسة (20 د)</span>';
        startBtn.setAttribute('onclick', 'startSmartSession(20)');
    } else if (minutes === 30) {
        recSpan.textContent = `اقترحنا لك: جلسة تركيز عميق (15 صفحة) يعقبها تدوين خاطرة سريعة في المنصة لتعزيز الاستيعاب.`;
        startBtn.innerHTML = '<i data-lucide="play"></i><span>ابدأ مؤقت الجلسة (30 د)</span>';
        startBtn.setAttribute('onclick', 'startSmartSession(30)');
    }
    if (window.lucide) lucide.createIcons();
}

function startSmartSession(minutes) {
    const minInput = document.getElementById('session-minutes-input');
    if (minInput) {
        minInput.value = minutes;
        minInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    openModal('session-modal');
    // Start timer automatically if available
    const toggleBtn = document.getElementById('stopwatch-toggle');
    if (toggleBtn && !toggleBtn.classList.contains('active') && !toggleBtn.classList.contains('running')) {
        toggleBtn.click();
    }
}

function openShareableCard() {
    openModal('shareable-card-modal');
    if (window.lucide) lucide.createIcons();
}

function downloadProgressCard() {
    // Generate an image via canvas
    const cardEl = document.getElementById('shareable-card-target');
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    canvas.width = 1080;
    canvas.height = 1080;

    // Draw rich background
    const grad = ctx.createLinearGradient(0, 0, 1080, 1080);
    grad.addColorStop(0, '#0E2B26');
    grad.addColorStop(0.5, '#173D36');
    grad.addColorStop(1, '#081D1A');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 1080, 1080);

    // Gold Ornamental Outer Border
    ctx.strokeStyle = '#C3A15D';
    ctx.lineWidth = 6;
    ctx.strokeRect(36, 36, 1008, 1008);

    ctx.strokeStyle = 'rgba(195, 161, 93, 0.4)';
    ctx.lineWidth = 2;
    ctx.strokeRect(48, 48, 984, 984);

    // Header Text
    ctx.fillStyle = '#C3A15D';
    ctx.font = 'bold 36px "Segoe UI", Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('رِواق المعرفة · حصاد القراءة 2026', 540, 120);

    // User Name
    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 54px "Segoe UI", Arial, sans-serif';
    ctx.fillText("{{ $user->name }}", 540, 240);

    ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
    ctx.font = '28px "Segoe UI", Arial, sans-serif';
    ctx.fillText('قارئ في رِواق المعرفة · مسار هادئ ومتصل', 540, 290);

    // Decorative Line
    ctx.strokeStyle = '#C3A15D';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(340, 330);
    ctx.lineTo(740, 330);
    ctx.stroke();

    // Stats Boxes
    const boxY = 400;
    const boxW = 280;
    const boxH = 180;
    const stats = [
        { num: "{{ $user->books_finished }} / {{ $user->annual_goal }}", label: "كتب أُنجزت هذا العام", x: 100 },
        { num: "{{ number_format($totalPagesRead ?? 2450) }}", label: "صفحة قراءة وتأمّل", x: 400 },
        { num: "{{ max(1, $daysSinceLastSession ?? 12) }}", label: "يوماً متواصلاً من الأثر", x: 700 }
    ];

    stats.forEach(st => {
        ctx.fillStyle = 'rgba(255, 255, 255, 0.06)';
        ctx.fillRect(st.x, boxY, boxW, boxH);
        ctx.strokeStyle = 'rgba(195, 161, 93, 0.5)';
        ctx.lineWidth = 2;
        ctx.strokeRect(st.x, boxY, boxW, boxH);

        ctx.fillStyle = '#FFE2A3';
        ctx.font = 'bold 50px "Segoe UI", Arial, sans-serif';
        ctx.fillText(st.num, st.x + boxW / 2, boxY + 80);

        ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
        ctx.font = '22px "Segoe UI", Arial, sans-serif';
        ctx.fillText(st.label, st.x + boxW / 2, boxY + 130);
    });

    // Quote Box
    ctx.fillStyle = 'rgba(195, 161, 93, 0.12)';
    ctx.fillRect(100, 660, 880, 160);
    ctx.strokeStyle = '#C3A15D';
    ctx.lineWidth = 1;
    ctx.strokeRect(100, 660, 880, 160);

    ctx.fillStyle = '#F5F1E8';
    ctx.font = 'italic 32px "Segoe UI", Arial, sans-serif';
    ctx.fillText('«ما تقرؤه على مهل، يَبني في نفسك أثراً لا يمحوه الزمن.»', 540, 735);

    ctx.fillStyle = '#C3A15D';
    ctx.font = '24px "Segoe UI", Arial, sans-serif';
    ctx.fillText('riwaq.test · منصتك العربية لإدارة القراءة والمعرفة', 540, 785);

    // Footer Emblem
    ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
    ctx.font = '20px "Segoe UI", Arial, sans-serif';
    ctx.fillText('صُنعت عبر رِواق المعرفة ✦ Knowledge Sanctuary', 540, 940);

    // Trigger download
    const link = document.createElement('a');
    link.download = 'riwaq-progress-2026.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function shareCardTwitter() {
    const text = encodeURIComponent("أشارككم مساري المعرفي في رِواق المعرفة لعام 2026 📖✨\n\nأنجزت {{ $user->books_finished }} كتب وقرأت أكثر من {{ number_format($totalPagesRead ?? 2450) }} صفحة بتأمل وطمأنينة.\n#رواق_المعرفة #تحدي_القراءة");
    window.open(`https://twitter.com/intent/tweet?text=${text}`, '_blank');
}

function copyCardInfo() {
    const text = "مساري في رِواق المعرفة 2026 📖: {{ $user->books_finished }} من {{ $user->annual_goal }} كتب · {{ number_format($totalPagesRead ?? 2450) }} صفحة · riwaq.test";
    navigator.clipboard.writeText(text).then(() => {
        alert("تم نسخ ملخص مسارك السنوي بنجاح!");
    });
}

// Interactive 3D Book Spine & Tilt
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        lucide.createIcons();
    }
    const stage = document.getElementById('currentBook3DStage');
    const bookCover = document.getElementById('currentBook3DCover');
    const shadow = document.getElementById('bookDynamicShadow');

    if (stage && bookCover) {
        stage.addEventListener('mousemove', (e) => {
            const rect = stage.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const rotY = (x / (rect.width / 2)) * 16;
            const rotX = -(y / (rect.height / 2)) * 16;

            bookCover.style.transform = `perspective(800px) rotateY(${rotY}deg) rotateX(${rotX}deg) scale(1.04)`;
            if (shadow) {
                shadow.style.transform = `translate(${x * 0.3}px, ${y * 0.3 + 15}px) scale(0.95)`;
                shadow.style.opacity = '0.45';
            }
        });

        stage.addEventListener('mouseleave', () => {
            bookCover.style.transform = 'perspective(800px) rotateY(0deg) rotateX(0deg) scale(1)';
            if (shadow) {
                shadow.style.transform = 'translate(0, 10px) scale(1)';
                shadow.style.opacity = '0.25';
            }
        });
    }
});
</script>
@endpush
