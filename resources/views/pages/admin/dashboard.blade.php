@extends('layouts.app')

@section('title', 'لوحة Super Admin')
@section('crumb', 'لوحة الإدارة العامة للمنصة')

@section('content')
<div class="ch-head">
    <div>
        <h1>لوحة إدارة المنصة (Super Admin)</h1>
        <p>مركز التحكم بالمستخدمين والمحتوى والبلاغات والإعدادات العامة لرِواق المعرفة.</p>
    </div>
    <span class="rq-chip green"><i data-lucide="shield-check"></i>صلاحيات المشرف العام</span>
</div>

<!-- Super Admin Tabs Bar -->
<div class="ch-toolbar">
    <div class="rq-tabs pf-tabs" role="tablist">
        <a href="{{ route('admin.dashboard', ['tab' => 'overview']) }}" class="{{ $tab === 'overview' ? 'on' : '' }}">
            <i data-lucide="layout-grid" style="width:14px;height:14px;"></i>
            <span>نظرة عامة ومؤشرات</span>
        </a>
        <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}" class="{{ $tab === 'users' ? 'on' : '' }}">
            <i data-lucide="users" style="width:14px;height:14px;"></i>
            <span>إدارة المستخدمين والحسابات</span>
            <span class="tab-counter">{{ $stats['users_count'] }}</span>
        </a>
        <a href="{{ route('admin.dashboard', ['tab' => 'content']) }}" class="{{ $tab === 'content' ? 'on' : '' }}">
            <i data-lucide="message-square" style="width:14px;height:14px;"></i>
            <span>إدارة المحتوى والمناقشات</span>
            <span class="tab-counter">{{ $stats['discussions_count'] }}</span>
        </a>
        <a href="{{ route('admin.dashboard', ['tab' => 'reports']) }}" class="{{ $tab === 'reports' ? 'on' : '' }}">
            <i data-lucide="alert-triangle" style="width:14px;height:14px;"></i>
            <span>البلاغات والرقابة المركزية</span>
            @if($stats['pending_reports_count'] > 0)
                <span class="tab-counter counter-unread">{{ $stats['pending_reports_count'] }}</span>
            @endif
        </a>
        <a href="{{ route('admin.dashboard', ['tab' => 'audit_logs']) }}" class="{{ $tab === 'audit_logs' ? 'on' : '' }}">
            <i data-lucide="clipboard-list" style="width:14px;height:14px;"></i>
            <span>سجل التدقيق والرقابة (Audit Log)</span>
            <span class="tab-counter">{{ $auditLogs->count() }}</span>
        </a>
        <a href="{{ route('admin.dashboard', ['tab' => 'settings']) }}" class="{{ $tab === 'settings' ? 'on' : '' }}">
            <i data-lucide="sliders" style="width:14px;height:14px;"></i>
            <span>الإعدادات العامة للمنصة</span>
        </a>
    </div>
</div>

