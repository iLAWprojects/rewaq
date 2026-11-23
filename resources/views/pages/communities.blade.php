@extends('layouts.app')

@section('title', 'نوادي القراءة')
@section('crumb', 'نوادي القراءة')

@push('styles')
<style>
.community-type-filter-bar {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 12px;
    margin-bottom: 16px;
}
.comm-type-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    color: #4A564A;
    background: #FAF8F3;
    border: 1px solid #DFD7C5;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.comm-type-pill:hover {
    background: #EDE6D6;
    color: var(--green);
}
.comm-type-pill.active {
    background: var(--green);
    color: #FFFDF8;
    border-color: var(--green);
    box-shadow: 0 3px 10px rgba(23,61,54,0.18);
}
/* Already flipped active council */
.comm-flip-card-wrapper.is-active-community .comm-flip-card-inner,
.comm-flip-card-wrapper.already-flipped .comm-flip-card-inner {
    transform: rotateY(180deg) !important;
}
.comm-flip-card-wrapper.is-active-community .comm-flip-card-front,
.comm-flip-card-wrapper.already-flipped .comm-flip-card-front {
    pointer-events: none !important;
    z-index: 1 !important;
}
.comm-flip-card-wrapper.is-active-community .comm-flip-card-back,
.comm-flip-card-wrapper.already-flipped .comm-flip-card-back {
    pointer-events: auto !important;
    z-index: 10 !important;
    border: 2px solid var(--gold) !important;
    box-shadow: 0 10px 28px rgba(195, 161, 93, 0.28) !important;
    background: linear-gradient(180deg, #FFFFFF 0%, #FAF6ED 100%) !important;
}
.comm-flip-card-wrapper.is-active-community:hover .comm-flip-card-inner,
.comm-flip-card-wrapper.already-flipped:hover .comm-flip-card-inner {
    transform: rotateY(180deg) translateY(-4px) !important;
    box-shadow: 0 16px 36px rgba(195, 161, 93, 0.38) !important;
}
.comm-flip-card-wrapper.is-active-community.unflipped-active .comm-flip-card-inner {
    transform: rotateY(0deg) !important;
}
.comm-flip-card-wrapper.is-active-community.unflipped-active .comm-flip-card-front {
    pointer-events: auto !important;
    z-index: 10 !important;
}
.comm-flip-card-wrapper.is-active-community.unflipped-active .comm-flip-card-back {
    pointer-events: none !important;
    z-index: 1 !important;
}
.highlight-reaction-btn.is-liked {
    background: var(--gold) !important;
    border-color: var(--gold) !important;
    color: #FFFDF8 !important;
    box-shadow: 0 2px 8px rgba(195, 161, 93, 0.35);
}
.highlight-reaction-btn.is-liked:hover {
    background: #A88636 !important;
    border-color: #A88636 !important;
    color: #FFFDF8 !important;
    transform: scale(1.05);
}
.comm-announcement-banner {
    background: linear-gradient(135deg, #FFFDF8 0%, #FAF5EA 100%);
    border: 1.5px solid var(--gold);
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 20px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
    box-shadow: 0 4px 16px rgba(195,161,93,0.15);
}
.requests-dashboard-panel {
    background: #FFFDF8;
    border: 1.5px solid #E1B367;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 16px rgba(195,161,93,0.12);
}
.request-item-card {
    background: #FAF7EF;
    border: 1px solid #E8E0D0;
    border-radius: 10px;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.role-badge-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 11.5px;
    font-weight: 700;
}
.moderation-queue-panel {
    background: #FFFDF8;
    border: 1.5px solid #D9534F;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 16px rgba(217,83,79,0.12);
}
.report-item-card {
    background: #FAF7EF;
    border: 1px solid #EED8D8;
    border-radius: 10px;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.comments-container {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #E2D9C5;
}
.comment-bubble {
    background: #FAF8F3;
    border: 1px solid #EAE3D2;
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 8px;
}
.nested-reply-box {
    margin-inline-start: 22px;
    border-inline-start: 3px solid var(--gold);
    background: #FFFDF9;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 6px;
    font-size: 13px;
}
.report-flag-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #A39B8B;
    padding: 2px 4px;
    transition: color 0.2s;
}
.report-flag-btn:hover {
    color: #C0392B;
}
.spoiler-blur {
    filter: blur(5px);
    background: rgba(195, 161, 93, 0.15);
    user-select: none;
    cursor: pointer;
    transition: filter 0.25s ease, background 0.25s ease;
    border-radius: 4px;
    padding: 1px 6px;
    display: inline-block;
}
.spoiler-blur.revealed {
    filter: none !important;
    background: transparent !important;
    user-select: auto !important;
}
.mention-tag {
    color: var(--green);
    background: rgba(35, 87, 56, 0.1);
    border-radius: 4px;
    padding: 1px 5px;
    font-weight: 700;
    font-size: 0.95em;
    display: inline-block;
}
.book-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
    background: #FAF8F3;
    border: 1px solid #DFD7C5;
    color: #4A564A;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.book-filter-pill:hover {
    background: #EDE6D6;
    color: var(--green);
}
.book-filter-pill.active {
    background: var(--green);
    color: #FFFDF8;
    border-color: var(--green);
    box-shadow: 0 2px 8px rgba(35,87,56,0.18);
}
.disc-react-btn.active-liked {
    background: #FFF1F2 !important;
    border-color: #FDA4AF !important;
    color: #E11D48 !important;
}
</style>
@endpush

@section('content')
@php
    $scopeNow = $scope ?? 'joined';
    $typeNow = ($typeFilter ?? 'الكل');
    $typeParam = $typeNow !== 'الكل' ? $typeNow : null;
    $typeLabels = [
        'الكل' => 'كل الأنواع',
        'open' => 'مفتوحة (انضمام مباشر)',
        'public' => 'عامة (بطلب وموافقة)',
        'private' => 'خاصة (مغلقة)',
        'institutional' => 'مؤسسية وأكاديمية',
        'specialized' => 'تخصصية',
        'book_of_month' => 'كتاب الشهر',
    ];
    $commIcon = fn ($cat) => match ($cat) { 'فلسفة ورواية' => 'feather', 'فكر' => 'lightbulb', 'تاريخ' => 'landmark', 'سيرة' => 'user-check', default => 'book-open' };
@endphp

<div class="cm-head">
    <div>
        <h1>نوادي القراءة</h1>
        <p>انضم إلى نوادٍ تقرأ معك، وناقش، وشارك في التصويت على الكتاب القادم.</p>
    </div>
    @auth
        <div class="cm-head-actions">
            <button type="button" class="rq-btn rq-btn-ghost" onclick="openModal('join-by-code-modal')"><i data-lucide="key-round"></i><span>الانضمام بكود</span></button>
            <button type="button" class="rq-btn rq-btn-primary" onclick="openModal('create-community-modal')"><i data-lucide="plus"></i><span>تأسيس نادي قراءة</span></button>
        </div>
    @endauth
</div>

<div class="cm-toolbar">
    @auth
        <div class="cm-scope" role="tablist">
            <a href="{{ route('communities.index', array_filter(['scope' => 'joined', 'type' => $typeParam])) }}" class="{{ $scopeNow === 'joined' ? 'on' : '' }}">نواديي <b>{{ $myCommunitiesCount ?? 0 }}</b></a>
            <a href="{{ route('communities.index', array_filter(['scope' => 'unjoined', 'type' => $typeParam])) }}" class="{{ $scopeNow === 'unjoined' ? 'on' : '' }}">اكتشف <b>{{ $unjoinedCommunitiesCount ?? 0 }}</b></a>
            <a href="{{ route('communities.index', array_filter(['scope' => 'all', 'type' => $typeParam])) }}" class="{{ $scopeNow === 'all' ? 'on' : '' }}">الكل <b>{{ $totalCommunitiesCount ?? 0 }}</b></a>
        </div>
    @endauth
    <label class="cm-type">
        <span>النوع</span>
        <select onchange="window.location.href = this.value">
            @foreach($typeLabels as $tKey => $tLabel)
                <option value="{{ route('communities.index', array_filter(['scope' => $scopeNow, 'type' => $tKey !== 'الكل' ? $tKey : null])) }}" {{ $typeNow === $tKey ? 'selected' : '' }}>{{ $tLabel }}</option>
            @endforeach
        </select>
    </label>
</div>

{{-- شريط نوادي القراءة --}}
<section id="knowledge-councils-showcase" class="cm-strip">
    @forelse($allCommunities ?? [] as $comm)
        @php
            $isActiveCommunity = $comm->id == $community->id;
            $isJoinedComm = in_array($comm->id, $joinedCommunityIds ?? []);
            $commColor = $comm->cover_color ?: '#173D36';
        @endphp
        <a href="{{ route('communities.index', array_filter(['community_id' => $comm->id, 'scope' => $scope, 'type' => $typeParam])) }}"
           class="cm-card {{ $isActiveCommunity ? 'active' : '' }}" style="--accent: {{ $commColor }};">
            <span class="cm-card-ico"><i data-lucide="{{ $commIcon($comm->category) }}"></i></span>
            <span class="cm-card-main">
                <b>{{ $comm->name }}</b>
                <small>{{ $comm->typeLabel() }} · {{ $comm->category }}</small>
                <span class="cm-card-stats">
                    <span><i data-lucide="users"></i>{{ $comm->members_count ?? 0 }}</span>
                    <span><i data-lucide="messages-square"></i>{{ $comm->discussions_count ?? 0 }}</span>
                    @if(Auth::check() && ($comm->creator_id === Auth::id() || (isset($comm->pivot) && $comm->pivot->role === 'owner')))
                        <span class="cm-joined" style="color:var(--gold-light);font-weight:700;"><i data-lucide="crown"></i>المؤسس</span>
                    @elseif($isJoinedComm)
                        <span class="cm-joined"><i data-lucide="check"></i>عضو</span>
                    @endif
                </span>
            </span>
        </a>
    @empty
        <div class="cm-empty">
            <i data-lucide="users"></i>
            @if($scopeNow === 'unjoined')
                <h3>أنت منضم إلى كل نوادي القراءة المتاحة</h3>
                <a href="{{ route('communities.index', ['scope' => 'joined']) }}" class="rq-btn rq-btn-ghost rq-btn-sm">العودة إلى نواديي</a>
            @elseif($scopeNow === 'joined')
                <h3>لم تنضم إلى أي نادٍ بعد</h3>
                <a href="{{ route('communities.index', ['scope' => 'all']) }}" class="rq-btn rq-btn-primary rq-btn-sm">استكشف نوادي القراءة</a>
            @else
                <h3>لا توجد نوادي مطابقة</h3>
                <a href="{{ route('communities.index', ['scope' => 'all']) }}" class="rq-btn rq-btn-ghost rq-btn-sm">عرض الكل</a>
            @endif
        </div>
    @endforelse
</section>

{{-- ترويسة النادي المختار --}}
<section class="cm-mast" style="--accent: {{ $community->cover_color ?? '#173D36' }};">
    <div class="cm-mast-top">
        <div class="cm-mast-tags">
            <span>{{ $community->typeLabel() }} · {{ $community->category }}</span>
            @if($community->city)<span><i data-lucide="map-pin"></i>{{ $community->city }}</span>@endif
            @if($community->language)<span><i data-lucide="globe"></i>{{ $community->language }}</span>@endif
            @if($community->linkedLibrary)<span><i data-lucide="library-big"></i>{{ $community->linkedLibrary->name }}</span>@endif
            @if($community->institutional_domain)<span><i data-lucide="graduation-cap"></i>{{ $community->institutional_domain }}</span>@endif
            @if($userRole)
                <span class="role" style="{{ $userRole === 'owner' ? 'background:linear-gradient(135deg, rgba(195,161,93,0.4), rgba(195,161,93,0.22));color:var(--gold-light);border:1px solid rgba(195,161,93,0.5);font-weight:700;' : '' }}">
                    @if($userRole === 'owner')
                        <i data-lucide="crown"></i><span>المؤسس</span>
                    @elseif($userRole === 'admin')
                        <i data-lucide="shield-check"></i><span>مشرف</span>
                    @elseif($userRole === 'coordinator')
                        <i data-lucide="compass"></i><span>منسق قراءات</span>
                    @else
                        <span>عضو</span>
                    @endif
                </span>
            @endif
        </div>
        <button type="button" class="cm-code" title="نسخ الكود"
            onclick="window.copyToClipboard('{{ $community->code }}', 'تم نسخ كود النادي: {{ $community->code }}');">
            <i data-lucide="hash"></i><span>{{ $community->code }}</span><i data-lucide="copy"></i>
        </button>
    </div>

    <h2>{{ $community->name }}</h2>
    <p>{{ $community->description }}</p>

    @if($community->welcome_message)
        <div class="cm-welcome"><i data-lucide="sparkles"></i><span>{{ $community->welcome_message }}</span></div>
    @endif

    <div class="cm-mast-stats">
        <div><b>{{ $community->members_count ?? $community->members()->count() }}@if($community->max_members) / {{ $community->max_members }}@endif</b><span>عضو</span></div>
        <div><b>{{ $discussions->count() }}</b><span>مناقشة</span></div>
        <div><b>{{ $booksReadCount ?? 1 }}</b><span>كتب منجزة</span></div>
        <div class="grow"><b>{{ $community->currentBook->title ?? 'وحي القلم' }}</b><span>كتاب الشهر</span></div>
    </div>

    <div class="cm-actions">
        @auth
            @if(! $isMember)
                @if($community->hasReachedCapacity())
                    <span class="cm-note"><i data-lucide="alert-triangle"></i>اكتملت سعة النادي ({{ $community->max_members }})</span>
                @elseif($hasPendingRequest)
                    <span class="cm-note"><i data-lucide="clock"></i>طلب انضمامك قيد المراجعة</span>
                @elseif(! $community->canDirectJoin(Auth::user()))
                    <button type="button" class="rq-btn rq-btn-gold" onclick="openModal('join-request-modal')"><i data-lucide="key"></i><span>طلب انضمام</span></button>
                @else
                    <form method="POST" action="{{ route('communities.toggle', $community->id) }}">
                        @csrf
                        <button type="submit" class="rq-btn rq-btn-gold"><i data-lucide="user-plus"></i><span>انضم إلى النادي</span></button>
                    </form>
                @endif
            @else
                @if($community->isOwner(Auth::user()))
                    <span class="rq-btn rq-btn-gold rq-btn-badge" style="cursor:default;pointer-events:none;background:linear-gradient(135deg, rgba(217,160,82,0.35), rgba(217,160,82,0.2));border-color:var(--gold);color:var(--gold-light);box-shadow:0 2px 10px rgba(0,0,0,0.18);font-weight:700;">
                        <i data-lucide="crown"></i><span>أنت المؤسس</span>
                    </span>
                @else
                    <form method="POST" action="{{ route('communities.toggle', $community->id) }}" onsubmit="return confirm('هل تريد مغادرة هذا النادي؟');">
                        @csrf
                        <button type="submit" class="rq-btn rq-btn-light"><i data-lucide="check"></i><span>أنت عضو · مغادرة</span></button>
                    </form>
                @endif
            @endif

            <button type="button" class="rq-btn rq-btn-light" onclick="openModal('invite-modal')"><i data-lucide="share-2"></i><span>دعوة رفاق</span></button>

            @if($community->rules)
                <button type="button" class="rq-btn rq-btn-light" onclick="openModal('community-rules-modal')"><i data-lucide="book-marked"></i><span>القواعد</span></button>
            @endif

            @if($canManage || ($community->allow_member_challenges && $isMember) || ($community->allow_member_meetings && $isMember))
                <details class="cm-more">
                    <summary class="rq-btn rq-btn-light"><i data-lucide="settings"></i><span>إدارة وأدوات</span></summary>
                    <div class="cm-more-menu">
                        @if($canManage)
                            <button type="button" onclick="openModal('community-settings-modal')"><i data-lucide="settings"></i>إعدادات النادي</button>
                            <button type="button" onclick="openModal('import-members-modal')"><i data-lucide="file-spreadsheet"></i>استيراد أعضاء</button>
                            <button type="button" onclick="openModal('announcement-modal')"><i data-lucide="pin"></i>نشر إعلان مثبت</button>
                        @endif
                        @if($canManage || ($community->allow_member_challenges && $isMember))
                            <button type="button" onclick="openModal('create-challenge-modal')"><i data-lucide="target"></i>إطلاق تحدٍّ</button>
                        @endif
                        @if($canManage || ($community->allow_member_meetings && $isMember))
                            <button type="button" onclick="openModal('create-meeting-modal')"><i data-lucide="calendar-plus"></i>جدولة لقاء</button>
                        @endif
                        @if($community->isOwner(Auth::user()) && $community->members->where('id', '!=', Auth::id())->count() > 0)
                            <button type="button" class="danger" onclick="openModal('transfer-community-ownership-modal')"><i data-lucide="shield-alert"></i>نقل الملكية</button>
                        @endif
                    </div>
                </details>
            @endif
        @else
            <a href="{{ route('login') }}" class="rq-btn rq-btn-gold"><i data-lucide="log-in"></i><span>سجّل الدخول للانضمام والمشاركة</span></a>
        @endauth
    </div>
</section>

<div class="cm-tabs" role="tablist" id="cm-tabs">
    <button type="button" role="tab" data-tab="overview" class="on"><i data-lucide="layout-dashboard"></i><span>نظرة عامة</span></button>
    <button type="button" role="tab" data-tab="discussions"><i data-lucide="messages-square"></i><span>النقاش والتصويت</span></button>
    <button type="button" role="tab" data-tab="reading"><i data-lucide="book-open"></i><span>الإشراقات وغرفة القراءة</span></button>
    <button type="button" role="tab" data-tab="members"><i data-lucide="trophy"></i><span>التحديات والأعضاء</span></button>
</div>



<div class="cm-panel on" data-tab-panel="overview">
<!-- Pinned Announcements Banner (الإعلانات والتنبيهات المتبثة) -->
@if(isset($announcements) && $announcements->count() > 0)
@foreach($announcements as $ann)
<div class="comm-announcement-banner animate-fade-in delay-1">
    <div style="background:var(--gold);color:#173D36;width:40px;height:40px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;">
        <i data-lucide="pin" style="width:20px;height:20px;"></i>
    </div>
    <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="tag" style="background:#FFF3D6;color:#785419;font-weight:700;font-size:11px;">إعلان مثبت 📌</span>
                <h3 style="margin:0;font-size:16.5px;color:var(--green);">{{ $ann->title }}</h3>
            </div>
            <small class="muted" style="font-size:12px;">نُشر {{ $ann->created_at->diffForHumans() }}</small>
        </div>
        <p style="margin:8px 0 0;line-height:1.75;font-size:13.5px;color:#354435;">
            {{ $ann->content }}
        </p>
    </div>
</div>
@endforeach
@endif

<!-- Manager Dashboard for Pending Join Requests (طلبات الانضمام المعلقة) -->
@if($canManage && isset($pendingRequests) && $pendingRequests->count() > 0)
<section class="requests-dashboard-panel animate-fade-in delay-1" id="pending-requests">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="shield-alert" style="color:var(--gold);width:22px;height:22px;"></i>
            <h3 style="margin:0;font-size:17px;color:var(--green);">
                طلبات انضمام معلقة تنتظر الموافقة ({{ $pendingRequests->count() }})
            </h3>
        </div>
        <span class="tag" style="background:#FFF3D6;color:#785419;font-weight:700;">لوحة الإدارة</span>
    </div>

    @foreach($pendingRequests as $req)
    <div class="request-item-card">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--green);color:#FFFDF8;display:grid;place-items:center;font-weight:700;font-size:13px;">
                {{ mb_substr($req->user->name ?? 'ق', 0, 1) }}
            </div>
            <div>
                <strong style="color:var(--ink);font-size:14px;display:block;">{{ $req->user->name ?? 'قارئ جديد' }}</strong>
                <small class="muted" style="font-size:12px;">{{ $req->notes ?: 'يرغب بالانضمام للمجلس ومناقشة قراءات كتاب الشهر.' }}</small>
                <div class="muted" style="font-size:11px;margin-top:2px;">تاريخ الطلب: {{ $req->created_at->diffForHumans() }}</div>
            </div>
        </div>

        <div style="display:flex;gap:8px;">
            <form method="POST" action="{{ route('communities.approveJoinRequest', $req->id) }}" style="margin:0;">
                @csrf
                <button type="submit" class="primary btn-sm" style="background:var(--green);color:#FFFDF8;">
                    <i data-lucide="check"></i>
                    <span>قبول العضوية</span>
                </button>
            </form>
            <form method="POST" action="{{ route('communities.rejectJoinRequest', $req->id) }}" style="margin:0;">
                @csrf
                <button type="submit" class="secondary btn-sm" style="color:#A13030;border-color:#E2BDBD;">
                    <i data-lucide="x"></i>
                    <span>رفض</span>
                </button>
            </form>
        </div>
    </div>
    @endforeach
