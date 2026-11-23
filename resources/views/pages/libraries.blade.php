@extends('layouts.app')

@section('title', 'مكتباتي')
@section('crumb', 'مكتباتي والنسخ المادية')

@section('content')
<div class="lb-head">
    <div>
        <h1>مكتباتي</h1>
        <p>أضف مكتباتك وكتبك، وحدّد إن كانت كل مكتبة خاصة بك أو عامة للجميع.</p>
    </div>
    <div class="lb-head-actions">
        <button type="button" class="rq-btn rq-btn-ghost" onclick="openModal('join-library-modal')">
            <i data-lucide="log-in"></i><span>الانضمام بكود</span>
        </button>
        <button type="button" class="rq-btn rq-btn-primary" onclick="openModal('create-library-modal')">
            <i data-lucide="plus"></i><span>مكتبة جديدة</span>
        </button>
    </div>
</div>

{{-- بطاقات المكتبات --}}
<div class="lb-cards">
    @foreach($allAccessibleLibraries as $lib)
        @php $isPublicLib = $lib->type === 'public'; @endphp
        <a href="{{ route('libraries.index', ['library_id' => $lib->id]) }}"
           class="lb-card {{ $library->id === $lib->id ? 'active' : '' }}"
           style="--lib: {{ $lib->cover_color ?? '#173D36' }};">
            <span class="lb-card-ico"><i data-lucide="{{ $isPublicLib ? 'globe' : 'lock' }}"></i></span>
            <span class="lb-card-body">
                <b>{{ $lib->name }}</b>
                <small>{{ $lib->copies()->count() }} كتاب · {{ $isPublicLib ? 'عامة' : 'خاصة' }}</small>
            </span>
        </a>
    @endforeach
    <button type="button" class="lb-card lb-card-new" onclick="openModal('create-library-modal')">
        <span class="lb-card-ico"><i data-lucide="plus"></i></span>
        <span class="lb-card-body"><b>مكتبة جديدة</b><small>خاصة أو عامة</small></span>
    </button>
</div>

{{-- ملخص المكتبة الحالية --}}
<section class="lb-active" style="--lib: {{ $library->cover_color ?? '#173D36' }};">
    <div class="lb-active-main">
        <div class="lb-active-ico"><i data-lucide="library-big"></i></div>
        <div>
            <div class="lb-badges">
                <span class="lb-badge {{ $library->type === 'public' ? 'pub' : 'prv' }}">
                    <i data-lucide="{{ $library->type === 'public' ? 'globe' : 'lock' }}"></i>
                    {{ $library->type === 'public' ? 'مكتبة عامة' : 'مكتبة خاصة' }}
                </span>
                <span class="lb-badge">{{ match($userRole) { 'owner' => 'أنت المالك', 'librarian' => 'أمين المكتبة', 'member' => 'عضو', default => 'قارئ' } }}</span>
                @if($library->code)
                    <button type="button" class="lb-badge lb-code" title="انقر لنسخ كود الانضمام"
                        onclick="navigator.clipboard.writeText('{{ $library->code }}'); this.querySelector('span').textContent='تم النسخ';">
                        <i data-lucide="key"></i><span>{{ $library->code }}</span>
                    </button>
                @endif
            </div>
            <h2>{{ $library->name }}</h2>
            @if($library->description)<p>{{ $library->description }}</p>@endif
        </div>
    </div>

    <div class="lb-stats">
        <div><b>{{ $totalTitles }}</b><span>عنوان</span></div>
        <div><b>{{ $totalCopies }}</b><span>نسخة</span></div>
        @if($bookLendingEnabled)
            <div><b>{{ $availableCopiesCount }}</b><span>متاحة</span></div>
            <div><b>{{ $activeLoansCount }}</b><span>إعارة جارية</span></div>
        @endif
        <div><b>{{ $members->count() }}</b><span>عضو</span></div>
    </div>

    <div class="lb-active-actions">
        @if($canManage)
            <button class="rq-btn rq-btn-gold" onclick="openAddBookModal({{ $library->id }})">
                <i data-lucide="plus"></i><span>إضافة كتاب</span>
            </button>
        @endif
        @can('update', $library)
            <button type="button" class="lb-gear" onclick="openModal('library-settings-modal')" title="إعدادات المكتبة" aria-label="إعدادات المكتبة">
                <i data-lucide="settings"></i>
            </button>
        @endcan
    </div>
</section>

<div class="ch-toolbar">
    <div class="rq-tabs" role="tablist">
        <a href="{{ route('libraries.index', ['library_id' => $library->id, 'tab' => 'books']) }}" class="{{ $tab === 'books' ? 'on' : '' }}"><i data-lucide="book-marked"></i><span>رفوف الكتب والنسخ المادية ({{ $totalCopies }})</span></a>
        @if($bookLendingEnabled)
            <a href="{{ route('libraries.index', ['library_id' => $library->id, 'tab' => 'loans']) }}" class="{{ $tab === 'loans' ? 'on' : '' }}">
                <i data-lucide="repeat"></i><span>طلبات الاستعارة وسجل الإعارات</span>
                @if($activeLoansCount > 0)<span class="tab-counter">{{ $activeLoansCount }}</span>@endif
                @if($overdueLoansCount > 0)<span class="tab-counter counter-unread" title="{{ $overdueLoansCount }} إعارات متأخرة">!</span>@endif
            </a>
            <a href="{{ route('libraries.index', ['library_id' => $library->id, 'tab' => 'waitlists']) }}" class="{{ $tab === 'waitlists' ? 'on' : '' }}">
                <i data-lucide="clock"></i><span>قوائم الانتظار</span>
                @if($waitlists->where('status', 'waiting')->count() > 0)<span class="tab-counter">{{ $waitlists->where('status', 'waiting')->count() }}</span>@endif
            </a>
        @endif
        <a href="{{ route('libraries.index', ['library_id' => $library->id, 'tab' => 'members']) }}" class="{{ $tab === 'members' ? 'on' : '' }}"><i data-lucide="users"></i><span>الأعضاء والأدوار ({{ $members->count() }})</span></a>
        <a href="{{ route('libraries.index', ['library_id' => $library->id, 'tab' => 'all_libraries']) }}" class="{{ $tab === 'all_libraries' ? 'on' : '' }}"><i data-lucide="layers"></i><span>كافة مكتباتي ({{ $allAccessibleLibraries->count() }})</span></a>
    </div>
</div>

@if($tab === 'books')
    @php
        $condLabels = ['mint' => 'ممتازة', 'good' => 'جيدة', 'fair' => 'مقبولة', 'damaged' => 'صيانة'];
        $baseParams = array_filter(['library_id' => $library->is_personal ? null : $library->id, 'tab' => 'books']);
    @endphp

    <form method="GET" action="{{ route('libraries.index') }}" class="lb-filters">
        <input type="hidden" name="tab" value="books">
        @if(! $library->is_personal)<input type="hidden" name="library_id" value="{{ $library->id }}">@endif
        <div class="ex-search">
            <i data-lucide="search"></i>
            <input type="text" class="search" name="q" value="{{ $query }}" placeholder="ابحث في رفوف «{{ $library->name }}»…" aria-label="البحث في رفوف المكتبة">
        </div>
        <label class="cm-type"><span>التصنيف</span>
            <select name="category" onchange="this.form.submit()">
                @foreach($categories as $cat)<option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>@endforeach
            </select>
        </label>
        <label class="cm-type"><span>الحالة</span>
            <select name="condition" onchange="this.form.submit()">
                @foreach($conditionsList as $cond)<option value="{{ $cond }}" {{ $conditionFilter === $cond ? 'selected' : '' }}>{{ $condLabels[$cond] ?? 'الكل' }}</option>@endforeach
            </select>
        </label>
        <button type="submit" class="rq-btn rq-btn-primary rq-btn-sm">بحث</button>
    </form>

    <div class="ex-grid libraries-shelf-grid">
        @forelse($copies as $copy)
            @php
                $b = $copy->book;
                $effectiveCover = $copy->cover_image ?: ($b->cover_image ?? null);
                $coverUrl = $effectiveCover ? (str_starts_with($effectiveCover, 'http') || str_starts_with($effectiveCover, 'data:') ? $effectiveCover : asset($effectiveCover)) : '';
                $hasActiveLoanForBook = in_array($b->id, $userActiveLoanedBookIds ?? []);
                $isUserWaiting = in_array($b->id, $userActiveWaitlists ?? []);
                $isNotifiedForThisBook = in_array($b->id, $userNotifiedWaitlists ?? []);
                $isOwnLibrary = $library->user_id === Auth::id();
            @endphp
            <article class="ex-card lb-copy">
                <button type="button" class="ex-cover-btn open-book-btn" onclick="openBookFromCard(this)" aria-label="تفاصيل «{{ $b->title }}»"
                    data-id="{{ $b->id }}"
                    data-title="{{ $b->title }}"
                    data-author="{{ $b->author }}"
                    data-category="{{ $b->category }}"
                    data-pages="{{ $b->total_pages }}"
                    data-color="{{ $b->cover_color }}"
                    data-cover="{{ $coverUrl }}"
                    data-desc="{{ $b->description }}"
                    data-copies="1"
                    data-loan-url="{{ ($hasActiveLoanForBook || $isOwnLibrary) ? '' : route('libraries.requestLoan', $copy->id) }}"
                    data-is-borrowed="{{ $hasActiveLoanForBook ? '1' : '0' }}"
                    data-in-library="{{ (Auth::check() && Auth::user()->hasBookInPersonalLibrary($b)) ? '1' : '0' }}">
                    <div class="bk-cover" style="--c: {{ $b->cover_color ?: '#315749' }};">
                        <span class="bk-frame"></span>
                        <span class="bk-knot">✦</span>
                        <strong>{{ $b->title }}</strong>
                        <small>{{ $b->author }}</small>
                        <span class="bk-brand">رِواق المعرفة</span>
                        @if($coverUrl)<img src="{{ $coverUrl }}" alt="غلاف {{ $b->title }}" loading="lazy">@endif
                    </div>
                </button>

                <div class="ex-card-body">
                    <h3>{{ $b->title }}</h3>
                    <p>{{ $b->author }}</p>
                    <div class="ex-tags">
                        @if($bookLendingEnabled)
                            @if($copy->status === 'available')
                                <span class="rq-chip green">{{ $hasActiveLoanForBook ? 'مستعار لك' : 'متاح للإعارة' }}</span>
                            @elseif($copy->status === 'borrowed')
                                <span class="rq-chip">معارة حالياً</span>
                            @elseif($copy->status === 'reserved')
                                <span class="rq-chip">محجوزة</span>
                            @elseif($copy->status === 'maintenance')
                                <span class="rq-chip">صيانة وترميم</span>
                            @else
                                <span class="rq-chip">{{ $copy->status }}</span>
                            @endif
                        @else
                            <span class="rq-chip green">نسخة بالرف</span>
                        @endif
                        <span class="rq-chip" title="{{ $copy->condition_notes ?? $copy->condition_label }}">{{ $copy->condition_label }}</span>
                    </div>
                    <small class="lb-shelf"><i data-lucide="archive"></i>{{ $copy->shelf_location ?? 'الرف الأول' }} · {{ $copy->barcode ?? 'WQ-01' }}</small>
                </div>

                <div class="lb-copy-actions">
                    @if($bookLendingEnabled && ! $isOwnLibrary)
                        @if($copy->status === 'available' && ! $hasActiveLoanForBook)
                            <button type="button" class="rq-btn rq-btn-primary rq-btn-sm open-loan-btn" onclick="openLoanModal({{ $copy->id }}, '{{ addslashes($b->title) }}')"><i data-lucide="book-copy"></i><span>طلب استعارة النسخة</span></button>
                        @elseif($copy->status === 'available')
                            <button type="button" class="rq-btn rq-btn-ghost rq-btn-sm" disabled><i data-lucide="check"></i><span>مستعار لك</span></button>
                        @elseif($copy->status === 'reserved' && $isNotifiedForThisBook)
                            <button type="button" class="rq-btn rq-btn-gold rq-btn-sm open-loan-btn" onclick="openLoanModal({{ $copy->id }}, '{{ addslashes($b->title) }}')"><i data-lucide="sparkles"></i><span>استعارة (دورك بالانتظار!)</span></button>
                        @elseif(in_array($copy->status, ['borrowed', 'reserved']))
                            @if($isUserWaiting)
                                <button type="button" class="rq-btn rq-btn-ghost rq-btn-sm" disabled><i data-lucide="clock"></i><span>أنت بقائمة الانتظار</span></button>
                            @else
                                <form method="POST" action="{{ route('libraries.joinWaitlist', [$library->id, $b->id]) }}">
                                    @csrf
                                    <button type="submit" class="rq-btn rq-btn-gold rq-btn-sm"><i data-lucide="clock"></i><span>قائمة الانتظار</span></button>
                                </form>
                            @endif
                        @else
                            <button type="button" class="rq-btn rq-btn-ghost rq-btn-sm" disabled><span>{{ $copy->status_label }}</span></button>
                        @endif
                    @endif

                    <details class="cm-more lb-more">
                        <summary class="rq-btn rq-btn-ghost rq-btn-sm" aria-label="المزيد"><i data-lucide="ellipsis"></i></summary>
                        <div class="cm-more-menu">
                            <button type="button" onclick="openPassportModal({{ $copy->id }}, '{{ addslashes($b->title) }}', '{{ $copy->barcode }}', '{{ $copy->readers_count }}', '{{ addslashes($copy->cities_traveled ?? 'الرياض') }}', `{{ addslashes($copy->margin_notes ?? '') }}`)"><i data-lucide="stamp"></i>جواز النسخة</button>
                            @if($canManage)
                                <button type="button" onclick="openUpdateConditionModal({{ $copy->id }}, '{{ $copy->condition }}', '{{ $copy->status }}', '{{ addslashes($copy->shelf_location ?? '') }}', `{{ addslashes($copy->condition_notes ?? '') }}`)"><i data-lucide="sliders-horizontal"></i>فحص الحالة وتعديل الرف</button>
                                @if($copy->status !== 'borrowed')
                                    <form method="POST" action="{{ route('libraries.destroyCopy', $copy->id) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذه النسخة من المكتبة؟')" class="copy-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="danger"><i data-lucide="trash-2"></i>حذف النسخة</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </details>
                </div>
            </article>
        @empty
            <div class="ex-empty">
                <i data-lucide="book-x"></i>
                <h3>لا توجد كتب مطابقة في هذا الرف بعد</h3>
                <p>يمكنك إضافة كتب ورقية جديدة لمكتبة «{{ $library->name }}».</p>
                @if($canManage)
                    <button class="rq-btn rq-btn-primary rq-btn-sm" onclick="openAddBookModal({{ $library->id }})"><i data-lucide="plus"></i><span>إضافة كتاب جديد للرف</span></button>
                @endif
            </div>
        @endforelse
    </div>


