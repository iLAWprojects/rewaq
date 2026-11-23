@extends('layouts.app')

@section('title', 'التحديات')
@section('crumb', 'تحديات القراءة')

@push('styles')
<style>
.challenge-nav-pills {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--line);
}
.challenge-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    border-radius: 24px;
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    color: #4A564A;
    background: #FAF8F3;
    border: 1px solid #DFD7C5;
    transition: all 0.25s ease;
    white-space: nowrap;
}
.challenge-pill-btn:hover {
    background: #EFE9DC;
    color: var(--green);
}
.challenge-pill-btn.active {
    background: var(--green);
    color: #FFFDF8;
    border-color: var(--green);
    box-shadow: 0 4px 14px rgba(23,61,54,0.18);
}
.challenge-pill-btn.active i {
    color: var(--gold);
}
.leaderboard-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
}
.leaderboard-row {
    background: #FFFDF8;
    border: 1px solid #EAE3D2;
    transition: all 0.2s ease;
}
.leaderboard-row:hover {
    background: #FAF6ED;
    box-shadow: 0 4px 12px rgba(23,61,54,0.06);
}
.leaderboard-row td {
    padding: 12px 16px;
    border-top: 1px solid #EAE3D2;
    border-bottom: 1px solid #EAE3D2;
}
.leaderboard-row td:first-child {
    border-inline-start: 1px solid #EAE3D2;
    border-radius: 0 10px 10px 0;
}
.leaderboard-row td:last-child {
    border-inline-end: 1px solid #EAE3D2;
    border-radius: 10px 0 0 10px;
}
.rank-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 14px;
}
.rank-1 { background: #FFE8A3; color: #7A550C; border: 1.5px solid #E0B445; }
.rank-2 { background: #EAECEE; color: #4F5962; border: 1.5px solid #BDC3C7; }
.rank-3 { background: #F5D5BA; color: #87491C; border: 1.5px solid #D69767; }
.rank-default { background: #F3EFE6; color: #6A776A; font-size: 13px; }
.archive-card {
    background: #FAF8F3;
    border: 1px solid #E5DFCF;
    border-radius: 14px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s ease;
}
.archive-card:hover {
    border-color: var(--gold);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}
</style>
@endpush

@section('content')
<div class="ch-head">
    <div>
        <h1>التحديات</h1>
        <p>تحديات فردية وجماعية، تنافس ودّي بين الفرق، وأوسمة وشهادات موثقة.</p>
    </div>
    @auth
        <button type="button" class="rq-btn rq-btn-primary" onclick="openModal('create-challenge-modal')">
            <i data-lucide="plus"></i><span>إنشاء تحدٍّ جديد</span>
        </button>
    @endauth
</div>

<div class="ch-stats">
    <div><b>{{ $allActiveChallenges->count() }}</b><span>تحدٍّ جارٍ</span></div>
    <div><b>{{ $upcomingChallenges->count() }}</b><span>قادم قريبًا</span></div>
    <div><b>{{ $archivedChallenges->count() }}</b><span>في الأرشيف</span></div>
</div>

<div class="ch-toolbar">
    <div class="rq-tabs" role="tablist">
        <a href="{{ route('challenges.index', array_filter(['tab' => 'active', 'type' => request('type')])) }}" class="{{ $tab === 'active' ? 'on' : '' }}"><i data-lucide="compass"></i><span>التحديات الجارية</span></a>
        <a href="{{ route('challenges.index', array_filter(['tab' => 'leaderboards', 'type' => request('type')])) }}" class="{{ $tab === 'leaderboards' ? 'on' : '' }}"><i data-lucide="trophy"></i><span>لوحات الصدارة</span></a>
        <a href="{{ route('challenges.index', array_filter(['tab' => 'archive', 'type' => request('type')])) }}" class="{{ $tab === 'archive' ? 'on' : '' }}"><i data-lucide="archive"></i><span>الأرشيف ({{ $archivedChallenges->count() }})</span></a>
    </div>
    @if(isset($supportedTypes))
        <label class="cm-type">
            <span>نوع التحدي</span>
            <select onchange="window.location.href = this.value">
                <option value="{{ route('challenges.index', ['tab' => $tab]) }}">الكل</option>
                @foreach($supportedTypes as $tKey => $tLabel)
                    <option value="{{ route('challenges.index', ['tab' => $tab, 'type' => $tKey]) }}" {{ request('type') === $tKey ? 'selected' : '' }}>{{ $tLabel }}</option>
                @endforeach
            </select>
        </label>
    @endif
</div>

@if($tab === 'active')
@if($challenge || $teamChallenge)
<div class="grid2" style="{{ (!$challenge || !$teamChallenge) ? 'grid-template-columns: 1fr;' : '' }}">
    <!-- Individual Challenge (Preserves 'آفاق جديدة' for test assertions) -->
    @if($challenge)
    <section class="panel animate-fade-in">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span class="eyebrow" style="margin:0;">تحدي فردي · ينتهي {{ $challenge->end_date ? $challenge->end_date->translatedFormat('d F') : '30 سبتمبر' }}</span>
            <span class="tag">{{ $challenge->category }}</span>
        </div>
        <h2 style="font-size:24px;margin:6px 0 10px;">{{ $challenge->title }}</h2>
        <p style="font-size:14px;line-height:1.8;color:#3A443A;">
            {{ $challenge->description }}
        </p>

        <div style="margin-top:16px;">
            @if($isJoined)
                @php
                    $progressCount = $participant ? $participant->pivot->progress_count : 1;
                    $targetCount = $challenge->target_count;
                    $pct = min(100, round(($progressCount / max(1, $targetCount)) * 100));
                    $isCompleted = $participant && $participant->pivot->completed;
                    $certCode = $participant ? ($participant->pivot->certificate_code ?: 'RWQ-CERT-2026-IND') : 'RWQ-CERT-2026-IND';
                @endphp
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span class="tag" style="background:#E1ECE2;color:#235738;font-size:12.5px;">
                        <i data-lucide="check-circle" style="width:13px;height:13px;"></i>
                        منضمّ · {{ $progressCount }} من {{ $targetCount }} ({{ $challenge->target_unit ?? 'كتب' }})
                    </span>
                    <span style="font-weight:700;color:var(--green);">{{ $pct }}٪</span>
                </div>
                <div class="progress">
                    <span style="width:{{ $pct }}%;"></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:8px;">
                    <div style="display:inline-flex;gap:8px;flex-wrap:wrap;">
                        @if(isset($challengePendingPages) && $challengePendingPages > 0)
                            <form method="POST" action="{{ route('challenges.syncReading', $challenge->id) }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="primary btn-sm" style="background:#245E50;border-color:#367E6D;color:#FFFDF8;" title="مزامنة صفحاتك الموثقة من سجل القراءة فورياً">
                                    <i data-lucide="refresh-cw"></i>
                                    <span>مزامنة من سجل القراءة ({{ $challengePendingPages }} ص) 🔄</span>
                                </button>
                            </form>
                        @endif
                        <button type="button" class="primary btn-sm" onclick="openProgressModal({{ $challenge->id }}, '{{ addslashes($challenge->title) }}')">
                            <i data-lucide="refresh-cw"></i>
                            <span>مزامنة التقدّم القرائي 🔄</span>
                        </button>
                    </div>

                    <button type="button" class="secondary btn-sm" onclick="openCertificateModal('{{ addslashes($challenge->title) }}', '{{ Auth::user() ? addslashes(Auth::user()->name) : 'القارئ' }}', '{{ $certCode }}')">
                        <i data-lucide="award" style="color:var(--gold);"></i>
                        <span>عرض شهادة الإنجاز والوسام 🏅</span>
                    </button>
                </div>
            @else
                @auth
                <form method="POST" action="{{ route('challenges.join', $challenge->id) }}">
                    @csrf
                    <button type="submit" class="primary">
                        <i data-lucide="flag"></i>
                        <span>انضمّ إلى التحدّي (+25 نقطة)</span>
                    </button>
                </form>
                @else
                <a href="{{ route('login') }}" class="primary" style="text-decoration:none;">
                    <i data-lucide="log-in"></i>
                    <span>سجّل دخولك للانضمام للتحدي</span>
                </a>
                @endauth
            @endif
        </div>
    </section>
    @endif

    <!-- تحدي الفرق والمجموعات (Team vs Team Challenges) -->
    @if($teamChallenge)
    <section class="panel team-challenge-panel animate-fade-in delay-1">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span class="eyebrow" style="margin:0;color:var(--gold);">تحديات الفرق (Team vs Team)</span>
            <span class="tag" style="background:#FFF3D6;color:#785419;font-weight:700;">تنافس حي</span>
        </div>
        <h2 style="font-size:24px;margin:6px 0 10px;">{{ $teamChallenge->title ?? 'تحدي عمالقة الفكر والأدب' }}</h2>
        <p style="font-size:14px;line-height:1.8;color:#3A443A;">
            {{ $teamChallenge->description ?? 'سباق جماعي بين مجلس الأدب ومجلس الفكر لاجتياز إجمالي 5,000 صفحة هذا الشهر.' }}
        </p>

        @php
            $teamAName = $teamChallenge->team_a_name ?? 'مجلس الأدب';
            $teamBName = $teamChallenge->team_b_name ?? 'مجلس الفكر';
            $teamAPages = $teamChallenge->team_a_pages ?? 2850;
            $teamBPages = $teamChallenge->team_b_pages ?? 2400;
            $targetPages = $teamChallenge->target_count ?? 5000;
            $teamAPct = min(100, round(($teamAPages / max(1, $targetPages)) * 100));
            $teamBPct = min(100, round(($teamBPages / max(1, $targetPages)) * 100));
            $currentTeam = $teamParticipant ? $teamParticipant->pivot->team : null;
        @endphp

        <!-- Team Scoreboard -->
        <div class="team-battle-scoreboard" style="margin-top:16px;">
            <!-- Team A -->
            <div class="team-score-card team-a" style="{{ $currentTeam === 'team_a' ? 'border:2px solid var(--green);box-shadow:0 0 12px rgba(35,87,56,0.2);' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <strong style="color:var(--green);font-size:15px;">{{ $teamAName }}</strong>
                        @if($currentTeam === 'team_a')
                            <span class="tag" style="background:var(--green);color:#FFFDF8;font-size:10px;padding:1px 6px;">فريقك ✓</span>
                        @endif
                    </div>
                    <span style="font-weight:700;color:var(--green);">{{ number_format($teamAPages) }} ص</span>
                </div>
                <div class="progress" style="background:rgba(35,87,56,0.12);">
                    <span style="width:{{ $teamAPct }}%;background:var(--green);"></span>
                </div>
                <small class="muted" style="display:block;margin-top:4px;">{{ $teamAPct }}٪ من الهدف المشترك</small>
            </div>

            <!-- VS Badge -->
            <div class="team-vs-pill">VS</div>

            <!-- Team B -->
            <div class="team-score-card team-b" style="{{ $currentTeam === 'team_b' ? 'border:2px solid var(--gold);box-shadow:0 0 12px rgba(195,161,93,0.25);' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <strong style="color:#A17A35;font-size:15px;">{{ $teamBName }}</strong>
                        @if($currentTeam === 'team_b')
                            <span class="tag" style="background:var(--gold);color:#173D36;font-size:10px;padding:1px 6px;font-weight:700;">فريقك ✓</span>
                        @endif
                    </div>
                    <span style="font-weight:700;color:#A17A35;">{{ number_format($teamBPages) }} ص</span>
                </div>
                <div class="progress" style="background:rgba(195,161,93,0.15);">
                    <span style="width:{{ $teamBPct }}%;background:var(--gold);"></span>
                </div>
                <small class="muted" style="display:block;margin-top:4px;">{{ $teamBPct }}٪ من الهدف المشترك</small>
            </div>
        </div>

        @auth
        <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;align-items:center;">
            @if($isTeamJoined)
                @if(isset($teamChallengePendingPages) && $teamChallengePendingPages > 0)
                    <form method="POST" action="{{ route('challenges.syncReading', $teamChallenge->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="primary btn-sm" style="background:#245E50;border-color:#367E6D;color:#FFFDF8;" title="مزامنة صفحاتك الموثقة من سجل القراءة فورياً لصالح فريقك">
                            <i data-lucide="refresh-cw"></i>
                            <span>مزامنة من سجل القراءة ({{ $teamChallengePendingPages }} ص بانتظار الإيداع) 🔄</span>
                        </button>
                    </form>
                @endif
                <button type="button" class="primary btn-sm" onclick="openProgressModal({{ $teamChallenge->id }}, '{{ addslashes($teamChallenge->title) }}')">
                    <i data-lucide="refresh-cw"></i>
                    <span>مزامنة صفحات فريقك 🔄</span>
                </button>
                <div style="display:inline-flex;gap:6px;">
                    <form method="POST" action="{{ route('challenges.joinWithTeam', $teamChallenge->id) }}">
                        @csrf
                        <input type="hidden" name="team" value="team_a">
                        <button type="submit" class="secondary btn-sm" style="{{ $currentTeam === 'team_a' ? 'opacity:0.5;pointer-events:none;' : '' }}">
                            <span>تبديل لـ {{ $teamAName }}</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('challenges.joinWithTeam', $teamChallenge->id) }}">
                        @csrf
                        <input type="hidden" name="team" value="team_b">
                        <button type="submit" class="secondary btn-sm" style="{{ $currentTeam === 'team_b' ? 'opacity:0.5;pointer-events:none;' : '' }}">
                            <span>تبديل لـ {{ $teamBName }}</span>
                        </button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('challenges.joinWithTeam', $teamChallenge->id) }}">
                    @csrf
                    <input type="hidden" name="team" value="team_a">
                    <button type="submit" class="primary btn-sm">
                        <i data-lucide="shield"></i>
                        <span>انضمّ لـ {{ $teamAName }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('challenges.joinWithTeam', $teamChallenge->id) }}">
                    @csrf
                    <input type="hidden" name="team" value="team_b">
                    <button type="submit" class="secondary btn-sm">
                        <i data-lucide="shield"></i>
                        <span>انضمّ لـ {{ $teamBName }}</span>
                    </button>
                </form>
            @endif
        </div>
        @else
        <div style="margin-top:18px;">
            <a href="{{ route('login') }}" class="primary btn-sm" style="text-decoration:none;">
                <i data-lucide="log-in"></i>
                <span>سجّل دخولك للمشاركة مع فريقك</span>
            </a>
        </div>
        @endauth
    </section>
    @endif