</section>
@endif

<!-- Moderation Queue for Reported Content -->
@if($canManage && isset($pendingReports) && $pendingReports->count() > 0)
<section class="moderation-queue-panel animate-fade-in delay-1">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="shield-alert" style="color:#C0392B;width:22px;height:22px;"></i>
            <h3 style="margin:0;font-size:17px;color:#900C3F;">
                لوحة الإشراف ومراجعة البلاغات ({{ $pendingReports->count() }})
            </h3>
        </div>
        <span class="tag" style="background:#FADBD8;color:#900C3F;font-weight:700;">إشراف أمني ومعرفي</span>
    </div>

    @foreach($pendingReports as $rep)
    <div class="report-item-card">
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <span class="tag" style="background:#F2D7D5;color:#78281F;font-size:11px;font-weight:700;">
                    {{ $rep->reasonLabel() }}
                </span>
                <small class="muted">أبلغ عنه: {{ $rep->user->name ?? 'قارئ' }} · {{ $rep->created_at->diffForHumans() }}</small>
            </div>
            @if($rep->details)
                <p style="font-size:12.5px;color:#555;margin:4px 0 6px;"><b>توضيح القارئ:</b> {{ $rep->details }}</p>
            @endif
            @php $targetContent = $rep->target(); @endphp
            @if($targetContent)
                <div style="background:#FFF;padding:8px 12px;border-radius:6px;border:1px solid #E5DFCF;font-size:12.5px;color:#2C3E50;">
                    <b>المحتوى المبلغ عنه:</b> «{{ \Illuminate\Support\Str::limit($targetContent->content, 140) }}»
                </div>
            @endif
        </div>

        <div style="display:flex;gap:8px;">
            <form method="POST" action="{{ route('moderation.resolveReport', $rep->id) }}" style="margin:0;">
                @csrf
                <input type="hidden" name="action" value="dismiss">
                <button type="submit" class="secondary btn-sm" style="color:#666;">
                    <i data-lucide="check"></i>
                    <span>تجاهل البلاغ</span>
                </button>
            </form>
            <form method="POST" action="{{ route('moderation.resolveReport', $rep->id) }}" style="margin:0;">
                @csrf
                <input type="hidden" name="action" value="delete_content">
                <button type="submit" class="primary btn-sm" style="background:#C0392B;color:#FFFDF8;">
                    <i data-lucide="trash-2"></i>
                    <span>حذف المحتوى المخالف</span>
                </button>
            </form>
        </div>
    </div>
    @endforeach
</section>
@endif
</div>

<div class="cm-panel" data-tab-panel="reading">
<!-- معجم أفكار المجلس (Community Highlights) -->
<section class="panel highlights-sanctuary-panel animate-fade-in delay-1" style="background:#FAF8F3;border:1px solid #E2D9C5;margin-bottom:20px;border-radius:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="background:var(--green);color:var(--gold);width:36px;height:36px;border-radius:10px;display:grid;place-items:center;box-shadow:0 2px 8px rgba(23,61,54,0.15);">
                <i data-lucide="sparkles" style="width:18px;height:18px;"></i>
            </div>
            <div>
                <h2 style="margin:0;font-size:18px;color:var(--green);">معجم أفكار {{ $community->name }} (Community Highlights)</h2>
                <small class="muted">أفضل الاقتباسات والتأملات التي أجمع عليها أعضاء «{{ $community->name }}» أثناء مناقشة كتاب الشهر</small>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            @auth
                <button type="button" class="primary btn-sm" onclick="openModal('add-highlight-modal')" style="background:var(--gold);color:#173D36;font-weight:700;display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:10px;box-shadow:0 2px 8px rgba(195,161,93,0.25);border:none;cursor:pointer;">
                    <i data-lucide="plus-circle" style="width:15px;height:15px;"></i>
                    <span>+ إضافة اقتباس من القارئ</span>
                </button>
            @else
                <a href="{{ route('login') }}" class="primary btn-sm" style="background:var(--gold);color:#173D36;font-weight:700;display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:10px;text-decoration:none;">
                    <i data-lucide="plus-circle" style="width:15px;height:15px;"></i>
                    <span>+ إضافة اقتباس من القارئ</span>
                </a>
            @endauth
            <span class="tag" style="background:#EBF3ED;color:#1B4D3E;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                <i data-lucide="check-check" style="width:13px;height:13px;color:var(--green);"></i>
                <span>خاص بمجلس: {{ $community->name }} ✦</span>
            </span>
        </div>
    </div>

    @if(isset($highlights) && $highlights->count() > 0)
    <div class="highlights-grid">
        @foreach($highlights as $hl)
        @php
            $isLiked = Auth::check() && $hl->isLikedBy(Auth::user());
            $isSpoiler = (bool)($hl->is_spoiler ?? false);
        @endphp
        <div class="highlight-card {{ $isSpoiler ? 'has-spoiler-card' : '' }}" style="{{ $isSpoiler ? 'border-color:rgba(195,161,93,0.55);background:#FFFDF7;' : '' }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div class="quote-symbol">«</div>
                @if($isSpoiler)
                    <span class="spoiler-chip" style="background:#FFF1D6;color:#8C6D2C;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;border:1px dashed #C3A15D;">
                        <i data-lucide="alert-triangle" style="width:12px;height:12px;"></i>
                        <span>حرق أحداث</span>
                    </span>
                @endif
            </div>

            @if($isSpoiler)
                <div class="highlight-spoiler-box" style="margin:2px 0 14px;flex:1;display:flex;flex-direction:column;">
                    <div class="spoiler-reveal-toggle" onclick="toggleHighlightSpoiler(this)" style="background:#FAF4E6;border:1px dashed #C3A15D;border-radius:8px;padding:8px 12px;font-size:11.5px;color:#8C6D2C;display:flex;align-items:center;justify-content:space-between;cursor:pointer;margin-bottom:8px;user-select:none;transition:background 0.2s;">
                        <span style="display:inline-flex;align-items:center;gap:6px;font-weight:600;">
                            <i data-lucide="eye" style="width:13px;height:13px;"></i>
                            <span class="spoiler-action-text">انقر لكشف الاقتباس (حرق أحداث)</span>
                        </span>
                        <i data-lucide="chevron-down" style="width:13px;height:13px;"></i>
                    </div>
                    <p class="highlight-text spoiler-blur" onclick="toggleHighlightSpoiler(this)" title="انقر لكشف الحرق" style="margin:0;flex:1;">
                        {{ $hl->content }}
                    </p>
                </div>
            @else
                <p class="highlight-text">{{ $hl->content }}</p>
            @endif

            <div class="highlight-footer">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="highlight-user-avatar">{{ mb_substr($hl->user->name ?? 'قارئ', 0, 1) }}</span>
                    <div>
                        <strong style="font-size:12px;color:var(--ink);display:block;">{{ $hl->user->name ?? 'قارئ المجلس' }}</strong>
                        <small class="muted" style="font-size:10.5px;">
                            {{ $hl->book->title ?? ($community->currentBook->title ?? 'كتاب المجلس') }}
                            @if($hl->page_number) · ص {{ $hl->page_number }} @endif
                        </small>
                    </div>
                </div>
                <button type="button" 
                        class="highlight-reaction-btn {{ $isLiked ? 'is-liked' : '' }}" 
                        data-reflection-id="{{ $hl->id }}"
                        onclick="reactHighlight(this, {{ $hl->id }})"
                        title="{{ $isLiked ? 'إلغاء الأثر' : 'ترك أثر (إعجاب)' }}">
                    <i data-lucide="sparkles"></i>
                    <span>أثر</span>
                    <b class="react-cnt">{{ $hl->likes_count ?? 0 }}</b>
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div style="text-align:center;padding:36px 20px;background:#FFFDF8;border:1px dashed #DFD6C2;border-radius:12px;">
        <div style="width:44px;height:44px;border-radius:50%;background:rgba(195,161,93,0.12);display:grid;place-items:center;margin:0 auto 12px;color:var(--gold);">
            <i data-lucide="sparkles" style="width:22px;height:22px;"></i>
        </div>
        <h4 style="margin:0 0 6px;color:var(--ink);font-size:15px;font-family:'Noto Naskh Arabic',serif;">معجم هذا المجلس بانتظار إشراقاتكم الأولى</h4>
        <p class="muted" style="font-size:13px;max-width:440px;margin:0 auto 14px;">لم يضف رفقاء «{{ $community->name }}» اقتباسات أو خواطر بعد. شارك تأملك واقتباسك الأول ليكون أثراً ملهماً في هذا المجلس!</p>
        @auth
            <button type="button" class="primary btn-sm" onclick="openModal('add-highlight-modal')" style="background:var(--gold);color:#173D36;font-weight:700;font-size:12.5px;padding:7px 18px;border-radius:8px;">
                <i data-lucide="plus-circle"></i>
                <span>+ إضافة اقتباس من القارئ</span>
            </button>
        @else
            <a href="{{ route('login') }}" class="primary btn-sm" style="background:var(--gold);color:#173D36;font-weight:700;font-size:12.5px;padding:7px 18px;border-radius:8px;text-decoration:none;">
                <i data-lucide="plus-circle"></i>
                <span>+ إضافة اقتباس من القارئ</span>
            </a>
        @endauth
    </div>
    @endif
</section>

<!-- غرف القراءة الصامتة الحية (Virtual Silent Reading Room / Pomodoro) -->
<section class="panel silent-room-panel animate-fade-in delay-1" style="margin-bottom:20px;border-radius:16px;">
    <div class="silent-room-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="live-beacon-pulse">
                <span class="pulse-ring"></span>
                <span class="pulse-dot"></span>
            </div>
            <div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <h2 style="margin:0;font-size:19px;">غرفة القراءة الصامتة (مؤقّت التركيز)</h2>
                    
                </div>
                <small class="muted">التزام وقراءة مشتركة في صمت (Body Doubling) · 45 دقيقة قراءة عميقة + 15 دقيقة تأمل ونقاش</small>
            </div>
        </div>

    </div>

    <div class="silent-room-body">
        <!-- Pomodoro Timer Box -->
        <div class="silent-timer-box">
            <div class="silent-phase-switcher">
                <button type="button" class="phase-btn active" id="phase-read-btn" onclick="setSilentPhase('reading')">
                    <i data-lucide="book-open" style="width:14px;height:14px;"></i>
                    <span>قراءة صامتة (45 د)</span>
                </button>
                <button type="button" class="phase-btn" id="phase-reflect-btn" onclick="setSilentPhase('reflection')">
                    <i data-lucide="coffee" style="width:14px;height:14px;"></i>
                    <span>تأمل ونقاش (15 د)</span>
                </button>
            </div>

            <div class="silent-digits" id="silent-timer-display">45:00</div>

            <div style="display:flex;gap:10px;justify-content:center;margin-top:14px;">
                <button type="button" class="primary" id="silent-play-btn" onclick="toggleSilentTimer()" style="min-height:38px;padding:0 22px;box-shadow:0 4px 12px rgba(23,61,54,0.15);">
                    <i data-lucide="play" id="silent-play-icon"></i>
                    <span id="silent-play-text">بدء القراءة الصامتة</span>
                </button>
                <button type="button" class="secondary" onclick="resetSilentTimer()" style="min-height:38px;padding:0 12px;" title="إعادة ضبط">
                    <i data-lucide="rotate-ccw"></i>
                </button>
                <button type="button" class="secondary" id="silent-sound-btn" onclick="toggleAmbientSound()" style="min-height:38px;padding:0 14px;" title="صوت هادئ">
                    <div class="audio-equalizer-bars" id="audio-eq-bars">
                        <span class="equalizer-bar"></span>
                        <span class="equalizer-bar"></span>
                        <span class="equalizer-bar"></span>
                    </div>
                    <span id="sound-label">صوت أثيري</span>
                </button>
            </div>
        </div>

        <!-- Silent Notes / Discussion Box -->
        <div class="silent-chat-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12.5px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:6px;">
                    <i data-lucide="feather" style="width:13px;height:13px;color:var(--gold);"></i>
                    <span>خاطرة الجلسة الصامتة الحالية:</span>
                </span>
                <small class="muted" style="font-size:11px;">تُشارك أثناء فترة الاستراحة</small>
            </div>
            <textarea id="silent-note-input" placeholder="اكتب ما تقرؤه الآن أو خاطرة تخطر ببالك لتشاركها مع رفقاء الغرفة..." rows="3" style="font-size:13px;width:100%;border-radius:8px;"></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                <small class="muted" style="font-size:11px;">متبقي على النقاش: 45 دقيقة</small>
                <button type="button" class="secondary btn-sm" onclick="saveSilentNote()" style="font-weight:600;">
                    <i data-lucide="send"></i>
                    <span>تثبيت الخاطرة</span>
                </button>
            </div>
        </div>
    </div>
</section>
</div>

<div class="cm-panel on" data-tab-panel="overview">
<div class="grid2">
    <!-- Book of the Month -->
    <section class="panel animate-fade-in delay-1" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(35,87,56,0.1);color:var(--green);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="book-open" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">نقرأ معًا هذا الشهر</h2>
            </div>
            <span class="tag" style="background:rgba(195,161,93,0.16);color:#927233;">كتاب سبتمبر</span>
        </div>

        @if($community->currentBook)
        @php $cb = $community->currentBook; @endphp
        <div style="display:flex;gap:20px;align-items:center;margin-top:14px;flex-wrap:wrap;">
            @php
                $cbCoverUrl = $cb->cover_image ? (str_starts_with($cb->cover_image, 'http') || str_starts_with($cb->cover_image, 'data:') ? $cb->cover_image : asset($cb->cover_image)) : '';
            @endphp
            <!-- 3D Book Cover with Spine & Tilt -->
            <div class="month-book-card-3d" title="انقر لتصفح الكتاب 3D" 
                onclick="previewBook(
                    {{ $cb->id }},
                    '{{ addslashes($cb->title) }}',
                    '{{ addslashes($cb->author) }}',
                    '{{ $cb->category }}',
                    {{ $cb->total_pages }},
                    '{{ $cb->cover_color }}',
                    '{{ addslashes(str_replace(["\r", "\n"], ' ', $cb->description ?? '')) }}',
                    {{ $cb->copies()->count() }},
                    '',
                    false,
                    '{{ $cbCoverUrl }}'
                )"
            >
                <div class="month-book-cover {{ $cbCoverUrl ? 'has-real-cover' : '' }}" style="background:{{ $cb->cover_color }};{{ $cbCoverUrl ? 'padding:0;overflow:hidden;' : '' }}">
                    @if($cbCoverUrl)
                        <img src="{{ $cbCoverUrl }}" alt="{{ $cb->title }}" style="width:100%;height:100%;object-fit:cover;border-radius:2px 6px 6px 2px;display:block;">
                    @else
                        <strong style="font-size:16px;line-height:1.2;font-family:'Noto Naskh Arabic',serif;">{{ $cb->title }}</strong>
                        <small style="opacity:0.85;font-size:11px;">{{ $cb->author }}</small>
                    @endif
                </div>
            </div>

            <div style="flex:1;min-width:200px;">
                <h3 style="margin:0 0 2px;font-size:20px;font-family:'Noto Naskh Arabic',serif;color:var(--ink);">{{ $cb->title }}</h3>
                <p class="muted" style="margin-bottom:8px;font-size:13.5px;"><i data-lucide="user" style="width:12px;height:12px;display:inline-block;vertical-align:middle;margin-left:4px;"></i> {{ $cb->author }}</p>
                <small class="muted" style="display:block;line-height:1.6;font-size:12.5px;margin-bottom:12px;">
                    نقرأ لنكتشف كيف يحمل الأسلوب الفكرة، وكيف تصير الصورة معنى وجدانياً متجدداً.
                </small>

                <!-- Progress Tracker -->
                <div style="background:#F7F3E9;border:1px solid #E4DAC4;border-radius:10px;padding:8px 12px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:11.5px;color:#6A6250;margin-bottom:4px;">
                        <span>مسار قراءة أعضاء المجلس</span>
                        <b style="color:var(--green);">65٪ منجز</b>
                    </div>
                    <div class="progress liquid-progress" style="--pct:65%;height:6px;margin:0;">
                        <span style="width: 65%;">
                            <span class="liquid-wave-effect"></span>
                        </span>
                    </div>
                </div>

                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px;">
                    <a href="{{ route('explore', ['q' => $cb->title]) }}" class="link" style="font-weight:600;font-size:13px;">حول الكتاب واستكشافه &larr;</a>
                    <button type="button" class="secondary btn-sm" onclick="openModal('past-books-modal')" style="font-size:12px;padding:4px 10px;">
                        <i data-lucide="archive" style="width:12px;height:12px;"></i>
                        <span>أرشيف كتب الأشهر السابقة</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
    </section>

    <!-- Next Meeting -->
    <section class="panel animate-fade-in delay-1" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(195,161,93,0.15);color:#927233;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="calendar" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">اللقاء القادم</h2>
            </div>
            <span class="tag" style="background:rgba(35,87,56,0.12);color:var(--green);font-weight:700;">موعد مؤكد</span>
        </div>

        <h3 style="margin:6px 0 10px;font-size:18px;font-family:'Noto Naskh Arabic',serif;color:var(--ink);">
            {{ $nextMeeting ? $nextMeeting->title : 'من جمال العبارة إلى عمق الفكرة' }}
        </h3>

        <div style="display:flex;gap:14px;align-items:center;margin:14px 0;background:#FFFDF9;border:1px solid #EAE3D2;padding:12px 14px;border-radius:12px;">
            <div style="background:linear-gradient(180deg, #F5EFE1 0%, #E9E1CE 100%);color:var(--green);padding:8px 14px;border-radius:10px;text-align:center;min-width:54px;border:1px solid #DFD5C2;box-shadow:0 2px 4px rgba(0,0,0,0.03);">
                <b style="font-size:22px;font-weight:700;display:block;line-height:1.1;">{{ $nextMeeting ? $nextMeeting->meeting_date->format('d') : '17' }}</b>
                <small style="font-size:11px;font-weight:600;color:#726A58;">{{ $nextMeeting ? $nextMeeting->meeting_date->translatedFormat('M') : 'سبتمبر' }}</small>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:14px;color:var(--ink);">
                    {{ $nextMeeting ? $nextMeeting->meeting_date->translatedFormat('l · g:i') . ' مساءً' : 'الخميس · 7:00 مساءً' }}
                </div>
                <small class="muted" style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                    <i data-lucide="video" style="width:12px;height:12px;color:var(--gold);"></i>
                    <span>جلسة مرئية عن بُعد · بتوقيت مكة والخليج</span>
                </small>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:12px;color:#726A58;">
            <span>أكّد الحضور حتى الآن: <b style="color:var(--green);">24 قارئاً ✦</b></span>
            <span class="tag" style="background:#EBF3ED;color:#1B4D3E;font-size:11px;">مقاعد متوفرة</span>
        </div>

        <a href="{{ route('calendar.index') }}" class="secondary" style="width:100%;justify-content:center;min-height:38px;font-weight:600;">
            <i data-lucide="calendar-check"></i>
            <span>التفاصيل وتأكيد الحضور (RSVP)</span>
        </a>
    </section>