<!-- TAB 1: OVERVIEW -->
@if($tab === 'overview')
<div class="animate-fade-in">
    <!-- KPI Metrics Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));gap:16px;margin-bottom:28px;">
        <div class="panel" style="padding:18px;">
            <span class="eyebrow">إجمالي المستخدمين</span>
            <div style="font-size:28px;font-weight:700;color:var(--green);font-family:'Noto Naskh Arabic';">{{ $stats['users_count'] }}</div>
            <small class="muted">{{ $stats['suspended_users_count'] }} حسابات موقوفة</small>
        </div>

        <div class="panel" style="padding:18px;">
            <span class="eyebrow">عناوين الكتب</span>
            <div style="font-size:28px;font-weight:700;color:var(--green);font-family:'Noto Naskh Arabic';">{{ $stats['books_count'] }}</div>
            <small class="muted">{{ $stats['copies_count'] }} نسخ ورقية موثقة</small>
        </div>

        <div class="panel" style="padding:18px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="eyebrow">الإعارات النشطة</span>
                @if(!$bookLendingEnabled)
                    <span class="tag" style="background:#757575;color:#fff;font-size:10px;padding:1px 6px;">معطّل في الموقع</span>
                @endif
            </div>
            <div style="font-size:28px;font-weight:700;color:var(--gold);font-family:'Noto Naskh Arabic';">{{ $stats['active_loans_count'] }}</div>
            <small class="muted">{{ $stats['pending_loans_count'] }} طلبات قيد المراجعة</small>
        </div>

        <div class="panel" style="padding:18px;">
            <span class="eyebrow">نوادي القراءة</span>
            <div style="font-size:28px;font-weight:700;color:var(--green);font-family:'Noto Naskh Arabic';">{{ $stats['communities_count'] }}</div>
            <small class="muted">{{ $stats['discussions_count'] }} مناقشات نشطة</small>
        </div>

        <div class="panel" style="padding:18px;">
            <span class="eyebrow">جلسات القراءة</span>
            <div style="font-size:28px;font-weight:700;color:var(--forest);font-family:'Noto Naskh Arabic';">{{ $stats['sessions_count'] }}</div>
            <small class="muted">{{ $stats['total_minutes'] }} دقيقة قراءة موثقة</small>
        </div>

        <div class="panel" style="padding:18px;">
            <span class="eyebrow">البلاغات المعلقة</span>
            <div style="font-size:28px;font-weight:700;color:{{ $stats['pending_reports_count'] > 0 ? '#8F3829' : 'var(--green)' }};font-family:'Noto Naskh Arabic';">
                {{ $stats['pending_reports_count'] }}
            </div>
            <small class="muted">تتطلب المراجعة الفورية</small>
        </div>
    </div>

    <div class="grid2" style="margin-top:0;">
        <!-- Recent Loans Oversight -->
        <section class="panel animate-fade-in delay-1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h2 style="margin:0;">أحدث طلبات وعمليات الإعارة</h2>
                <span class="tag">{{ $recentLoans->count() }} إعارات</span>
            </div>
            @forelse($recentLoans as $loan)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--line);">
                    <div>
                        <b style="font-size:14px;display:block;">{{ $loan->copy->book->title ?? 'كتاب' }}</b>
                        <small class="muted">المستعير: {{ $loan->borrower_name ?? $loan->user->name }} · رقم النسخة: {{ $loan->copy->copy_number ?? '1' }}</small>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <span class="tag" style="font-size:11px;">{{ $loan->status }}</span>
                        @if($loan->status === 'pending')
                        <form method="POST" action="{{ route('libraries.updateLoanStatus', $loan->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="primary" style="min-height:28px;padding:2px 10px;font-size:11px;">موافقة</button>
                        </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted" style="padding:20px 0;text-align:center;">لا توجد عمليات إعارة حديثة.</p>
            @endforelse
        </section>

        <!-- Books Catalog Oversight -->
        <section class="panel animate-fade-in delay-2">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h2 style="margin:0;">فهرس الكتب وحصر النسخ المادية</h2>
                <a href="{{ route('explore') }}" class="secondary btn-sm" style="font-size:11px;text-decoration:none;">الفهرس الكامل</a>
            </div>
            @foreach($books as $b)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--line);">
                    <div style="display:flex;gap:12px;align-items:center;">
                        <div style="width:12px;height:12px;border-radius:50%;background:{{ $b->cover_color }};"></div>
                        <div>
                            <b style="font-size:13.5px;display:block;">{{ $b->title }}</b>
                            <small class="muted">{{ $b->author }} · {{ $b->category }}</small>
                        </div>
                    </div>
                    <span class="tag" style="background:#E1ECE2;color:#235738;font-size:11px;">
                        {{ $b->copies_count }} نسخ مادية
                    </span>
                </div>
            @endforeach
        </section>
    </div>
</div>
@endif