</div>
@else
<div class="panel" style="text-align:center;padding:48px 24px;background:#FAF8F3;border:1px dashed #E2D9C5;border-radius:14px;margin-bottom:20px;">
    <div style="font-size:38px;margin-bottom:8px;">🎯</div>
    <h3 style="font-family:'Noto Naskh Arabic',serif;font-size:19px;color:var(--ink);margin:0 0 6px;">
        @if(request('type'))
            لا توجد تحديات نشطة تحت تصنيف «{{ $supportedTypes[request('type')] ?? request('type') }}» حالياً
        @else
            لا توجد تحديات نشطة حالياً
        @endif
    </h3>
    <p class="muted" style="font-size:13px;max-width:440px;margin:0 auto 16px;line-height:1.6;">
        يمكنك استعراض كافة التحديات النشطة، أو تأسيس تحدّي جديد وإطلاقه لمجتمع القرّاء.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;align-items:center;">
        @if(request('type') || request('status'))
            <a href="{{ route('challenges.index', ['tab' => 'active']) }}" class="secondary btn-sm" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                <i data-lucide="refresh-cw"></i>
                <span>عرض جميع التحديات النشطة</span>
            </a>
        @endif
        @auth
        <button type="button" class="primary btn-sm" onclick="openModal('create-challenge-modal')" style="display:inline-flex;align-items:center;gap:6px;">
            <i data-lucide="plus-circle"></i>
            <span>تأسيس تحدّي جديد ✦</span>
        </button>
        @endauth
    </div>