</div>
</div>

<div class="cm-panel" data-tab-panel="discussions">
<div class="grid2">
    <!-- Community Question & Isolated Book Discussions -->
    <section class="panel animate-fade-in delay-2" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(195,161,93,0.15);color:#927233;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="message-square" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h2 style="margin:0;font-size:18px;">مساحات النقاش والحوار المعرفي</h2>
                    <small class="muted" style="font-size:11px;">مساحات نقاش مستقلة لكل كتاب داخل المجلس (معزولة عن المراجعات العامة)</small>
                </div>
            </div>
            <span class="tag" style="background:rgba(195,161,93,0.18);color:#927233;">{{ $discussions->count() }} مشاركة</span>
        </div>

        <!-- Book Isolation Filter Bar -->
        <div style="background:#FAF8F3;border:1px solid #EAE0CD;border-radius:12px;padding:10px 12px;margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-size:11.5px;font-weight:700;color:var(--green);display:flex;align-items:center;gap:4px;">
                    <i data-lucide="book-marked" style="width:13px;height:13px;"></i>
                    <span>تصفية النقاشات بحسب الكتاب:</span>
                </span>
                @if($selectedBookId)
                    <a href="{{ route('communities.index', ['community_id' => $community->id]) }}" class="link" style="font-size:11px;color:#A855F7;text-decoration:none;">
                        عرض كل النقاشات &times;
                    </a>
                @endif
            </div>

            <div style="display:flex;gap:6px;overflow-x:auto;padding-bottom:4px;">
                <a href="{{ route('communities.index', ['community_id' => $community->id]) }}" class="book-filter-pill {{ !$selectedBookId ? 'active' : '' }}">
                    <i data-lucide="layers" style="width:12px;height:12px;"></i>
                    <span>جميع كتب المجلس</span>
                </a>

                @foreach($communityBooks as $cb)
                    @php
                        $isCur = $community->current_book_id == $cb->id;
                        $isSelected = $selectedBookId == $cb->id;
                    @endphp
                    <a href="{{ route('communities.index', ['community_id' => $community->id, 'book_id' => $cb->id]) }}" class="book-filter-pill {{ $isSelected ? 'active' : '' }}">
                        <i data-lucide="book-open" style="width:12px;height:12px;"></i>
                        <span>{{ Str::limit($cb->title, 22) }}</span>
                        @if($isCur)
                            <small style="background:rgba(195,161,93,0.3);padding:1px 5px;border-radius:10px;font-size:10px;font-weight:700;">الشهر الحالي</small>
                        @endif
                    </a>
                @endforeach
            </div>

            @if($selectedBook)
                <div style="background:rgba(35,87,56,0.06);border:1px solid rgba(35,87,56,0.18);border-radius:8px;padding:6px 10px;margin-top:8px;display:flex;align-items:center;justify-content:space-between;font-size:11.5px;color:var(--green);">
                    <span>📖 تصفح نقاشات كتاب: <b>{{ $selectedBook->title }}</b></span>
                    <span class="tag" style="background:#E8F3EA;color:var(--green);font-size:10px;">مساحة خاصة بالمجلس</span>
                </div>
            @endif
        </div>

        @if($community->featured_question)
        <div class="question-block" style="border-radius:8px;border-right:4px solid var(--gold);background:rgba(195,161,93,0.08);padding:12px 16px;margin:8px 0 12px;">
            <div style="font-size:11px;font-weight:700;color:var(--gold);margin-bottom:2px;">سؤال المجلس للتأمل:</div>
            <div style="font-weight:600;color:var(--ink);">« {{ $community->featured_question }} »</div>
        </div>
        @endif

        <button class="secondary" style="width:100%;margin-bottom:16px;min-height:40px;font-weight:600;" onclick="openModal('discussion-modal')" {{ !$isMember ? 'disabled' : '' }}>
            <i data-lucide="plus-circle"></i>
            <span>{{ $isMember ? 'أضف مشاركتك أو سؤالك في النقاش' : 'انضمّ أولًا للمشاركة في النقاش' }}</span>
        </button>

        <div class="discussions-list-wrap">
            @forelse($discussions as $disc)
                <div class="discussion-card-item" id="disc-item-{{ $disc->id }}" style="{{ $disc->is_pinned ? 'border:1.5px solid #FCD34D;background:#FFFDF7;' : '' }}">
                    <div class="disc-author-header">
                        <div class="disc-author-info">
                            <span class="disc-avatar">{{ mb_substr($disc->user->name ?? 'ق', 0, 1) }}</span>
                            <div>
                                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                    <b style="color:var(--green);font-size:13px;">{{ $disc->user->name ?? 'قارئ' }}</b>
                                    <span class="tag" style="font-size:10px;padding:1px 6px;background:rgba(35,87,56,0.08);color:var(--green);">عضو المجلس</span>

                                    @if($disc->is_pinned)
                                        <span class="tag" style="background:#FEF3C7;color:#92400E;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:3px;">
                                            <i data-lucide="pin" style="width:10px;height:10px;"></i>
                                            <span>مثبت</span>
                                        </span>
                                    @endif

                                    @if($disc->is_coordinator_question)
                                        <span class="tag" style="background:#E0E7FF;color:#3730A3;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:3px;">
                                            <i data-lucide="help-circle" style="width:10px;height:10px;"></i>
                                            <span>سؤال منسق القراءة</span>
                                        </span>
                                    @endif

                                    @if($disc->book)
                                        <span class="tag" style="background:#F3F4F6;color:#374151;font-size:10px;display:inline-flex;align-items:center;gap:3px;">
                                            <i data-lucide="book-open" style="width:10px;height:10px;"></i>
                                            <span>{{ Str::limit($disc->book->title, 20) }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:6px;">
                            <span class="muted" style="font-size:11px;">{{ $disc->created_at->diffForHumans() }}</span>

                            @if($canManage || (Auth::check() && $community->isReadingCoordinator(Auth::user())))
                                <form action="{{ route('discussions.pin', $disc->id) }}" method="POST" style="margin:0;display:inline;">
                                    @csrf
                                    <button type="submit" class="link" style="border:none;background:none;cursor:pointer;padding:2px 4px;color:{{ $disc->is_pinned ? '#B45309' : '#A39B8B' }};" title="{{ $disc->is_pinned ? 'إلغاء التثبيت' : 'تثبيت المنشور في أعلى المجلس' }}">
                                        <i data-lucide="pin" style="width:13px;height:13px;{{ $disc->is_pinned ? 'fill:#F59E0B;color:#B45309;' : '' }}"></i>
                                    </button>
                                </form>
                            @endif

                            <button type="button" class="report-flag-btn" onclick="openReportModal('discussion', {{ $disc->id }})" title="الإبلاغ عن محتوى غير لائق">
                                <i data-lucide="flag" style="width:12px;height:12px;"></i>
                            </button>
                        </div>
                    </div>

                    @if($disc->title && $disc->title !== Str::limit($disc->content, 60))
                        <h4 style="margin:6px 0 4px;font-size:13.5px;font-weight:700;color:var(--ink);">{{ $disc->title }}</h4>
                    @endif

                    <!-- Discussion Body with Spoiler & Mention Support -->
                    @php
                        $formattedBody = e($disc->content);
                        $formattedBody = preg_replace('/@([\w\p{Arabic}\.]+)/u', '<span class="mention-tag">@$1</span>', $formattedBody);
                        $formattedBody = preg_replace('/\[حرق\](.*?)\[\/حرق\]/u', '<span class="spoiler-blur" onclick="this.classList.toggle(\'revealed\')" title="انقر لكشف الحرق">$1</span>', $formattedBody);
                    @endphp

                    @if($disc->is_spoiler)
                        <div style="background:#FFF9ED;border:1px dashed #C3A15D;border-radius:8px;padding:10px 14px;margin:8px 0 6px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="this.nextElementSibling.classList.toggle('revealed')">
                                <span style="font-size:12px;font-weight:700;color:#925A12;display:flex;align-items:center;gap:6px;">
                                    <i data-lucide="alert-triangle" style="width:13px;height:13px;"></i>
                                    <span>تحذير حرق الأحداث (Spoiler) — انقر لإظهار النص</span>
                                </span>
                                <i data-lucide="chevron-down" style="width:14px;height:14px;color:#925A12;"></i>
                            </div>
                            <p class="spoiler-blur" style="margin:8px 0 0;line-height:1.75;color:#3A443A;font-size:13.5px;display:block;">
                                {!! nl2br($formattedBody) !!}
                            </p>
                        </div>
                    @else
                        <p style="margin:6px 0 8px;line-height:1.75;color:#3A443A;font-size:13.5px;">
                            {!! nl2br($formattedBody) !!}
                        </p>
                    @endif

                    <!-- Actions Row: Real Likes and Comments Toggle -->
                    <div class="disc-actions-row" style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;">
                        <div style="display:flex;gap:8px;align-items:center;">
                            @php $isLiked = Auth::check() && $disc->isLikedBy(Auth::user()); @endphp
                            <form action="{{ route('discussions.like', $disc->id) }}" method="POST" style="margin:0;display:inline;">
                                @csrf
                                <button type="submit" class="disc-react-btn {{ $isLiked ? 'active-liked' : '' }}" {{ !Auth::check() ? 'disabled' : '' }}>
                                    <i data-lucide="heart" style="width:12px;height:12px;{{ $isLiked ? 'fill:#E11D48;color:#E11D48;' : 'color:#927233;' }}"></i>
                                    <span>إعجاب</span>
                                    <b class="disc-cnt">{{ $disc->likes_count }}</b>
                                </button>
                            </form>

                            <button type="button" class="disc-react-btn" onclick="toggleDiscussionComments({{ $disc->id }})">
                                <i data-lucide="message-circle" style="width:12px;height:12px;"></i>
                                <span>الردود ({{ $disc->comments->count() }})</span>
                            </button>
                        </div>

                        <button type="button" class="secondary btn-sm" onclick="toggleDiscussionComments({{ $disc->id }}); document.getElementById('comment-input-{{ $disc->id }}')?.focus();" style="font-size:11.5px;padding:3px 10px;">
                            <i data-lucide="reply" style="width:11px;height:11px;"></i>
                            <span>أضف رداً</span>
                        </button>
                    </div>

                    <!-- Nested Comments & Replies Box -->
                    <div id="comments-box-{{ $disc->id }}" class="comments-container" style="display:none;">
                        <!-- List of Comments -->
                        @if($disc->comments && $disc->comments->count() > 0)
                            <div style="display:grid;gap:8px;margin-bottom:12px;">
                                @foreach($disc->comments as $comment)
                                    <div class="comment-bubble">
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <strong style="color:var(--green);font-size:12.5px;">{{ $comment->user->name ?? 'قارئ' }}</strong>
                                                <small class="muted" style="font-size:11px;">· {{ $comment->created_at->diffForHumans() }}</small>
                                            </div>
                                            <button type="button" class="report-flag-btn" onclick="openReportModal('comment', {{ $comment->id }})" title="إبلاغ">
                                                <i data-lucide="flag" style="width:11px;height:11px;"></i>
                                            </button>
                                        </div>

                                        @php
                                            $formattedComment = e($comment->content);
                                            $formattedComment = preg_replace('/@([\w\p{Arabic}\.]+)/u', '<span class="mention-tag">@$1</span>', $formattedComment);
                                        @endphp

                                        <p style="margin:0;font-size:13px;line-height:1.6;color:#354435;{{ $comment->is_spoiler ? 'filter:blur(4px);cursor:pointer;' : '' }}" onclick="{{ $comment->is_spoiler ? "this.style.filter='none'" : '' }}">
                                            @if($comment->is_spoiler) <small style="color:#C0392B;font-weight:700;">[حرق أحداث - انقر للإظهار] </small> @endif
                                            {!! nl2br($formattedComment) !!}
                                        </p>

                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                                            @php $isCommentLiked = Auth::check() && $comment->isLikedBy(Auth::user()); @endphp
                                            <form action="{{ route('comments.like', $comment->id) }}" method="POST" style="margin:0;display:inline;">
                                                @csrf
                                                <button type="submit" class="link" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:{{ $isCommentLiked ? '#E11D48' : '#786E58' }};text-decoration:none;border:none;background:none;cursor:pointer;" {{ !Auth::check() ? 'disabled' : '' }}>
                                                    <i data-lucide="heart" style="width:11px;height:11px;{{ $isCommentLiked ? 'fill:#E11D48;color:#E11D48;' : '' }}"></i>
                                                    <span>{{ $comment->likes_count }}</span>
                                                </button>
                                            </form>

                                            <button type="button" class="link" onclick="setReplyParent({{ $disc->id }}, {{ $comment->id }}, '{{ addslashes($comment->user->name ?? 'قارئ') }}')" style="font-size:11px;color:var(--gold);text-decoration:none;">
                                                <i data-lucide="corner-down-left" style="width:10px;height:10px;"></i>
                                                <span>رد على {{ $comment->user->name ?? 'التعليق' }}</span>
                                            </button>
                                        </div>

                                        <!-- Nested Replies -->
                                        @if($comment->replies && $comment->replies->count() > 0)
                                            @foreach($comment->replies as $reply)
                                                @php
                                                    $formattedReply = e($reply->content);
                                                    $formattedReply = preg_replace('/@([\w\p{Arabic}\.]+)/u', '<span class="mention-tag">@$1</span>', $formattedReply);
                                                @endphp
                                                <div class="nested-reply-box">
                                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                                                        <strong style="color:var(--green);font-size:12px;">{{ $reply->user->name ?? 'قارئ' }}</strong>
                                                        <small class="muted" style="font-size:10.5px;">{{ $reply->created_at->diffForHumans() }}</small>
                                                    </div>
                                                    <p style="margin:0;font-size:12.5px;line-height:1.6;color:#3A443A;{{ $reply->is_spoiler ? 'filter:blur(4px);cursor:pointer;' : '' }}" onclick="{{ $reply->is_spoiler ? "this.style.filter='none'" : '' }}">
                                                        @if($reply->is_spoiler) <small style="color:#C0392B;font-weight:700;">[حرق أحداث] </small> @endif
                                                        {!! nl2br($formattedReply) !!}
                                                    </p>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Add Comment / Reply Form -->
                        @auth
                        <form action="{{ route('discussions.storeComment', $disc->id) }}" method="POST" style="margin:0;">
                            @csrf
                            <input type="hidden" name="parent_id" id="reply-parent-{{ $disc->id }}" value="">
                            
                            <div id="replying-to-banner-{{ $disc->id }}" style="display:none;background:#FAF0D6;color:#785419;padding:4px 10px;border-radius:6px;font-size:11.5px;margin-bottom:6px;justify-content:space-between;align-items:center;">
                                <span id="replying-to-text-{{ $disc->id }}">الرد على: </span>
                                <button type="button" onclick="cancelReplyParent({{ $disc->id }})" style="background:none;border:none;cursor:pointer;color:#785419;font-weight:700;">&times;</button>
                            </div>

                            <div style="display:flex;gap:6px;align-items:flex-start;">
                                <input name="content" id="comment-input-{{ $disc->id }}" placeholder="اكتب تعليقك... (استخدم @ للإشارة إلى الأعضاء)" required style="flex:1;font-size:12.5px;padding:8px 12px;border-radius:8px;">
                                <button type="submit" class="primary btn-sm" style="padding:8px 14px;white-space:nowrap;">
                                    <i data-lucide="send" style="width:12px;height:12px;"></i>
                                    <span>إرسال</span>
                                </button>
                            </div>

                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:#6A604F;margin-top:6px;cursor:pointer;">
                                <input type="checkbox" name="is_spoiler" value="1" style="width:14px;height:14px;accent-color:var(--green);">
                                <span>يتضمن ردي حرقاً لأحداث الكتاب (Spoiler)</span>
                            </label>
                        </form>
                        @else
                        <small class="muted" style="font-size:11.5px;display:block;text-align:center;padding:6px;">
                            <a href="{{ route('login') }}" class="link">سجل دخولك</a> للمشاركة والرد على أعضاء المجلس.
                        </small>
                        @endauth
                    </div>
                </div>
            @empty
                <p class="muted" style="font-size:13px;text-align:center;padding:24px 15px;background:#FAF8F3;border:1px dashed #E0D6C2;border-radius:10px;">
                    لا توجد مشاركات حتى الآن في هذه المساحة. كن أول من يشارك تأمله في المجلس!
                </p>
            @endforelse
        </div>
    </section>

    <!-- Next Book Voting Poll with Duration, Result Visibility & Workflow -->
    <section class="panel animate-fade-in delay-2" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(35,87,56,0.1);color:var(--green);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="vote" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">أيّ كتاب نقرأ بعد ذلك؟</h2>
            </div>
            @if($activePoll)
                @if($activePoll->isClosed())
                    <span class="tag" style="background:#FEE2E2;color:#991B1B;font-weight:700;">مغلق</span>
                @else
                    <span class="tag" style="background:rgba(195,161,93,0.18);color:#927233;">استطلاع مفتوح</span>
                @endif
            @else
                <span class="tag" style="background:rgba(195,161,93,0.18);color:#927233;">استطلاع</span>
            @endif
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <span class="eyebrow" style="color:var(--gold);">كتاب الشهر القادم</span>
            @if($activePoll && $activePoll->closes_at)
                <small class="muted" style="font-size:11.5px;">
                    <i data-lucide="clock" style="width:12px;height:12px;display:inline-block;vertical-align:middle;"></i>
                    ينتهي في: {{ $activePoll->closes_at->translatedFormat('j F Y') }}
                </small>
            @endif
        </div>

        @if($activePoll)
            @php
                $totalPollVotes = max(1, $activePoll->options->sum('votes_count'));
                $canSeeResults = $activePoll->canUserSeeResults(Auth::user());
                $isPollClosed = $activePoll->isClosed();
            @endphp

            @if(!$canSeeResults)
                <div style="background:#FFF9ED;border:1px dashed #C3A15D;border-radius:8px;padding:10px 14px;margin-top:10px;font-size:12px;color:#925A12;display:flex;align-items:center;gap:8px;">
                    <i data-lucide="eye-off" style="width:16px;height:16px;flex-shrink:0;"></i>
                    <span>
                        @if($activePoll->show_results === \App\Models\BookPoll::SHOW_RESULTS_AFTER_VOTE)
                            🔒 نتائج التصويت مخفية؛ ستظهر النسب والأصوات مباشرة بعد قيامك باختيار صوتك.
                        @else
                            🔒 نتائج التصويت مخفية؛ ستُعلن الأصوات والنسب رسمياً بعد إغلاق فترة التصويت من إدارة المجلس.
                        @endif
                    </span>
                </div>
            @endif

            <div style="display:grid;gap:12px;margin-top:14px;">
                @foreach($activePoll->options as $option)
                    @php
                        $isVoted = $userVotedOptionId === $option->id;
                        $pct = round(($option->votes_count / $totalPollVotes) * 100);
                    @endphp

                    @if(!$isPollClosed)
                    <form method="POST" action="{{ route('polls.vote', $activePoll->id) }}">
                        @csrf
                        <input type="hidden" name="option_id" value="{{ $option->id }}">
                        <button type="submit" class="secondary" style="width:100%;text-align:right;flex-direction:column;align-items:stretch;gap:6px;padding:14px;border-radius:12px;transition:all 0.25s ease;{{ $isVoted ? 'background:#E8F3EA;border-color:var(--green);box-shadow:0 2px 10px rgba(35,87,56,0.08);' : 'background:#FFFDF9;' }}">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-weight:700;font-size:14px;color:var(--ink);">
                                    @if($isVoted) <span style="color:var(--green);margin-left:4px;">✓ صوتك الحالي:</span> @endif
                                    {{ $option->custom_title }}
                                </span>
                                @if($canSeeResults)
                                    <span class="tag" style="background:rgba(0,0,0,0.06);font-size:11.5px;font-weight:700;">{{ $option->votes_count }} صوت ({{ $pct }}%)</span>
                                @else
                                    <span class="tag" style="background:rgba(0,0,0,0.06);font-size:11px;">{{ $isVoted ? 'مُختار' : 'تصويت' }}</span>
                                @endif
                            </div>
                            @if($canSeeResults)
                            <div class="poll-bar-container">
                                <div class="poll-option-elastic-bar" style="width: {{ $pct }}%;"></div>
                            </div>
                            @endif
                        </button>
                    </form>
                    @else
                    <div style="padding:14px;border-radius:12px;border:1px solid #E2D9C5;{{ $isVoted ? 'background:#E8F3EA;border-color:var(--green);' : 'background:#FFFDF9;' }}">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-weight:700;font-size:14px;color:var(--ink);">
                                @if($isVoted) <span style="color:var(--green);margin-left:4px;">✓ اختيارك:</span> @endif
                                {{ $option->custom_title }}
                            </span>
                            @if($canSeeResults)
                                <span class="tag" style="background:rgba(0,0,0,0.06);font-size:11.5px;font-weight:700;">{{ $option->votes_count }} صوت ({{ $pct }}%)</span>
                            @endif
                        </div>
                        @if($canSeeResults)
                        <div class="poll-bar-container" style="margin-top:6px;">
                            <div class="poll-option-elastic-bar" style="width: {{ $pct }}%;"></div>
                        </div>
                        @endif
                    </div>
                    @endif
                @endforeach
            </div>

            <small class="muted" style="display:block;margin-top:14px;text-align:center;">
                @if($isPollClosed)
                    انتهت فترة التصويت لهذا الاستطلاع.
                @else
                    {{ $isMember ? 'قاعدة الصوت الواحد: يُمنع التصويت المتكرر؛ يمكنك تعديل اختيارك بأي وقت.' : 'انضمّ للمجلس للمشاركة في التصويت.' }}
                @endif
            </small>

            <!-- One-Click Close & Integrated Workflow for Coordinators / Managers -->
            @if($canManage || (Auth::check() && $community->isReadingCoordinator(Auth::user())))
                <div style="margin-top:16px;padding:14px;background:#FAF8F3;border:1px dashed #C3A15D;border-radius:12px;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <i data-lucide="workflow" style="width:16px;height:16px;color:var(--green);"></i>
                        <strong style="font-size:13px;color:var(--green);">سير عمل إغلاق الاستطلاع واعتماد كتاب الشهر بنقرة واحدة</strong>
                    </div>
                    <small class="muted" style="font-size:11.5px;display:block;margin-bottom:10px;">
                        يقوم هذا الإجراء بحساب الفائز بأعلى الأصوات، وتعيينه ككتاب الشهر وتحديث أرشيف المجلس، مع إمكانية إنشاء تحدي قراءة واجتماع نقاش مرتبطين تلقائياً.
                    </small>

                    <form method="POST" action="{{ route('polls.closeAndSetWinner', $activePoll->id) }}" onsubmit="return confirm('هل أنت متأكد من رغبتك في إغلاق التصويت واعتماد الكتاب الفائز وتشغيل سير العمل التلقائي؟');" style="margin:0;">
                        @csrf
                        <div style="display:grid;gap:6px;margin-bottom:12px;background:#FFFDF9;padding:10px;border-radius:8px;border:1px solid #EAE3D2;">
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--ink);cursor:pointer;">
                                <input type="checkbox" name="create_challenge" value="1" checked>
                                <span>🏆 إنشاء تحدي قراءة مرتبط تلقائياً للكتاب الفائز</span>
                            </label>
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--ink);cursor:pointer;">
                                <input type="checkbox" name="create_meeting" value="1" checked>
                                <span>📅 جدولة اجتماع نقاش مرتبط تلقائياً للكتاب الفائز</span>
                            </label>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                                <span style="font-size:11.5px;color:#64748B;">نوع الاجتماع المقترح:</span>
                                <select name="meeting_type" style="font-size:11.5px;padding:3px 8px;border-radius:6px;border:1px solid #CBD5E1;">
                                    <option value="remote">عن بعد (Remote)</option>
                                    <option value="in_person">حضوري (In-person)</option>
                                    <option value="hybrid">هجين (Hybrid)</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="primary btn-sm" style="width:100%;font-size:12.5px;padding:8px 14px;background:var(--gold);border-color:var(--gold);color:#1F2937;font-weight:700;display:flex;justify-content:center;align-items:center;gap:6px;">
                            <i data-lucide="trophy" style="width:14px;height:14px;"></i>
                            <span>اعتماد الفائز وتشغيل سير العمل التلقائي 🚀</span>
                        </button>
                    </form>
                </div>
            @endif
        @else
            <p class="muted" style="font-size:13px;text-align:center;padding:24px 15px;background:#FAF8F3;border:1px dashed #E0D6C2;border-radius:10px;margin-top:14px;">
                لا يوجد استطلاع تصويت نشط حالياً في هذا المجلس.
            </p>
        @endif
    </section>