<!-- TAB 2: USERS MANAGEMENT & SUSPENSION -->
@if($tab === 'users')
<div class="panel animate-fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:14px;">
        <div>
            <h2 style="margin:0 0 4px;">إدارة المستخدمين وتعيين الأدوار والتعليق</h2>
            <div class="muted" style="font-size:13px;">تعديل الرتب وتجميد الحسابات المخالفة للسياسات.</div>
        </div>

        <!-- Filter / Search Form -->
        <form method="GET" action="{{ route('admin.dashboard') }}" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="tab" value="users">
            <input type="text" name="user_search" value="{{ request('user_search') }}" placeholder="ابحث بالاسم أو البريد..." style="padding:6px 12px;border:1px solid var(--line);border-radius:8px;font-size:12.5px;outline:none;">
            <select name="role_filter" style="padding:6px 10px;border:1px solid var(--line);border-radius:8px;font-size:12.5px;outline:none;">
                <option value="">جميع الأدوار</option>
                <option value="super_admin" {{ request('role_filter') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                <option value="admin" {{ request('role_filter') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="user" {{ request('role_filter') === 'user' ? 'selected' : '' }}>User</option>
            </select>
            <button type="submit" class="secondary" style="min-height:34px;padding:4px 12px;font-size:12px;">تصفية</button>
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);color:var(--sub);">
                    <th style="padding:10px 14px;">المستخدم</th>
                    <th style="padding:10px 14px;">البريد الإلكتروني</th>
                    <th style="padding:10px 14px;">الدور والصلاحية</th>
                    <th style="padding:10px 14px;">الحالة</th>
                    <th style="padding:10px 14px;">النقاط والمستوى</th>
                    <th style="padding:10px 14px;">تعديل الصلاحية</th>
                    <th style="padding:10px 14px;">إجراء الحساب</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                    <tr style="border-bottom:1px solid var(--line);background:{{ $u->is_suspended ? 'rgba(143,56,41,0.03)' : 'transparent' }};">
                        <td style="padding:12px 14px;font-weight:600;display:flex;align-items:center;gap:10px;">
                            <span class="avatar" style="width:30px;height:30px;font-size:12px;">{{ $u->avatar ?? mb_substr($u->name, 0, 1) }}</span>
                            <div>
                                <a href="{{ route('profile.show', $u->id) }}" style="text-decoration:none;color:var(--green);font-weight:700;">
                                    {{ $u->name }}
                                </a>
                                @if($u->id === Auth::id())
                                    <span style="font-size:10px;color:var(--gold);margin-inline-start:4px;">(أنت)</span>
                                @endif
                            </div>
                        </td>
                        <td style="padding:12px 14px;" class="muted">{{ $u->email }}</td>
                        <td style="padding:12px 14px;">
                            <span class="tag" style="background:{{ $u->isSuperAdmin() ? '#173D36' : '#EEE9DD' }};color:{{ $u->isSuperAdmin() ? '#FFFDF8' : 'var(--ink)' }};">
                                {{ $u->role }}
                            </span>
                        </td>
                        <td style="padding:12px 14px;">
                            @if($u->is_suspended)
                                <span class="tag" style="background:#FBEAE5;color:#8F3829;border:1px solid #F5CAC2;" title="{{ $u->suspension_reason }}">
                                    موقوف
                                </span>
                            @else
                                <span class="tag" style="background:#E2ECDE;color:#214F34;">
                                    نشط
                                </span>
                            @endif
                        </td>
                        <td style="padding:12px 14px;">
                            <b>{{ $u->points }}</b> نقطة
                            <span class="muted" style="font-size:11px;display:block;">{{ $u->level_data['name'] }}</span>
                        </td>
                        <td style="padding:12px 14px;">
                            <form method="POST" action="{{ route('admin.updateRole', $u->id) }}" style="display:flex;gap:6px;align-items:center;" onsubmit="return confirm('تأكيد أمني: هل أنت متأكد من تغيير صلاحية ودور المستخدم «{{ $u->name }}»؟ سيتم توثيق هذا الإجراء الحساس في سجل التدقيق (Audit Log).');">
                                @csrf
                                <input type="hidden" name="confirmed" value="1">
                                <select name="role" style="min-height:30px;padding:2px 8px;font-size:11.5px;border-radius:6px;">
                                    <option value="user" {{ $u->role === 'user' ? 'selected' : '' }}>User</option>
                                    <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="super_admin" {{ $u->role === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                                </select>
                                <button type="submit" class="secondary" style="min-height:30px;padding:2px 8px;font-size:11.5px;">
                                    حفظ
                                </button>
                            </form>
                        </td>
                        <td style="padding:12px 14px;">
                            @if($u->id !== Auth::id())
                                <div style="display:flex;gap:4px;align-items:center;">
                                    <form method="POST" action="{{ route('admin.toggleSuspension', $u->id) }}" style="display:inline;" onsubmit="return confirm('تأكيد أمني: هل أنت متأكد من {{ $u->is_suspended ? 'فك تعليق' : 'تعليق وتجميد' }} حساب «{{ $u->name }}»؟ سيتم تسجيل الحدث في سجل التدقيق.');">
                                        @csrf
                                        <input type="hidden" name="confirmed" value="1">
                                        @if($u->is_suspended)
                                            <button type="submit" class="secondary" style="min-height:30px;padding:2px 10px;font-size:11px;color:var(--green);border-color:var(--green);">
                                                فك التعليق
                                            </button>
                                        @else
                                            <button type="submit" class="secondary" style="min-height:30px;padding:2px 10px;font-size:11px;color:#8F3829;border-color:#F5CAC2;">
                                                تعليق الحساب
                                            </button>
                                        @endif
                                    </form>
                                    <form method="POST" action="{{ route('admin.destroyUser', $u->id) }}" style="display:inline;" onsubmit="return confirm('تأكيد أمني: هل أنت متأكد من أرشفة وتعطيل حساب «{{ $u->name }}»؟ سيتم استخدام الحذف المرن الآمن (Soft Delete).');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="secondary" style="min-height:30px;padding:2px 8px;font-size:11px;color:#8F3829;border-color:#F5CAC2;" title="أرشفة وتعطيل الحساب بأمان">
                                            <i data-lucide="trash-2" style="width:12px;height:12px;vertical-align:middle;"></i>
                                        </button>
                                    </form>
                                </div>
                            @else
                                <small class="muted">حسابك الحالي</small>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- TAB 3: CONTENT & DISCUSSIONS MANAGEMENT -->
@if($tab === 'content')
<div class="panel animate-fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <div>
            <h2 style="margin:0 0 4px;">إدارة المحتوى والمناقشات والردود</h2>
            <div class="muted" style="font-size:13px;">حذف المشاركات المخالفة وتنقية الحوارات في نوادي الرواق.</div>
        </div>
        <span class="tag">{{ $discussions->count() }} مناقشة حديثة</span>
    </div>

    <!-- Discussions Table -->
    <div style="overflow-x:auto;margin-bottom:30px;">
        <h3 style="font-size:15px;margin-bottom:10px;">المناقشات المنشورة:</h3>
        <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);color:var(--sub);">
                    <th style="padding:10px 14px;">عنوان المناقشة</th>
                    <th style="padding:10px 14px;">النادي</th>
                    <th style="padding:10px 14px;">الكاتب</th>
                    <th style="padding:10px 14px;">الردود</th>
                    <th style="padding:10px 14px;">التاريخ</th>
                    <th style="padding:10px 14px;">الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($discussions as $disc)
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:12px 14px;font-weight:600;">
                            {{ $disc->title ?: (mb_substr($disc->content, 0, 40) . '...') }}
                            @if($disc->is_spoiler)
                                <span class="tag" style="background:#FAF4E6;color:#785419;font-size:10px;">حرق أحداث</span>
                            @endif
                        </td>
                        <td style="padding:12px 14px;">{{ $disc->community->name ?? '-' }}</td>
                        <td style="padding:12px 14px;">{{ $disc->user->name ?? '-' }}</td>
                        <td style="padding:12px 14px;">{{ $disc->comments_count }}</td>
                        <td style="padding:12px 14px;" class="muted">{{ $disc->created_at->locale('ar')->diffForHumans() }}</td>
                        <td style="padding:12px 14px;">
                            <form method="POST" action="{{ route('admin.deleteContent', ['type' => 'discussion', 'id' => $disc->id]) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذه المناقشة؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="secondary" style="min-height:28px;padding:2px 10px;font-size:11px;color:#8F3829;">
                                    حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:24px;text-align:center;" class="muted">لا توجد مناقشات بعد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Recent Comments Table -->
    <div style="overflow-x:auto;">
        <h3 style="font-size:15px;margin-bottom:10px;">أحدث التعليقات والردود:</h3>
        <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);color:var(--sub);">
                    <th style="padding:10px 14px;">نص التعليق</th>
                    <th style="padding:10px 14px;">المناقشة</th>
                    <th style="padding:10px 14px;">الكاتب</th>
                    <th style="padding:10px 14px;">التاريخ</th>
                    <th style="padding:10px 14px;">الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentComments as $cm)
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:12px 14px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $cm->content }}
                        </td>
                        <td style="padding:12px 14px;">{{ $cm->discussion->title ?? '-' }}</td>
                        <td style="padding:12px 14px;">{{ $cm->user->name ?? '-' }}</td>
                        <td style="padding:12px 14px;" class="muted">{{ $cm->created_at->locale('ar')->diffForHumans() }}</td>
                        <td style="padding:12px 14px;">
                            <form method="POST" action="{{ route('admin.deleteContent', ['type' => 'comment', 'id' => $cm->id]) }}" onsubmit="return confirm('هل تريد حذف هذا التعليق؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="secondary" style="min-height:28px;padding:2px 10px;font-size:11px;color:#8F3829;">
                                    حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:24px;text-align:center;" class="muted">لا توجد تعليقات منشورة بعد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- TAB 4: REPORTS & CENTRAL MODERATION -->