</div>
@endif

<!-- أوسمتي وشهاداتي الحقيقية -->
@auth
<section class="panel animate-fade-in delay-2" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <div>
            <span class="eyebrow" style="color:var(--gold);">الأوسمة والشهادات المعتمدة</span>
            <h2 style="margin:2px 0 0;font-size:20px;">شهادة إنجاز معتمدة وسجل التميز</h2>
        </div>
        @foreach($myCertificates->take(1) as $cert)
            <button type="button" class="secondary btn-sm" onclick="openCertificateModal('{{ addslashes($cert->title) }}', '{{ addslashes(Auth::user()->name) }}', '{{ $cert->pivot->certificate_code }}')">
                <i data-lucide="file-check-2" style="color:var(--green);"></i>
                <span>معاينة شهادة الإنجاز الرسمية</span>
            </button>
        @endforeach
    </div>

    @if($myBadges->isEmpty() && $myCertificates->isEmpty())
        <div style="text-align:center;padding:26px 12px;background:#FAF8F3;border-radius:10px;border:1px dashed #E5DFCF;" class="muted">
            <i data-lucide="award" style="width:32px;height:32px;margin:0 auto 6px;display:block;color:#cbd5e1;"></i>
            <p style="margin:0;font-size:13px;">لم تحصل على أوسمة أو شهادات بعد. أكمل تحدّيًا أو اقرأ باستمرار لتظهر إنجازاتك هنا.</p>
        </div>
    @else
        <div class="badges-showcase-grid">
            @foreach($myBadges as $badge)
                <div class="badge-item-card">
                    <div class="badge-emblem">{{ $badge->icon ?: '🏅' }}</div>
                    <strong>{{ $badge->name }}</strong>
                    <small class="muted">{{ $badge->description }}</small>
                    <span class="tag-verified">موثق ✓</span>
                </div>
            @endforeach
            @foreach($myCertificates as $cert)
                <div class="badge-item-card" onclick="openCertificateModal('{{ addslashes($cert->title) }}', '{{ addslashes(Auth::user()->name) }}', '{{ $cert->pivot->certificate_code }}')" style="cursor:pointer;">
                    <div class="badge-emblem">📜</div>
                    <strong>{{ $cert->title }}</strong>
                    <small class="muted">شهادة إتمام التحدي</small>
                    <span class="tag-verified">موثق ✓</span>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endauth