</div>
</div>

<div class="cm-panel" data-tab-panel="members">
<!-- Book Nominations & Community Badges Section -->
<div class="grid2" style="margin-top:20px;">
    <!-- 1. Book Nominations for Next Month (ترشيحات كتب الشهر القادم) -->
    <section class="panel animate-fade-in delay-2" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(195,161,93,0.15);color:var(--gold);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="lightbulb" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">ترشيحات القراء لكتاب الشهر</h2>
            </div>
            <button type="button" class="primary btn-sm" onclick="openModal('nominate-modal')" style="font-size:12px;padding:6px 12px;" {{ !$isMember ? 'disabled' : '' }}>
                <i data-lucide="plus"></i>
                <span>اقترح كتاباً (+10 أثر)</span>
            </button>
        </div>
        <p class="muted" style="font-size:12.5px;margin-bottom:14px;">شارك بترشيح عنوان للشهر القادم وادعم ترشيحات رفقاء القراءة بالتأييد.</p>

        <div style="display:grid;gap:10px;">
            @forelse($nominations as $nom)
                @php $isEndorsed = $nom->isEndorsedBy(Auth::user()); @endphp
                <div style="background:#FFFDF9;border:1px solid #EAE3D2;border-radius:12px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <strong style="font-size:14px;color:var(--ink);">{{ $nom->title }}</strong>
                            <small class="muted">· {{ $nom->author }}</small>
                            @if($nom->status === 'added_to_poll')
                                <span class="tag" style="background:#E8F3EA;color:var(--green);font-size:10px;font-weight:700;">مدرج في التصويت 🗳️</span>
                            @endif
                        </div>
                        <p style="margin:0;font-size:12px;color:#5A5445;line-height:1.5;">{{ $nom->reason }}</p>
                        <small class="muted" style="font-size:11px;display:block;margin-top:4px;">
                            اقترحه: {{ $nom->user->name ?? 'قارئ' }}
                        </small>
                    </div>

                    <div style="display:flex;gap:8px;align-items:center;">
                        <form method="POST" action="{{ route('nominations.endorse', $nom->id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="secondary btn-sm" style="font-size:11.5px;padding:5px 12px;{{ $isEndorsed ? 'background:#E8F3EA;border-color:var(--green);color:var(--green);font-weight:700;' : '' }}">
                                <i data-lucide="thumbs-up" style="width:12px;height:12px;"></i>
                                <span>تأييد ({{ $nom->endorsements_count }})</span>
                            </button>
                        </form>

                        @if($canManage && $nom->status !== 'added_to_poll')
                            <form method="POST" action="{{ route('nominations.addToPoll', $nom->id) }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="primary btn-sm" style="font-size:11px;padding:5px 10px;background:#235738;">
                                    <i data-lucide="plus-circle" style="width:11px;height:11px;"></i>
                                    <span>إدراج بالتصويت</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted" style="font-size:12.5px;text-align:center;padding:16px;background:#FAF8F3;border:1px dashed #DFD5C2;border-radius:10px;">
                    لا توجد ترشيحات حتى الآن. كن أول من يرشح كتاباً للقراءة القادمة!
                </p>
            @endforelse
        </div>
    </section>

    <!-- 2. Community Badges & Honors (أوسمة المجلس ولوحة الشرف) -->
    <section class="panel animate-fade-in delay-2" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(35,87,56,0.1);color:var(--green);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="award" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">أوسمة المجلس ولوحة الشرف</h2>
            </div>
            @if($canManage)
                <div style="display:flex;gap:6px;">
                    <button type="button" class="secondary btn-sm" onclick="openModal('create-badge-modal')" style="font-size:11.5px;padding:4px 10px;">
                        <i data-lucide="plus"></i>
                        <span>استحداث وسام</span>
                    </button>
                    <button type="button" class="primary btn-sm" onclick="openModal('award-badge-modal')" style="font-size:11.5px;padding:4px 10px;">
                        <i data-lucide="crown"></i>
                        <span>تقليد عضو</span>
                    </button>
                </div>
            @endif
        </div>
        <p class="muted" style="font-size:12.5px;margin-bottom:14px;">أوسمة شرفية يُتوج بها أعضاء المجلس تقديراً لإسهاماتهم الفكرية والنقدية.</p>

        <div style="display:grid;gap:10px;">
            @forelse($communityBadges as $cBadge)
                <div style="background:#FFFDF9;border:1px solid #EAE3D2;border-radius:12px;padding:12px 14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:18px;">
                                @if($cBadge->icon === 'crown') 👑
                                @elseif($cBadge->icon === 'coffee') ☕
                                @elseif($cBadge->icon === 'sparkles') ✨
                                @else 🏅
                                @endif
                            </span>
                            <strong style="font-size:14px;color:var(--ink);">{{ $cBadge->name }}</strong>
                        </div>
                        <span class="tag" style="background:rgba(195,161,93,0.15);color:#785419;font-size:11px;font-weight:700;">
                            {{ $cBadge->users->count() }} مُتوَّج
                        </span>
                    </div>
                    <p style="margin:0 0 6px;font-size:12px;color:#5A5445;line-height:1.5;">{{ $cBadge->description }}</p>

                    @if($cBadge->users && $cBadge->users->count() > 0)
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:6px;border-top:1px dashed #EDE5D5;padding-top:6px;">
                            <small class="muted" style="font-size:11px;">المتوَّجون بهذا الوسام:</small>
                            @foreach($cBadge->users as $u)
                                <span class="tag" style="background:#EBF3ED;color:var(--green);font-size:11px;padding:1px 8px;" title="{{ $u->pivot->reason }}">
                                    👤 {{ $u->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="muted" style="font-size:12.5px;text-align:center;padding:16px;background:#FAF8F3;border:1px dashed #DFD5C2;border-radius:10px;">
                    لم يتم استحداث أوسمة شرفية خاصة بهذا المجلس بعد.
                </p>
            @endforelse
        </div>
<!-- Council Ecosystem: Active Challenge, New Members & Leaderboard -->
<div class="grid2" style="margin-top:20px;">
    <!-- Component 1: Active Challenge Card (التحدي النشط) -->
    <section class="panel animate-fade-in delay-2" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(195,161,93,0.18);color:var(--gold);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="target" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">التحدي القرائي النشط للمجلس</h2>
            </div>
            @if($activeChallenge)
                <span class="tag" style="background:#EBF3ED;color:var(--green);font-weight:700;">تحدٍ جارٍ 🌿</span>
            @endif
        </div>

        @if($activeChallenge)
            @php
                $participantsCount = $activeChallenge->participants()->count();
                $isParticipating = Auth::check() && $activeChallenge->participants()->where('user_id', Auth::id())->exists();
                $userParticipant = $isParticipating ? $activeChallenge->participants()->where('user_id', Auth::id())->first() : null;
                $userProgress = $userParticipant ? ($userParticipant->pivot->progress_count ?? 0) : 0;
                $targetCount = max(1, $activeChallenge->target_count);
                $progressPct = min(100, round(($userProgress / $targetCount) * 100));
            @endphp
            <div style="background:#FFFDF9;border:1px solid #EAE3D2;border-radius:14px;padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;">
                    <div>
                        <h3 style="margin:0 0 4px;font-size:16.5px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">
                            {{ $activeChallenge->title }}
                        </h3>
                        <p style="margin:0;font-size:12.5px;color:#5A5445;line-height:1.6;">
                            {{ $activeChallenge->description }}
                        </p>
                    </div>
                    <span class="tag" style="background:rgba(195,161,93,0.15);color:#785419;font-weight:700;white-space:nowrap;">
                        +{{ $activeChallenge->reward_points ?? 50 }} أثر 🏅
                    </span>
                </div>

                <div style="display:flex;gap:12px;align-items:center;margin:12px 0;flex-wrap:wrap;font-size:12px;color:#726A58;">
                    <span><i data-lucide="users" style="width:13px;height:13px;display:inline-block;vertical-align:middle;"></i> {{ $participantsCount }} قارئاً مشاركاً</span>
                    <span>·</span>
                    <span><i data-lucide="book-check" style="width:13px;height:13px;display:inline-block;vertical-align:middle;"></i> الهدف: {{ $activeChallenge->target_count }} كتب</span>
                    <span>·</span>
                    <span><i data-lucide="calendar" style="width:13px;height:13px;display:inline-block;vertical-align:middle;"></i> ينتهي في: {{ $activeChallenge->end_date ? $activeChallenge->end_date->translatedFormat('d F Y') : 'نهاية الشهر' }}</span>
                </div>

                @if($isParticipating)
                    <div style="background:#F7F3E9;border:1px solid #E4DAC4;border-radius:10px;padding:10px 14px;margin-top:10px;">
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;">
                            <span style="color:#6A6250;">إنجازك الشخصي بالتحدي:</span>
                            <b style="color:var(--green);">{{ $userProgress }} / {{ $targetCount }} كتب ({{ $progressPct }}%)</b>
                        </div>
                        <div class="progress liquid-progress" style="--pct:{{ $progressPct }}%;height:8px;margin:0;">
                            <span style="width: {{ $progressPct }}%;">
                                <span class="liquid-wave-effect"></span>
                            </span>
                        </div>
                    </div>
                @else
                    @auth
                        <form method="POST" action="{{ route('challenges.join', $activeChallenge->id) }}" style="margin-top:12px;">
                            @csrf
                            <button type="submit" class="primary" style="width:100%;font-size:13px;padding:8px 16px;">
                                <i data-lucide="trophy"></i>
                                <span>انضمام إلى تحدي المجلس الآن (+20 أثر)</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="primary" style="display:block;text-align:center;text-decoration:none;margin-top:12px;font-size:13px;padding:8px 16px;">
                            سجل دخولك للانضمام للتحدي
                        </a>
                    @endauth
                @endif
            </div>
        @else
            <div style="text-align:center;padding:26px 16px;background:#FAF8F3;border:1px dashed #DFD5C2;border-radius:12px;">
                <p class="muted" style="font-size:13px;margin-bottom:12px;">لا يوجد تحدٍ جارٍ في المجلس حالياً.</p>
                @if($canManage || ($community->allow_member_challenges && $isMember))
                    <button type="button" class="primary btn-sm" onclick="openModal('create-challenge-modal')">
                        <i data-lucide="plus"></i>
                        <span>إطلاق تحدي قرائي جديد للمجلس</span>
                    </button>
                @endif
            </div>
        @endif
    </section>

    <!-- Component 2: New Members & Welcome Message (الأعضاء الجدد ورسالة الترحيب) -->
    <section class="panel animate-fade-in delay-2" style="border-radius:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(35,87,56,0.1);color:var(--green);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="user-plus" style="width:18px;height:18px;"></i>
                </div>
                <h2 style="margin:0;font-size:18px;">الأعضاء الجدد في المجلس</h2>
            </div>
            <span class="tag" style="background:#EBF3ED;color:var(--green);font-size:11px;font-weight:700;">أهلاً بضيوف المعرفة 🌿</span>
        </div>

        @if($community->welcome_message)
            <div style="background:#FAF6EE;border:1px solid #EAE0CD;border-radius:12px;padding:12px 14px;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;">
                <i data-lucide="sparkles" style="width:16px;height:16px;color:var(--gold);flex-shrink:0;margin-top:2px;"></i>
                <div style="font-size:12.5px;color:#5A4F3A;line-height:1.6;">
                    <strong>رسالة ترحيب المجلس:</strong> «{{ $community->welcome_message }}»
                </div>
            </div>
        @endif

        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(130px, 1fr));gap:10px;">
            @forelse($newestMembers as $nm)
                <div style="background:#FFFDF9;border:1px solid #EAE3D2;border-radius:10px;padding:10px;text-align:center;transition:transform 0.2s ease;" onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform='translateY(0)'">
                    <div style="width:40px;height:40px;border-radius:50%;background:var(--green);color:#FFFDF8;display:grid;place-items:center;font-weight:700;font-size:14px;margin:0 auto 6px;box-shadow:0 2px 6px rgba(23,61,54,0.15);">
                        {{ mb_substr($nm->name, 0, 1) }}
                    </div>
                    <strong style="font-size:12.5px;color:var(--ink);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $nm->name }}">
                        {{ $nm->name }}
                    </strong>
                    @if($nm->id === $community->creator_id || (isset($nm->pivot) && $nm->pivot->role === 'owner'))
                        <span class="tag" style="background:#FFF3D6;color:#785419;font-size:10px;font-weight:700;margin-top:2px;display:inline-block;">المؤسس 👑</span>
                    @else
                        <small class="muted" style="font-size:10.5px;display:block;">
                            {{ $nm->pivot->joined_at ? \Carbon\Carbon::parse($nm->pivot->joined_at)->diffForHumans() : 'عضو جديد' }}
                        </small>
                    @endif
                </div>
            @empty
                <p class="muted" style="grid-column:1/-1;text-align:center;padding:16px;font-size:12.5px;">
                    لا يوجد أعضاء جدد مسجلون بعد.
                </p>
            @endforelse
        </div>
    </section>
</div>

<!-- Component 3: Leaderboard (لوحة المتصدرين لأعضاء المجلس) -->
<section class="panel animate-fade-in delay-2" style="border-radius:16px;margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="background:var(--gold);color:#173D36;width:34px;height:34px;border-radius:8px;display:grid;place-items:center;box-shadow:0 2px 8px rgba(195,161,93,0.25);">
                <i data-lucide="crown" style="width:18px;height:18px;"></i>
            </div>
            <div>
                <h2 style="margin:0;font-size:18.5px;">لوحة المتصدرين (Council Leaderboard)</h2>
                <small class="muted">أعلى القراء نشاطاً وإثراءً للحوار المعرفي وإنجازاً للكتب داخل مجلس «{{ $community->name }}»</small>
            </div>
        </div>
        <span class="tag" style="background:#FFF3D6;color:#785419;font-weight:700;">أهل الأثر القرائي ✦</span>
    </div>

    <div style="display:grid;gap:8px;">
        @forelse($leaderboard as $index => $lbUser)
            @php
                $rank = $index + 1;
                $medal = match($rank) {
                    1 => '🥇',
                    2 => '🥈',
                    3 => '🥉',
                    default => "#{$rank}",
                };
                $isTop3 = $rank <= 3;
            @endphp
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 16px;border-radius:12px;border:1px solid {{ $isTop3 ? '#E4DAC4' : '#F0ECE1' }};background:{{ $isTop3 ? '#FFFDF8' : '#FAF8F3' }};flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="font-size:{{ $isTop3 ? '20px' : '14px' }};font-weight:700;min-width:32px;text-align:center;color:{{ $isTop3 ? 'var(--gold)' : '#786E58' }};">
                        {{ $medal }}
                    </span>
                    <div style="width:36px;height:36px;border-radius:50%;background:{{ $isTop3 ? 'var(--green)' : '#4A6B53' }};color:#FFFDF8;display:grid;place-items:center;font-weight:700;font-size:13px;">
                        {{ mb_substr($lbUser->name, 0, 1) }}
                    </div>
                    <div>
                        <strong style="font-size:14px;color:var(--ink);display:block;">
                            {{ $lbUser->name }}
                            @if(Auth::id() === $lbUser->id)
                                <span class="tag" style="background:rgba(35,87,56,0.12);color:var(--green);font-size:10px;margin-right:4px;">أنت</span>
                            @endif
                        </strong>
                        <small class="muted" style="font-size:11.5px;">{{ $lbUser->level_data['title'] ?? 'قارئ مطلع' }}</small>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="text-align:left;">
                        <b style="font-size:14px;color:var(--gold);display:block;direction:ltr;">{{ number_format($lbUser->points ?? 0) }} أثر</b>
                        <small class="muted" style="font-size:11px;">نقاط التفاعل</small>
                    </div>
                    @if($lbUser->reading_streak > 0)
                        <span class="tag" style="background:#FFF3E0;color:#E65100;font-size:11px;font-weight:700;">
                            🔥 {{ $lbUser->reading_streak }} يوم
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted" style="text-align:center;padding:20px;">لا توجد بيانات للمتصدرين حتى الآن.</p>
        @endforelse
    </div>
</section>
</div>
</div>
@endsection

@section('modals')
<!-- Join Request Modal for Private Communities -->
<div id="join-request-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:480px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:18px;">طلب انضمام إلى «{{ $community->name }}»</h2>
                <small class="muted">هذا مجلس خاص يتطلب موافقة الإدارة لضمان نوعية الحوار والالتزام.</small>
            </div>
            <button class="close-btn" onclick="closeModal('join-request-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.requestJoin', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>رسالة تعريفية أو سبب رغبتك بالانضمام</span>
                    <textarea name="notes" placeholder="اكتب نبذة عن اهتمامك بموضوعات هذا المجلس أو قراءاتك السابقة ذات الصلة..." rows="4" required></textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="send"></i>
                    <span>إرسال طلب الانضمام للمشرفين</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Manager Announcement Modal -->
<div id="announcement-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:520px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:18px;">نشر إعلان مثبت في صدر المجلس</h2>
                <small class="muted">يظهر هذا الإعلان بوضوح لكافة أعضاء وزوار «{{ $community->name }}».</small>
            </div>
            <button class="close-btn" onclick="closeModal('announcement-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.storeAnnouncement', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>عنوان الإعلان</span>
                    <input name="title" type="text" placeholder="مثال: موعد مناقشة الجزء الثاني من كتاب الشهر..." required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>تفاصيل الإعلان</span>
                    <textarea name="content" rows="4" placeholder="اكتب نص الإعلان، التعليمات أو الروابط الهامة..." required></textarea>
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="is_pinned" value="1" checked style="width:18px;height:18px;accent-color:var(--gold);">
                    <span style="font-size:13px;font-weight:600;">تثبيت الإعلان في صدر صفحة المجلس 📌</span>
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="pin"></i>
                    <span>نشر وتثبيت الإعلان الآن</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Add Council Highlight Modal (إضافة اقتباس لمعجم المجلس) -->
<div id="add-highlight-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:540px;width:100%;">
        <div class="heading" style="margin-bottom:14px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="background:var(--green);color:var(--gold);width:32px;height:32px;border-radius:8px;display:grid;place-items:center;">
                    <i data-lucide="quote" style="width:16px;height:16px;"></i>
                </div>
                <h2 style="margin:0;font-size:17px;color:var(--green);">إضافة اقتباس إلى معجم «{{ $community->name }}»</h2>
            </div>
            <button class="close-btn" onclick="closeModal('add-highlight-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.highlights.store', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span style="font-size:12.5px;font-weight:600;">الكتاب المرتبط بالاقتباس:</span>
                    <select name="book_id" style="font-size:13px;padding:8px 10px;border-radius:8px;border:1px solid #D1C7B7;">
                        @if($community->currentBook)
                            <option value="{{ $community->currentBook->id }}" selected>
                                كتاب المجلس الحالي: {{ $community->currentBook->title }} ({{ $community->currentBook->author }})
                            </option>
                        @endif
                        @foreach($communityBooks as $cb)
                            @if(!$community->currentBook || $community->currentBook->id !== $cb->id)
                                <option value="{{ $cb->id }}">{{ $cb->title }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>

                <label style="display:grid;gap:6px;">
                    <span style="font-size:12.5px;font-weight:600;">نص الاقتباس أو التأمل المعرفي: *</span>
                    <textarea name="content" required rows="4" placeholder="اكتب الاقتباس أو الفكرة الملهمة التي تركت أثراً فيك من قراءات هذا المجلس..." style="font-size:13px;padding:10px;border-radius:8px;border:1px solid #D1C7B7;line-height:1.7;"></textarea>
                </label>

                <label style="display:grid;gap:6px;">
                    <span style="font-size:12.5px;font-weight:600;">رقم الصفحة (اختياري):</span>
                    <input type="number" name="page_number" min="1" placeholder="مثال: 45" style="font-size:13px;padding:8px 10px;border-radius:8px;border:1px solid #D1C7B7;">
                </label>

                <!-- Spoiler Toggle Checkbox -->
                <label style="display:flex;align-items:flex-start;gap:10px;background:#FFF9ED;border:1px dashed #C3A15D;border-radius:10px;padding:10px 14px;cursor:pointer;">
                    <input type="checkbox" name="is_spoiler" value="1" style="width:16px;height:16px;margin-top:2px;accent-color:var(--gold);">
                    <div>
                        <strong style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:#8C6D2C;">
                            <i data-lucide="alert-triangle" style="width:13px;height:13px;"></i>
                            <span>تنبيه حرق الأحداث (Spoiler Alert)</span>
                        </strong>
                        <small class="muted" style="font-size:11px;color:#786E58;display:block;margin-top:2px;">
                            حدد هذا الخيار إذا كان الاقتباس يتضمن حرقاً لأحداث الرواية أو نهايتها، ليتم تعتيمه تلقائياً وحماية القراء.
                        </small>
                    </div>
                </label>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px;">
                    <button type="button" class="secondary" onclick="closeModal('add-highlight-modal')">إلغاء</button>
                    <button type="submit" class="primary" style="background:var(--gold);color:#173D36;font-weight:700;">
                        <i data-lucide="sparkles"></i>
                        <span>حفظ في معجم المجلس (+15 أثر)</span>
                    </button>
                </div>
            </div>
        </form>
    </section>
</div>

<!-- Discussion Modal -->
<div id="discussion-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:540px;width:100%;">
        <div class="heading" style="margin-bottom:14px;">
            <h2 style="margin:0;font-size:18px;">مشاركة في نقاش المجلس المعرفي</h2>
            <button class="close-btn" onclick="closeModal('discussion-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.storeDiscussion', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span style="font-size:12.5px;font-weight:600;">الكتاب المرتبط بالنقاش:</span>
                    <select name="book_id" style="font-size:13px;padding:8px 10px;border-radius:8px;border:1px solid #D1C7B7;">
                        @if($community->currentBook)
                            <option value="{{ $community->currentBook->id }}" {{ (!$selectedBookId || $selectedBookId == $community->currentBook->id) ? 'selected' : '' }}>
                                كتاب الشهر الحالي: {{ $community->currentBook->title }}
                            </option>
                        @endif
                        @foreach($communityBooks as $cb)
                            @if(!$community->currentBook || $community->currentBook->id !== $cb->id)
                                <option value="{{ $cb->id }}" {{ ($selectedBookId == $cb->id) ? 'selected' : '' }}>{{ $cb->title }}</option>
                            @endif
                        @endforeach
                        <option value="">نقاش عام بلا كتاب محدد</option>
                    </select>
                </label>

                <label style="display:grid;gap:6px;">
                    <span style="font-size:12.5px;font-weight:600;">عنوان المشاركة (اختياري):</span>
                    <input name="title" placeholder="مثال: البعد الفلسفي في المشهد الختامي" style="font-size:13px;padding:8px 10px;border-radius:8px;border:1px solid #D1C7B7;">
                </label>

                <label style="display:grid;gap:6px;">
                    <span style="font-size:12.5px;font-weight:600;">نص المشاركة أو التأمل القرائي:</span>
                    <textarea name="content" placeholder="اكتب تأملك أو فكرتك حول سؤال الأسبوع أو الكتاب... (يمكنك الإشارة للأعضاء بكتابة @اسم_المستخدم)" rows="4" required style="font-size:13px;padding:8px 10px;border-radius:8px;border:1px solid #D1C7B7;line-height:1.6;"></textarea>
                </label>

                <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#925A12;cursor:pointer;">
                    <input type="checkbox" name="is_spoiler" value="1" style="width:14px;height:14px;accent-color:var(--gold);">
                    <span>⚠️ تحذير حرق الأحداث (Spoiler Flag): طمس المحتوى لحماية القراء حتى ينقروا لكشفه</span>
                </label>

                @if($canManage || (Auth::check() && $community->isReadingCoordinator(Auth::user())))
                    <div style="background:#FAF8F3;border:1px solid #E8E0D0;border-radius:8px;padding:10px 12px;display:grid;gap:6px;">
                        <span style="font-size:11.5px;font-weight:700;color:var(--green);">صلاحيات الإشراف والتنسيق:</span>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#235738;cursor:pointer;">
                            <input type="checkbox" name="is_coordinator_question" value="1" style="accent-color:var(--green);">
                            <span>✍️ اعتماد هذا المنشور كـ «سؤال منسق القراءة» الرسمي للنقاش</span>
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#92400E;cursor:pointer;">
                            <input type="checkbox" name="is_pinned" value="1" style="accent-color:#92400E;">
                            <span>📌 تثبيت هذا المنشور في أعلى مساحة نقاش الكتاب</span>
                        </label>
                    </div>
                @endif

                <small class="muted" style="font-size:11.5px;color:#786E58;">
                    💡 تلميح: يمكنك إخفاء مقاطع محددة فقط بكتابتها بين <code>[حرق] هنا النص المحروق [/حرق]</code>.
                </small>

                <button type="submit" class="primary" style="width:100%;padding:10px;font-weight:700;">
                    <i data-lucide="send"></i>
                    <span>نشر المشاركة في المجلس (+15 أثر)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Enhanced Invite Member & Invite Links Hub Modal -->
<div id="invite-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:640px;max-width:96vw;max-height:92vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:20px;font-weight:800;">إدارة الدعوات وروابط الانضمام 🌿</h2>
                <small class="muted">دعوة القراء بالاسم أو البريد أو الهاتف، وتوليد روابط مؤمنة بـ Token ورمز QR مع التحكم بالصلاحية والاستخدامات.</small>
            </div>
            <button class="close-btn" onclick="closeModal('invite-modal')">&times;</button>
        </div>

        <!-- Tabs Navigation -->
        <div style="display:flex;gap:8px;border-bottom:2px solid #EAE0CD;margin-bottom:16px;padding-bottom:4px;">
            <button type="button" class="tag active-tab-btn" id="tab-invite-form-btn" onclick="switchInviteTab('invite-form')" style="font-weight:700;padding:6px 14px;border-radius:8px;cursor:pointer;">
                <i data-lucide="send" style="width:14px;height:14px;"></i>
                <span>إنشاء دعوة جديدة</span>
            </button>
            <button type="button" class="tag" id="tab-invite-list-btn" onclick="switchInviteTab('invite-list')" style="font-weight:700;padding:6px 14px;border-radius:8px;cursor:pointer;">
                <i data-lucide="link" style="width:14px;height:14px;"></i>
                <span>الروابط والدعوات النشطة (<span id="invite-count-badge">{{ isset($communityInvitations) ? $communityInvitations->count() : 0 }}</span>)</span>
            </button>
        </div>

        <!-- Tab 1: Create Invitation Form -->
        <div id="tab-invite-form">
            <!-- Generated Invitation Box (shown dynamically or if flashed in session) -->
            <div id="invite-generated-box" style="{{ session('generated_invite_url') ? 'display:block;' : 'display:none;' }}background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:12px;padding:16px;margin-bottom:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:8px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="display:grid;place-items:center;width:28px;height:28px;border-radius:50%;background:#22C55E;color:#fff;flex-shrink:0;">
                            <i data-lucide="check" style="width:16px;height:16px;"></i>
                        </span>
                        <div>
                            <strong style="color:#15803D;font-size:14px;display:block;">تم توليد رابط الدعوة بنجاح! 📋</strong>
                            <small id="generated-invite-recipient" style="color:#166534;font-size:11.5px;">تم نسخ الرابط تلقائياً إلى الحافظة، وجاهز للمشاركة الآن:</small>
                        </div>
                    </div>
                    <span class="tag" style="background:#DCFCE7;color:#166534;font-size:11px;font-weight:700;">رابط نشط ✦</span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
                    <input type="text" id="generated-invite-url" readonly value="{{ session('generated_invite_url', '') }}" 
                           style="flex:1;min-width:220px;font-size:13px;font-family:monospace;direction:ltr;text-align:left;background:#fff;padding:8px 12px;border:1px solid #86EFAC;border-radius:8px;"
                           onclick="this.select();">
                    <button type="button" class="primary btn-sm" id="btn-copy-generated" onclick="copyGeneratedLink()" style="background:#15803D;border-color:#15803D;padding:8px 14px;font-size:12.5px;">
                        <i data-lucide="copy" style="width:14px;height:14px;"></i>
                        <span id="btn-copy-text">نسخ الرابط</span>
                    </button>
                    <a id="btn-whatsapp-share" href="https://api.whatsapp.com/send?text={{ urlencode('انضم إلى نادي القراءة عبر هذا الرابط: ' . session('generated_invite_url', '')) }}" target="_blank" class="secondary btn-sm" style="background:#25D366;color:#fff;border-color:#25D366;padding:8px 12px;font-size:12.5px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                        <i data-lucide="message-circle" style="width:14px;height:14px;"></i>
                        <span>واتساب</span>
                    </a>
                    <button type="button" id="btn-qr-generated" class="secondary btn-sm" onclick="previewInvitationQr(document.getElementById('generated-invite-url').value, '')" style="padding:8px 12px;font-size:12.5px;">
                        <i data-lucide="qr-code" style="width:14px;height:14px;color:var(--gold);"></i>
                        <span>رمز QR</span>
                    </button>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;border-top:1px dashed #BBF7D0;padding-top:10px;flex-wrap:wrap;gap:8px;">
                    <span class="muted" style="color:#166534;">💡 نصيحة: يمكنك دائماً استعراض وإلغاء جميع روابطك من تبويب «الروابط والدعوات النشطة».</span>
                    <button type="button" class="secondary btn-sm" onclick="switchInviteTab('invite-list')" style="font-size:11.5px;padding:4px 10px;">
                        عرض كل الروابط النشطة
                    </button>
                </div>
            </div>

            <!-- 1. Community Direct Code Info -->
            <div style="background:#F2EDE2;border:1px solid #DFD5C2;border-radius:12px;padding:14px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <i data-lucide="hash" style="width:15px;height:15px;color:var(--gold);"></i>
                        <span style="font-weight:700;font-size:13px;color:var(--green);">كود المجلس المباشر:</span>
                        <span style="font-size:16px;font-weight:800;letter-spacing:1px;color:var(--ink);background:#FFFDF8;padding:3px 12px;border-radius:6px;border:1px dashed var(--gold);">
                            {{ $community->code }}
                        </span>
                    </div>
                    <button type="button" class="secondary btn-sm" onclick="window.copyToClipboard('{{ $community->code }}', 'تم نسخ كود المجلس: {{ $community->code }}');" style="padding:4px 10px;font-size:12px;">
                        <i data-lucide="copy" style="width:13px;height:13px;"></i>
                        <span>نسخ الكود</span>
                    </button>
                </div>
            </div>

            <!-- Friendship Independence Banner -->
            <div style="background:#FAF6EE;border:1px solid #EAE0CD;border-radius:10px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                <i data-lucide="info" style="width:16px;height:16px;color:var(--gold);flex-shrink:0;"></i>
                <span style="font-size:12px;color:#5A4F3A;line-height:1.5;">
                    <strong>استقلالية العضوية:</strong> العضوية في مجالس القراءة مستقلة تماماً عن الصداقة والمتابعة؛ لا يُشترط أن تكون صديقاً للمنشئ أو الأعضاء للانضمام.
                </span>
            </div>

            @if(isset($userFriends) && $userFriends->count() > 0)
            <!-- Quick Friend Invitation Selection -->
            <div style="margin-bottom:14px;">
                <span style="font-size:12px;font-weight:700;color:var(--green);display:block;margin-bottom:6px;">
                    اختيار سريع من رفاق القراءة المتابعين:
                </span>
                <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;">
                    @foreach($userFriends as $friend)
                        <div style="background:#FAF8F3;border:1px solid #E4DAC4;border-radius:8px;padding:4px 8px;display:flex;align-items:center;gap:6px;flex-shrink:0;">
                            <div style="width:24px;height:24px;border-radius:50%;background:var(--green);color:#FFFDF8;display:grid;place-items:center;font-size:11px;font-weight:700;">
                                {{ mb_substr($friend->name, 0, 1) }}
                            </div>
                            <span style="font-size:12px;font-weight:600;color:var(--ink);">{{ $friend->name }}</span>
                            <button type="button" class="primary btn-sm" onclick="document.getElementById('recipient-input').value='{{ $friend->email }}'; showToast('تم اختيار {{ $friend->name }} للدعوة');" style="font-size:10.5px;padding:2px 6px;">
                                اختيار
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Main Invitation Form -->
            <form id="community-invite-form" action="{{ route('communities.invitations.store', $community->id) }}" method="POST">
                @csrf
                <div style="display:grid;gap:12px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <!-- Recipient by Username or Email -->
                        <label style="display:grid;gap:4px;">
                            <span style="font-weight:600;font-size:12.5px;">اسم المستخدم أو البريد:</span>
                            <input name="recipient" id="recipient-input" placeholder="مثال: admin@riwaq.test أو اسم المستخدم" style="font-size:13px;">
                        </label>

                        <!-- Recipient Phone (للتفعيل لاحقاً SMS / WhatsApp) -->
                        <label style="display:grid;gap:4px;">
                            <span style="font-weight:600;font-size:12.5px;display:flex;align-items:center;gap:4px;">
                                <span>رقم الهاتف / واتساب:</span>
                                <small class="muted" style="font-size:10.5px;color:var(--gold);">(للتفعيل لاحقاً)</small>
                            </span>
                            <input name="recipient_phone" placeholder="مثال: +966500000000" style="font-size:13px;direction:ltr;text-align:right;">
                        </label>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <!-- Expiry Days -->
                        <label style="display:grid;gap:4px;">
                            <span style="font-weight:600;font-size:12.5px;">صلاحية الرابط (Expiry):</span>
                            <select name="expiry_days" style="font-size:13px;">
                                <option value="7" selected>7 أيام (موصى به)</option>
                                <option value="1">24 ساعة (يوم واحد)</option>
                                <option value="30">30 يوماً (شهر كامل)</option>
                                <option value="0">صلاحية مفتوحة (بلا انتهاء)</option>
                            </select>
                        </label>

                        <!-- Max Uses -->
                        <label style="display:grid;gap:4px;">
                            <span style="font-weight:600;font-size:12.5px;">حد مرات الاستخدام (Max Uses):</span>
                            <select name="max_uses" style="font-size:13px;">
                                <option value="1">استخدام واحد (دعوة فردية)</option>
                                <option value="5">5 استخدامات</option>
                                <option value="25">25 استخدام</option>
                                <option value="0">غير محدود (رابط عام مفتوح)</option>
                            </select>
                        </label>
                    </div>

                    <!-- Custom Message -->
                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:500;font-size:12px;color:var(--sub);">رسالة ترحيبية مخصصة (اختياري):</span>
                        <textarea name="custom_message" placeholder="يسعدني انضمامك معنا لمناقشة كتاب الشهر ومشاركتنا أفكارك وتأملاتك القرائية..." rows="2" style="font-size:13px;"></textarea>
                    </label>

                    <button type="submit" class="primary" style="width:100%;padding:10px;font-size:14px;font-weight:700;">
                        <i data-lucide="sparkles"></i>
                        <span>توليد رابط الدعوة ورمز QR المعتمد (+10 أثر)</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab 2: Active Invitations & Links List -->
        <div id="tab-invite-list" style="display:none;">
            @if(isset($communityInvitations) && $communityInvitations->count() > 0)
                <div id="active-invitations-list-container" style="display:grid;gap:10px;">
                    @foreach($communityInvitations as $inv)
                        <div style="background:#FAF8F3;border:1px solid #E4DAC4;border-radius:10px;padding:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                            <div style="flex:1;min-width:200px;">
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                    <!-- Status Badge -->
                                    <span class="badge" style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;
                                        @if($inv->status === 'accepted') background:#dcfce7;color:#166534;
                                        @elseif($inv->status === 'opened') background:#fef9c3;color:#854d0e;
                                        @elseif($inv->status === 'expired') background:#f3f4f6;color:#4b5563;
                                        @elseif($inv->status === 'revoked') background:#fee2e2;color:#991b1b;
                                        @elseif($inv->status === 'rejected') background:#fef2f2;color:#b91c1c;
                                        @else background:#e0f2fe;color:#0369a1; @endif">
                                        {{ $inv->status_label }}
                                    </span>

                                    <strong style="font-size:13px;color:var(--forest-green);">
                                        {{ $inv->recipient_name ?? $inv->recipient_email ?? 'رابط دعوة عام' }}
                                    </strong>
                                    @if($inv->recipient_phone)
                                        <small class="muted" style="font-size:11px;">({{ $inv->recipient_phone }})</small>
                                    @endif
                                </div>

                                <div style="font-size:11.5px;color:#64748b;line-height:1.4;">
                                    <span>الاستخدامات: <b>{{ $inv->uses_count }}</b> / {{ $inv->max_uses ?? 'غير محدود' }}</span>
                                    <span style="margin:0 4px;">•</span>
                                    <span>الانتهاء: {{ $inv->expires_at ? $inv->expires_at->format('Y-m-d') : 'مفتوح' }}</span>
                                    <span style="margin:0 4px;">•</span>
                                    <code style="font-size:11px;color:var(--green);">{{ substr($inv->token, 0, 10) }}...</code>
                                </div>
                            </div>

                            <div style="display:flex;align-items:center;gap:6px;">
                                <!-- Copy Link -->
                                <button type="button" class="secondary btn-sm" onclick="window.copyToClipboard('{{ $inv->invite_url }}', 'تم نسخ رابط الدعوة!');" title="نسخ رابط الدعوة" style="padding:4px 8px;font-size:11px;">
                                    <i data-lucide="copy" style="width:12px;height:12px;"></i>
                                    <span>نسخ</span>
                                </button>

                                <!-- QR Preview Button -->
                                <button type="button" class="secondary btn-sm" onclick="previewInvitationQr('{{ $inv->invite_url }}', '{{ substr($inv->token, 0, 12) }}')" title="معاينة رمز QR" style="padding:4px 8px;font-size:11px;">
                                    <i data-lucide="qr-code" style="width:12px;height:12px;color:var(--gold);"></i>
                                    <span>QR</span>
                                </button>

                                <!-- Open Landing Page Link -->
                                <a href="{{ $inv->invite_url }}" target="_blank" class="secondary btn-sm" title="فتح صفحة الدعوة" style="padding:4px 8px;font-size:11px;text-decoration:none;">
                                    <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                                </a>

                                <!-- Revoke Button if active -->
                                @if(!$inv->isRevoked() && !$inv->isExpired())
                                    <form action="{{ route('communities.invitations.revoke', $inv->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من رغبتك في إلغاء رابط الدعوة هذا؟ لن يتمكن أحد من استخدامه بعد الآن.');" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="secondary btn-sm" title="إلغاء الرابط" style="padding:4px 8px;font-size:11px;color:#dc2626;border-color:#fca5a5;">
                                            <i data-lucide="ban" style="width:12px;height:12px;"></i>
                                            <span>إلغاء</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div id="active-invitations-list-container" style="display:grid;gap:10px;">
                    <div style="text-align:center;padding:24px 12px;" class="muted">
                        <i data-lucide="inbox" style="width:32px;height:32px;display:block;margin:0 auto 6px;color:#cbd5e1;"></i>
                        <p style="margin:0;font-size:13px;">لا توجد روابط أو دعوات نشطة حالياً لهذا المجلس.</p>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>

<!-- QR Code Quick Preview Modal -->
<div id="invite-qr-preview-modal" class="overlay" style="display:none;z-index:9999;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:340px;max-width:90vw;text-align:center;">
        <div class="heading" style="margin-bottom:12px;">
            <h3 style="margin:0;font-size:16px;">رمز QR لرابط الدعوة ✦</h3>
            <button class="close-btn" onclick="closeModal('invite-qr-preview-modal')">&times;</button>
        </div>
        <div id="qr-preview-canvas" style="background:#FFFDF8;padding:16px;border-radius:12px;border:1px solid #DFD5C2;margin-bottom:12px;display:inline-block;">
            <div id="qr-svg-holder"></div>
        </div>
        <div style="margin-bottom:12px;">
            <input type="text" id="qr-preview-url" readonly style="width:100%;font-size:11px;font-family:monospace;direction:ltr;text-align:center;background:#f8fafc;padding:6px;border-radius:6px;border:1px solid #cbd5e1;">
        </div>
        <button type="button" class="primary" onclick="window.copyToClipboard(document.getElementById('qr-preview-url').value, 'تم نسخ الرابط بنجاح!');" style="width:100%;font-size:13px;padding:8px;">
            <i data-lucide="copy"></i>
            <span>نسخ رابط الدعوة</span>
        </button>
    </section>
</div>

<script>
function copyGeneratedLink() {
    const input = document.getElementById('generated-invite-url');
    if (!input || !input.value) return;
    window.copyToClipboard(input.value, 'تم نسخ رابط الدعوة إلى الحافظة!');
    const btnText = document.getElementById('btn-copy-text');
    if (btnText) {
        const prev = btnText.textContent;
        btnText.textContent = 'تم النسخ! ✓';
        setTimeout(() => { btnText.textContent = prev; }, 2000);
    }
}

function showGeneratedInviteResult(url, token, recipient) {
    const box = document.getElementById('invite-generated-box');
    const input = document.getElementById('generated-invite-url');
    const whatsappBtn = document.getElementById('btn-whatsapp-share');
    const qrBtn = document.getElementById('btn-qr-generated');
    const recipientSpan = document.getElementById('generated-invite-recipient');
    
    if (input) input.value = url;
    if (whatsappBtn) {
        whatsappBtn.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent('يسعدني انضمامك معنا إلى نادي القراءة عبر هذا الرابط: ' + url);
    }
    if (qrBtn && token) {
        qrBtn.setAttribute('onclick', `previewInvitationQr('${url}', '${token}')`);
    }
    if (recipientSpan) {
        recipientSpan.textContent = recipient ? `موجهة إلى: ${recipient} — تم نسخ الرابط تلقائياً إلى الحافظة:` : 'تم نسخ الرابط تلقائياً إلى الحافظة، وجاهز للمشاركة الآن:';
    }
    if (box) {
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function switchInviteTab(tab) {
    const formTab = document.getElementById('tab-invite-form');
    const listTab = document.getElementById('tab-invite-list');
    const formBtn = document.getElementById('tab-invite-form-btn');
    const listBtn = document.getElementById('tab-invite-list-btn');

    if (tab === 'invite-form') {
        if (formTab) formTab.style.display = 'block';
        if (listTab) listTab.style.display = 'none';
        if (formBtn) formBtn.classList.add('active-tab-btn');
        if (listBtn) listBtn.classList.remove('active-tab-btn');
    } else {
        if (formTab) formTab.style.display = 'none';
        if (listTab) listTab.style.display = 'block';
        if (formBtn) formBtn.classList.remove('active-tab-btn');
        if (listBtn) listBtn.classList.add('active-tab-btn');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const inviteForm = document.getElementById('community-invite-form');
    if (inviteForm) {
        inviteForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const submitBtn = inviteForm.querySelector('button[type="submit"]');
            const origHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i data-lucide="loader-2" class="spin"></i><span>جاري توليد الرابط...</span>';
                if (window.lucide) window.lucide.createIcons();
            }

            try {
                const formData = new FormData(inviteForm);
                const res = await fetch(inviteForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token')
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success && data.invite_url) {
                    const recipientInput = document.getElementById('recipient-input');
                    const recipientVal = recipientInput ? recipientInput.value.trim() : '';
                    showGeneratedInviteResult(data.invite_url, data.invitation?.token, recipientVal);
                    
                    window.copyToClipboard(data.invite_url, 'تم توليد الرابط ونسخه إلى الحافظة بنجاح! 📋');

                    if (recipientInput) recipientInput.value = '';

                    const badge = document.getElementById('invite-count-badge');
                    if (badge) {
                        badge.textContent = parseInt(badge.textContent || '0') + 1;
                    }

                    const listContainer = document.getElementById('active-invitations-list-container');
                    if (listContainer) {
                        const tokenShort = (data.invitation?.token || '').substring(0, 10);
                        const recipientName = data.invitation?.recipient_name || recipientVal || 'رابط دعوة عام';
                        const newCard = document.createElement('div');
                        newCard.style.cssText = 'background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;';
                        newCard.innerHTML = `
                            <div style="flex:1;min-width:200px;">
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                    <span class="badge" style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;background:#e0f2fe;color:#0369a1;">مرسلة</span>
                                    <strong style="font-size:13px;color:var(--forest-green);">${recipientName}</strong>
                                </div>
                                <div style="font-size:11.5px;color:#64748b;line-height:1.4;">
                                    <span>الاستخدامات: <b>0</b> / ${data.invitation?.max_uses || 'غير محدود'}</span>
                                    <span style="margin:0 4px;">•</span>
                                    <code style="font-size:11px;color:var(--green);">${tokenShort}...</code>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <button type="button" class="secondary btn-sm" onclick="window.copyToClipboard('${data.invite_url}', 'تم نسخ رابط الدعوة!');" title="نسخ رابط الدعوة" style="padding:4px 8px;font-size:11px;">
                                    <i data-lucide="copy" style="width:12px;height:12px;"></i>
                                    <span>نسخ</span>
                                </button>
                                <button type="button" class="secondary btn-sm" onclick="previewInvitationQr('${data.invite_url}', '${tokenShort}')" title="معاينة رمز QR" style="padding:4px 8px;font-size:11px;">
                                    <i data-lucide="qr-code" style="width:12px;height:12px;color:var(--gold);"></i>
                                    <span>QR</span>
                                </button>
                                <a href="${data.invite_url}" target="_blank" class="secondary btn-sm" title="فتح صفحة الدعوة" style="padding:4px 8px;font-size:11px;text-decoration:none;">
                                    <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                                </a>
                            </div>
                        `;
                        const emptyP = listContainer.querySelector('.muted');
                        if (emptyP) emptyP.remove();
                        listContainer.prepend(newCard);
                    }

                    if (window.lucide) window.lucide.createIcons();
                } else {
                    alert(data.message || 'تعذر توليد رابط الدعوة');
                }
            } catch (err) {
                console.error('AJAX invite creation failed, submitting normally:', err);
                inviteForm.submit();
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
            }
        });
    }

    @if(session('generated_invite_url'))
        openModal('invite-modal');
        window.copyToClipboard('{{ session('generated_invite_url') }}', 'تم نسخ رابط الدعوة إلى الحافظة بنجاح! 📋');
    @endif
});

function previewInvitationQr(url, token) {
    const holder = document.getElementById('qr-svg-holder');
    const urlField = document.getElementById('qr-preview-url');
    if (urlField) urlField.value = url;
    if (holder) {
        holder.innerHTML = `<svg width="200" height="200" viewBox="0 0 100 100" style="margin:0 auto;display:block;">
            <rect width="100" height="100" fill="#FFFDF8"/>
            <rect x="5" y="5" width="26" height="26" fill="#173D36"/>
            <rect x="9" y="9" width="18" height="18" fill="#FFFDF8"/>
            <rect x="13" y="13" width="10" height="10" fill="#C3A15D"/>
            <rect x="69" y="5" width="26" height="26" fill="#173D36"/>
            <rect x="73" y="9" width="18" height="18" fill="#FFFDF8"/>
            <rect x="77" y="13" width="10" height="10" fill="#C3A15D"/>
            <rect x="5" y="69" width="26" height="26" fill="#173D36"/>
            <rect x="9" y="73" width="18" height="18" fill="#FFFDF8"/>
            <rect x="13" y="77" width="10" height="10" fill="#C3A15D"/>
            <rect x="36" y="36" width="28" height="28" fill="#173D36"/>
            <rect x="42" y="42" width="16" height="16" fill="#C3A15D"/>
            <circle cx="50" cy="50" r="4" fill="#FFFDF8"/>
            <rect x="36" y="10" width="8" height="8" fill="#173D36"/>
            <rect x="52" y="18" width="8" height="8" fill="#173D36"/>
            <rect x="12" y="42" width="8" height="8" fill="#173D36"/>
            <rect x="72" y="42" width="8" height="8" fill="#173D36"/>
            <rect x="42" y="72" width="8" height="8" fill="#173D36"/>
            <rect x="72" y="72" width="8" height="8" fill="#173D36"/>
        </svg>`;
    }
    openModal('invite-qr-preview-modal');
}
</script>

<!-- Create Community Modal (تأسيس مجلس جديد) -->
<div id="create-community-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:580px;max-height:90vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:19px;">تأسيس مجلس قراءة جديد 🌿</h2>
                <small class="muted">أنشئ مساحة حوارية رصينة تجمع القراء حول أفكار وكتب ملهمة.</small>
            </div>
            <button class="close-btn" onclick="closeModal('create-community-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.store') }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>اسم المجلس *</span>
                        <input name="name" placeholder="مثال: مجلس الأدب والفلسفة، صالون الفكر..." required>
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>المدينة / المنطقة</span>
                        <input name="city" placeholder="مثال: الرياض، القاهرة...">
                    </label>
                </div>

                <label style="display:grid;gap:6px;">
                    <span>نوع المجلس وصلاحية الانضمام *</span>
                    <select name="type" required id="create-community-type-select" onchange="toggleDomainField(this.value, 'create-domain-box')">
                        <option value="open">🌿 مفتوح (ظهور في البحث وانضمام فوري مباشر دون موافقة)</option>
                        <option value="public" selected>🌐 عام (ظهور في البحث ويتطلب طلب انضمام وموافقة الإدارة)</option>
                        <option value="private">🗝️ خاص ومغلق (مخفي في البحث ويحتاج دعوة أو رابط/كود خاص)</option>
                        <option value="institutional">🏛️ مؤسسي / أكاديمي (تحقق بالنطاق الجامعي أو استيراد قوائم)</option>
                    </select>
                </label>

                <div id="create-domain-box" style="display:none;background:#F0F8FF;border:1px solid #BEE3F8;border-radius:10px;padding:12px;">
                    <label style="display:grid;gap:6px;">
                        <span style="font-size:12.5px;font-weight:700;color:#2B6CB0;">نطاق البريد المؤسسي / الجامعي المعتمد:</span>
                        <input name="institutional_domain" placeholder="مثال: @ksu.edu.sa أو @iau.edu.sa">
                        <small class="muted" style="font-size:11px;">سيتمكن أي قارئ ينتهي بريده بهذا النطاق من الانضمام المباشر تلقائياً.</small>
                    </label>
                </div>

                <label style="display:grid;gap:6px;">
                    <span>وصف ورؤية المجلس</span>
                    <textarea name="description" rows="3" placeholder="ما هي أهداف المجلس؟ ما نوع الكتب التي ستتم قراءتها ومناقشتها؟"></textarea>
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>لغة القراءة والحوار</span>
                        <input name="language" value="العربية">
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>الحد الأقصى للأعضاء (اتركه فارغاً لغير محدود)</span>
                        <input name="max_members" type="number" min="2" max="50000" placeholder="مثال: 50، 100...">
                    </label>
                </div>

                <label style="display:grid;gap:6px;">
                    <span>الاهتمامات والتصنيفات (افصل بينها بفواصل)</span>
                    <input name="interests" placeholder="مثال: أدب, فلسفة, تاريخ, علوم, تطوير ذات">
                </label>

                <label style="display:grid;gap:6px;">
                    <span>ربط المجلس بمكتبة معتمدة (اختياري)</span>
                    <select name="linked_library_id">
                        <option value="">-- بدون ربط بمكتبة --</option>
                        @foreach($libraries ?? [] as $lib)
                            <option value="{{ $lib->id }}">{{ $lib->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label style="display:grid;gap:6px;">
                    <span>رسالة الترحيب بالأعضاء الجدد</span>
                    <textarea name="welcome_message" rows="2" placeholder="رسالة تظهر في صدر المجلس للأعضاء عند انضمامهم..."></textarea>
                </label>

                <label style="display:grid;gap:6px;">
                    <span>ميثاق وقواعد المجلس (Community Rules)</span>
                    <textarea name="rules" rows="3" placeholder="قواعد المشاركة والنقاش، مثل احترام الآراء، ومنع حرق الأحداث دون تنبيه..."></textarea>
                </label>

                <div class="riwaq-permission-group">
                    <div class="riwaq-permission-header">
                        <div class="riwaq-permission-title">
                            <i data-lucide="sliders" style="width:16px;height:16px;color:var(--gold);"></i>
                            <span>صلاحيات وإعدادات المجلس</span>
                        </div>
                        <span class="tag" style="background:rgba(23,61,54,0.08);color:var(--green);font-size:10.5px;font-weight:700;">تحكم مباشر</span>
                    </div>
                    <p class="riwaq-permission-subtitle">حدّد مستوى التفاعل والصلاحيات الممنوحة للأعضاء داخل مجلِسك الثقافي:</p>

                    <!-- Permission 1: Invites -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon">
                                <i data-lucide="user-plus" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">السماح للأعضاء بدعوة رفاقهم</span>
                                <span class="riwaq-switch-tile-desc">تمكين الأعضاء من مشاركة رابط المجلس وتوجيه دعوات لأصدقائهم</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="allow_member_invites" value="1" checked>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>

                    <!-- Permission 2: Challenges -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon" style="background:rgba(35,87,56,0.12);color:var(--green);">
                                <i data-lucide="trophy" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">إطلاق تحديات القراءة للمجلس</span>
                                <span class="riwaq-switch-tile-desc">السماح للأعضاء بإنشاء تحديات تنافسية ومسارات ختم للكتب</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="allow_member_challenges" value="1" checked>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>

                    <!-- Permission 3: Meetings -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon" style="background:rgba(180,83,9,0.12);color:#B45309;">
                                <i data-lucide="calendar-days" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">جدولة لقاءات وجلسات حوارية</span>
                                <span class="riwaq-switch-tile-desc">إتاحة تنظيم لقاءات ومناقشات دورية لجميع أعضاء المجلس</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="allow_member_meetings" value="1" checked>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>
                </div>

                <button type="submit" class="primary" style="width:100%;padding:12px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;box-shadow:0 4px 14px rgba(23,61,54,0.22);transition:all 0.25s ease;">
                    <i data-lucide="plus-circle" style="width:17px;height:17px;"></i>
                    <span>تأسيس المجلس الآن (+50 أثر)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Community Settings Modal (إعدادات وبيانات المجلس) -->
<div id="community-settings-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:580px;max-height:90vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:19px;">إعدادات وبيانات «{{ $community->name }}»</h2>
                <small class="muted">تحديث بيانات وهوية المجلس وقواعده وصلاحيات الأعضاء.</small>
            </div>
            <button class="close-btn" onclick="closeModal('community-settings-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.updateSettings', $community->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div style="display:grid;gap:14px;">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>اسم المجلس *</span>
                        <input name="name" value="{{ $community->name }}" required>
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>المدينة / المنطقة</span>
                        <input name="city" value="{{ $community->city }}">
                    </label>
                </div>

                <label style="display:grid;gap:6px;">
                    <span>نوع المجلس *</span>
                    <select name="type" required onchange="toggleDomainField(this.value, 'edit-domain-box')">
                        <option value="open" {{ $community->type === 'open' ? 'selected' : '' }}>🌿 مفتوح (ظهور في البحث وانضمام فوري مباشر)</option>
                        <option value="public" {{ $community->type === 'public' ? 'selected' : '' }}>🌐 عام (ظهور في البحث وموافقة الإدارة)</option>
                        <option value="private" {{ $community->type === 'private' ? 'selected' : '' }}>🗝️ خاص ومغلق (مخفي في البحث وبدعوة فقط)</option>
                        <option value="institutional" {{ $community->type === 'institutional' ? 'selected' : '' }}>🏛️ مؤسسي / أكاديمي</option>
                        <option value="specialized" {{ $community->type === 'specialized' ? 'selected' : '' }}>✨ صالون تخصصي</option>
                        <option value="book_of_month" {{ $community->type === 'book_of_month' ? 'selected' : '' }}>📖 نادي كتاب الشهر</option>
                    </select>
                </label>

                <div id="edit-domain-box" style="{{ $community->type === 'institutional' ? '' : 'display:none;' }}background:#F0F8FF;border:1px solid #BEE3F8;border-radius:10px;padding:12px;">
                    <label style="display:grid;gap:6px;">
                        <span style="font-size:12.5px;font-weight:700;color:#2B6CB0;">نطاق البريد المؤسسي / الجامعي:</span>
                        <input name="institutional_domain" value="{{ $community->institutional_domain }}" placeholder="مثال: @ksu.edu.sa">
                    </label>
                </div>

                <label style="display:grid;gap:6px;">
                    <span>وصف المجلس</span>
                    <textarea name="description" rows="3">{{ $community->description }}</textarea>
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>لغة المجلس</span>
                        <input name="language" value="{{ $community->language ?? 'العربية' }}">
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>الحد الأقصى للأعضاء (Capacity)</span>
                        <input name="max_members" type="number" min="2" max="50000" value="{{ $community->max_members }}">
                    </label>
                </div>

                <label style="display:grid;gap:6px;">
                    <span>الاهتمامات والتصنيفات (افصل بفواصل)</span>
                    <input name="interests" value="{{ is_array($community->interests) ? implode(', ', $community->interests) : $community->interests }}">
                </label>

                <label style="display:grid;gap:6px;">
                    <span>المكتبة المرتبطة بالمجلس</span>
                    <select name="linked_library_id">
                        <option value="">-- بدون ربط بمكتبة --</option>
                        @foreach($libraries ?? [] as $lib)
                            <option value="{{ $lib->id }}" {{ $community->linked_library_id == $lib->id ? 'selected' : '' }}>{{ $lib->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label style="display:grid;gap:6px;">
                    <span>رسالة الترحيب بالأعضاء</span>
                    <textarea name="welcome_message" rows="2">{{ $community->welcome_message }}</textarea>
                </label>

                <label style="display:grid;gap:6px;">
                    <span>ميثاق وقواعد المجلس (Community Rules)</span>
                    <textarea name="rules" rows="3">{{ $community->rules }}</textarea>
                </label>

                <div class="riwaq-permission-group">
                    <div class="riwaq-permission-header">
                        <div class="riwaq-permission-title">
                            <i data-lucide="sliders" style="width:16px;height:16px;color:var(--gold);"></i>
                            <span>الصلاحيات وسياسات المجلس</span>
                        </div>
                        <span class="tag" style="background:rgba(23,61,54,0.08);color:var(--green);font-size:10.5px;font-weight:700;">تحكم مباشر</span>
                    </div>
                    <p class="riwaq-permission-subtitle">تعديل مستوى الصلاحيات وسياسات الانضمام والظهور لأعضاء «{{ $community->name }}»:</p>

                    <!-- Setting 1: Requires approval -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon" style="background:rgba(180,83,9,0.12);color:#B45309;">
                                <i data-lucide="user-check" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">طلب موافقة الإدارة قبل الانضمام</span>
                                <span class="riwaq-switch-tile-desc">مراجعة طلبات الانضمام يدوياً من المشرفين قبل قبول أي عضو جديد</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="requires_approval" value="1" {{ $community->requires_approval ? 'checked' : '' }}>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>

                    <!-- Setting 2: Member Invites -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon">
                                <i data-lucide="user-plus" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">السماح للأعضاء بدعوة رفاقهم</span>
                                <span class="riwaq-switch-tile-desc">تمكين الأعضاء من مشاركة الرابط وتوجيه دعوات مباشرة للمجلس</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="allow_member_invites" value="1" {{ $community->allow_member_invites ? 'checked' : '' }}>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>

                    <!-- Setting 3: Challenges -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon" style="background:rgba(35,87,56,0.12);color:var(--green);">
                                <i data-lucide="trophy" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">إنشاء تحديات قراءة للمجلس</span>
                                <span class="riwaq-switch-tile-desc">السماح للأعضاء بإطلاق سباقات ومسارات ختم جماعية للكتب</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="allow_member_challenges" value="1" {{ $community->allow_member_challenges ? 'checked' : '' }}>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>

                    <!-- Setting 4: Meetings -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon" style="background:rgba(180,83,9,0.12);color:#B45309;">
                                <i data-lucide="calendar-days" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">جدولة لقاءات وجلسات حوارية</span>
                                <span class="riwaq-switch-tile-desc">تمكين الأعضاء من تنظيم جلسات نقاشية دورية داخل المجلس</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="allow_member_meetings" value="1" {{ $community->allow_member_meetings ? 'checked' : '' }}>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>

                    <!-- Setting 5: Search Visibility -->
                    <label class="riwaq-switch-tile">
                        <div class="riwaq-switch-tile-content">
                            <div class="riwaq-switch-tile-icon" style="background:rgba(23,61,54,0.1);color:var(--green);">
                                <i data-lucide="compass" style="width:16px;height:16px;"></i>
                            </div>
                            <div class="riwaq-switch-tile-texts">
                                <span class="riwaq-switch-tile-title">الظهور في نتائج البحث العام بالرواق</span>
                                <span class="riwaq-switch-tile-desc">إتاحة ظهور المجلس في دليل المجالس للزوار والمستكشفين</span>
                            </div>
                        </div>
                        <div class="riwaq-toggle-switch">
                            <input type="checkbox" name="is_visible_in_search" value="1" {{ $community->is_visible_in_search ? 'checked' : '' }}>
                            <span class="riwaq-toggle-slider"></span>
                        </div>
                    </label>
                </div>

                <button type="submit" class="primary" style="width:100%;padding:12px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;box-shadow:0 4px 14px rgba(23,61,54,0.22);transition:all 0.25s ease;">
                    <i data-lucide="check" style="width:17px;height:17px;"></i>
                    <span>حفظ التعديلات والإعدادات</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Import Members Modal (استيراد أعضاء دفعة واحدة) -->
<div id="import-members-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:500px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:19px;">استيراد أعضاء إلى «{{ $community->name }}»</h2>
                <small class="muted">أدخل قائمة عناوين البريد الإلكتروني (مفصولة بأسطر جديدة أو فواصل) لإضافتهم للمجلس فوراً.</small>
            </div>
            <button class="close-btn" onclick="closeModal('import-members-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.importMembers', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>عناوين البريد الإلكتروني</span>
                    <textarea name="emails" rows="6" placeholder="reader1@domain.com&#10;reader2@domain.com&#10;reader3@domain.com" required style="font-family:monospace;direction:ltr;font-size:12.5px;"></textarea>
                </label>
                <small class="muted" style="font-size:11.5px;color:#6A604F;">
                    💡 سيتم إلحاق المستخدمين المسجلين بالمجلس تلقائياً وإرسال إشعار ترحيبي رسمي لهم، مع مراعاة السعة القصوى للمجلس.
                </small>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="file-spreadsheet"></i>
                    <span>بدء استيراد وإلحاق الأعضاء</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Create Challenge Modal (إطلاق تحدي قرائي للمجلس) -->
<div id="create-challenge-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:500px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:19px;">إطلاق تحدي قرائي في «{{ $community->name }}» 🎯</h2>
                <small class="muted">حدّد هدفاً جماعياً لتعزيز التزام القراء وتفاعلهم مع الكتب.</small>
            </div>
            <button class="close-btn" onclick="closeModal('create-challenge-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.storeChallenge', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>عنوان التحدي *</span>
                    <input name="title" placeholder="مثال: سباق 5 كتب في 30 يوماً، تحدي الأدب الأندلسي..." required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>وصف التحدي وشروطه</span>
                    <textarea name="description" rows="3" placeholder="وضح قواعد المشاركة ومجالات الكتب المقبولة..." required></textarea>
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>هدف التحدي (عدد الكتب) *</span>
                        <input type="number" name="target_count" min="1" max="100" value="3" required>
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>تاريخ انتهاء التحدي *</span>
                        <input type="date" name="end_date" value="{{ now()->addDays(30)->format('Y-m-d') }}" required>
                    </label>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>التصنيف</span>
                        <input name="category" placeholder="مثال: تنوع معرفي, أدب, سيرة">
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>نقاط الأثر للمنجزين</span>
                        <input type="number" name="reward_points" min="10" max="500" value="50">
                    </label>
                </div>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="trophy"></i>
                    <span>إطلاق التحدي في المجلس الآن</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Create Meeting Modal (جدولة لقاء للمجلس) -->
<div id="create-meeting-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:500px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:19px;">جدولة لقاء جديد في «{{ $community->name }}» 📅</h2>
                <small class="muted">حدّد موعد الجلسة الحوارية القادمة لمناقشة أفكار كتاب الشهر.</small>
            </div>
            <button class="close-btn" onclick="closeModal('create-meeting-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.storeMeeting', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>عنوان اللقاء *</span>
                    <input name="title" placeholder="مثال: نقاش كتاب الشهر، جلسة حوارية مفتوحة..." required>
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>تاريخ ووقت اللقاء *</span>
                        <input type="datetime-local" name="meeting_date" required>
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>طبيعة اللقاء *</span>
                        <select name="meeting_type" required>
                            <option value="remote">💻 عن بُعد (جلسة مرئية)</option>
                            <option value="in_person">📍 حضوري في المجلس</option>
                            <option value="hybrid">🌐 هجين (حضوري وعن بُعد)</option>
                        </select>
                    </label>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label style="display:grid;gap:6px;">
                        <span>المكان / المنصة</span>
                        <input name="location" placeholder="مثال: منصة رِواق، مقهى أدبي...">
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>الحد الأقصى للمقاعد</span>
                        <input type="number" name="capacity" placeholder="مثال: 30 (فارغ لغير محدود)">
                    </label>
                </div>
                <label style="display:grid;gap:6px;">
                    <span>رابط الجلسة المرئية</span>
                    <input name="link" placeholder="مثال: https://meet.riwaq.test/adab">
                </label>
                <label style="display:grid;gap:6px;">
                    <span>محاور ونقاط النقاش</span>
                    <textarea name="description" rows="3" placeholder="اكتب نبذة عن محاور الجلسة والأسئلة المركزية..."></textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="calendar-check"></i>
                    <span>جدولة اللقاء وتثبيته في التقويم</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Community Rules Modal (ميثاق وقواعد المجلس) -->
<div id="community-rules-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:500px;">
        <div class="heading" style="margin-bottom:14px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="book-marked" style="width:20px;height:20px;color:var(--gold);"></i>
                <h2 style="margin:0;font-size:18px;">ميثاق وقواعد «{{ $community->name }}»</h2>
            </div>
            <button class="close-btn" onclick="closeModal('community-rules-modal')">&times;</button>
        </div>
        <div style="background:#FAF8F3;border:1px solid #DFD7C5;border-radius:12px;padding:16px;line-height:1.8;font-size:13.5px;color:#354435;white-space:pre-line;">
            {{ $community->rules ?: "مرحباً بك في مجلسنا المعرفي.\n• الحوار بأدب واحترام متبادل بين جميع القراء.\n• يُمنع حرق أحداث الكتب دون استخدام وسوم الحرق (Spoiler Blur).\n• التركيز على الأفكار والنقد البناء دون الإساءة للأشخاص.\n• الالتزام بمواعيد الجلسات والقراءات المحددة." }}
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
            <button type="button" class="primary btn-sm" onclick="closeModal('community-rules-modal')">
                فهمت وألتزم بالميثاق ✓
            </button>
        </div>
    </section>
</div>

<!-- Join by Code Modal -->
<div id="join-by-code-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:440px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">الانضمام إلى نادٍ عبر الكود</h2>
                <small class="muted">أدخل كود النادي الذي وصلك للانضمام فوراً إلى مجلس القراءة.</small>
            </div>
            <button class="close-btn" onclick="closeModal('join-by-code-modal')">&times;</button>
        </div>

        <form action="{{ route('communities.joinByCode') }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span style="font-weight:600;font-size:13px;">كود النادي</span>
                    <input name="code" placeholder="مثال: ADAB-2026" required style="font-size:16px;letter-spacing:1px;text-transform:uppercase;" autocomplete="off">
                </label>
                <small class="muted" style="display:flex;align-items:center;gap:6px;">
                    <i data-lucide="info" style="width:14px;height:14px;color:var(--gold);"></i>
                    <span>يمكنك الحصول على الكود من دعوة صديق أو من مسؤولي نوادي رِواق.</span>
                </small>
                <button type="submit" class="primary" style="width:100%;margin-top:4px;">
                    <i data-lucide="user-check"></i>
                    <span>الانضمام إلى المجلس الآن (+25 أثر)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Report Content Modal -->
<div id="report-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:460px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h3 style="margin:0;font-size:18px;color:#C0392B;">الإبلاغ عن محتوى مخالف</h3>
                <small class="muted">ساعدنا في الحفاظ على رقي الحوار المعرفي في رِواق.</small>
            </div>
            <button class="close-btn" onclick="closeModal('report-modal')">&times;</button>
        </div>
        <form action="{{ route('content.report') }}" method="POST">
            @csrf
            <input type="hidden" name="reportable_type" id="report-type" value="discussion">
            <input type="hidden" name="reportable_id" id="report-id" value="">
            
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>سبب الإبلاغ</span>
                    <select name="reason" required style="font-size:14px;">
                        <option value="spoiler">حرق أحداث بدون تحذير (Unmarked Spoiler)</option>
                        <option value="inappropriate">محتوى غير لائق أو مخالف للآداب العامة</option>
                        <option value="spam">إعلانات تجارية أو رسائل متكررة (Spam)</option>
                        <option value="disrespect">إساءة شخصية أو تنمر على القراء</option>
                        <option value="other">مخالفة أخرى</option>
                    </select>
                </label>

                <label style="display:grid;gap:6px;">
                    <span>تفاصيل إضافية (اختياري)</span>
                    <textarea name="details" rows="3" placeholder="وضح لمشرفي المجلس سبب انزعاجك من هذه المشاركة..."></textarea>
                </label>

                <button type="submit" class="primary" style="width:100%;background:#C0392B;border-color:#C0392B;">
                    <i data-lucide="flag"></i>
                    <span>إرسال البلاغ إلى المشرفين</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Past Books of the Month Modal (أرشيف كتب الأشهر السابقة) -->
<div id="past-books-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:540px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h3 style="margin:0;font-size:18px;">أرشيف كتب الأشهر السابقة</h3>
                <small class="muted">الكتب التي طالعها وناقشها أعضاء «{{ $community->name }}» عبر الأشهر والسنوات.</small>
            </div>
            <button class="close-btn" onclick="closeModal('past-books-modal')">&times;</button>
        </div>

        <div style="display:grid;gap:12px;max-height:60vh;overflow-y:auto;padding-left:4px;">
            @forelse($booksHistory as $hist)
                <div style="background:#FFFDF9;border:1px solid #DFD5C2;border-radius:10px;padding:12px 14px;display:flex;gap:12px;align-items:center;">
                    <div style="background:#E8DFCB;color:var(--green);padding:6px 10px;border-radius:8px;font-weight:700;font-size:12px;min-width:85px;text-align:center;">
                        {{ $hist->month_name }}
                    </div>
                    <div style="flex:1;">
                        <strong style="font-size:14px;color:var(--ink);display:block;">{{ $hist->book->title ?? 'كتاب' }}</strong>
                        <small class="muted">{{ $hist->book->author ?? '' }}</small>
                        @if($hist->notes)
                            <p style="margin:4px 0 0;font-size:11.5px;color:#666;">{{ $hist->notes }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted" style="text-align:center;padding:20px;">لا توجد كتب مؤرشفة سابقة حتى الآن.</p>
            @endforelse
        </div>
    </section>
</div>

<!-- Nominate Book Modal (ترشيح كتاب للشهر القادم) -->
<div id="nominate-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:500px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h3 style="margin:0;font-size:18px;">ترشيح كتاب للشهر القادم</h3>
                <small class="muted">اقترح كتاباً مميزاً للمطالعة الجماعية والنقاش في المجلس.</small>
            </div>
            <button class="close-btn" onclick="closeModal('nominate-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.storeNomination', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:12px;">
                <label style="display:grid;gap:6px;">
                    <span>عنوان الكتاب</span>
                    <input name="title" placeholder="مثال: البخلاء، طوق الحمامة..." required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>اسم المؤلف</span>
                    <input name="author" placeholder="مثال: الجاحظ، ابن حزم..." required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>لماذا ترشح هذا الكتاب للمجلس؟</span>
                    <textarea name="reason" rows="3" placeholder="وضح القيمة الفكرية أو الأدبية أو محاور النقاش التي يقدمها الكتاب..." required></textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="send"></i>
                    <span>إرسال الترشيح وإدراجه لمقترحات المجلس (+10 أثر)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Create Community Badge Modal (استحداث وسام شرفي للمجلس) -->
<div id="create-badge-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:480px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h3 style="margin:0;font-size:18px;">استحداث وسام شرفي للمجلس</h3>
                <small class="muted">خصّص وساماً جديداً يُمنح لأعضاء «{{ $community->name }}» تقديراً لإسهاماتهم.</small>
            </div>
            <button class="close-btn" onclick="closeModal('create-badge-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.storeBadge', $community->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:12px;">
                <label style="display:grid;gap:6px;">
                    <span>اسم الوسام</span>
                    <input name="name" placeholder="مثال: وسام أديب المجلس، رفيق القراءة الصامتة..." required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>وصف الوسام ومبرر منحه</span>
                    <textarea name="description" rows="3" placeholder="مثال: يُمنح للقراء الأكثر إثراءً للنقاشات النقدية والتأملات البلاغية..." required></textarea>
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:6px;">
                        <span>الأيقونة</span>
                        <select name="icon">
                            <option value="crown">تاج (Crown) 👑</option>
                            <option value="sparkles">بريق (Sparkles) ✨</option>
                            <option value="coffee">قهوة وقراءة (Coffee) ☕</option>
                            <option value="award">وسام شرفي (Award) 🏅</option>
                        </select>
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span>اللون</span>
                        <select name="color">
                            <option value="#C3A15D">ذهبي تراثي</option>
                            <option value="#235738">أخضر رِواقي</option>
                            <option value="#785419">عنبري دافئ</option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="plus-circle"></i>
                    <span>اعتماد واستحداث الوسام في المجلس</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Award Community Badge Modal (تقليد عضو وسام المجلس) -->
<div id="award-badge-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:480px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h3 style="margin:0;font-size:18px;">تقليد عضو وسام المجلس</h3>
                <small class="muted">اختر العضو والوسام مع كتابة حيثيات التكريم وإشعاره فورياً.</small>
            </div>
            <button class="close-btn" onclick="closeModal('award-badge-modal')">&times;</button>
        </div>
        <form id="award-badge-form" method="POST" action="">
            @csrf
            <div style="display:grid;gap:12px;">
                <label style="display:grid;gap:6px;">
                    <span>اختر الوسام الشرفي</span>
                    <select id="award-badge-select" onchange="document.getElementById('award-badge-form').action='/communities/badges/' + this.value + '/award'" required>
                        <option value="">-- اختر الوسام --</option>
                        @foreach($communityBadges as $cb)
                            <option value="{{ $cb->id }}">{{ $cb->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>اختر العضو المُكرَّم</span>
                    <select name="user_id" required>
                        <option value="">-- اختر العضو من المجلس --</option>
                        @foreach($community->members as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                        @endforeach
                    </select>
                </label>
                <label style="display:grid;gap:6px;">
                    <span>سبب وحيثيات التكريم</span>
                    <textarea name="reason" rows="3" placeholder="اكتب مبررات التتويج وما قدمه القارئ من إسهام للمجلس..." required></textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="crown"></i>
                    <span>تتويج القارئ بالوسام الشرفي (+50 أثر)</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Transfer Community Ownership Modal -->
@if(Auth::check() && $community->isOwner(Auth::user()))
<div id="transfer-community-ownership-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:480px;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h3 style="margin:0;font-size:18px;color:#8F3829;">نقل ملكية المجلس القرائي</h3>
                <small class="muted">إجراء حساس: نقل السيطرة الكاملة على «{{ $community->name }}» إلى عضو آخر مع توثيقه في سجل التدقيق.</small>
            </div>
            <button class="close-btn" onclick="closeModal('transfer-community-ownership-modal')">&times;</button>
        </div>
        <form action="{{ route('communities.transferOwnership', $community->id) }}" method="POST" onsubmit="return confirm('تنبيه أمني: هل أنت متأكد تماماً من التنازل عن ملكية المجلس؟ لن يعود بإمكانك استرجاع الملكية إلا بتصرف المالك الجديد.');">
            @csrf
            <div style="display:grid;gap:12px;">
                <label style="display:grid;gap:6px;">
                    <span>اختر المالك الجديد للمجلس:</span>
                    <select name="new_owner_id" required style="padding:8px 12px;border-radius:8px;border:1px solid #D5C8B2;background:#FFFDF8;">
                        <option value="">-- اختر من أعضاء المجلس --</option>
                        @foreach($community->members as $m)
                            @if($m->id !== Auth::id())
                                <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;cursor:pointer;background:#FAF6EE;padding:10px;border-radius:8px;border:1px solid #D5C8B2;">
                    <input type="checkbox" name="confirm" value="1" required>
                    <span>أقرّ وأوافق صراحةً على نقل ملكية المجلس وتنازلي عنها للمستخدم المختار.</span>
                </label>
                <button type="submit" class="primary" style="background:#8F3829;border-color:#8F3829;width:100%;">
                    <i data-lucide="shield-alert"></i>
                    <span>تأكيد نقل الملكية نهائياً</span>
                </button>
            </div>
        </form>
    </section>
</div>
@endif
@endsection

@push('scripts')
<script>
let silentTotalSeconds = 45 * 60;
let silentSecondsLeft = silentTotalSeconds;
let silentInterval = null;
let silentPhase = 'reading';
let audioCtx = null;
let ambientOsc = null;
let ambientGain = null;
let isAudioPlaying = false;

function updateSilentDisplay() {
    const mins = Math.floor(silentSecondsLeft / 60);
    const secs = silentSecondsLeft % 60;
    const disp = document.getElementById('silent-timer-display');
    if (disp) {
        disp.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
}

function setSilentPhase(phase) {
    silentPhase = phase;
    clearInterval(silentInterval);
    silentInterval = null;

    const playText = document.getElementById('silent-play-text');
    const playIcon = document.getElementById('silent-play-icon');
    if (playText) playText.textContent = phase === 'reading' ? 'بدء القراءة الصامتة' : 'بدء التأمل والنقاش';
    if (playIcon) playIcon.setAttribute('data-lucide', 'play');

    document.querySelectorAll('.phase-btn').forEach(b => b.classList.remove('active'));

    if (phase === 'reading') {
        silentTotalSeconds = 45 * 60;
        document.getElementById('phase-read-btn')?.classList.add('active');
    } else {
        silentTotalSeconds = 15 * 60;
        document.getElementById('phase-reflect-btn')?.classList.add('active');
    }
    silentSecondsLeft = silentTotalSeconds;
    updateSilentDisplay();
    if (window.lucide) lucide.createIcons();
}

function toggleSilentTimer() {
    const playText = document.getElementById('silent-play-text');
    const playIcon = document.getElementById('silent-play-icon');
    const timerDisplay = document.getElementById('silent-timer-display');

    if (silentInterval) {
        clearInterval(silentInterval);
        silentInterval = null;
        if (playText) playText.textContent = 'متابعة الجلسة';
        if (playIcon) playIcon.setAttribute('data-lucide', 'play');
        timerDisplay?.classList.remove('timer-breathe-glow');
    } else {
        if (playText) playText.textContent = 'إيقاف مؤقت';
        if (playIcon) playIcon.setAttribute('data-lucide', 'pause');
        timerDisplay?.classList.add('timer-breathe-glow');

        silentInterval = setInterval(() => {
            if (silentSecondsLeft > 0) {
                silentSecondsLeft--;
                updateSilentDisplay();
            } else {
                clearInterval(silentInterval);
                silentInterval = null;
                timerDisplay?.classList.remove('timer-breathe-glow');
                playChime();
                if (silentPhase === 'reading') {
                    alert('انتهت جلسة القراءة الصامتة (45 دقيقة)! حان وقت الاستراحة وتدوين الخواطر (15 دقيقة).');
                    setSilentPhase('reflection');
                } else {
                    alert('انتهت فترة التأمل والنقاش بنجاح! تم توثيق التزامك في رِواق.');
                    setSilentPhase('reading');
                }
            }
        }, 1000);
    }
    if (window.lucide) lucide.createIcons();
}

function resetSilentTimer() {
    clearInterval(silentInterval);
    silentInterval = null;
    silentSecondsLeft = silentTotalSeconds;
    updateSilentDisplay();
    document.getElementById('silent-timer-display')?.classList.remove('timer-breathe-glow');
    const playText = document.getElementById('silent-play-text');
    const playIcon = document.getElementById('silent-play-icon');
    if (playText) playText.textContent = silentPhase === 'reading' ? 'بدء القراءة الصامتة' : 'بدء التأمل والنقاش';
    if (playIcon) playIcon.setAttribute('data-lucide', 'play');
    if (window.lucide) lucide.createIcons();
}

function toggleAmbientSound() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }

    const soundBtn = document.getElementById('silent-sound-btn');
    const soundLabel = document.getElementById('sound-label');

    if (isAudioPlaying) {
        if (ambientGain) {
            ambientGain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 1);
            setTimeout(() => {
                ambientOsc?.stop();
                ambientOsc?.disconnect();
            }, 1000);
        }
        isAudioPlaying = false;
        if (soundLabel) soundLabel.textContent = 'صوت أثيري';
        soundBtn?.classList.remove('active', 'audio-playing');
    } else {
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        ambientOsc = audioCtx.createOscillator();
        ambientGain = audioCtx.createGain();

        // Soothing harmonic drone
        ambientOsc.type = 'sine';
        ambientOsc.frequency.setValueAtTime(174, audioCtx.currentTime);

        ambientGain.gain.setValueAtTime(0.0001, audioCtx.currentTime);
        ambientGain.gain.exponentialRampToValueAtTime(0.04, audioCtx.currentTime + 1.5);

        ambientOsc.connect(ambientGain);
        ambientGain.connect(audioCtx.destination);
        ambientOsc.start();

        isAudioPlaying = true;
        if (soundLabel) soundLabel.textContent = 'إيقاف الصوت';
        soundBtn?.classList.add('active', 'audio-playing');
    }
}

function playChime() {
    try {
        const chimeCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = chimeCtx.createOscillator();
        const gain = chimeCtx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(528, chimeCtx.currentTime);
        gain.gain.setValueAtTime(0.15, chimeCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, chimeCtx.currentTime + 3);
        osc.connect(gain);
        gain.connect(chimeCtx.destination);
        osc.start();
        osc.stop(chimeCtx.currentTime + 3);
    } catch(e) {}
}

function saveSilentNote() {
    const input = document.getElementById('silent-note-input');
    if (!input || !input.value.trim()) {
        alert('يرجى كتابة خاطرة أولاً.');
        return;
    }
    showToast('تم حفظ خاطرتك في الجلسة الصامتة بنجاح، وستظهر لرفقاء الغرفة!');
    input.value = '';
}

function reactHighlight(btn, reflectionId) {
    @guest
        showToast('يرجى تسجيل الدخول أولاً لترك أثر.', 'warning');
        return;
    @endguest

    if (btn.disabled) return;
    btn.disabled = true;

    const cntEl = btn.querySelector('.react-cnt');
    btn.style.transform = 'scale(1.2)';
    setTimeout(() => { btn.style.transform = 'scale(1)'; }, 200);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    fetch(`/reflections/${reflectionId}/like`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(res => {
        if (!res.ok) {
            if (res.status === 401) {
                window.location.href = '{{ route("login") }}';
                throw new Error('Unauthenticated');
            }
            throw new Error('Server error');
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            if (cntEl) {
                cntEl.textContent = data.likes_count;
            }
            if (data.is_liked) {
                btn.classList.add('is-liked');
                btn.title = 'إلغاء الأثر';
                showToast(data.message || 'تم ترك الأثر بنجاح ✦', 'success');
            } else {
                btn.classList.remove('is-liked');
                btn.title = 'ترك أثر (إعجاب)';
                showToast(data.message || 'تم سحب الأثر.', 'info');
            }
        }
    })
    .catch(err => {
        console.error(err);
        showToast('تعذر تحديث الأثر، يرجى المحاولة مرة أخرى.', 'error');
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function toggleHighlightSpoiler(el) {
    const parent = el.closest('.highlight-spoiler-box');
    if (!parent) return;
    const blurEl = parent.querySelector('.spoiler-blur');
    const promptText = parent.querySelector('.spoiler-action-text');
    if (blurEl) {
        blurEl.classList.toggle('revealed');
        const isRevealed = blurEl.classList.contains('revealed');
        if (promptText) {
            promptText.textContent = isRevealed ? 'إخفاء الاقتباس (إعادة تعتيم الحرق)' : 'انقر لكشف الاقتباس (حرق أحداث)';
        }
        if (window.lucide) lucide.createIcons();
    }
}

function reactDiscussion(btn) {
    const cntEl = btn.querySelector('.disc-cnt');
    if (cntEl) {
        let cur = parseInt(cntEl.textContent, 10) || 0;
        cntEl.textContent = cur + 1;
        btn.style.transform = 'scale(1.15)';
        btn.style.borderColor = 'var(--gold)';
        btn.style.color = 'var(--green)';
        setTimeout(() => {
            btn.style.transform = 'scale(1)';
        }, 300);
    }
}

function toggleDiscussionComments(discId) {
    const box = document.getElementById('comments-box-' + discId);
    if (!box) return;
    if (box.style.display === 'none' || !box.style.display) {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

function setReplyParent(discId, commentId, authorName) {
    const parentInput = document.getElementById('reply-parent-' + discId);
    const banner = document.getElementById('replying-to-banner-' + discId);
    const textEl = document.getElementById('replying-to-text-' + discId);
    const commentInput = document.getElementById('comment-input-' + discId);

    if (parentInput) parentInput.value = commentId;
    if (textEl) textEl.textContent = 'الرد على: ' + authorName;
    if (banner) banner.style.display = 'flex';
    if (commentInput) {
        commentInput.focus();
        commentInput.placeholder = 'اكتب ردك على ' + authorName + '...';
    }
}

function cancelReplyParent(discId) {
    const parentInput = document.getElementById('reply-parent-' + discId);
    const banner = document.getElementById('replying-to-banner-' + discId);
    const commentInput = document.getElementById('comment-input-' + discId);

    if (parentInput) parentInput.value = '';
    if (banner) banner.style.display = 'none';
    if (commentInput) {
        commentInput.placeholder = 'اكتب تعليقك أو إضافتك على هذا النقاش...';
    }
}

function openReportModal(type, id) {
    const typeEl = document.getElementById('report-type');
    const idEl = document.getElementById('report-id');
    if (typeEl) typeEl.value = type;
    if (idEl) idEl.value = id;
    if (typeof window.openModal === 'function') {
        window.openModal('report-modal');
    } else {
        const modal = document.getElementById('report-modal');
        if (modal) modal.style.display = 'flex';
    }
}

function toggleDomainField(type, elementId) {
    const el = document.getElementById(elementId);
    if (el) {
        el.style.display = (type === 'institutional') ? 'block' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        lucide.createIcons();
    }
});
</script>
@endpush

@push('scripts')
<script>
(function () {
    const tabs = document.querySelectorAll('#cm-tabs [data-tab]');
    const panels = document.querySelectorAll('[data-tab-panel]');
    if (!tabs.length) return;
    function show(name) {
        tabs.forEach(t => t.classList.toggle('on', t.dataset.tab === name));
        panels.forEach(p => p.classList.toggle('on', p.dataset.tabPanel === name));
        try { sessionStorage.setItem('cm-tab', name); } catch (e) {}
        if (location.hash !== '#' + name) history.replaceState(null, '', '#' + name);
    }
    window.switchCommunityTab = show;
    tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.tab)));
    const valid = Array.from(tabs).map(t => t.dataset.tab);
    let hash = location.hash.replace('#', '');
    if (hash === 'pending-requests' || hash === 'requests') {
        show('overview');
        setTimeout(() => {
            const reqEl = document.getElementById('pending-requests');
            if (reqEl) {
                reqEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                reqEl.style.transition = 'all 0.5s ease';
                reqEl.style.boxShadow = '0 0 0 4px rgba(217,160,82,0.6)';
                setTimeout(() => { reqEl.style.boxShadow = ''; }, 3500);
            }
        }, 150);
    } else {
        let start = hash;
        if (!valid.includes(start)) { try { start = sessionStorage.getItem('cm-tab'); } catch (e) { start = null; } }
        if (valid.includes(start)) show(start);
    }
})();
</script>
@endpush