<!-- ==========================================
     TAB 2: سجل الإعارات والطلبات والتمديد
     ========================================== -->
@elseif($tab === 'loans')
    <div class="panel animate-fade-in" style="background:#FFFDF9;border:1px solid #E4DAC4;border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid #ECE4D4;padding-bottom:12px;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(195,161,93,0.15);color:#8C6D2C;display:grid;place-items:center;">
                    <i data-lucide="repeat" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h2 style="margin:0;font-size:18px;">سجل الإعارات والطلبات في «{{ $library->name }}»</h2>
                    <small class="muted">متابعة تواريخ الاستحقاق، التمديد، فحص النسخ المسترجعة، ومصافحة QR Handshake</small>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="tag" style="background:rgba(35,87,56,0.1);color:var(--green);font-weight:700;">{{ $loans->count() }} إعارات</span>
                @if($overdueLoansCount > 0)
                    <span class="tag" style="background:#FDE8E4;color:#B3261E;font-weight:700;">{{ $overdueLoansCount }} متأخرة</span>
                @endif
            </div>
        </div>

        <div style="display:grid;gap:14px;">
            @forelse($loans as $loan)
                @php
                    $isBorrower = $loan->user_id === Auth::id();
                    $isOverdue = $loan->isOverdue();
                    $isDueSoon = $loan->isDueSoon();
                    $daysRemaining = $loan->daysRemaining();
                @endphp
                <div class="loan-card-spring" style="display:flex;gap:16px;align-items:center;padding:16px;background:#FAF7F0;border:1px solid {{ $isOverdue ? '#F7B8B0' : '#E8DFCC' }};border-radius:12px;flex-wrap:wrap;transition:all 0.25s ease;">
                    <div style="background:{{ $isOverdue ? '#B3261E' : 'var(--green)' }};color:#FFE2A3;padding:10px;border-radius:10px;display:flex;box-shadow:0 2px 8px rgba(23,61,54,0.15);">
                        <i data-lucide="book-copy" style="width:20px;height:20px;"></i>
                    </div>

                    <div style="flex:1;min-width:220px;">
                        <h3 style="margin:0 0 3px;font-size:15px;color:var(--ink);font-family:'Noto Naskh Arabic',serif;">
                            {{ $loan->copy->book->title ?? 'كتاب' }}
                        </h3>
                        <small class="muted" style="display:block;font-size:12px;">
                            المستعير: <b style="color:var(--green);">{{ $loan->borrower_name ?? $loan->user->name }}</b> 
                            · رمز النسخة: <span class="barcode-laser-box" style="padding:1px 6px;background:#FFFDF8;border-radius:4px;border:1px solid #E0D5C0;"><span class="barcode-laser-sweep"></span>{{ $loan->copy->barcode ?? 'WQ-01' }}</span>
                            · حالة النسخة الحالية: <b>{{ $loan->copy->condition_label }}</b>
                        </small>

                        <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;align-items:center;">
                            <!-- Due Date & Countdown Badge -->
                            @if($loan->due_date && $loan->status === 'active')
                                @if($isOverdue)
                                    <span class="due-badge due-badge-overdue">
                                        <i data-lucide="alert-triangle" style="width:11px;height:11px;"></i>
                                        <span>تجاوز الاستحقاق بـ {{ abs($daysRemaining) }} يوم ({{ $loan->due_date->format('Y-m-d') }})</span>
                                    </span>
                                @elseif($isDueSoon)
                                    <span class="due-badge due-badge-soon">
                                        <i data-lucide="clock" style="width:11px;height:11px;"></i>
                                        <span>يستحق قريباً: متبقي {{ $daysRemaining }} يوم ({{ $loan->due_date->format('Y-m-d') }})</span>
                                    </span>
                                @else
                                    <span class="due-badge due-badge-normal">
                                        <i data-lucide="calendar" style="width:11px;height:11px;"></i>
                                        <span>تاريخ الإعادة: {{ $loan->due_date->format('Y-m-d') }} (متبقي {{ $daysRemaining }} يوم)</span>
                                    </span>
                                @endif

                                <span class="tag" style="background:#F0EBE1;color:#6B5F4A;font-size:11px;">
                                    التمديد: {{ $loan->renewal_count }}/{{ $loan->max_renewals ?? 2 }}
                                </span>
                            @endif

                            @if($loan->swap_book_title)
                                <span class="tag" style="background:#EBF3ED;color:#1B4D3E;font-size:11px;font-weight:600;">
                                    <i data-lucide="repeat" style="width:11px;height:11px;"></i> مبادلة مقترحة: {{ $loan->swap_book_title }}
                                </span>
                            @endif

                            @if($loan->handshake_at)
                                <span class="tag" style="background:#FFF3D6;color:#785419;font-size:11px;font-weight:600;">
                                    <i data-lucide="shield-check" style="width:11px;height:11px;"></i> موثق بـ QR Handshake ✦
                                </span>
                            @endif

                            @if($loan->return_condition)
                                <span class="tag" style="background:#EBF3ED;color:#1B4D3E;font-size:11px;">
                                    حالة الإرجاع: {{ $loan->return_condition }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Status Tag -->
                    <div>
                        @if($loan->status === 'pending')
                            <span class="tag status-badge-pulse status-reserved">طلب قيد المراجعة ⏳</span>
                        @elseif($loan->status === 'approved')
                            <span class="tag" style="background:rgba(195,161,93,0.18);color:#8C6D2C;font-weight:700;">تمت الموافقة · جاهز للتسليم ✦</span>
                        @elseif($loan->status === 'active')
                            <span class="tag status-badge-pulse status-available">إعارة نشطة 🌿</span>
                        @elseif($loan->status === 'returned')
                            <span class="tag status-badge-pulse status-borrowed">مُسترجع · قيد الاعتماد 📋</span>
                        @elseif($loan->status === 'closed')
                            <span class="tag" style="background:#E7F4EE;color:var(--green);font-weight:700;">مكتمل ومغلق ✓</span>
                        @elseif($loan->status === 'rejected')
                            <span class="tag" style="background:#FCE8E6;color:#C5221F;">مرفوض ✕</span>
                        @elseif($loan->status === 'overdue')
                            <span class="tag" style="background:#FDE8E4;color:#B3261E;font-weight:700;">متأخرة عن الاستحقاق ⚠️</span>
                        @else
                            <span class="tag">{{ $loan->status_label }}</span>
                        @endif
                    </div>

                    <!-- Action Buttons -->
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        @if($loan->qr_token && in_array($loan->status, ['pending', 'approved', 'active']))
                            <button type="button" class="secondary" style="min-height:34px;padding:4px 10px;font-size:12px;font-weight:600;" onclick="showQrHandshake('{{ $loan->qr_token }}', '{{ addslashes($loan->copy->book->title ?? 'الكتاب') }}', '{{ $loan->status }}')">
                                <i data-lucide="qr-code" style="width:13px;height:13px;color:var(--gold);"></i>
                                <span>رمز QR</span>
                            </button>
                        @endif

                        @if($loan->status === 'pending')
                            @if($canManage)
                                <!-- 1. Approve Request -->
                                <form method="POST" action="{{ route('libraries.updateLoanStatus', $loan->id) }}" style="display:inline-flex;margin:0;">
                                    @csrf
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="secondary" style="min-height:34px;padding:4px 12px;font-size:12px;border-color:var(--gold);color:#785419;font-weight:700;" title="الموافقة على الطلب وتجهيز النسخة للتسليم">
                                        <i data-lucide="check" style="width:13px;height:13px;"></i>
                                        <span>موافقة</span>
                                    </button>
                                </form>

                                <!-- 2. Handover with Condition Inspection -->
                                <button type="button" class="primary" style="min-height:34px;padding:4px 12px;font-size:12px;background:#245E50;font-weight:700;" onclick="openHandoverLoanModal({{ $loan->id }}, '{{ addslashes($loan->copy->book->title ?? 'الكتاب') }}', '{{ $loan->copy->condition }}')" title="تسليم النسخة للمستعير وتوثيق فحص الحالة المبدئي">
                                    <i data-lucide="handshake" style="width:13px;height:13px;"></i>
                                    <span>تسليم وفحص</span>
                                </button>

                                <!-- 3. Reject Request -->
                                <form method="POST" action="{{ route('libraries.updateLoanStatus', $loan->id) }}" style="display:inline-flex;margin:0;">
                                    @csrf
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn-delete-tool" style="min-height:34px;padding:4px 8px;font-size:12px;border:1px solid #E0CFCB;border-radius:6px;background:transparent;color:#B3261E;" title="رفض الطلب" onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟')">
                                        &times;
                                    </button>
                                </form>
                            @else
                                <span class="muted" style="font-size:12px;">بانتظار مراجعة أمين المكتبة</span>
                            @endif

                        @elseif($loan->status === 'approved')
                            @if($canManage)
                                <!-- Handover with Condition Inspection -->
                                <button type="button" class="primary" style="min-height:34px;padding:4px 12px;font-size:12px;background:#245E50;font-weight:700;" onclick="openHandoverLoanModal({{ $loan->id }}, '{{ addslashes($loan->copy->book->title ?? 'الكتاب') }}', '{{ $loan->copy->condition }}')" title="تسليم النسخة للمستعير وتوثيق فحص الحالة المبدئي">
                                    <i data-lucide="handshake" style="width:13px;height:13px;"></i>
                                    <span>تسليم وفحص الحالة</span>
                                </button>
                            @else
                                <span class="tag" style="background:#EBF3ED;color:#1B4D3E;font-size:12px;">جاهز للتسليم في المكتبة</span>
                            @endif

                        @elseif(in_array($loan->status, ['active', 'overdue']))
                            <!-- Renewal / Extension Button -->
                            @if(($isBorrower || $canManage) && $loan->canRenew())
                                <form method="POST" action="{{ route('libraries.extendLoan', $loan->id) }}" style="display:inline-flex;margin:0;">
                                    @csrf
                                    <button type="submit" class="secondary" style="min-height:34px;padding:4px 12px;font-size:12px;font-weight:600;border-color:var(--gold);color:#785419;" title="تمديد فترة الاستعارة 14 يوماً إضافية">
                                        <i data-lucide="calendar-plus" style="width:13px;height:13px;color:var(--gold);"></i>
                                        <span>تمديد (+14 يوم)</span>
                                    </button>
                                </form>
                            @endif

                            <!-- Return Copy with Condition Inspection -->
                            @if($canManage)
                                <button type="button" class="primary" style="min-height:34px;padding:4px 14px;font-size:12px;font-weight:600;" onclick="openReturnLoanModal({{ $loan->id }}, '{{ addslashes($loan->copy->book->title ?? 'الكتاب') }}', '{{ $loan->copy->condition }}')">
                                    <i data-lucide="check-square" style="width:13px;height:13px;"></i>
                                    <span>تأكيد الإرجاع والفحص</span>
                                </button>
                            @endif

                        @elseif($loan->status === 'returned')
                            @if($canManage)
                                <form method="POST" action="{{ route('libraries.updateLoanStatus', $loan->id) }}" style="display:inline-flex;margin:0;">
                                    @csrf
                                    <input type="hidden" name="status" value="closed">
                                    <button type="submit" class="secondary" style="min-height:34px;padding:4px 12px;font-size:12px;font-weight:700;color:var(--green);border-color:var(--green);" title="اعتماد التقرير وإغلاق ملف الإعارة نهائياً">
                                        <i data-lucide="archive" style="width:13px;height:13px;"></i>
                                        <span>إغلاق الإعارة نهائياً</span>
                                    </button>
                                </form>
                            @endif
                        @endif

                        <!-- Inspection Condition Report (Available for all inspected/active/returned/closed loans) -->
                        @if(in_array($loan->status, ['active', 'returned', 'closed', 'overdue']))
                            <a href="{{ route('libraries.conditionReport', $loan->id) }}" target="_blank" class="secondary" style="min-height:34px;padding:4px 10px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none;border-color:#C3A15D;color:#8C6D2C;" title="عرض تقرير فحص وسلامة النسخة">
                                <i data-lucide="file-check" style="width:13px;height:13px;"></i>
                                <span>تقرير الفحص 📋</span>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div style="text-align:center;padding:40px;background:#FAF8F3;border:1px dashed #E0D6C2;border-radius:12px;">
                    <i data-lucide="inbox" style="width:36px;height:36px;color:var(--sub);margin-bottom:8px;"></i>
                    <h3 style="font-size:16px;">لا توجد سجلات إعارة حالياً</h3>
                    <p class="muted" style="font-size:13px;margin:0;">تظهر هنا جميع طلبات الإعارة الجارية والسابقة وتواريخ الاستحقاق والتمديد.</p>
                </div>
            @endforelse
        </div>
    </div>

<!-- ==========================================
     TAB 3: قوائم الانتظار وحجز النسخ
     ========================================== -->
@elseif($tab === 'waitlists')
    <div class="panel animate-fade-in" style="background:#FFFDF9;border:1px solid #E4DAC4;border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid #ECE4D4;padding-bottom:12px;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(140,97,24,0.15);color:#8C6118;display:grid;place-items:center;">
                    <i data-lucide="clock" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h2 style="margin:0;font-size:18px;">قوائم الانتظار وحجز النسخ في «{{ $library->name }}»</h2>
                    <small class="muted">القرّاء المنتظرون للكتب المعارة حالياً؛ يتم إشعارهم وحجز النسخة تلقائياً فور إعادتها للمكتبة.</small>
                </div>
            </div>
            <span class="tag" style="background:rgba(140,97,24,0.12);color:#8C6118;font-weight:700;">{{ $waitlists->where('status', 'waiting')->count() }} في الانتظار</span>
        </div>

        <div class="waitlist-grid">
            @forelse($waitlists as $wl)
                <div class="waitlist-card">
                    <div class="waitlist-rank-circle" title="ترتيب الدور في قائمة الانتظار">
                        <span>الدور</span>
                        <b>#{{ $wl->priority }}</b>
                    </div>

                    <div style="flex:1;min-width:160px;">
                        <h4 style="margin:0 0 4px;font-size:15px;color:var(--ink);">{{ $wl->book->title ?? 'كتاب' }}</h4>
                        <small class="muted" style="display:block;font-size:12px;">
                            القارئ: <b>{{ $wl->user->name ?? 'عضو' }}</b>
                            · تاريخ الطلب: {{ $wl->created_at->format('Y-m-d') }}
                        </small>

                        <div style="margin-top:6px;">
                            <span class="tag" style="font-size:11px;background:{{ $wl->status === 'notified' ? '#E6F4EA' : '#FEF7E0' }};color:{{ $wl->status === 'notified' ? '#137333' : '#B06000' }};">
                                {{ $wl->status_label }}
                            </span>
                        </div>
                    </div>

                    @if($wl->user_id === Auth::id() || $canManage)
                        <form method="POST" action="{{ route('libraries.cancelWaitlist', $wl->id) }}" onsubmit="return confirm('إلغاء هذا الحجز في قائمة الانتظار؟')" style="margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-delete-tool" style="padding:6px 10px;font-size:12px;border:1px solid #E0D6C2;border-radius:6px;background:transparent;color:#993322;" title="إلغاء الحجز">
                                <i data-lucide="x" style="width:13px;height:13px;"></i>
                                <span>إلغاء</span>
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div style="grid-column:1/-1;text-align:center;padding:40px;background:#FAF8F3;border:1px dashed #E0D6C2;border-radius:12px;">
                    <i data-lucide="clock" style="width:36px;height:36px;color:var(--sub);margin-bottom:8px;"></i>
                    <h3 style="font-size:16px;">لا توجد طلبات انتظار حالياً</h3>
                    <p class="muted" style="font-size:13px;margin:0;">عندما تكون جميع نسخ كتاب معين معارة، يمكن للأعضاء الانضمام لقائمة الانتظار لحجزها فور عودتها.</p>
                </div>
            @endforelse
        </div>
    </div>

<!-- ==========================================
     TAB 4: أعضاء المكتبة والأدوار
     ========================================== -->
@elseif($tab === 'members')
    <div class="panel animate-fade-in" style="background:#FFFDF9;border:1px solid #E4DAC4;border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid #ECE4D4;padding-bottom:12px;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(23,61,54,0.12);color:var(--green);display:grid;place-items:center;">
                    <i data-lucide="users" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h2 style="margin:0;font-size:18px;">أعضاء مكتبة «{{ $library->name }}»</h2>
                    <small class="muted">إدارة الصلاحيات (مالك، أمين مكتبة، عضو مستعير، قارئ) ودعوة أعضاء جدد</small>
                </div>
            </div>

            @if($library->code)
                <div class="lib-code-chip" onclick="navigator.clipboard.writeText('{{ $library->code }}'); alert('تم نسخ كود المكتبة: {{ $library->code }}');">
                    <i data-lucide="key" style="width:13px;height:13px;color:var(--gold);"></i>
                    <span>كود الانضمام المباشر: <b>{{ $library->code }}</b></span>
                    <i data-lucide="copy" style="width:12px;height:12px;"></i>
                </div>
            @endif
        </div>

        <!-- Add/Invite Member Bar for Managers -->
        @if($canManage && $availableUsersToInvite->count() > 0)
            <div style="background:#FAF7F0;border:1px solid #E6DCC8;border-radius:12px;padding:16px;margin-bottom:20px;">
                <h4 style="margin:0 0 10px;font-size:14px;display:flex;align-items:center;gap:6px;color:var(--green);">
                    <i data-lucide="user-plus" style="width:15px;height:15px;"></i>
                    <span>إضافة عضو جديد وتحديد دوره</span>
                </h4>
                <form method="POST" action="{{ route('libraries.addMember', $library->id) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    @csrf
                    <select name="user_id" required style="padding:8px 12px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;flex:1;min-width:200px;background:#FFFDF8;">
                        <option value="">-- اختر المستخدم المراد إضافته --</option>
                        @foreach($availableUsersToInvite as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>

                    <select name="role" required style="padding:8px 12px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;min-width:150px;background:#FFFDF8;">
                        <option value="member">عضو مستعير 📖</option>
                        <option value="librarian">أمين مكتبة 🗝️</option>
                        <option value="viewer">قارئ ومطّلع 👁️</option>
                    </select>

                    <button type="submit" class="primary" style="padding:8px 18px;font-size:13px;font-weight:700;">
                        <span>إضافة للمكتبة</span>
                    </button>
                </form>
            </div>
        @endif

        @if($library->isOwner(Auth::user()) && $members->where('user_id', '!=', Auth::id())->count() > 0)
            <div style="background:#FAF6EE;border:1px dashed #D5C8B2;border-radius:12px;padding:16px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <i data-lucide="shield-alert" style="width:16px;height:16px;color:#8F3829;"></i>
                    <h4 style="margin:0;font-size:13.5px;color:#8F3829;">إجراء حساس: نقل ملكية الخزانة/المكتبة</h4>
                </div>
                <p style="font-size:12px;color:var(--sub);margin:0 0 12px;line-height:1.5;">
                    وفق ضوابط الصلاحيات، لا يجوز ترك المكتبة بلا مالك. لنقل الملكية بالكامل لشخص آخر من الأعضاء، اختر العضو وأكد العملية (ستُوثّق في سجل التدقيق):
                </p>
                <form method="POST" action="{{ route('libraries.transferOwnership', $library->id) }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm('تنبيه أمني: هل أنت متأكد تماماً من التنازل عن ملكية المكتبة ونقلها للمستخدم المختار؟ لا يمكن التراجع إلا بموافقة المالك الجديد.');">
                    @csrf
                    <select name="new_owner_id" required style="padding:6px 12px;font-size:12.5px;border-radius:8px;border:1px solid #D5C8B2;background:#FFFDF8;">
                        <option value="" disabled selected>اختر المالك الجديد...</option>
                        @foreach($members as $m)
                            @if($m->user_id !== Auth::id())
                                <option value="{{ $m->user_id }}">{{ $m->user->name }} ({{ $m->user->email }})</option>
                            @endif
                        @endforeach
                    </select>

                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
                        <input type="checkbox" name="confirm" value="1" required>
                        <span>أوافق صراحة على نقل الملكية الكاملة</span>
                    </label>

                    <button type="submit" class="secondary" style="color:#8F3829;border-color:#E3B5AD;padding:6px 14px;font-size:12px;font-weight:700;">
                        تأكيد نقل الملكية
                    </button>
                </form>
            </div>
        @endif

        <!-- Members Grid -->
        <div class="members-grid">
            @foreach($members as $m)
                @php
                    $isLibraryOwner = $m->user_id === $library->user_id;
                @endphp
                <div class="member-card">
                    <div class="member-avatar-box">
                        {{ mb_substr($m->user->name ?? 'ع', 0, 1) }}
                    </div>

                    <div style="flex:1;min-width:140px;">
                        <h4 style="margin:0 0 2px;font-size:14.5px;">{{ $m->user->name ?? 'عضو' }}</h4>
                        <small class="muted" style="display:block;font-size:11.5px;margin-bottom:6px;">{{ $m->user->email ?? '' }}</small>
                        
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <span class="role-badge role-badge-{{ $m->role }}">
                                {{ $m->role_name }}
                            </span>
                            <span style="font-size:11px;color:var(--sub);">انضم: {{ $m->joined_at ? $m->joined_at->format('Y-m-d') : 'مؤسس' }}</span>
                        </div>
                    </div>

                    <!-- Role Modification / Removal for Managers -->
                    @if($canManage && !$isLibraryOwner)
                        <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
                            <form method="POST" action="{{ route('libraries.updateMemberRole', [$library->id, $m->id]) }}" style="margin:0;">
                                @csrf
                                @method('PUT')
                                <select name="role" onchange="this.form.submit()" style="font-size:11px;padding:3px 6px;border-radius:6px;border:1px solid #D5C8B2;background:#FFFDF8;cursor:pointer;">
                                    <option value="librarian" {{ $m->role === 'librarian' ? 'selected' : '' }}>أمين مكتبة</option>
                                    <option value="member" {{ $m->role === 'member' ? 'selected' : '' }}>عضو</option>
                                    <option value="viewer" {{ $m->role === 'viewer' ? 'selected' : '' }}>قارئ</option>
                                </select>
                            </form>

                            <form method="POST" action="{{ route('libraries.removeMember', [$library->id, $m->id]) }}" onsubmit="return confirm('إزالة هذا العضو من المكتبة؟')" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete-tool" style="font-size:11px;padding:2px 6px;border:1px solid #F0D5CF;border-radius:4px;background:transparent;color:#B3261E;" title="إزالة العضو">
                                    إزالة
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

<!-- ==========================================
     TAB 5: كافة مكتباتي (All Libraries)
     ========================================== -->
@elseif($tab === 'all_libraries')
    <div class="panel animate-fade-in" style="background:#FFFDF9;border:1px solid #E4DAC4;border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid #ECE4D4;padding-bottom:12px;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(23,61,54,0.12);color:var(--green);display:grid;place-items:center;">
                    <i data-lucide="layers" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h2 style="margin:0;font-size:18px;">كافة المكتبات المتاحة لك</h2>
                    <small class="muted">مكتباتك الشخصية، المشتركة، والأندية التي تشارك فيها</small>
                </div>
            </div>

            <button type="button" class="primary" onclick="openModal('create-library-modal')" style="font-size:13px;padding:8px 16px;">
                <i data-lucide="plus"></i>
                <span>تأسيس مكتبة جديدة</span>
            </button>
        </div>

        <div class="all-libraries-grid">
            @foreach($allAccessibleLibraries as $libItem)
                @php
                    $isCurrent = $libItem->id === $library->id;
                    $itemRole = $libItem->userRole(Auth::user());
                @endphp
                <div class="library-tile-card" style="border-top: 4px solid {{ $libItem->cover_color ?? '#173D36' }};">
                    <div class="library-tile-banner" style="background:linear-gradient(135deg, {{ $libItem->cover_color ?? '#173D36' }}, #091D1A);">
                        <div>
                            <span style="font-size:11px;background:rgba(255,255,255,0.15);padding:2px 8px;border-radius:12px;">{{ $libItem->type_label }}</span>
                            <h3 style="margin:6px 0 0;font-size:16px;color:#FFFDF8;">{{ $libItem->name }}</h3>
                        </div>
                        @if($isCurrent)
                            <span class="tag" style="background:#FFE2A3;color:#173D36;font-weight:700;font-size:11px;">النشطة حالياً ✦</span>
                        @endif
                    </div>

                    <div class="library-tile-body">
                        <p class="muted" style="font-size:13px;margin:0 0 14px;line-height:1.5;">
                            {{ Str::limit($libItem->description ?? 'مساحة لحفظ وتوثيق وتبادل الكتب الورقية.', 90) }}
                        </p>

                        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #ECE4D4;padding-top:12px;margin-top:auto;">
                            <div style="font-size:12px;color:var(--sub);display:flex;gap:10px;">
                                <span><i data-lucide="book" style="width:12px;height:12px;display:inline-block;vertical-align:middle;"></i> {{ $libItem->copies()->count() }} نسخة</span>
                                <span><i data-lucide="users" style="width:12px;height:12px;display:inline-block;vertical-align:middle;"></i> {{ $libItem->members()->count() }} عضو</span>
                            </div>

                            <a href="{{ route('libraries.index', ['library_id' => $libItem->id]) }}" class="primary btn-sm" style="font-size:12px;padding:5px 12px;">
                                <span>دخول المكتبة &larr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@endsection

@section('modals')
<!-- 1. Add Book Modal with CamScanner & Physical Copy Condition -->
<div id="add-book-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:560px;max-width:96vw;max-height:92vh;overflow-y:auto;position:relative;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">أضف كتابًا ونسخة مادية إلى المكتبة</h2>
                <small class="muted">تحقق من الفهرس لمنع التكرار، وحدد حالة النسخة ومكانها في الرف.</small>
            </div>
            <button class="close-btn" onclick="closeModal('add-book-modal')">&times;</button>
        </div>

        <form id="add-book-form" action="{{ route('libraries.storeBook') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="existing_book_id" id="book-input-existing-id" value="">
            <input type="hidden" name="library_id" id="target-library-id" value="{{ $library->id }}">

            <!-- Existing Book Selected Banner -->
            <div id="selected-book-alert" style="display:none;background:#EBF3ED;border:1px solid #C4DEC9;color:#1B4D3E;border-radius:10px;padding:12px;margin-bottom:14px;font-size:13px;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="background:var(--green);color:#FFFDF8;padding:6px;border-radius:6px;display:flex;">
                        <i data-lucide="check" style="width:16px;height:16px;"></i>
                    </div>
                    <div>
                        <strong>تم اختيار الكتاب من الفهرس العام</strong>
                        <div id="selected-book-title-display" style="font-size:12px;color:#2B6352;"></div>
                        <small style="color:#527B6D;display:block;">سيتم تسجيل نسخة مادية جديدة دون تكرار في قاعدة البيانات.</small>
                    </div>
                </div>
                <button type="button" id="btn-cancel-existing-selection" class="secondary" style="padding:4px 10px;font-size:11.5px;min-height:30px;white-space:nowrap;">
                    إلغاء
                </button>
            </div>

            <div style="display:grid;gap:14px;">
                <!-- Target Library Selection -->
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;color:var(--green);">المكتبة المستهدفة لحفظ النسخة المادية:</label>
                    <select name="library_id" id="select-target-lib" style="padding:8px 12px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;background:#FFFDF8;">
                        @foreach($allAccessibleLibraries as $al)
                            <option value="{{ $al->id }}" {{ $al->id === $library->id ? 'selected' : '' }}>
                                {{ $al->name }} ({{ $al->type_label }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- ISBN: جلب بيانات الكتاب تلقائيًا -->
                <div class="isbn-box">
                    <label for="book-input-isbn" style="font-weight:600;font-size:13.5px;">رقم ISBN (اختياري) — لجلب البيانات تلقائيًا</label>
                    <div class="isbn-row">
                        <input name="isbn" id="book-input-isbn" inputmode="numeric" placeholder="مثال: 978-0-14-032872-1" maxlength="30" autocomplete="off">
                        <button type="button" id="btn-isbn-lookup" class="secondary"><i data-lucide="scan-search"></i><span>جلب البيانات</span></button>
                    </div>
                    <small id="isbn-lookup-status" class="isbn-status" hidden></small>
                    <input type="hidden" name="cover_url" id="book-input-cover-url" value="">

                    <!-- معاينة ما تم جلبه: الغلاف وكل التفاصيل -->
                    <div class="isbn-preview" id="isbn-preview" hidden>
                        <img id="isbn-preview-cover" src="" alt="غلاف الكتاب" hidden>
                        <div class="isbn-preview-body">
                            <strong id="isbn-preview-title"></strong>
                            <span id="isbn-preview-author"></span>
                            <ul id="isbn-preview-facts"></ul>
                            <small id="isbn-preview-source"></small>
                        </div>
                    </div>

                    <label for="book-input-description" style="font-weight:600;font-size:13px;margin-top:4px;">وصف الكتاب (يُملأ تلقائيًا ويمكنك تعديله)</label>
                    <textarea name="description" id="book-input-description" rows="3" maxlength="3000" placeholder="نبذة عن الكتاب…"></textarea>
                    <input name="language" id="book-input-language" maxlength="30" placeholder="لغة الكتاب (مثال: العربية)">
                </div>

                <!-- Title Input with Real-time Ajax Filter -->
                <div style="position:relative;display:grid;gap:6px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-weight:600;font-size:13.5px;">عنوان الكتاب</span>
                        <span id="title-search-indicator" style="font-size:11.5px;color:var(--sub);display:none;align-items:center;gap:4px;">
                            <span class="spinner-dot" style="display:inline-block;width:8px;height:8px;background:var(--gold);border-radius:50%;animation:pulseGlow 1s infinite;"></span>
                            جاري فحص الفهرس…
                        </span>
                    </div>
                    <input name="title" id="book-input-title" placeholder="اكتب عنوان الكتاب (مثال: وحي القلم، الأيام)..." required maxlength="80" autocomplete="off">
                    
                    <!-- Ajax Autocomplete Dropdown List -->
                    <div id="duplicate-suggestions-box" style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--paper);border:1px solid var(--gold);box-shadow:0 12px 28px rgba(14,43,38,0.18);border-radius:10px;z-index:40;max-height:260px;overflow-y:auto;margin-top:4px;padding:8px;">
                        <div style="padding:6px 10px;font-size:11.5px;font-weight:600;color:var(--green);background:rgba(195,161,93,0.12);border-radius:6px;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                            <i data-lucide="sparkles" style="width:13px;height:13px;"></i>
                            <span>كتب مطابقة مسجلة في رِواق:</span>
                        </div>
                        <div id="duplicate-suggestions-list" style="display:grid;gap:4px;"></div>
                    </div>
                </div>

                <div style="display:grid;gap:6px;">
                    <span style="font-weight:600;font-size:13.5px;">المؤلف</span>
                    <input name="author" id="book-input-author" placeholder="اسم المؤلف (مثال: مصطفى صادق الرافعي)" required maxlength="60">
                </div>

                <div style="display:grid;gap:6px;">
                    <span style="font-weight:600;font-size:13.5px;">دار النشر (اختياري)</span>
                    <input name="publisher" id="book-input-publisher" placeholder="مثال: دار المعارف" maxlength="150">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="display:grid;gap:6px;">
                        <span style="font-weight:600;font-size:13.5px;">التصنيف</span>
                        <select name="category" id="book-input-category" style="padding:9px 12px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13.5px;background:#FFFDF8;">
                            <option value="أدب">أدب</option>
                            <option value="سيرة">سيرة</option>
                            <option value="تاريخ">تاريخ</option>
                            <option value="فكر">فكر</option>
                            <option value="فلسفة">فلسفة</option>
                            <option value="شعر">شعر</option>
                        </select>
                    </div>

                    <div style="display:grid;gap:6px;">
                        <span style="font-weight:600;font-size:13.5px;">عدد الصفحات</span>
                        <input name="total_pages" id="book-input-pages" type="number" min="1" max="5000" placeholder="200" value="200">
                    </div>
                </div>

                <!-- Physical Copy Condition & Shelf Location -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px;background:#F9F6EE;border:1px solid #E6DCC8;border-radius:10px;">
                    <div style="display:grid;gap:6px;">
                        <label style="font-weight:700;font-size:12.5px;color:var(--green);">حالة النسخة المادية:</label>
                        <select name="condition" style="padding:8px 10px;border:1px solid #D5C8B2;border-radius:6px;font-family:inherit;font-size:12.5px;background:#FFFDF8;">
                            <option value="mint">ممتازة (كالجديدة) ✦</option>
                            <option value="good" selected>جيدة جداً 🌿</option>
                            <option value="fair">مقبولة / مستعملة 📖</option>
                            <option value="damaged">تحتاج صيانة وترميم ⚠️</option>
                        </select>
                    </div>

                    <div style="display:grid;gap:6px;">
                        <label style="font-weight:700;font-size:12.5px;color:var(--green);">مكان الرف الفعلي:</label>
                        <input name="shelf_location" placeholder="مثال: الرف الأدبي - أ3" style="font-size:12.5px;padding:8px 10px;">
                    </div>
                </div>

                <!-- CamScanner Book Cover Studio -->
                <div class="camscanner-studio" style="border:1px solid #E2D7C3;border-radius:12px;padding:14px;background:#FAF7F0;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
                        <label style="font-weight:700;font-size:13px;display:flex;align-items:center;gap:6px;color:var(--green);">
                            <i data-lucide="scan-line" style="width:15px;height:15px;color:var(--gold);"></i>
                            <span>استوديو تصوير وتحسين الأغلفة (CamScanner Studio)</span>
                        </label>
                        <div style="display:flex;gap:4px;">
                            <button type="button" class="camscanner-preset-pill tag selected" data-preset="royal" style="cursor:pointer;font-size:11px;">ملكي</button>
                            <button type="button" class="camscanner-preset-pill tag" data-preset="manuscript" style="cursor:pointer;font-size:11px;">مخطوطة</button>
                            <button type="button" class="camscanner-preset-pill tag" data-preset="clean" style="cursor:pointer;font-size:11px;">نقي</button>
                        </div>
                    </div>

                    <div class="camscanner-dropzone" style="border:2px dashed #D5C8B2;border-radius:8px;padding:12px;text-align:center;background:#FFFDF8;cursor:pointer;">
                        <input type="file" id="book-cover-input" name="cover_image_file" accept="image/*" style="font-size:12px;margin-bottom:8px;width:100%;">
                        <input type="hidden" name="cover_image_enhanced" id="book-cover-enhanced-data" value="">
                        <input type="hidden" id="cover-image-enhanced-data" value="">
                        <small class="muted" style="font-size:11.5px;display:block;">اسحب صورة الغلاف إلى هنا أو انقر للاختيار من جهازك</small>
                    </div>

                    <div id="camscanner-preview-box" style="display:none;margin-top:10px;text-align:center;">
                        <div style="position:relative;display:inline-block;border-radius:8px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                            <img id="camscanner-preview-img" src="" alt="معاينة الغلاف المحسّن" style="max-height:160px;max-width:100%;display:block;border-radius:8px;">
                            <div id="camscanner-badge" style="position:absolute;bottom:6px;left:6px;background:rgba(23,61,54,0.85);color:#FFE2A3;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;">
                                تم التحسين بـ CamScanner ✓
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="secondary" onclick="closeModal('add-book-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 24px;font-weight:700;">
                    <i data-lucide="plus"></i>
                    <span>حفظ النسخة في المكتبة</span>
                </button>
            </div>
        </form>
    </section>
</div>

@can('update', $library)
<!-- Library Settings Modal -->
<div id="library-settings-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:520px;max-width:96vw;max-height:92vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">إعدادات المكتبة</h2>
                <small class="muted">{{ $library->name }}</small>
            </div>
            <button class="close-btn" onclick="closeModal('library-settings-modal')">&times;</button>
        </div>

        <form method="POST" action="{{ route('libraries.updateSettings', $library->id) }}">
            @csrf
            @method('PUT')
            <div style="display:grid;gap:12px;">
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">اسم المكتبة *</label>
                    <input name="name" required maxlength="100" value="{{ $library->name }}">
                </div>
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">الوصف</label>
                    <textarea name="description" rows="2" maxlength="500">{{ $library->description }}</textarea>
                </div>
                @if(! $library->is_personal)
                    <div style="display:grid;gap:6px;">
                        <label style="font-weight:600;font-size:13px;">الخصوصية</label>
                        <div class="lb-type-pick">
                            <label><input type="radio" name="type" value="personal" {{ $library->type !== 'public' ? 'checked' : '' }}><span><i data-lucide="lock"></i><b>خاصة</b><small>لك ولمن تدعوهم فقط</small></span></label>
                            <label><input type="radio" name="type" value="public" {{ $library->type === 'public' ? 'checked' : '' }}><span><i data-lucide="globe"></i><b>عامة</b><small>يراها ويستعير منها الجميع</small></span></label>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="type" value="personal">
                    <small class="muted">المكتبة الشخصية خاصة بك دائمًا.</small>
                @endif
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">لون الهوية</label>
                    <select name="cover_color" style="padding:9px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;background:#FFFDF8;">
                        @foreach(['#173D36' => 'أخضر زمردي ملكي', '#795B44' => 'بني جلدي تراثي', '#245E50' => 'أخضر غابات هادئ', '#4b6068' => 'رمادي كحلي رصين', '#7A4C2E' => 'خزفي أندلسي'] as $hex => $label)
                            <option value="{{ $hex }}" {{ strcasecmp($library->cover_color ?? '#173D36', $hex) === 0 ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($bookLendingEnabled)
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div style="display:grid;gap:6px;">
                            <label style="font-weight:600;font-size:13px;">مدة الإعارة (أيام)</label>
                            <input type="number" name="loan_duration_days" min="1" max="90" value="{{ $library->loan_duration_days }}">
                        </div>
                        <div style="display:grid;gap:6px;">
                            <label style="font-weight:600;font-size:13px;">مرات التجديد</label>
                            <input type="number" name="max_renewals" min="0" max="5" value="{{ $library->max_renewals }}">
                        </div>
                    </div>
                @endif
                <a href="{{ route('libraries.index', ['library_id' => $library->id, 'tab' => 'members']) }}" class="rq-btn rq-btn-ghost rq-btn-sm" style="justify-self:start;">
                    <i data-lucide="users"></i><span>إدارة الأعضاء وصلاحياتهم</span>
                </a>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
                @if(! $library->is_personal)
                    <button type="button" class="lb-delete-link" onclick="document.getElementById('lb-danger-zone').scrollIntoView({behavior:'smooth', block:'center'}); document.querySelector('#lb-danger-zone input[name=confirm_name]').focus();">
                        <i data-lucide="trash-2"></i><span>حذف المكتبة</span>
                    </button>
                @endif
                <button type="button" class="secondary" onclick="closeModal('library-settings-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 22px;font-weight:700;"><span>حفظ الإعدادات</span></button>
            </div>
        </form>

        @if(! $library->is_personal)
            <form method="POST" action="{{ route('libraries.forceDestroy', $library->id) }}" class="lb-danger-zone" id="lb-danger-zone" onsubmit="return confirm('سيتم حذف المكتبة وكل نسخها حذفًا تامًا ولا يمكن التراجع. متأكد؟');">
                @csrf
                @method('DELETE')
                <b><i data-lucide="triangle-alert"></i> حذف تام للمكتبة</b>
                <small>يحذف المكتبة وجميع نسخها وأعضائها نهائيًا. اكتب اسم المكتبة للتأكيد.</small>
                <div class="lb-danger-row">
                    <input name="confirm_name" required placeholder="{{ $library->name }}" autocomplete="off">
                    <button type="submit" class="rq-btn rq-btn-sm lb-danger-btn"><i data-lucide="trash-2"></i><span>حذف نهائي</span></button>
                </div>
            </form>
        @else
            <div class="lb-danger-zone" style="background:#F5F1E6;border-color:#E2D7C3;">
                <small class="muted">هذه مكتبتك الشخصية ولا يمكن حذفها. لحذف مكتبة أخرى اخترها من الشريط أعلاه ثم افتح إعداداتها.</small>
            </div>
        @endif
    </section>
</div>
@endcan

<!-- 2. Create New Library Modal -->
<div id="create-library-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:500px;max-width:96vw;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">تأسيس مكتبة جديدة</h2>
                <small class="muted">أنشئ مساحة مخصصة للكتب المشتركة، الأندية الأدبية، أو مكتبة العائلة.</small>
            </div>
            <button class="close-btn" onclick="closeModal('create-library-modal')">&times;</button>
        </div>

        <form method="POST" action="{{ route('libraries.store') }}">
            @csrf
            <div style="display:grid;gap:12px;">
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">اسم المكتبة *</label>
                    <input name="name" required placeholder="مثال: خزانة مجلس الأدب، مكتبة العائلة والرفاق..." maxlength="80">
                </div>

                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">وصف ورسالة المكتبة</label>
                    <textarea name="description" rows="2" placeholder="نبذة عن هذه المكتبة والقراء المنتمين إليها..."></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                    <div style="display:grid;gap:6px;">
                        <label style="font-weight:600;font-size:13px;">نوع المكتبة</label>
                        <div class="lb-type-pick"><label><input type="radio" name="type" value="personal" checked><span><i data-lucide="lock"></i><b>خاصة</b><small>لك ولمن تدعوهم فقط</small></span></label><label><input type="radio" name="type" value="public"><span><i data-lucide="globe"></i><b>عامة</b><small>يراها ويستعير منها الجميع</small></span></label></div>
                    </div>

                    <div style="display:grid;gap:6px;">
                        <label style="font-weight:600;font-size:13px;">لون الهوية</label>
                        <select name="cover_color" style="padding:9px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;background:#FFFDF8;">
                            <option value="#173D36">أخضر زمردي ملكي</option>
                            <option value="#795B44">بني جلدي تراثي</option>
                            <option value="#245E50">أخضر غابات هادئ</option>
                            <option value="#4b6068">رمادي كحلي رصين</option>
                            <option value="#7A4C2E">خزفي أندلسي</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="secondary" onclick="closeModal('create-library-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 22px;font-weight:700;">
                    <i data-lucide="plus"></i>
                    <span>تأسيس المكتبة</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- 3. Join Library with Code Modal -->
<div id="join-library-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:440px;max-width:96vw;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">الانضمام إلى مكتبة بكود الدعوة</h2>
                <small class="muted">أدخل الكود التعريفي الفريد للمكتبة (مثال: LIB-PRSN-01)</small>
            </div>
            <button class="close-btn" onclick="closeModal('join-library-modal')">&times;</button>
        </div>

        <form method="POST" action="{{ route('libraries.joinByCode') }}">
            @csrf
            <div style="display:grid;gap:12px;">
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">كود الانضمام (Invite Code)</label>
                    <input name="code" required placeholder="LIB-XXXX" style="text-transform:uppercase;letter-spacing:1px;font-weight:700;font-size:15px;text-align:center;">
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="secondary" onclick="closeModal('join-library-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 22px;font-weight:700;">
                    <i data-lucide="log-in"></i>
                    <span>الانضمام للمكتبة</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- 4. Update Copy Condition & Inspection Modal -->
<div id="update-condition-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:480px;max-width:96vw;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">فحص وتحديث حالة النسخة المادية</h2>
                <small class="muted">تعديل حالة النسخة، مكان الرف، وملاحظات الفحص الدوري.</small>
            </div>
            <button class="close-btn" onclick="closeModal('update-condition-modal')">&times;</button>
        </div>

        <form id="update-condition-form" method="POST" action="">
            @csrf
            <div style="display:grid;gap:12px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="display:grid;gap:6px;">
                        <label style="font-weight:600;font-size:13px;">حالة النسخة الورقية:</label>
                        <select name="condition" id="copy-condition-select" style="padding:8px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;background:#FFFDF8;">
                            <option value="mint">ممتازة (كالجديدة) ✦</option>
                            <option value="good">جيدة جداً 🌿</option>
                            <option value="fair">مقبولة / مستعملة 📖</option>
                            <option value="damaged">تحتاج صيانة وترميم ⚠️</option>
                        </select>
                    </div>

                    <div style="display:grid;gap:6px;">
                        <label style="font-weight:600;font-size:13px;">حالة التوفر:</label>
                        <select name="status" id="copy-status-select" style="padding:8px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;background:#FFFDF8;">
                            <option value="available">متاح للإعارة 🌿</option>
                            <option value="borrowed">معارة حالياً</option>
                            <option value="reserved">محجوزة / قيد المراجعة</option>
                            <option value="maintenance">قيد الصيانة والترميم 🛠️</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">مكان الرف الفعلي:</label>
                    <input name="shelf_location" id="copy-shelf-input" placeholder="مثال: الرف الأدبي - أ3">
                </div>

                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">ملاحظات الفحص الدوري والتجليد:</label>
                    <textarea name="condition_notes" id="copy-notes-input" rows="2" placeholder="ملاحظات حول حالة الأوراق، الغلاف، التجليد..."></textarea>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="secondary" onclick="closeModal('update-condition-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 22px;font-weight:700;">
                    <span>حفظ التعديلات</span>
                </button>
            </div>
        </form>
    </section>
</div>

@if($bookLendingEnabled)
<!-- 5. Return Loan with Condition Inspection Modal -->
<div id="return-loan-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:460px;max-width:96vw;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">تأكيد استرجاع النسخة وفحصها</h2>
                <small class="muted" id="return-modal-book-title">فحص حالة الكتاب الورقي عند استلامه من المستعير</small>
            </div>
            <button class="close-btn" onclick="closeModal('return-loan-modal')">&times;</button>
        </div>

        <form id="return-loan-form" method="POST" action="">
            @csrf
            <input type="hidden" name="status" value="returned">
            <div style="display:grid;gap:12px;">
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">حالة النسخة عند الاسترجاع:</label>
                    <select name="return_condition" id="return-cond-select" style="padding:9px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;background:#FFFDF8;">
                        <option value="mint">ممتازة (كالجديدة تماماً) ✦</option>
                        <option value="good" selected>جيدة جداً وبحالة سليمة 🌿</option>
                        <option value="fair">مقبولة / ظهور علامات قراءة خفيفة 📖</option>
                        <option value="damaged">متضررة / تحتاج صيانة وترميم ⚠️</option>
                    </select>
                </div>

                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">ملاحظات الفحص والاستلام (اختياري):</label>
                    <textarea name="return_notes" rows="2" placeholder="ملاحظات حول سلامة الصفحات والغلاف..."></textarea>
                </div>

                <div style="background:#FAF8F3;border:1px solid #E6DCC8;border-radius:8px;padding:10px;font-size:12px;color:#797362;">
                    <i data-lucide="info" style="width:13px;height:13px;color:var(--gold);display:inline-block;vertical-align:middle;"></i>
                    <span>إذا كان هناك أعضاء في قائمة الانتظار، سيتم حجز النسخة تلقائياً للعضو التالي وإشعاره فوراً!</span>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="secondary" onclick="closeModal('return-loan-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 22px;font-weight:700;">
                    <i data-lucide="check"></i>
                    <span>تأكيد الاستلام والإرجاع</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- 5.5 Handover Loan with Condition Inspection Modal -->
<div id="handover-loan-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:460px;max-width:96vw;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">تسليم النسخة وفحص الحالة المبدئي</h2>
                <small class="muted" id="handover-modal-book-title">توثيق حالة الكتاب الورقي عند تسليمه للمستعير</small>
            </div>
            <button class="close-btn" onclick="closeModal('handover-loan-modal')">&times;</button>
        </div>

        <form id="handover-loan-form" method="POST" action="">
            @csrf
            <input type="hidden" name="status" value="active">
            <div style="display:grid;gap:12px;">
                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">حالة النسخة عند التسليم:</label>
                    <select name="handover_condition" id="handover-cond-select" style="padding:9px;border:1px solid #D5C8B2;border-radius:8px;font-family:inherit;font-size:13px;background:#FFFDF8;">
                        <option value="mint">ممتازة (كالجديدة تماماً) ✦</option>
                        <option value="good" selected>جيدة جداً وبحالة سليمة 🌿</option>
                        <option value="fair">مقبولة / ظهور علامات قراءة خفيفة 📖</option>
                        <option value="damaged">متضررة / تحتاج صيانة وترميم ⚠️</option>
                    </select>
                </div>

                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;">ملاحظات الفحص والتسليم (اختياري):</label>
                    <textarea name="handover_notes" rows="2" placeholder="ملاحظات حول حالة الغلاف والصفحات عند التسليم..."></textarea>
                </div>

                <div style="background:#FAF8F3;border:1px solid #E6DCC8;border-radius:8px;padding:10px;font-size:12px;color:#797362;">
                    <i data-lucide="info" style="width:13px;height:13px;color:var(--gold);display:inline-block;vertical-align:middle;"></i>
                    <span>سيتم توثيق تقرير الفحص المبدئي وبدء احتساب أيام الإعارة للمستعير فوراً.</span>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="secondary" onclick="closeModal('handover-loan-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 22px;font-weight:700;">
                    <i data-lucide="check"></i>
                    <span>اعتماد التسليم وبدء الإعارة</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- 6. Loan Request Modal -->
<div id="loan-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:480px;max-width:96vw;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;">طلب استعارة نسخة ورقية</h2>
                <small class="muted" id="loan-modal-book-title">استعارة مادية لمدة 14 يوماً مع خيار المبادلة</small>
            </div>
            <button class="close-btn" onclick="closeModal('loan-modal')">&times;</button>
        </div>

        <form id="loan-request-form" method="POST" action="">
            @csrf
            <div style="display:grid;gap:14px;">
                <div style="background:#FAF7F0;border:1px solid #E8DFCC;border-radius:10px;padding:12px;font-size:13px;line-height:1.5;">
                    <div style="display:flex;align-items:center;gap:6px;font-weight:700;color:var(--green);margin-bottom:4px;">
                        <i data-lucide="shield-check" style="width:16px;height:16px;color:var(--gold);"></i>
                        <span>ميثاق الاستعارة المعتمد</span>
                    </div>
                    <span>مدة الاستعارة الأساسية 14 يوماً قابلة للتمديد حتى مرتين. يتم تأكيد التسليم عبر مسح رمز QR Handshake المشفر.</span>
                </div>

                <div style="display:grid;gap:6px;">
                    <label style="font-weight:600;font-size:13px;display:flex;align-items:center;gap:6px;">
                        <i data-lucide="repeat" style="width:14px;height:14px;color:var(--gold);"></i>
                        <span>اقتراح مبادلة مؤقتة بكتاب من مكتبتك (اختياري)</span>
                    </label>
                    <input name="swap_book_title" placeholder="اكتب عنوان الكتاب الذي تقترح إعارته مؤقتاً لصاحب النسخة..." maxlength="100">
                    <small class="muted" style="font-size:11.5px;">تشجع هذه الميزة تداول المعرفة وتبادل النسخ بين القرّاء في نفس المجلس.</small>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="secondary" onclick="closeModal('loan-modal')">إلغاء</button>
                <button type="submit" class="primary" style="padding:10px 22px;font-weight:700;">
                    <i data-lucide="send"></i>
                    <span>إرسال طلب الاستعارة</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- 7. QR Handshake Modal -->
<div id="qr-handshake-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:420px;max-width:96vw;text-align:center;">
        <div class="heading" style="margin-bottom:14px;justify-content:center;">
            <div>
                <h2 style="margin:0;font-size:18px;">رمز المصافحة الرقمي ✦</h2>
                <small class="muted" id="qr-modal-sub">QR Handshake Token للتسليم المباشر</small>
            </div>
        </div>

        <div style="background:#FFFDF8;border:2px dashed var(--gold);border-radius:14px;padding:24px;margin:16px 0;box-shadow:0 6px 20px rgba(0,0,0,0.06);">
            <div style="width:80px;height:80px;margin:0 auto 12px;background:rgba(23,61,54,0.08);border-radius:12px;display:grid;place-items:center;">
                <i data-lucide="qr-code" style="width:50px;height:50px;color:var(--green);"></i>
            </div>
            <div id="qr-token-display" style="font-family:monospace;font-size:22px;font-weight:800;color:var(--green);letter-spacing:2px;user-select:all;">
                RWQ-QR-XXXX
            </div>
            <small style="color:#785419;font-size:12px;display:block;margin-top:8px;">أظهر هذا الرمز لأمين المكتبة أو المستعير عند لقاء التسليم لتأكيد العملية في النظام.</small>
        </div>

        <button type="button" class="secondary" onclick="closeModal('qr-handshake-modal')" style="width:100%;">
            <span>إغلاق النافذة</span>
        </button>
    </section>
</div>
@endif

<!-- 8. Physical Book Passport Modal -->
<div id="passport-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="width:540px;max-width:96vw;max-height:90vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <div>
                <h2 style="margin:0;display:flex;align-items:center;gap:6px;">
                    <i data-lucide="stamp" style="width:20px;height:20px;color:var(--gold);"></i>
                    <span>جواز سفر النسخة المادية</span>
                </h2>
                <small class="muted" id="passport-book-title">سجل التداول وهوامش القراء المتعاقبين</small>
            </div>
            <button class="close-btn" onclick="closeModal('passport-modal')">&times;</button>
        </div>

        <!-- Passport Stamp Grid -->
        <div style="background:#F9F6EE;border:1px solid #E2D7C3;border-radius:12px;padding:16px;margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="background:#FFFDF8;padding:10px;border-radius:8px;border:1px solid #E8DFCC;text-align:center;">
                <span class="muted" style="font-size:11px;display:block;">الرمز الأرشيفي للنسخة</span>
                <b id="passport-barcode" style="font-size:15px;color:var(--green);font-family:monospace;">WQ-01</b>
            </div>
            <div style="background:#FFFDF8;padding:10px;border-radius:8px;border:1px solid #E8DFCC;text-align:center;">
                <span class="muted" style="font-size:11px;display:block;">عدد القرّاء المتعاقبين</span>
                <b id="passport-readers-count" style="font-size:15px;color:var(--gold);">1</b>
            </div>
            <div style="grid-column:1/-1;background:#FFFDF8;padding:10px;border-radius:8px;border:1px solid #E8DFCC;">
                <span class="muted" style="font-size:11px;display:block;margin-bottom:2px;">المدن التي سافرت إليها هذه النسخة:</span>
                <span id="passport-cities-text" style="font-weight:700;font-size:13px;color:var(--ink);">الرياض</span>
            </div>
        </div>

        <!-- Margin Notes Feed -->
        <div style="margin-bottom:16px;">
            <h4 style="margin:0 0 8px;font-size:13.5px;color:var(--green);">هوامش وانطباعات القرّاء السابقين:</h4>
            <div id="passport-notes-box" style="display:grid;gap:8px;max-height:180px;overflow-y:auto;padding:4px;"></div>
        </div>

        <!-- Add Margin Note Form -->
        <form id="passport-note-form" method="POST" action="">
            @csrf
            <div style="display:grid;gap:10px;background:#FAF8F3;border:1px solid #E4DAC4;border-radius:10px;padding:12px;">
                <div style="display:grid;gap:4px;">
                    <label style="font-weight:600;font-size:12.5px;">سجّل بصمتك وانطباعك في هوامش النسخة:</label>
                    <textarea name="margin_note" rows="2" placeholder="اكتب خاطرة أو انطباعاً تتركه لمن يقرأ هذه النسخة بعدك..." required maxlength="500"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;">
                    <input name="city" placeholder="المدينة (مثال: الشارقة، فاس، الإسكندرية)" maxlength="60" style="font-size:12.5px;">
                    <button type="submit" class="primary btn-sm" style="padding:8px 16px;white-space:nowrap;font-weight:700;">
                        <span>تخليد البصمة ✦</span>
                    </button>
                </div>
            </div>
        </form>
    </section>
</div>

<!-- 9. 3D FlipBook Modal (Full Manuscript Experience) -->
<div id="book-open-modal" class="overlay book-3d-overlay" style="display:none;" onclick="if(event.target === this) closeBookModal()">
    <div class="book-modal-stage">
        <div class="book-modal-topbar">
            <span class="sanctuary-modal-brand">
                <i data-lucide="book-open" style="width:14px;height:14px;color:var(--gold);"></i>
                <span>محراب رِواق للقراءة المادية · تجربة التصفح 3D</span>
            </span>
            <button class="book-modal-close" onclick="closeBookModal()" title="إغلاق الكتاب">&times;</button>
        </div>

        <div class="book-volume-perspective" id="bmodal-book-box">
            <div class="book-case book-open-state" id="bmodal-case">
                <div class="book-spine-block" id="bmodal-spine"></div>

                <!-- Right Base Leaf: Fixed Back Cover & Underlay -->
                <div class="book-leaf leaf-right" id="bmodal-leaf-right">
                    <div class="page-sheet-underlay underlay-2"></div>
                    <div class="page-sheet-underlay underlay-1"></div>

                    <div class="book-open-page page-right">
                        <div class="page-paper">
                            <!-- Manuscript Border Frame -->
                            <div class="manuscript-border-frame">
                                <span class="corner-knot knot-tl">✦</span>
                                <span class="corner-knot knot-tr">✦</span>
                                <span class="corner-knot knot-bl">✦</span>
                                <span class="corner-knot knot-br">✦</span>
                            </div>

                            <!-- Gutter Shadow -->
                            <div class="page-gutter-shadow gutter-right"></div>

                            <!-- Running Header -->
                            <div class="manuscript-running-head head-right">
                                <span class="manuscript-page-no" id="bmodal-page-num-right">صـ ١٢٠</span>
                                <span class="head-ornament">❧ ✦ ❧</span>
                                <span class="head-title">رِواق المعرفة · الخزانة المادية</span>
                            </div>

                            <!-- Decorative Arch Folio Vignette -->
                            <div class="folio-vignette-arch">
                                <div class="arch-vignette-inner">
                                    <div class="gold-bismillah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
                                    <div class="vignette-ornament">❖ ─── ✦ ─── ❖</div>
                                </div>
                            </div>

                            <!-- Manuscript Body Text -->
                            <div class="manuscript-body-content">
                                <p class="manuscript-dropcap-para">
                                    <span class="dropcap-box" id="bmodal-dropcap">ك</span>
                                    <span id="bmodal-intro-text">
                                        كتابٌ جامعٌ لأصول الأدب ومحاسن الفكر، حُفظت نسخته في رفوف الرواق لتكون مناراً لسالكي درب المعرفة ومرتعاً للأفهام المستنيرة.
                                    </span>
                                </p>
                            </div>

                            <!-- Archival Seal Stamp -->
                            <div class="archival-seal-cluster">
                                <div class="wax-seal-outer">
                                    <div class="wax-seal-inner">
                                        <span>مُعْتَمَد</span>
                                        <small>رِوَاق</small>
                                    </div>
                                </div>
                                <div class="curator-signature">
                                    <span class="sig-title">خزانة المخطوطات</span>
                                    <span class="sig-name">قُيّد بالفهرس المعتمد</span>
                                </div>
                            </div>

                            <!-- Page Footer -->
                            <div class="page-footer-tag">
                                <span>صحيفة الفهرسة المادية والتوثيق الأرشيفي ✦</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Left Leaf (Opening Hardcover Face & Page) -->
                <div class="book-leaf leaf-left is-page-spread" id="bmodal-leaf-left">
                    <div class="leaf-face face-inside">
                        <div class="page-sheet-underlay underlay-2"></div>
                        <div class="page-sheet-underlay underlay-1"></div>

                        <div class="book-open-page page-left">
                            <div class="page-paper">
                                <div class="manuscript-border-frame">
                                    <span class="corner-knot knot-tl">✦</span>
                                    <span class="corner-knot knot-tr">✦</span>
                                    <span class="corner-knot knot-bl">✦</span>
                                    <span class="corner-knot knot-br">✦</span>
                                </div>

                                <div class="page-gutter-shadow gutter-left"></div>

                                <div class="manuscript-running-head head-left">
                                    <span class="head-title">بطاقة المصنف والنبذة المعتمدة</span>
                                    <span class="head-ornament">❧ ✦ ❧</span>
                                    <span class="manuscript-page-no" id="bmodal-page-num-left">صـ ١٢١</span>
                                </div>

                                <div class="manuscript-folio-header">
                                    <span class="folio-eyebrow">خزانة الآداب والعلوم · فهرس الرواق ✦</span>
                                    <h2 id="bmodal-title" class="manuscript-work-title">عنوان الكتاب</h2>
                                    <div class="manuscript-author-line">
                                        <i data-lucide="pen-tool" style="width:13px;height:13px;color:var(--gold);"></i>
                                        <span id="bmodal-author-cat" class="author-cat-text">المؤلف والتصنيف</span>
                                    </div>
                                </div>

                                <div class="page-divider-ornament">
                                    <span>❧ ─── ✦ ─── ❧</span>
                                </div>

                                <div class="manuscript-synopsis-container">
                                    <div class="synopsis-label-row">
                                        <i data-lucide="scroll" style="width:13px;height:13px;color:var(--green);"></i>
                                        <h4>فحوى المصنف والنبذة:</h4>
                                    </div>
                                    <p id="bmodal-desc" class="book-synopsis-text"></p>
                                </div>

                                <div class="page-actions-bottom" style="display:flex;flex-direction:column;gap:8px;">
                                    <!-- Add to Personal Library Button -->
                                    <form id="bmodal-add-library-form" method="POST" action="{{ route('libraries.addToPersonal') }}" style="margin:0;">
                                        @csrf
                                        <input type="hidden" name="book_id" id="bmodal-add-library-book-id" value="">
                                        <button type="submit" id="bmodal-add-library-btn" class="book-add-royal-btn" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:9px 16px;background:linear-gradient(135deg, #FAF6EC 0%, #F3EBD8 100%);color:#173D36;border:1.5px solid #C3A15D;border-radius:8px;font-size:13px;font-weight:700;font-family:'Noto Naskh Arabic',serif;cursor:pointer;box-shadow:0 3px 10px rgba(195,161,93,0.18);transition:all 0.25s ease;">
                                            <i data-lucide="library-big" class="btn-add-icon" style="width:16px;height:16px;color:#C3A15D;"></i>
                                            <span id="bmodal-add-library-text" class="btn-add-text">أضف لمكتبتي الشخصية ✦</span>
                                        </button>
                                    </form>

                                    @if($bookLendingEnabled)
                                    <!-- Loan Button -->
                                    <form id="bmodal-loan-form" method="POST" action="" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="primary book-loan-royal-btn book-loan-btn">
                                            <i data-lucide="book-copy" class="btn-royal-icon"></i>
                                            <span class="btn-royal-text">طلب استعارة نسخة ورقية</span>
                                            <i data-lucide="arrow-left" class="btn-royal-arrow"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>

                                <div class="page-footer-tag">
                                    <span>رِواق المعرفة · خزانة الفهرس · مساحتك للقراءة الهادئة</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outside Face: Leather Cover / Real Image -->
                    <div class="leaf-face face-outside" id="bmodal-cover-face" title="انقر لفتح الكتاب">
                        <img id="bmodal-cover-face-img" src="" alt="غلاف الكتاب المادي" class="bmodal-face-real-img" style="display:none;">
                        <div class="bmodal-face-crease-overlay" id="bmodal-face-crease-overlay" style="display:none;"></div>

                        <div class="brass-corner corner-tr" style="z-index:15;">
                            <svg viewBox="0 0 40 40" width="30" height="30"><path d="M40 0 L0 0 L0 8 L32 8 L32 40 L40 40 Z" fill="#C3A15D"/><circle cx="26" cy="14" r="2.5" fill="#5F4B23"/></svg>
                        </div>
                        <div class="brass-corner corner-br" style="z-index:15;">
                            <svg viewBox="0 0 40 40" width="30" height="30"><path d="M40 40 L0 40 L0 32 L32 32 L32 0 L40 0 Z" fill="#C3A15D"/><circle cx="26" cy="26" r="2.5" fill="#5F4B23"/></svg>
                        </div>

                        <div class="cover-frame-gold" id="bmodal-face-typography">
                            <div class="cover-emblem">
                                <i data-lucide="book-marked" style="width:38px;height:38px;color:var(--gold);"></i>
                            </div>
                            <h3 id="bmodal-cover-flap-title" class="flap-title">عنوان الكتاب</h3>
                            <span id="bmodal-cover-flap-author" class="flap-author">المؤلف</span>
                            <div class="cover-flap-divider">✦ ✧ ✦</div>
                            <small class="cover-flap-brand">رِواق المعرفة</small>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function openAddBookModal(libraryId) {
    const libSelect = document.getElementById('select-target-lib');
    if (libSelect && libraryId) {
        libSelect.value = libraryId;
    }
    openModal('add-book-modal');
    if (window.lucide) window.lucide.createIcons();
}

function openUpdateConditionModal(copyId, condition, status, shelf, notes) {
    const form = document.getElementById('update-condition-form');
    if (form) form.action = `/libraries/copies/${copyId}/condition`;

    const condSelect = document.getElementById('copy-condition-select');
    if (condSelect) condSelect.value = condition || 'good';

    const statusSelect = document.getElementById('copy-status-select');
    if (statusSelect) statusSelect.value = status || 'available';

    const shelfInput = document.getElementById('copy-shelf-input');
    if (shelfInput) shelfInput.value = shelf || '';

    const notesInput = document.getElementById('copy-notes-input');
    if (notesInput) notesInput.value = notes || '';

    openModal('update-condition-modal');
    if (window.lucide) window.lucide.createIcons();
}

function openHandoverLoanModal(loanId, title, currentCondition) {
    const form = document.getElementById('handover-loan-form');
    if (form) form.action = `/libraries/loans/${loanId}/status`;

    const titleEl = document.getElementById('handover-modal-book-title');
    if (titleEl) titleEl.textContent = `تسليم وفحص نسخة «${title}»`;

    const condSelect = document.getElementById('handover-cond-select');
    if (condSelect) condSelect.value = currentCondition || 'good';

    openModal('handover-loan-modal');
    if (window.lucide) window.lucide.createIcons();
}

function openReturnLoanModal(loanId, title, currentCondition) {
    const form = document.getElementById('return-loan-form');
    if (form) form.action = `/libraries/loans/${loanId}/status`;

    const titleEl = document.getElementById('return-modal-book-title');
    if (titleEl) titleEl.textContent = `فحص واسترجاع نسخة «${title}»`;

    const condSelect = document.getElementById('return-cond-select');
    if (condSelect) condSelect.value = currentCondition || 'good';

    openModal('return-loan-modal');
    if (window.lucide) window.lucide.createIcons();
}

function openLoanModal(copyId, title) {
    const form = document.getElementById('loan-request-form');
    const titleEl = document.getElementById('loan-modal-book-title');
    if (form) form.action = `/libraries/copies/${copyId}/loan`;
    if (titleEl) titleEl.textContent = `طلب استعارة: «${title}»`;
    openModal('loan-modal');
    if (window.lucide) window.lucide.createIcons();
}

function openPassportModal(copyId, title, barcode, readersCount, cities, notes) {
    document.getElementById('passport-book-title').textContent = title;
    document.getElementById('passport-barcode').textContent = barcode;
    document.getElementById('passport-readers-count').textContent = readersCount || '1';
    
    const citiesArr = (cities || 'الرياض').split('،').map(c => c.trim()).filter(Boolean);
    document.getElementById('passport-cities-text').textContent = cities || 'الرياض';

    const form = document.getElementById('passport-note-form');
    if (form) form.action = `/libraries/copies/${copyId}/passport-note`;

    const notesBox = document.getElementById('passport-notes-box');
    notesBox.innerHTML = '';

    if (notes && notes.trim().length > 0) {
        const notesList = notes.split(/\n\n|---/).filter(n => n.trim().length > 0);
        notesList.forEach(n => {
            const noteDiv = document.createElement('div');
            noteDiv.style.cssText = 'background:#FFFDF8;padding:8px 10px;border-radius:6px;border:1px solid #E8DFCC;font-size:12px;margin-bottom:4px;';
            noteDiv.innerHTML = `<span style="color:var(--gold);font-weight:700;">✦ ختم القارئ:</span> ${n.trim()}`;
            notesBox.appendChild(noteDiv);
        });
    } else {
        notesBox.innerHTML = `
            <div style="font-size:12.5px;color:#8B826E;font-style:italic;padding:8px 0;">
                كن أول من يترك بصمته وانطباعه على كعب هذه النسخة!
            </div>
        `;
    }

    openModal('passport-modal');
    if (window.lucide) window.lucide.createIcons();
}

function showQrHandshake(token, title, status) {
    const tokenDisplay = document.getElementById('qr-token-display');
    const subDisplay = document.getElementById('qr-modal-sub');
    if (tokenDisplay) tokenDisplay.textContent = token || 'RWQ-QR-HANDSHAKE';
    if (subDisplay) {
        subDisplay.textContent = status === 'active' 
            ? `النسخة مستعارة وموثقة بـ QR: «${title}»` 
            : `رمز استلام «${title}» — أظهره لأمين المكتبة لتأكيد التسليم`;
    }
    openModal('qr-handshake-modal');
    if (window.lucide) window.lucide.createIcons();
}

function openBookFromCard(cardOrStage) {
    const card = cardOrStage.closest('.physical-copy-card');
    if (!card) return;

    const id = card.dataset.id;
    const title = card.dataset.title;
    const author = card.dataset.author;
    const category = card.dataset.category;
    const pages = card.dataset.pages;
    const color = card.dataset.color || '#315749';
    const cover = card.dataset.cover || '';
    const desc = card.dataset.desc;
    const loanUrl = card.dataset.loanUrl;
    const isBorrowed = card.dataset.isBorrowed === '1';

    document.getElementById('bmodal-title').textContent = title;
    document.getElementById('bmodal-author-cat').textContent = `${author} · ${category} (${pages} صفحة)`;
    document.getElementById('bmodal-desc').textContent = desc || 'مصنف معتمد ومحفوظ في رفوف خزانة رِواق المعرفة.';
    document.getElementById('bmodal-dropcap').textContent = (title.trim()[0] || 'ك');

    @if($bookLendingEnabled)
    const form = document.getElementById('bmodal-loan-form');
    if (form) {
        const btn = form.querySelector('.book-loan-btn');
        if (loanUrl && !isBorrowed) {
            form.action = loanUrl;
            form.style.display = 'block';
            if (btn) {
                btn.disabled = false;
                const btnTxt = btn.querySelector('.btn-royal-text');
                if (btnTxt) btnTxt.textContent = 'طلب استعارة نسخة ورقية';
            }
        } else {
            form.style.display = 'none';
        }
    }
    @endif

    const inLibrary = card.dataset.inLibrary === '1' || card.dataset.inLibrary === 'true';
    const addLibForm = document.getElementById('bmodal-add-library-form');
    const addLibBookId = document.getElementById('bmodal-add-library-book-id');
    const addLibBtn = document.getElementById('bmodal-add-library-btn');
    const addLibText = document.getElementById('bmodal-add-library-text');

    if (addLibBookId) addLibBookId.value = id;
    if (addLibBtn) {
        if (inLibrary) {
            addLibBtn.disabled = true;
            addLibBtn.style.background = 'rgba(33, 79, 52, 0.15)';
            addLibBtn.style.color = '#173D36';
            addLibBtn.style.borderColor = 'rgba(74, 222, 128, 0.5)';
            addLibBtn.style.cursor = 'default';
            if (addLibText) addLibText.innerHTML = '<i data-lucide="check-circle" style="width:15px;height:15px;display:inline-block;vertical-align:middle;color:#1F5148;margin-inline-end:4px;"></i> موجود في مكتبتك الشخصية ✦';
        } else {
            addLibBtn.disabled = false;
            addLibBtn.style.background = 'linear-gradient(135deg, #FAF6EC 0%, #F3EBD8 100%)';
            addLibBtn.style.color = '#173D36';
            addLibBtn.style.borderColor = '#C3A15D';
            addLibBtn.style.cursor = 'pointer';
            if (addLibText) addLibText.innerHTML = '<i data-lucide="library-big" style="width:15px;height:15px;display:inline-block;vertical-align:middle;color:#C3A15D;margin-inline-end:4px;"></i> أضف لمكتبتي الشخصية ✦';
        }
    }

    // Cover Image setup
    const coverFace = document.getElementById('bmodal-cover-face');
    const faceImg = document.getElementById('bmodal-cover-face-img');
    const creaseOverlay = document.getElementById('bmodal-face-crease-overlay');
    const typography = document.getElementById('bmodal-face-typography');

    if (cover) {
        coverFace.style.background = '#1a1816';
        if (faceImg) {
            faceImg.src = cover;
            faceImg.style.display = 'block';
        }
        if (creaseOverlay) creaseOverlay.style.display = 'block';
        if (typography) typography.style.display = 'none';
    } else {
        coverFace.style.background = color;
        if (faceImg) faceImg.style.display = 'none';
        if (creaseOverlay) creaseOverlay.style.display = 'none';
        if (typography) {
            typography.style.display = 'flex';
            document.getElementById('bmodal-cover-flap-title').textContent = title;
            document.getElementById('bmodal-cover-flap-author').textContent = author;
        }
    }

    const spine = document.getElementById('bmodal-spine');
    if (spine) spine.style.background = color;

    document.getElementById('book-open-modal').style.display = 'flex';
    if (window.lucide) window.lucide.createIcons();
}

function closeBookModal() {
    document.getElementById('book-open-modal').style.display = 'none';
}

// CamScanner & Autocomplete logic
document.addEventListener('DOMContentLoaded', () => {
    const titleInput = document.getElementById('book-input-title');
    const suggestionsBox = document.getElementById('duplicate-suggestions-box');
    const suggestionsList = document.getElementById('duplicate-suggestions-list');
    const indicator = document.getElementById('title-search-indicator');
    const selectedAlert = document.getElementById('selected-book-alert');
    const selectedTitleDisplay = document.getElementById('selected-book-title-display');
    const cancelSelectBtn = document.getElementById('btn-cancel-existing-selection');
    const existingIdInput = document.getElementById('book-input-existing-id');

    let debounceTimer = null;

    const coverStudio = document.querySelector('.camscanner-studio');
    const coverDropzone = coverStudio ? coverStudio.querySelector('.camscanner-dropzone') : null;
    const coverPreviewBox = document.getElementById('camscanner-preview-box');
    const coverPreviewImg = document.getElementById('camscanner-preview-img');
    const coverBadge = document.getElementById('camscanner-badge');
    const coverFileInput = document.getElementById('book-cover-input');

    const resolveCover = (src) => {
        if (!src) return '';
        return /^(https?:|data:|\/)/.test(src) ? src : '{{ rtrim(asset(''), '/') }}/' + src;
    };

    // يعرض غلاف الكتاب المختار من الفهرس بدل رفع غلاف جديد
    function showCatalogCover(book) {
        const url = resolveCover(book.cover_image);
        if (!url || !coverPreviewImg) return;
        if (coverFileInput) coverFileInput.value = '';
        coverPreviewImg.src = url;
        if (coverBadge) coverBadge.textContent = 'غلاف الكتاب من الفهرس ✓';
        if (coverPreviewBox) coverPreviewBox.style.display = 'block';
        if (coverDropzone) coverDropzone.style.display = 'none';
    }

    function resetCoverPreview() {
        if (coverPreviewBox) coverPreviewBox.style.display = 'none';
        if (coverPreviewImg) coverPreviewImg.src = '';
        if (coverBadge) coverBadge.textContent = 'تم التحسين بـ CamScanner ✓';
        if (coverDropzone) coverDropzone.style.display = '';
    }

    if (titleInput && suggestionsBox) {
        titleInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            if (existingIdInput.value) {
                existingIdInput.value = '';
                if (selectedAlert) selectedAlert.style.display = 'none';
                resetCoverPreview();
            }

            clearTimeout(debounceTimer);
            if (query.length < 2) {
                suggestionsBox.style.display = 'none';
                if (indicator) indicator.style.display = 'none';
                return;
            }

            if (indicator) indicator.style.display = 'inline-flex';

            debounceTimer = setTimeout(() => {
                fetch(`{{ route('libraries.searchExisting') }}?q=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (indicator) indicator.style.display = 'none';
                        if (data.count > 0) {
                            suggestionsList.innerHTML = '';
                            data.books.forEach(bk => {
                                const item = document.createElement('div');
                                item.style.cssText = 'padding:6px 10px;background:#FFFDF8;border:1px solid #E8DFCC;border-radius:6px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;';
                                const thumb = resolveCover(bk.cover_image);
                                item.innerHTML = `<div style="display:flex;align-items:center;gap:8px;">${thumb ? `<img src="${thumb}" alt="" style="width:28px;height:40px;object-fit:cover;border-radius:3px;">` : ''}<span><strong>${bk.title}</strong> · <small class="muted">${bk.author}</small></span></div><span class="tag">ربط نسخة</span>`;
                                item.onclick = () => {
                                    titleInput.value = bk.title;
                                    document.getElementById('book-input-author').value = bk.author;
                                    document.getElementById('book-input-category').value = bk.category;
                                    document.getElementById('book-input-pages').value = bk.total_pages;
                                    existingIdInput.value = bk.id;
                                    suggestionsBox.style.display = 'none';
                                    showCatalogCover(bk);
                                    if (selectedAlert) {
                                        selectedTitleDisplay.textContent = `«${bk.title}» للمؤلف ${bk.author}`;
                                        selectedAlert.style.display = 'flex';
                                    }
                                };
                                suggestionsList.appendChild(item);
                            });
                            suggestionsBox.style.display = 'block';
                        } else {
                            suggestionsBox.style.display = 'none';
                        }
                    })
                    .catch(() => {
                        if (indicator) indicator.style.display = 'none';
                    });
            }, 300);
        });
    }

    if (cancelSelectBtn) {
        cancelSelectBtn.addEventListener('click', () => {
            existingIdInput.value = '';
            selectedAlert.style.display = 'none';
            resetCoverPreview();
        });
    }

    // ===== البحث بالـ ISBN (Open Library / Google Books / WorldCat / Amazon) =====
    const isbnInput = document.getElementById('book-input-isbn');
    const isbnBtn = document.getElementById('btn-isbn-lookup');
    const isbnStatus = document.getElementById('isbn-lookup-status');
    const coverUrlInput = document.getElementById('book-input-cover-url');
    const descInput = document.getElementById('book-input-description');
    const langInput = document.getElementById('book-input-language');

    function setIsbnStatus(message, kind) {
        if (!isbnStatus) return;
        isbnStatus.textContent = message;
        isbnStatus.dataset.kind = kind || '';
        isbnStatus.hidden = !message;
    }

    function renderIsbnPreview(res) {
        const box = document.getElementById('isbn-preview');
        if (!box) return;
        const b = res.book || {};
        const cover = document.getElementById('isbn-preview-cover');
        if (b.cover) { cover.src = b.cover; cover.hidden = false; } else { cover.hidden = true; }
        document.getElementById('isbn-preview-title').textContent = b.title || '';
        document.getElementById('isbn-preview-author').textContent = b.author ? 'تأليف: ' + b.author : '';

        const facts = document.getElementById('isbn-preview-facts');
        facts.innerHTML = '';
        [
            ['الناشر', b.publisher],
            ['الصفحات', b.pages],
            ['اللغة', b.language],
            ['التصنيف', b.category],
            ['ISBN', b.isbn || res.isbn],
        ].forEach(function (pair) {
            if (!pair[1]) return;
            const li = document.createElement('li');
            const label = document.createElement('span');
            label.textContent = pair[0];
            const value = document.createElement('b');
            value.textContent = String(pair[1]);
            li.appendChild(label);
            li.appendChild(value);
            facts.appendChild(li);
        });

        document.getElementById('isbn-preview-source').textContent = 'المصدر: ' + (res.sources || []).join(' + ');
        box.hidden = false;
    }

    function fillFromLookup(res) {
        const b = res.book || {};
        if (b.title) titleInput.value = b.title;
        if (b.author) document.getElementById('book-input-author').value = b.author;
        if (b.pages) document.getElementById('book-input-pages').value = b.pages;
        const publisher = document.getElementById('book-input-publisher');
        if (b.publisher && publisher) publisher.value = b.publisher;
        const category = document.getElementById('book-input-category');
        if (b.category && category && Array.prototype.some.call(category.options, (o) => o.value === b.category)) {
            category.value = b.category;
        }
        if (coverUrlInput) coverUrlInput.value = res.status === 'found' && b.cover ? b.cover : '';
        if (descInput) descInput.value = b.description || '';
        if (langInput) langInput.value = b.language || '';
        renderIsbnPreview(res);

        if (res.status === 'catalog') {
            existingIdInput.value = b.id;
            if (selectedAlert) {
                selectedTitleDisplay.textContent = '«' + b.title + '» للمؤلف ' + (b.author || '');
                selectedAlert.style.display = 'flex';
            }
        } else if (existingIdInput.value) {
            existingIdInput.value = '';
            if (selectedAlert) selectedAlert.style.display = 'none';
        }

        if (b.cover && coverPreviewImg) {
            if (coverFileInput) coverFileInput.value = '';
            coverPreviewImg.src = b.cover;
            if (coverBadge) coverBadge.textContent = 'الغلاف من ' + (res.sources || []).join(' + ');
            if (coverPreviewBox) coverPreviewBox.style.display = 'block';
            if (coverDropzone) coverDropzone.style.display = 'none';
        }
    }

    async function runIsbnLookup() {
        const raw = (isbnInput.value || '').replace(/[^0-9Xx]/g, '');
        if (raw.length !== 10 && raw.length !== 13) {
            setIsbnStatus('أدخل رقم ISBN من 10 أو 13 خانة.', 'error');
            return;
        }
        isbnBtn.disabled = true;
        setIsbnStatus('جاري البحث في المصادر…', 'loading');
        try {
            const r = await fetch('{{ url('/books/isbn') }}/' + encodeURIComponent(raw), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            const res = await r.json();
            if (res.status === 'found' || res.status === 'catalog') {
                fillFromLookup(res);
                const labels = { publisher: 'الناشر', pages: 'عدد الصفحات', cover: 'صورة الغلاف', description: 'الوصف' };
                const missing = (res.missing || []).map((k) => labels[k]).filter(Boolean);
                if (missing.length) {
                    setIsbnStatus(res.message + ' ⚠️ لم تتوفر في أي مصدر: ' + missing.join('، ') + '. أكملها يدويًا' + (res.missing.includes('cover') ? ' وارفع صورة الغلاف من الأسفل.' : '.'), 'warn');
                } else {
                    setIsbnStatus(res.message, 'ok');
                }
            } else {
                setIsbnStatus(res.message || 'تعذّر جلب البيانات.', 'error');
            }
        } catch (e) {
            setIsbnStatus('تعذّر الاتصال بخدمة البحث. حاول لاحقًا أو أدخل البيانات يدويًا.', 'error');
        } finally {
            isbnBtn.disabled = false;
        }
    }

    if (isbnBtn && isbnInput) {
        isbnBtn.addEventListener('click', runIsbnLookup);
        isbnInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); runIsbnLookup(); }
        });
    }

    // CamScanner Photo Processing
    const coverInput = document.getElementById('book-cover-input');
    const previewBox = document.getElementById('camscanner-preview-box');
    const previewImg = document.getElementById('camscanner-preview-img');
    const enhancedDataInput = document.getElementById('cover-image-enhanced-data');

    if (coverInput) {
        coverInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    ctx.drawImage(img, 0, 0);

                    // CamScanner contrast & sharpen
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const d = imgData.data;
                    const contrast = 1.15;
                    const intercept = 128 * (1 - contrast);

                    for (let i = 0; i < d.length; i += 4) {
                        d[i] = d[i] * contrast + intercept;
                        d[i+1] = d[i+1] * contrast + intercept;
                        d[i+2] = d[i+2] * contrast + intercept;
                    }
                    ctx.putImageData(imgData, 0, 0);

                    const enhancedUrl = canvas.toDataURL('image/jpeg', 0.9);
                    if (previewImg) previewImg.src = enhancedUrl;
                    if (coverBadge) coverBadge.textContent = 'تم التحسين بـ CamScanner ✓';
                    if (enhancedDataInput) enhancedDataInput.value = enhancedUrl;
                    if (previewBox) previewBox.style.display = 'block';
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }
});
</script>
@endsection