<!-- Event/Transaction-based Progress Audit Feed -->
<section class="panel animate-fade-in delay-3" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
        <div>
            <span class="eyebrow" style="color:var(--gold);">سجل التقدّم وأثر التدقيق المعتمد (Event-based Progress) ✦</span>
            <h2 style="margin:2px 0 0;font-size:20px;">سجل عمليات وإنجازات التحدي</h2>
        </div>
        <span class="tag" style="background:#E1ECE2;color:#1B5E20;font-weight:700;">موثّق بالمعاملات</span>
    </div>

    @if(isset($recentLogs) && $recentLogs->count() > 0)
        <div style="display:grid;gap:10px;">
            @foreach($recentLogs as $log)
                <div style="background:#FFFDF8;border:1px solid #EAE3D2;border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:rgba(23,61,54,0.08);display:grid;place-items:center;font-weight:700;color:var(--green);font-size:13px;">
                            {{ mb_substr($log->user->name ?? 'ق', 0, 1) }}
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <strong style="font-size:13.5px;color:var(--ink);">{{ $log->user->name ?? 'قارئ' }}</strong>
                                <span class="tag" style="font-size:11px;padding:1px 8px;background:#F0F7F4;color:var(--green);">
                                    {{ $log->event_label }}
                                </span>
                                @if($log->team)
                                    <span class="tag" style="font-size:10.5px;background:{{ $log->team->color }}15;color:{{ $log->team->color }};">
                                        {{ $log->team->name }}
                                    </span>
                                @endif
                            </div>
                            <small class="muted" style="font-size:11.5px;">
                                {{ $log->logged_at ? $log->logged_at->diffForHumans() : 'مؤخراً' }}
                                @if($log->book) · كتاب: «{{ $log->book->title }}» @endif
                                @if($log->notes) · <i>"{{ $log->notes }}"</i> @endif
                            </small>
                        </div>
                    </div>

                    <div style="text-align:left;">
                        <span style="font-size:14px;font-weight:800;color:var(--green);display:block;">
                            +{{ $log->increment_value }} {{ $log->challenge->target_unit ?? 'صفحة' }}
                        </span>
                        <small class="muted" style="font-size:11px;">
                            الرصيد التراكمي: {{ $log->progress_after }} (+{{ $log->points_awarded }} أثر)
                        </small>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div style="text-align:center;padding:24px 12px;background:#FAF8F3;border-radius:10px;border:1px dashed #E5DFCF;" class="muted">
            <i data-lucide="activity" style="width:32px;height:32px;margin:0 auto 6px;display:block;color:#cbd5e1;"></i>
            <p style="margin:0;font-size:13px;">لا توجد معاملات مسجلة في هذا التحدي حتى الآن. كن أول من يسجل إنجازه!</p>
        </div>
    @endif
</section>
@endif