@if($tab === 'reports')
<div class="panel animate-fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <div>
            <h2 style="margin:0 0 4px;">طابور البلاغات والشكاوى المركزية</h2>
            <div class="muted" style="font-size:13px;">مراجعة بلاغات القراء عن حرق الأحداث أو المحتوى المسيء واتخاذ الإجراء الفوري.</div>
        </div>
        <span class="tag" style="background:{{ $stats['pending_reports_count'] > 0 ? '#FBEAE5' : '#E2ECDE' }};color:{{ $stats['pending_reports_count'] > 0 ? '#8F3829' : '#214F34' }};">
            {{ $stats['pending_reports_count'] }} بلاغات معلقة
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);color:var(--sub);">
                    <th style="padding:10px 14px;">المُبلّغ</th>
                    <th style="padding:10px 14px;">السبب</th>
                    <th style="padding:10px 14px;">التفاصيل</th>
                    <th style="padding:10px 14px;">نوع المحتوى</th>
                    <th style="padding:10px 14px;">الحالة</th>
                    <th style="padding:10px 14px;">إجراء الإدارة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $rep)
                    <tr style="border-bottom:1px solid var(--line);background:{{ $rep->status === 'pending' ? 'rgba(251,234,229,0.2)' : 'transparent' }};">
                        <td style="padding:12px 14px;font-weight:600;">
                            {{ $rep->user->name ?? 'قارئ' }}
                        </td>
                        <td style="padding:12px 14px;">
                            <span class="tag" style="background:#FAF4E6;color:#785419;font-size:11px;">
                                {{ $rep->reasonLabel() }}
                            </span>
                        </td>
                        <td style="padding:12px 14px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="muted">
                            {{ $rep->details ?: '-' }}
                        </td>
                        <td style="padding:12px 14px;">
                            <span class="tag" style="font-size:11px;">{{ $rep->typeLabel() }} #{{ $rep->reportable_id }}</span>
                            <small class="muted" style="display:block;margin-top:4px;max-width:200px;">{{ $rep->targetSummary() }}</small>
                        </td>
                        <td style="padding:12px 14px;">
                            @if($rep->status === 'pending')
                                <span class="tag" style="background:#FBEAE5;color:#8F3829;">قيد المراجعة</span>
                            @elseif($rep->status === 'action_taken')
                                <span class="tag" style="background:#E2ECDE;color:#214F34;">تم الإجراء</span>
                            @else
                                <span class="tag" style="background:#EBE6DB;color:#4F493D;">محفوظ</span>
                            @endif
                        </td>
                        <td style="padding:12px 14px;">
                            @if($rep->status === 'pending')
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <form method="POST" action="{{ route('admin.handleReport', $rep->id) }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="action" value="resolve_and_delete">
                                        <button type="submit" class="primary" style="min-height:28px;padding:2px 8px;font-size:11px;">
                                            حذف المحتوى
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.handleReport', $rep->id) }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="action" value="suspend_author">
                                        <button type="submit" class="secondary" style="min-height:28px;padding:2px 8px;font-size:11px;color:#8F3829;">
                                            تعليق الكاتب
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.handleReport', $rep->id) }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="action" value="dismiss">
                                        <button type="submit" class="secondary" style="min-height:28px;padding:2px 8px;font-size:11px;">
                                            تجاهل
                                        </button>
                                    </form>
                                </div>
                            @else
                                <small class="muted">{{ $rep->moderator_notes ?: 'مكتمل' }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:28px;text-align:center;" class="muted">لا توجد بلاغات مسجلة في النظام.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- TAB 5: GENERAL SYSTEM SETTINGS -->
@if($tab === 'settings')
<div class="panel animate-fade-in" style="max-width:760px;">
    <div style="margin-bottom:20px;border-bottom:1px solid var(--line);padding-bottom:12px;">
        <h2 style="margin:0 0 4px;">الإعدادات العامة وسياسات المنصة</h2>
        <div class="muted" style="font-size:13px;">تخصيص معايير الإعارة، نقاط التحفيز، وإتاحة التسجيل العام.</div>
    </div>

    <form method="POST" action="{{ route('admin.updateSettings') }}">
        @csrf
        
        <div style="display:flex;flex-direction:column;gap:18px;">
            <!-- Site Name -->
            <div>
                <label style="display:block;font-size:13.5px;font-weight:600;margin-bottom:6px;">اسم المنصة:</label>
                <input type="text" name="settings[site_name]" value="{{ $settings['site_name']->value ?? 'رِواق المعرفة' }}" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;">
            </div>

            <!-- Site Tagline -->
            <div>
                <label style="display:block;font-size:13.5px;font-weight:600;margin-bottom:6px;">الشعار والوصف العام للمنصة:</label>
                <input type="text" name="settings[site_tagline]" value="{{ $settings['site_tagline']->value ?? 'منصة القراءة المجتمعية وتبادل الكتب الورقية' }}" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;">
            </div>

            <!-- Book Lending & Borrowing System Toggle & Controls -->
            @php
                $isLendingActive = ($settings['enable_book_lending']->value ?? '1') == '1';
            @endphp
            <div style="background:{{ $isLendingActive ? '#F6FAF7' : '#FAF8F8' }};padding:18px;border-radius:12px;border:1.5px solid {{ $isLendingActive ? '#C8E6C9' : '#E8DFCC' }};display:flex;flex-direction:column;gap:14px;transition:all 0.3s ease;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <div style="width:38px;height:38px;border-radius:10px;background:{{ $isLendingActive ? 'rgba(35,87,56,0.12)' : 'rgba(100,100,100,0.1)' }};display:flex;align-items:center;justify-content:center;color:{{ $isLendingActive ? 'var(--green)' : '#777' }};flex-shrink:0;">
                            <i data-lucide="book-copy" style="width:20px;height:20px;"></i>
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <h3 style="margin:0;font-size:15px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">نظام إعارة واستعارة الكتب الورقية</h3>
                                @if($isLendingActive)
                                    <span class="tag" style="background:#2E7D32;color:#fff;font-size:11px;padding:2px 8px;">مفعّل في الموقع</span>
                                @else
                                    <span class="tag" style="background:#757575;color:#fff;font-size:11px;padding:2px 8px;">معطّل ومخفي</span>
                                @endif
                            </div>
                            <p class="muted" style="margin:4px 0 0;font-size:12.5px;line-height:1.5;">
                                عند تشغيل هذا الزر، تتاح وظائف الاستعارة وتبادل الكتب الورقية وسجلات الإعارة للمستخدمين. عند إيقاف تشغيله، يتم إخفاء وتعطيل كافة أزرار وخدمات الاستعارة في جميع صفحات الموقع فوراً.
                            </p>
                        </div>
                    </div>

                    <!-- Modern Toggle Button -->
                    <label style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;position:relative;user-select:none;">
                        <input type="checkbox" id="lending-toggle-checkbox" name="settings[enable_book_lending]" value="1" {{ $isLendingActive ? 'checked' : '' }} onchange="const sub = document.getElementById('lending-subsettings'); const label = document.getElementById('lending-toggle-label'); if (sub) { sub.style.opacity = this.checked ? '1' : '0.4'; sub.style.pointerEvents = this.checked ? 'auto' : 'none'; } if (label) { label.textContent = this.checked ? 'تشغيل الإعارة' : 'إيقاف الإعارة'; label.style.color = this.checked ? 'var(--green)' : '#777'; } const track = document.getElementById('lending-toggle-track'); const knob = document.getElementById('lending-toggle-knob'); if (track && knob) { track.style.background = this.checked ? 'var(--green)' : '#CCC'; knob.style.left = this.checked ? '27px' : '3px'; }" style="position:absolute;opacity:0;width:0;height:0;">
                        <div id="lending-toggle-track" style="width:52px;height:28px;background:{{ $isLendingActive ? 'var(--green)' : '#CCC' }};border-radius:14px;position:relative;transition:background 0.3s ease;">
                            <div id="lending-toggle-knob" style="width:22px;height:22px;background:#FFF;border-radius:50%;position:absolute;top:3px;left:{{ $isLendingActive ? '27px' : '3px' }};transition:left 0.3s ease;box-shadow:0 2px 5px rgba(0,0,0,0.2);"></div>
                        </div>
                        <span id="lending-toggle-label" style="font-size:13px;font-weight:700;color:{{ $isLendingActive ? 'var(--green)' : '#666' }};">
                            {{ $isLendingActive ? 'تشغيل الإعارة' : 'إيقاف الإعارة' }}
                        </span>
                    </label>
                </div>

                <!-- Sub-settings: Only active when lending is enabled -->
                <div id="lending-subsettings" class="grid2" style="margin-top:4px;padding-top:12px;border-top:1px dashed {{ $isLendingActive ? '#C8E6C9' : '#DDD' }};opacity:{{ $isLendingActive ? '1' : '0.4' }};pointer-events:{{ $isLendingActive ? 'auto' : 'none' }};transition:all 0.3s ease;">
                    <div>
                        <label style="display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;">الحد الأقصى للإعارات المتزامنة لكل قارئ:</label>
                        <input type="number" name="settings[max_active_loans]" value="{{ $settings['max_active_loans']->value ?? 3 }}" min="1" max="20" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:13px;outline:none;background:#FFF;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;">المدة الافتراضية للإعارة (بالأيام):</label>
                        <input type="number" name="settings[loan_duration_days]" value="{{ $settings['loan_duration_days']->value ?? 14 }}" min="1" max="90" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:13px;outline:none;background:#FFF;">
                    </div>
                </div>
            </div>

            <!-- Gamification & Knowledge Points -->
            <div class="grid2" style="margin-top:0;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">نقاط الأثر لكل جلسة قراءة:</label>
                    <input type="number" name="settings[points_per_session]" value="{{ $settings['points_per_session']->value ?? 10 }}" min="1" max="100" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:13px;outline:none;">
                </div>

                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">نقاط الأثر لكل كتاب مكتمل:</label>
                    <input type="number" name="settings[points_per_finished_book]" value="{{ $settings['points_per_finished_book']->value ?? 50 }}" min="5" max="500" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:13px;outline:none;">
                </div>
            </div>

            <!-- Registration & Maintenance Toggles -->
            <div style="background:#FAF8F2;padding:14px;border-radius:10px;border:1px solid var(--line);display:flex;flex-direction:column;gap:12px;">
                <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;">
                    <input type="checkbox" name="settings[allow_registrations]" value="1" {{ ($settings['allow_registrations']->value ?? '1') == '1' ? 'checked' : '' }}>
                    <span><strong>السماح بتسجيل مستخدمين جدد</strong> (إتاحة إنشاء حسابات جديدة في الرواق)</span>
                </label>

                <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;">
                    <input type="checkbox" name="settings[maintenance_mode]" value="1" {{ ($settings['maintenance_mode']->value ?? '0') == '1' ? 'checked' : '' }}>
                    <span><strong>تفعيل وضع الصيانة العام</strong> (إيقاف العمليات مؤقتاً للتحديثات)</span>
                </label>
            </div>

            <!-- Admin Security & MFA Enforcement -->
            <div style="background:#F6FBF7;padding:16px;border-radius:10px;border:1px solid #D5EBD9;display:flex;flex-direction:column;gap:14px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="shield-check" style="width:18px;height:18px;color:var(--green);"></i>
                    <strong style="font-size:14px;color:#173D36;">إعدادات الأمان المتقدم وإلزامية التحقق الثنائي للمشرفين</strong>
                </div>

                <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;">
                    <input type="checkbox" name="settings[admin_mfa_enforced]" value="1" {{ ($settings['admin_mfa_enforced']->value ?? '1') == '1' ? 'checked' : '' }}>
                    <span><strong>إلزامية التحقق الثنائي (MFA) لكافة المشرفين</strong> (طلب رمز 6 أرقام عند دخول لوحة التحكم)</span>
                </label>

                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <label style="display:block;font-size:13px;font-weight:600;">مهلة انتهاء جلسة الإدارة (Session Timeout):</label>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="number" name="settings[admin_session_timeout_minutes]" value="{{ $settings['admin_session_timeout_minutes']->value ?? 15 }}" min="5" max="120" style="width:90px;border:1px solid var(--line);border-radius:8px;padding:6px 10px;font-size:13px;outline:none;">
                        <span class="muted" style="font-size:12.5px;">دقيقة من عدم النشاط الأمني</span>
                    </div>
                </div>
            </div>

            <div style="margin-top:10px;">
                <button type="submit" class="primary" style="padding:10px 24px;font-size:13.5px;font-weight:600;">
                    حفظ وتحديث الإعدادات العامة
                </button>
            </div>
        </div>
    </form>
</div>
@endif

<!-- TAB: AUDIT LOGS -->
@if($tab === 'audit_logs')
<div class="panel animate-fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <div>
            <h2 style="margin:0 0 4px;">سجل التدقيق والرقابة الشامل (Audit Log)</h2>
            <div class="muted" style="font-size:13px;">توثيق غير قابل للتعديل لكافة العمليات الحساسة ونقل الملكيات وتعديل الصلاحيات والإشراف المنصي.</div>
        </div>
        <span class="tag" style="background:#173D36;color:#FFFDF8;font-size:12px;">{{ $auditLogs->count() }} عملية مسجلة</span>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;text-align:right;font-size:12.5px;">
            <thead>
                <tr style="border-bottom:2px solid var(--line);color:var(--sub);">
                    <th style="padding:10px 14px;">التاريخ والوقت</th>
                    <th style="padding:10px 14px;">المنفّذ (المستخدم)</th>
                    <th style="padding:10px 14px;">نوع العملية</th>
                    <th style="padding:10px 14px;">المورد المستهدف</th>
                    <th style="padding:10px 14px;">بيان العملية والتفاصيل</th>
                    <th style="padding:10px 14px;">عنوان IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:12px 14px;white-space:nowrap;font-size:11.5px;color:var(--sub);">
                            <div>{{ $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '-' }}</div>
                            <small class="muted">{{ $log->created_at ? $log->created_at->locale('ar')->diffForHumans() : '' }}</small>
                        </td>
                        <td style="padding:12px 14px;white-space:nowrap;">
                            @if($log->user)
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div class="avatar" style="width:26px;height:26px;font-size:11px;">
                                        {{ mb_substr($log->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:12px;">{{ $log->user->name }}</div>
                                        <div class="muted" style="font-size:10.5px;">{{ $log->user->role }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="muted">النظام التلقائي</span>
                            @endif
                        </td>
                        <td style="padding:12px 14px;white-space:nowrap;">
                            @php
                                $badgeStyle = match(true) {
                                    str_contains($log->action, 'transfer') => 'background:#FAF4E6;color:#785419;border:1px solid #EEDBB4;',
                                    str_contains($log->action, 'delete') => 'background:#FBEAE5;color:#8F3829;border:1px solid #F5CAC2;',
                                    str_contains($log->action, 'suspend') => 'background:#FDF2E9;color:#B45309;border:1px solid #FED7AA;',
                                    str_contains($log->action, 'role') => 'background:#E8F0FE;color:#1A73E8;border:1px solid #C2D7FA;',
                                    default => 'background:#EAF3EE;color:#173D36;border:1px solid #C8E1D5;',
                                };
                            @endphp
                            <span class="tag" style="{{ $badgeStyle }}font-size:11px;padding:3px 8px;">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td style="padding:12px 14px;white-space:nowrap;font-size:11.5px;">
                            @if($log->auditable_type)
                                <span style="font-family:monospace;background:#F0EEE6;padding:2px 6px;border-radius:4px;">
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                </span>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td style="padding:12px 14px;max-width:320px;">
                            <div style="font-size:12.5px;color:var(--text);line-height:1.4;">{{ $log->description }}</div>
                            @if($log->old_values || $log->new_values)
                                <details style="margin-top:4px;font-size:11px;">
                                    <summary style="cursor:pointer;color:var(--green);font-size:10.5px;">تفاصيل التغيير (Diff)</summary>
                                    <div style="background:#F6F4EB;padding:6px;border-radius:6px;margin-top:4px;font-family:monospace;font-size:10px;direction:ltr;text-align:left;">
                                        @if($log->old_values)
                                            <div style="color:#C0392B;">- Old: {{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE) }}</div>
                                        @endif
                                        @if($log->new_values)
                                            <div style="color:#27AE60;">+ New: {{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}</div>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td style="padding:12px 14px;white-space:nowrap;font-family:monospace;font-size:11px;color:var(--sub);">
                            {{ $log->ip_address ?: '127.0.0.1' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:36px;text-align:center;" class="muted">
                            <i data-lucide="clipboard-check" style="width:36px;height:36px;margin:0 auto 10px;display:block;opacity:0.4;"></i>
                            لا توجد سجلات تدقيق حتى الآن. سيتم توثيق كافة العمليات الحساسة ونقل الملكيات آلياً هنا.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