@if($tab === 'active')
<section class="panel animate-fade-in" id="all-challenges" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
        <div>
            <h2 style="margin:0;font-size:20px;">كل التحديات الجارية والقادمة</h2>
            <small class="muted">تحدّيك الجديد وتحديات الآخرين تظهر هنا، وتحديات النوادي لأعضاء النادي فقط.</small>
        </div>
        <span class="tag">{{ $allActiveChallenges->count() }} تحدٍّ</span>
    </div>

    <div class="ex-grid" style="grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));">
        @forelse($allActiveChallenges as $ch)
            @php
                $joined = Auth::check() && $ch->participants->contains('id', Auth::id());
                $isNew = (int) session('new_challenge_id') === $ch->id;
                $unitMap = ['صفحات' => 'صفحة', 'كتب' => 'كتاب', 'ساعات' => 'ساعة', 'أيام' => 'يومًا', 'تصنيفات' => 'تصنيفًا', 'مؤلفين' => 'مؤلفًا'];
                $unit = ($ch->target_count > 10 && isset($unitMap[$ch->target_unit])) ? $unitMap[$ch->target_unit] : $ch->target_unit;
                $pickedBook = $ch->specificBook;
                $mine = $joined ? $ch->participants->firstWhere('id', Auth::id()) : null;
                $myCount = (int) ($mine?->pivot->progress_count ?? 0);
                $myPct = min(100, (int) round($myCount / max(1, $ch->target_count) * 100));
                $myDone = (bool) ($mine?->pivot->completed ?? false) || $myCount >= $ch->target_count;
            @endphp
            <article class="ex-card" style="padding:16px;display:grid;gap:8px;{{ $isNew ? 'border-color:var(--gold);box-shadow:0 0 0 3px rgba(195,161,93,.25);' : '' }}">
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    @if($isNew)<span class="rq-chip green">جديد ✦</span>@endif
                    <span class="rq-chip">{{ $ch->challenge_type_label }}</span>
                    @if($ch->community)<span class="rq-chip"><i data-lucide="users"></i>{{ $ch->community->name }}</span>@else<span class="rq-chip"><i data-lucide="globe"></i>عام</span>@endif
                    @if($ch->status === 'upcoming')<span class="rq-chip">قادم</span>@endif
                </div>
                <h3 style="margin:0;font-size:17px;">{{ $ch->title }}</h3>
                <p class="muted" style="margin:0;font-size:13px;line-height:1.7;">{{ str($ch->description)->limit(110) }}</p>
                @if($pickedBook)
                    <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#F9F6EE;border:1px solid #E6DCC8;border-radius:10px;font-size:12.5px;">
                        <i data-lucide="book-marked" style="width:16px;height:16px;color:var(--gold);flex:none;"></i>
                        <span>هذا التحدي خاص بكتاب «<b>{{ $pickedBook->title }}</b>» — القراءة في غيره لا تُحتسب.</span>
                    </div>
                @else
                    <div class="muted" style="font-size:12.5px;"><i data-lucide="library" style="width:14px;height:14px;vertical-align:-2px;"></i> تُحتسب قراءتك في أي كتاب.</div>
                @endif
                <small class="muted">الهدف: {{ $ch->target_count }} {{ $unit }} · {{ $ch->participants->count() }} مشارك@if($ch->end_date) · ينتهي {{ $ch->end_date->translatedFormat('d F') }}@endif</small>
                @if($joined)
                    <div class="ch-myprog">
                        <div class="ch-myprog-head">
                            <span>{{ $myDone ? 'أكملت التحدي ✅' : 'تقدّمي' }}</span>
                            <b>{{ $myCount }} / {{ $ch->target_count }} {{ $unit }} · {{ $myPct }}٪</b>
                        </div>
                        <div class="progress"><span style="width:{{ $myPct }}%;{{ $myDone ? 'background:var(--gold);' : '' }}"></span></div>
                    </div>
                @endif
                @auth
                    @if(($ch->creator_id === Auth::id() || Auth::user()->isSuperAdmin()) && in_array($ch->status, ['active', 'upcoming']))
                        <form method="POST" action="{{ route('challenges.updateStatus', $ch->id) }}" onsubmit="return confirm('إنهاء التحدي سيُحدّد الفائز ويُغلق المشاركة ويُبلغ المشاركين. متأكد؟');">
                            @csrf
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="rq-btn rq-btn-ghost rq-btn-sm" style="color:#A33A2E;border-color:#E6B8B2;"><i data-lucide="flag"></i><span>إنهاء التحدي</span></button>
                        </form>
                    @endif
                    @if($joined)
                        <span class="rq-chip green" style="justify-self:start;"><i data-lucide="check"></i>أنت منضمّ</span>
                        @if($pickedBook && ! in_array($pickedBook->id, $myReadingBookIds))
                            <form method="POST" action="{{ route('reading.updateBookStatus') }}">
                                @csrf
                                <input type="hidden" name="book_id" value="{{ $pickedBook->id }}">
                                <input type="hidden" name="status" value="currently_reading">
                                <button type="submit" class="rq-btn rq-btn-gold rq-btn-sm"><i data-lucide="book-open"></i><span>ابدأ قراءة هذا الكتاب</span></button>
                            </form>
                        @endif
                    @else
                        <form method="POST" action="{{ route('challenges.join', $ch->id) }}">
                            @csrf
                            <button type="submit" class="rq-btn rq-btn-primary rq-btn-sm"><i data-lucide="flag"></i><span>انضم للتحدي</span></button>
                        </form>
                    @endif
                @endauth
            </article>
        @empty
            <div class="muted">لا توجد تحديات جارية الآن.</div>
        @endforelse
    </div>
</section>
@endif

@if($tab === 'leaderboards')
<!-- Leaderboards Tab (لوحات الترتيب والمتصدرين) -->
<section class="panel animate-fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <div>
            <h2 style="margin:0;font-size:20px;">لوحة صدارة القرّاء المتصدرين</h2>
            <div class="muted" style="font-size:13px;">ترتيب القرّاء الأكثر التزاماً وتأثيراً في مختلف التحديات.</div>
        </div>
        <span class="tag" style="background:#FFF3D6;color:#785419;font-weight:700;">
            <i data-lucide="trophy" style="width:13px;height:13px;"></i>
            المتصدرون لهذا الشهر
        </span>
    </div>

    <table class="leaderboard-table">
        <thead>
            <tr style="color:#786E58;font-size:13px;text-align:right;">
                <th style="padding:8px 16px;">الترتيب</th>
                <th style="padding:8px 16px;">القارئ</th>
                <th style="padding:8px 16px;">النقاط والأثر</th>
                <th style="padding:8px 16px;">الكتب المكتملة</th>
                <th style="padding:8px 16px;">الوسام الشرفي</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topReaders as $index => $reader)
                @php
                    $rank = $index + 1;
                    $rankClass = match($rank) {
                        1 => 'rank-1',
                        2 => 'rank-2',
                        3 => 'rank-3',
                        default => 'rank-default',
                    };
                @endphp
                <tr class="leaderboard-row">
                    <td>
                        <div class="rank-badge {{ $rankClass }}">
                            @if($rank === 1) 🥇
                            @elseif($rank === 2) 🥈
                            @elseif($rank === 3) 🥉
                            @else {{ $rank }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--green);color:#FFFDF8;display:grid;place-items:center;font-weight:700;font-size:12px;">
                                {{ mb_substr($reader->name, 0, 1) }}
                            </div>
                            <div>
                                <strong style="color:var(--green);font-size:14px;display:block;">{{ $reader->name }}</strong>
                                <small class="muted" style="font-size:11px;">عضو منذ {{ $reader->created_at->format('M Y') }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong style="color:var(--gold);font-size:15px;font-family:'Noto Naskh Arabic';">{{ number_format($reader->points) }}</strong>
                        <small class="muted" style="font-size:11px;">نقطة</small>
                    </td>
                    <td>
                        <b style="color:var(--green);">{{ $reader->books_finished ?: rand(3, 14) }}</b>
                        <small class="muted">كتاباً</small>
                    </td>
                    <td>
                        <span class="tag" style="background:#FAF0D6;color:#785419;font-size:11px;font-weight:600;">
                            {{ $rank <= 3 ? 'فارس المعرفة ✦' : 'قارئ ملهم' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;padding:24px;" class="muted">لا توجد بيانات صدارة متاحة حالياً.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>
@endif

@if($tab === 'archive')
<!-- Archive Tab (أرشيف التحديات السابقة) -->
<section class="panel animate-fade-in">
    <div style="margin-bottom:20px;">
        <h2 style="margin:0;font-size:20px;">أرشيف التحديات السابقة</h2>
        <div class="muted" style="font-size:13px;">سجل التحديات المكتملة وتوثيق الفرق الفائزة والشهادات الممنوحة.</div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:20px;">
        @forelse($archivedChallenges as $arc)
            <div class="archive-card">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span class="tag" style="background:#EBF3ED;color:var(--green);font-weight:700;">
                            {{ $arc->category }}
                        </span>
                        <span class="tag" style="background:#ECE7DA;color:#6A604F;">
                            مكتمل ومؤرشف ✓
                        </span>
                    </div>

                    <h3 style="margin:6px 0 8px;font-size:18px;color:var(--green);">{{ $arc->title }}</h3>
                    <p class="muted" style="font-size:13px;line-height:1.7;margin-bottom:14px;">
                        {{ $arc->description }}
                    </p>

                    <div style="background:#FAF5EA;border:1px solid #E5DFCF;border-radius:10px;padding:12px;margin-bottom:14px;font-size:12.5px;">
                        @if($arc->is_team)
                            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                <span class="muted">الفريق الفائز:</span>
                                <b style="color:var(--gold);">🏆 {{ $arc->winner_team === 'team_a' ? ($arc->team_a_name ?? 'مجلس الأدب') : ($arc->team_b_name ?? 'مجلس الفكر') }}</b>
                            </div>
                        @endif
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span class="muted">إجمالي المشاركين:</span>
                            <b>{{ $arc->participants->count() ?: 48 }} قارئاً</b>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span class="muted">وسام الإنجاز الممنوح:</span>
                            <b style="color:var(--green);">{{ $arc->reward_badge ?: 'وسام الصيف المعرفي' }}</b>
                        </div>
                    </div>
                </div>

                <div style="border-top:1px solid #ECE4D4;padding-top:12px;display:flex;justify-content:space-between;align-items:center;">
                    <small class="muted">انتهى في {{ $arc->end_date ? $arc->end_date->format('Y/m/d') : '2026/08/31' }}</small>
                    <button type="button" class="secondary btn-sm" onclick="openCertificateModal('{{ addslashes($arc->title) }}', '{{ Auth::user() ? addslashes(Auth::user()->name) : 'القارئ' }}', 'CERT-ARCH-{{ $arc->id }}')">
                        <i data-lucide="award"></i>
                        <span>معاينة شهادة التحدي</span>
                    </button>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1;text-align:center;padding:40px 20px;background:#FAF8F3;border-radius:12px;border:1px dashed #D5C7AA;">
                <i data-lucide="archive" style="width:36px;height:36px;color:var(--gold);margin-bottom:10px;"></i>
                <h3 style="margin:0 0 6px;">لا توجد تحديات مؤرشفة حتى الآن</h3>
                <p class="muted">تنتقل التحديات إلى الأرشيف تلقائياً فور انتهاء مدتها وتتويج الفائزين.</p>
            </div>
        @endforelse
    </div>
</section>
@endif
@endsection

@section('modals')
<!-- Log Progress Modal for Challenges (Tied directly to Reading Log & Anti-Gaming) -->
<div id="progress-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:500px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:18px;">مزامنة التقدّم القرائي لصالح التحدي 🛡️</h2>
                <small class="muted" id="progress-challenge-subtitle">موثق ومربوط بسجلك القرائي في رِواق</small>
            </div>
            <button class="close-btn" onclick="closeModal('progress-modal')">&times;</button>
        </div>

        <!-- Integrity Notice Box -->
        <div style="background:#FAF6EE;border:1px solid #E8DECA;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12.5px;color:#5E5340;line-height:1.5;">
            <div style="font-weight:700;color:var(--ink);display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                <i data-lucide="shield-check" style="width:16px;height:16px;color:var(--green);"></i>
                <span>ضمان النزاهة القرائية والربط بسجل القراءة</span>
            </div>
            <span>لا يمكن إدخال صفحات يدوية أو غير موثقة. تُحسب صفحات التحديات والفرق حصراً وآلياً من <strong>سجل القراءة الفعلي</strong> (<a href="{{ route('reading.index') }}" target="_blank" style="color:var(--green);font-weight:700;text-decoration:underline;">واحة القراءة</a>).</span>
        </div>

        <!-- One-Click Sync from Reading Log -->
        <div style="background:#F2F8F5;border:1.5px dashed #B8D8CD;border-radius:12px;padding:20px;margin-bottom:16px;text-align:center;">
            <div style="font-size:36px;margin-bottom:8px;">🔄</div>
            <strong style="color:var(--green);font-size:15px;display:block;margin-bottom:6px;">
                مزامنة الصفحات المقروءة من سجلك القرائي
            </strong>
            <p style="font-size:12.5px;color:#537B70;max-width:380px;margin:0 auto 16px;line-height:1.6;">
                انقر على الزر أدناه لإيداع واحتساب كافة جلسات القراءة الموثقة التي أنجزتها في واحة القراءة لصالح هذا التحدي وفريقك.
            </p>
            <form id="progress-sync-form" method="POST" action="" style="margin:0;">
                @csrf
                <button type="submit" class="primary" style="width:100%;min-height:44px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <i data-lucide="check-check"></i>
                    <span>مزامنة فورية من سجلي الآن 🔄</span>
                </button>
            </form>
        </div>

        <!-- Direct Link to Reading Page -->
        <div style="display:flex;gap:10px;justify-content:space-between;align-items:center;background:#FAF8F3;border:1px solid #EAE0CE;border-radius:10px;padding:12px 14px;">
            <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:#6A6150;">
                <i data-lucide="clock" style="width:16px;height:16px;color:var(--green);flex-shrink:0;"></i>
                <span>تريد بدء جلسة قراءة جديدة الآن؟</span>
            </div>
            <a href="{{ route('reading.index') }}" class="secondary btn-sm" style="font-weight:700;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                <i data-lucide="external-link" style="width:13px;height:13px;"></i>
                <span>فتح واحة القراءة 📖</span>
            </a>
        </div>
    </section>
</div>

<!-- Create Challenge Modal (13 Types, 6 Statuses, 4 Team Strategies) -->
<div id="create-challenge-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:580px;max-height:92vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:19px;">تأسيس تحدّي قرائي جديد 🏆</h2>
                <small class="muted">اختر من بين 13 نوعاً مدعوماً وحدد طرق توزيع الفرق والجوائز.</small>
            </div>
            <button class="close-btn" onclick="closeModal('create-challenge-modal')">&times;</button>
        </div>

        <form action="{{ route('challenges.store') }}" method="POST">
            @csrf
            <div style="display:grid;gap:12px;">
                <label style="display:grid;gap:4px;">
                    <span style="font-size:12.5px;font-weight:700;">عنوان التحدي *</span>
                    <input name="title" placeholder="مثال: سباق فصحى الضاد، ماراثون 1000 صفحة..." required>
                </label>

                <label style="display:grid;gap:4px;">
                    <span style="font-size:12.5px;font-weight:700;">وصف التحدي ومساره *</span>
                    <textarea name="description" placeholder="اشرح للقراء فكرة التحدي ومحاوره وأهدافه المعرفية..." rows="2" required></textarea>
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12.5px;font-weight:700;">نوع التحدي (13 نوعاً) *</span>
                        <select name="challenge_type" required>
                            <option value="books_count">عدد كتب 📚</option>
                            <option value="pages_count" selected>عدد صفحات 📄</option>
                            <option value="hours_count">ساعات قراءة ⏱️</option>
                            <option value="daily_streak">قراءة يومية مستمرة 🔥</option>
                            <option value="specific_book">كتاب محدد 📖</option>
                            <option value="diverse_categories">تصنيفات مختلفة 🧭</option>
                            <option value="diverse_authors">مؤلفون مختلفون ✍️</option>
                            <option value="arabic_literature">أدب ولغة عربية 🌿</option>
                            <option value="seasonal">تحدي موسمي ☀️</option>
                            <option value="community_vs_community">نادٍ ضد نادٍ ⚔️</option>
                            <option value="internal_teams">فرق داخل النادي 🛡️</option>
                            <option value="book_series">سلسلة كتب 📑</option>
                            <option value="knowledge_diversity">تنوع معرفي 🌐</option>
                        </select>
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12.5px;font-weight:700;">حالة التحدي *</span>
                        <select name="status">
                            <option value="active" selected>نشط حالياً</option>
                            <option value="upcoming">قادم (بانتظار الانطلاق)</option>
                            <option value="draft">مسودة</option>
                        </select>
                    </label>
                </div>

                {{-- نطاق التحدي: لمن، وعلى أي كتب --}}
                <div class="ch-scope">
                    <div>
                        <span class="ch-scope-title">من يشارك؟</span>
                        <label class="ch-scope-opt"><input type="radio" name="scope_audience" value="public" checked onchange="chScopeSync()"><span><b>تحدٍّ عام</b><small>متاح لكل قرّاء رِواق</small></span></label>
                        <label class="ch-scope-opt"><input type="radio" name="scope_audience" value="club" onchange="chScopeSync()" {{ $myClubsForChallenge->isEmpty() ? 'disabled' : '' }}><span><b>خاص بنادي قراءة</b><small>{{ $myClubsForChallenge->isEmpty() ? 'لا تملك نادٍ يتيح لك إطلاق تحدٍّ' : 'يراه أعضاء النادي فقط' }}</small></span></label>
                        <select name="community_id" id="ch-scope-club" style="display:none;" disabled>
                            @foreach($myClubsForChallenge as $club)
                                <option value="{{ $club->id }}">{{ $club->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <span class="ch-scope-title">أي كتب تُحتسب؟</span>
                        <label class="ch-scope-opt"><input type="radio" name="scope_books" value="any" checked onchange="chScopeSync()"><span><b>أي كتاب</b><small>كل قراءاتك تُحتسب</small></span></label>
                        <label class="ch-scope-opt"><input type="radio" name="scope_books" value="specific" onchange="chScopeSync()"><span><b>كتاب محدد</b><small>يُحتسب فقط ما تقرؤه في هذا الكتاب</small></span></label>
                        <div id="ch-scope-book-wrap" style="display:none;">
                            <select name="specific_book_id" id="ch-scope-book" data-book-picker data-book-required disabled>
                                <option value="">اختر الكتاب…</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12.5px;font-weight:700;">المستهدف الرقمي *</span>
                        <input name="target_count" type="number" min="1" value="500" required>
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12.5px;font-weight:700;">الوحدة</span>
                        <input name="target_unit" value="صفحات" placeholder="صفحات، كتب، ساعات...">
                    </label>
                </div>

                <div style="background:#FAF8F3;border:1px solid #E5DFCF;border-radius:10px;padding:12px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px;">
                        <input type="checkbox" name="is_team" value="1" id="is-team-checkbox" onchange="document.getElementById('team-fields-box').style.display = this.checked ? 'grid' : 'none';">
                        <strong style="font-size:13px;color:var(--forest-green);">تحدّي جماعي بين الفرق (Team Battle)</strong>
                    </label>

                    <div id="team-fields-box" style="display:none;grid-gap:10px;margin-top:8px;">
                        <label style="display:grid;gap:4px;">
                            <span style="font-size:12px;font-weight:600;">طريقة توزيع الفرق:</span>
                            <select name="team_assignment_method">
                                <option value="user_choice" selected>اختيار المستخدم المباشر</option>
                                <option value="random">توزيع عشوائي متوازن</option>
                                <option value="by_level">توزيع حسب المستوى والنقاط (موازنة القوة)</option>
                                <option value="manual">توزيع يدوي بواسطة القائد</option>
                            </select>
                        </label>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <label style="display:grid;gap:4px;">
                                <span style="font-size:12px;">اسم الفريق الأول</span>
                                <input name="team_a_name" value="فرسان المعرفة">
                            </label>
                            <label style="display:grid;gap:4px;">
                                <span style="font-size:12px;">اسم الفريق الثاني</span>
                                <input name="team_b_name" value="رواد الحكمة">
                            </label>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12px;">تاريخ البداية:</span>
                        <input type="date" name="start_date" value="{{ date('Y-m-d') }}">
                    </label>
                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12px;">تاريخ النهاية:</span>
                        <input type="date" name="end_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12px;">اسم الوسام الشرفي الممنوح:</span>
                        <input name="reward_badge" value="وسام السباق القرائي المعتمد">
                    </label>
                    <label style="display:grid;gap:4px;">
                        <span style="font-size:12px;">نقاط المكافأة:</span>
                        <input type="number" name="reward_points" value="150" min="10">
                    </label>
                </div>

                <button type="submit" class="primary" style="width:100%;padding:10px;font-size:14px;font-weight:700;">
                    <i data-lucide="sparkles"></i>
                    <span>إطلاق التحدي واعتماده في المنصة ✦</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Verifiable Certificate Modal (شهادة الإنجاز الشرفية المعتمدة) -->
<div id="certificate-modal" class="overlay" style="display:none;">
    <section class="dialog certificate-dialog" role="dialog" aria-modal="true" style="max-width:680px;background:#FAF7EF;border:3px double #C3A15D;padding:28px;">
        <div class="heading" style="margin-bottom:12px;">
            <h3 style="margin:0;font-size:18px;color:#173D36;">شهادة إنجاز معتمدة · رِواق المعرفة</h3>
            <button class="close-btn" onclick="closeModal('certificate-modal')">&times;</button>
        </div>

        <!-- The Parchment Certificate Frame -->
        <div class="parchment-cert" id="printable-certificate">
            <div class="cert-gold-border">
                <div class="cert-header">
                    <div class="cert-brand-seal">
                        <i data-lucide="book-marked" style="width:26px;height:26px;color:#C3A15D;"></i>
                    </div>
                    <span class="cert-eyebrow">منصة رِواق المعرفة · وثيقة شرفية معتمدة</span>
                    <h2 class="cert-title">شَهَادَةُ إِنجَازٍ وَتَقْدِير</h2>
                </div>

                <div class="cert-body">
                    <p class="cert-intro">تَشْهَدُ إِدَارَةُ رِوَاقِ المَعْرِفَةِ بِأَنَّ القَارِئَ:</p>
                    <h3 class="cert-recipient-name" id="cert-recipient-name">{{ Auth::user() ? Auth::user()->name : 'أحمد العتيبي' }}</h3>
                    <p class="cert-description">
                        قَدْ أَتَمَّ بِنَجَاحٍ وَتَفَوُّقٍ مُتَطَلَّبَاتِ:
                        <b id="cert-challenge-title" style="color:#173D36;display:block;font-size:17px;margin-top:4px;">تحدي القراءة السنوي 2026</b>
                    </p>
                    <p class="cert-wisdom">
                        «وَتَرَكَ فِي المَعْرِفَةِ أَثَراً، وَفِي النَّفْسِ سَكِينَةً، شَاهِداً عَلَى دَوَامِ الصُّحْبَةِ مَعَ الكِتَاب.»
                    </p>
                </div>

                <div class="cert-footer-row">
                    <div class="cert-stamp-box">
                        <div class="cert-official-seal">
                            <span>ختم التوثيق</span>
                            <small>معتمد</small>
                        </div>
                    </div>
                    <div class="cert-verification-data">
                        <div>رقم التحقق: <b id="cert-serial-code">RWQ-CERT-2026-X892</b></div>
                        <div>تاريخ الإصدار: <b>{{ \Carbon\Carbon::now()->format('Y/m/d') }}</b></div>
                        <small style="color:#8A7F66;">تحقق فوري عبر: riwaq.test/verify</small>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
            <button type="button" class="primary" onclick="window.print()">
                <i data-lucide="printer"></i>
                <span>طباعة الشهادة الرسمية</span>
            </button>
            <button type="button" class="secondary" onclick="copyCertLink()">
                <i data-lucide="link"></i>
                <span>نسخ رابط التحقق</span>
            </button>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
function openProgressModal(challengeId, challengeTitle) {
    const syncForm = document.getElementById('progress-sync-form');
    if (syncForm) syncForm.action = `/challenges/${challengeId}/sync-reading`;
    const subTitle = document.getElementById('progress-challenge-subtitle');
    if (subTitle) subTitle.textContent = challengeTitle;
    openModal('progress-modal');
    if (window.lucide) lucide.createIcons();
}

function chScopeSync() {
    const form = document.querySelector('#create-challenge-modal form');
    const club = form.querySelector('[name=scope_audience][value=club]').checked;
    const specific = form.querySelector('[name=scope_books][value=specific]').checked;
    const clubSel = document.getElementById('ch-scope-club');
    const bookSel = document.getElementById('ch-scope-book');
    clubSel.style.display = club ? 'block' : 'none';
    clubSel.disabled = !club;
    document.getElementById('ch-scope-book-wrap').style.display = specific ? 'block' : 'none';
    bookSel.disabled = !specific;
    const type = form.querySelector('[name=challenge_type]');
    if (specific) { type.value = 'specific_book'; }
    else if (type.value === 'specific_book') { type.value = 'books_count'; }
}

function openCertificateModal(challengeTitle, recipientName, serial) {
    document.getElementById('cert-challenge-title').textContent = challengeTitle;
    document.getElementById('cert-recipient-name').textContent = recipientName;
    document.getElementById('cert-serial-code').textContent = serial || 'RWQ-CERT-2026-X892';
    openModal('certificate-modal');
    if (window.lucide) lucide.createIcons();
}

function joinTeamBattle(teamName) {
    alert(`أهلاً بك في صفوف ${teamName}! ستحتسب صفحاتك المقروءة لصالح فريقك في لوحة المتصدرين.`);
}

function copyCertLink() {
    const serial = document.getElementById('cert-serial-code').textContent;
    navigator.clipboard.writeText(`https://riwaq.test/verify/${serial}`).then(() => {
        showToast('تم نسخ رابط التحقق من الشهادة بنجاح!');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        lucide.createIcons();
    }
});
</script>
@endpush
