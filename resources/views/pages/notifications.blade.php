@extends('layouts.app')

@section('title', 'الإشعارات والتذكيرات')
@section('crumb', 'واحة التنبيهات والسكينة')

@section('content')
@php
    $unreadCount = $notifications->where('is_read', false)->count();
    $loansCount = $notifications->where('category', 'loans')->count() ?: $notifications->where('type', 'loan')->count();
    $meetingsCount = $notifications->where('category', 'meetings')->count() ?: $notifications->where('type', 'event')->count();
    $challengesCount = $notifications->where('category', 'challenges')->count() ?: $notifications->where('type', 'reading')->count();
    $userPrefs = $user->notificationPreferences();
@endphp

@php
    $quietNow = $user->quiet_hours_enabled && $isInQuietHours;
@endphp
<div class="ch-head">
    <div>
        <h1>الإشعارات</h1>
        <p>
            @if($unreadCount > 0)
                لديك <b>{{ $unreadCount }}</b> إشعارات جديدة.
            @else
                سكينة تامة: جميع الإشعارات مقروءة.
            @endif
            إشعارات الويب (Push): {{ $user->push_opt_in ? 'مفعّلة' : 'متوقفة' }}.
            @if($quietNow)
                ساعات الهدوء مفعّلة الآن (حتى {{ $user->quiet_hours_end }}).
            @endif
        </p>
    </div>
    <div class="nt-head-actions">
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.markAllAsRead') }}" style="margin:0;">
                @csrf
                <button type="submit" class="rq-btn rq-btn-primary" title="تعيين كافة التنبيهات كمقروءة"><i data-lucide="check-check"></i><span>تحديد الكل كمقروء</span></button>
            </form>
        @endif
        <a href="{{ route('settings.index') }}" class="rq-btn rq-btn-ghost"><i data-lucide="settings"></i><span>التفضيلات</span></a>
    </div>
</div>

<details class="nt-extras">
    <summary><i data-lucide="sliders-horizontal"></i><span>الإشعارات الذكية وساعات الهدوء</span></summary>
<!-- 2. Web Push Notification & Opt-In Banner -->
<div class="notif-digest-card animate-fade-in" style="background:linear-gradient(135deg, #FAF8F2 0%, #FFFDF9 100%);border:1px dashed #C3A15D;border-radius:12px;padding:16px 22px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;font-size:13.5px;color:#173D36;">
    <div style="display:flex;align-items:center;gap:14px;">
        <div style="width:42px;height:42px;border-radius:12px;background:rgba(195,161,93,0.18);display:flex;align-items:center;justify-content:center;color:#8C6A21;flex-shrink:0;">
            <i data-lucide="bell-plus" style="width:22px;height:22px;"></i>
        </div>
        <div>
            <strong style="display:block;font-size:14.5px;color:#173D36;margin-bottom:2px;">
                إشعارات المتصفح والـ Web Push للتذكيرات المهمة:
            </strong>
            <span style="color:#5C5443;font-size:13px;line-height:1.6;">
                تصلك تنبيهات المواعيد والاجتماعات والإعارات المستعجلة في متصفحك حتى عند إغلاق التبويب.
                <strong>لا نرسل أي تنبيهات اجتماعية منخفضة الأهمية افتراضياً</strong>، وتتوقف كلياً خلال ساعات هدوئك.
            </span>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;white-space:nowrap;">
        <form method="POST" action="{{ route('notifications.push.toggleOptIn') }}" style="margin:0;">
            @csrf
            <button type="submit" class="{{ $user->push_opt_in ? 'secondary' : 'primary' }} btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
                <i data-lucide="{{ $user->push_opt_in ? 'bell-off' : 'bell-ring' }}" style="width:14px;height:14px;"></i>
                <span>{{ $user->push_opt_in ? 'إيقاف إشعارات الويب' : 'تفعيل إشعارات الويب (Opt-in)' }}</span>
            </button>
        </form>
    </div>
</div>

<!-- 3. Quiet Hours Night Mode Banner -->
<div class="quiet-hours-banner {{ ($user->quiet_hours_enabled && $isInQuietHours) ? 'quiet-hours-night-fade' : '' }}" id="quietHoursBanner">
    <div style="display:flex;align-items:center;gap:14px;">
        <div class="quiet-moon-halo">
            <span class="glowing-crescent">
                <i data-lucide="moon" style="width:26px;height:26px;"></i>
            </span>
        </div>
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                <strong style="font-size:15px;font-family:'Noto Naskh Arabic', serif;">ساعات الهدوء والسكينة الليلية (Quiet Hours)</strong>
                @if($user->quiet_hours_enabled)
                    @if($isInQuietHours)
                        <span class="tag" style="background:rgba(255,226,163,0.25);color:#FFE2A3;border:1px solid rgba(195,161,93,0.5);font-size:11px;">مفعّل وسارٍ الآن 🌙</span>
                    @else
                        <span class="tag" style="background:rgba(255,226,163,0.18);color:#FFE2A3;border:1px solid rgba(195,161,93,0.4);font-size:11px;">مجدول ({{ $user->quiet_hours_start }} - {{ $user->quiet_hours_end }})</span>
                    @endif
                @else
                    <span class="tag" style="background:rgba(0,0,0,0.06);font-size:11px;color:#6A614E;">افتراضي وهادئ</span>
                @endif
            </div>
            <small class="muted" style="display:block;font-size:12.5px;line-height:1.5;">
                @if($user->quiet_hours_enabled)
                    بحسب توقيتك المحلي ({{ $user->timezone ?? 'Asia/Riyadh' }}): تُعلّق إشعارات الـ Push والتنبيهات غير العاجلة يومياً من {{ $user->quiet_hours_start }} حتى {{ $user->quiet_hours_end }} لصفاء ليلك.
                @else
                    تصلك التنبيهات بصورة هادئة، ويمكنك ضبط ساعات الهدوء والمنطقة الزمنية من صفحة التفضيلات.
                @endif
            </small>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <button type="button" class="secondary btn-quiet-toggle" onclick="toggleQuietHoursAmbientMode()" title="تبديل الإضاءة والنمط الليلي">
            <i data-lucide="sparkle" style="width:14px;height:14px;color:var(--gold);"></i>
            <span id="quietToggleLabel">{{ ($user->quiet_hours_enabled && $isInQuietHours) ? 'إضاءة طبيعية' : 'تفعيل النمط الليلي' }}</span>
        </button>
        <a href="{{ route('settings.index') }}" class="secondary btn-quiet-toggle" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;" title="ضبط التوقيت وساعات الهدوء">
            <i data-lucide="clock" style="width:14px;height:14px;color:var(--gold);"></i>
            <span>ضبط التوقيت</span>
        </a>
    </div>
</div>

<!-- 4. Gentle Reading Nudge Card -->
<div class="gentle-nudge-banner animate-fade-in">
    <div class="nudge-icon">
        <i data-lucide="sparkles" style="width:22px;height:22px;color:var(--gold);"></i>
    </div>
    <div class="nudge-text">
        <strong>همسة معرفية وهدأة بال (Gentle Nudge)</strong>
        @if($activeBook && $activeBook->book)
            <span>«{{ $activeBook->book->title }}» ينتظرك في صفحة {{ $activeBook->current_page }}... 10 دقائق هادئة اليوم كافية لصنع الفرق واستعادة سكينتك 🌿</span>
        @else
            <span>«وحي القلم» ينتظرك في صفحة 124... 10 دقائق هادئة اليوم كافية لصنع الفرق واستعادة سكينتك 🌿</span>
        @endif
    </div>
    <a href="{{ route('reading.index') }}" class="primary btn-sm" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;white-space:nowrap;">
        <i data-lucide="book-open" style="width:15px;height:15px;"></i>
        <span>متابعة القراءة</span>
    </a>
</div>
</details>

<!-- 5. Controls Bar: Filter Tabs & Live Search -->
<div class="ch-toolbar">
    <!-- Filter Tabs (Categories + Read Status) -->
    <div class="rq-tabs pf-tabs" role="tablist">
        <button type="button" class="notif-tab-btn pf-tab active" data-filter="all" onclick="filterNotifs('all', this)">
            <i data-lucide="inbox" style="width:14px;height:14px;"></i>
            <span>الكل</span>
            <span class="tab-counter">{{ $notifications->count() }}</span>
        </button>
        <button type="button" class="notif-tab-btn pf-tab" data-filter="unread" onclick="filterNotifs('unread', this)">
            <i data-lucide="bell-ring" style="width:14px;height:14px;"></i>
            <span>غير المقروءة</span>
            <span class="tab-counter {{ $unreadCount > 0 ? 'counter-unread' : '' }}">{{ $unreadCount }}</span>
        </button>
        <button type="button" class="notif-tab-btn pf-tab" data-filter="communities" onclick="filterNotifs('communities', this)">
            <i data-lucide="users" style="width:14px;height:14px;"></i>
            <span>نوادي القراءة</span>
            @if(($categoryCounts['communities'] ?? 0) > 0)<span class="tab-counter">{{ $categoryCounts['communities'] }}</span>@endif
        </button>
        <button type="button" class="notif-tab-btn pf-tab" data-filter="challenges" onclick="filterNotifs('challenges', this)">
            <i data-lucide="award" style="width:14px;height:14px;"></i>
            <span>التحديات</span>
            @if(($categoryCounts['challenges'] ?? 0) > 0)<span class="tab-counter">{{ $categoryCounts['challenges'] }}</span>@endif
        </button>
        <button type="button" class="notif-tab-btn pf-tab" data-filter="meetings" onclick="filterNotifs('meetings', this)">
            <i data-lucide="calendar-days" style="width:14px;height:14px;"></i>
            <span>الاجتماعات</span>
            @if(($categoryCounts['meetings'] ?? 0) > 0)<span class="tab-counter">{{ $categoryCounts['meetings'] }}</span>@endif
        </button>
        @if($bookLendingEnabled)
        <button type="button" class="notif-tab-btn pf-tab" data-filter="loans" onclick="filterNotifs('loans', this)">
            <i data-lucide="library-big" style="width:14px;height:14px;"></i>
            <span>الإعارات</span>
            @if(($categoryCounts['loans'] ?? 0) > 0)<span class="tab-counter">{{ $categoryCounts['loans'] }}</span>@endif
        </button>
        @endif
        <button type="button" class="notif-tab-btn pf-tab" data-filter="comments" onclick="filterNotifs('comments', this)">
            <i data-lucide="message-square" style="width:14px;height:14px;"></i>
            <span>التعليقات</span>
            @if(($categoryCounts['comments'] ?? 0) > 0)<span class="tab-counter">{{ $categoryCounts['comments'] }}</span>@endif
        </button>
        <button type="button" class="notif-tab-btn pf-tab" data-filter="badges" onclick="filterNotifs('badges', this)">
            <i data-lucide="sparkles" style="width:14px;height:14px;"></i>
            <span>الأوسمة</span>
            @if(($categoryCounts['badges'] ?? 0) > 0)<span class="tab-counter">{{ $categoryCounts['badges'] }}</span>@endif
        </button>
        <button type="button" class="notif-tab-btn pf-tab" data-filter="friendships" onclick="filterNotifs('friendships', this)">
            <i data-lucide="user-check" style="width:14px;height:14px;"></i>
            <span>الصداقة</span>
            @if(($categoryCounts['friendships'] ?? 0) > 0)<span class="tab-counter">{{ $categoryCounts['friendships'] }}</span>@endif
        </button>
    </div>

    <!-- Real-time Live Search -->
    <div class="notif-search-box">
        <i data-lucide="search" style="width:15px;height:15px;color:#9E917D;"></i>
        <input type="text" id="notifSearchInput" placeholder="ابحث في نص التنبيه أو العنوان..." oninput="filterNotifsBySearch()">
    </div>
</div>

<!-- 6. Notifications Panel -->
<section class="panel animate-fade-in {{ ($user->quiet_hours_enabled && $isInQuietHours) ? 'quiet-hours-night-fade' : '' }}" id="notificationsPanel" style="max-width:100%;transition:all 0.5s ease;padding:20px 24px;">
    <div class="notif-cards-list" id="notifCardsList">
        @forelse($notifications as $notif)
            @php
                $cat = $notif->category ?: 'system';
                $priority = $notif->priority ?: 'normal';

                // Map category icons and labels
                $iconMap = [
                    'communities' => 'users',
                    'challenges' => 'award',
                    'meetings' => 'calendar-heart',
                    'loans' => 'book-marked',
                    'comments' => 'message-square',
                    'badges' => 'sparkles',
                    'friendships' => 'user-check',
                    'system' => 'bell',
                ];
                $iconName = $notif->icon ?: ($iconMap[$cat] ?? 'bell');

                // Priority style classes
                $priorityPillClass = match($priority) {
                    'urgent' => 'tag-urgent',
                    'high' => 'tag-high',
                    'low' => 'tag-low',
                    default => 'tag-normal',
                };

                $priorityLabel = match($priority) {
                    'urgent' => 'عاجل ⚠️',
                    'high' => 'هام ✦',
                    'low' => 'منخفض',
                    default => 'عادي',
                };

                $timeAgo = $notif->created_at ? $notif->created_at->locale('ar')->diffForHumans() : 'مؤخراً';
            @endphp

            <div class="notif-card notif-item {{ $notif->is_read ? 'is-read' : 'is-unread' }}" 
                 id="notif-row-{{ $notif->id }}"
                 data-category="{{ $cat }}"
                 data-priority="{{ $priority }}"
                 data-read="{{ $notif->is_read ? '1' : '0' }}">
                
                <!-- Category Icon Badge -->
                <div class="notif-icon-badge badge-{{ $cat }}">
                    <i data-lucide="{{ $iconName }}" style="width:22px;height:22px;"></i>
                </div>

                <!-- Notification Body -->
                <div class="notif-body-content">
                    <div class="notif-meta-row">
                        <span class="notif-category-chip chip-{{ $cat }}">{{ $notif->category_label }}</span>
                        
                        <!-- Priority Badge -->
                        @if($priority !== 'normal')
                            <span class="priority-tag {{ $priorityPillClass }}">{{ $priorityLabel }}</span>
                        @endif

                        <span class="notif-time-ago">
                            <i data-lucide="clock" style="width:12px;height:12px;"></i>
                            <span>{{ $timeAgo }}</span>
                        </span>

                        @if($notif->read_at)
                            <span class="read-at-timestamp" title="تاريخ وساعة الاطلاع">
                                · قُرئ {{ $notif->read_at->locale('ar')->diffForHumans() }}
                            </span>
                        @endif
                    </div>

                    <h3 class="notif-title">{{ $notif->title }}</h3>
                    <p class="notif-desc">{{ $notif->body }}</p>
                </div>

                <!-- Actions Column -->
                <div class="notif-actions-col">
                    @php
                        $isJoinReq = str_contains($notif->title, 'طلب انضمام') || $notif->type === 'community_join_request';
                        $joinReq = null;
                        if ($isJoinReq) {
                            $commId = $notif->metadata['community_id'] ?? null;
                            $reqId = $notif->metadata['request_id'] ?? null;
                            if (! $commId && $notif->link) {
                                parse_str(parse_url($notif->link, PHP_URL_QUERY) ?? '', $qParams);
                                $commId = $qParams['community_id'] ?? null;
                            }
                            if ($reqId) {
                                $joinReq = \App\Models\CommunityJoinRequest::where('id', $reqId)->where('status', 'pending')->first();
                            } elseif ($commId) {
                                $joinReq = \App\Models\CommunityJoinRequest::where('community_id', $commId)->where('status', 'pending')->latest()->first();
                            }
                        }
                    @endphp

                    @if($joinReq)
                        <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
                            <form method="POST" action="{{ route('communities.approveJoinRequest', $joinReq->id) }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="primary btn-sm" style="padding:4px 10px;font-size:11.5px;background:var(--green);color:#fff;">
                                    <i data-lucide="check"></i><span>قبول</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('communities.rejectJoinRequest', $joinReq->id) }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="secondary btn-sm" style="padding:4px 10px;font-size:11.5px;color:#A13030;border-color:#E2BDBD;">
                                    <i data-lucide="x"></i><span>رفض</span>
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($notif->link)
                        <a href="{{ $notif->url }}" class="notif-action-btn" title="الانتقال إلى التفاصيل المعرفية">
                            <span>عرض التفاصيل</span>
                            <i data-lucide="arrow-left" class="action-arrow"></i>
                        </a>
                    @endif

                    @if(!$notif->is_read)
                        <form method="POST" action="{{ route('notifications.markAsRead', $notif->id) }}" style="margin:0;" onsubmit="handleMarkReadAjax(event, {{ $notif->id }})">
                            @csrf
                            <button type="submit" class="notif-btn-check" title="تعيين كمقروء" id="btn-read-{{ $notif->id }}">
                                <i data-lucide="check" style="width:14px;height:14px;"></i>
                                <span>مقروء</span>
                            </button>
                        </form>
                    @else
                        <span class="notif-read-chip" title="تمت قراءة هذا التنبيه">
                            <i data-lucide="check-check" style="width:14px;height:14px;color:#1A543B;"></i>
                            <span>تم الاطلاع</span>
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="notif-empty-sanctuary">
                <div class="sanctuary-halo-icon">
                    <i data-lucide="bell-off" style="width:32px;height:32px;"></i>
                </div>
                <h3>سكينة تامة في رواقك 🌿</h3>
                <p>مساحتك هادئة تماماً، ولا توجد تذكيرات مستحقة حالياً. استمتع بوقتك في خلوتك القرائية ومجالس المعرفة.</p>
                <div class="empty-shortcuts">
                    <a href="{{ route('explore') }}" class="primary btn-sm">
                        <i data-lucide="compass"></i>
                        <span>استكشاف الكتب</span>
                    </a>
                    <a href="{{ route('communities.index') }}" class="secondary btn-sm">
                        <i data-lucide="users"></i>
                        <span>مجالس الأدب</span>
                    </a>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Filter Zero State (When filter or search matches nothing) -->
    <div class="notif-empty-sanctuary" id="filterEmptyState" style="display:none;">
        <div class="sanctuary-halo-icon">
            <i data-lucide="search-x" style="width:30px;height:30px;"></i>
        </div>
        <h3>لا توجد نتائج مطابقة</h3>
        <p>لم نجد أي تنبيهات تطابق معايير البحث أو التصفية المختارة.</p>
        <button type="button" class="secondary btn-sm" onclick="resetNotifFilters()">
            <i data-lucide="rotate-ccw"></i>
            <span>إعادة تعيين التصفية</span>
        </button>
    </div>
</section>

<!-- 7. Privacy-Preserving Delivery Logs Strip -->
@if(isset($recentDeliveryLogs) && $recentDeliveryLogs->isNotEmpty())
<div class="panel animate-fade-in" style="margin-top:24px;padding:16px 20px;background:#FAF8F4;border:1px solid #E6DEC9;border-radius:12px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="shield-check" style="width:18px;height:18px;color:#173D36;"></i>
            <strong style="font-size:13.5px;color:#173D36;">سجل وصول الإشعارات وحماية الخصوصية (Delivery Audit)</strong>
        </div>
        <small class="muted" style="font-size:11.5px;">يحفظ حالة القناة ومستوى الأولوية دون أي بيانات أو نصوص شخصية حساسة 🛡️</small>
    </div>
    <div style="display:grid;gap:8px;font-size:12px;">
        @foreach($recentDeliveryLogs as $log)
            @php
                $statusBadge = match($log->status) {
                    'delivered' => ['text' => 'تم التوصيل بنجاح', 'color' => '#1A543B', 'bg' => '#E8F5E9'],
                    'suppressed_quiet_hours' => ['text' => 'مؤجل: ساعات الهدوء 🌙', 'color' => '#8C6A21', 'bg' => '#FFF8E1'],
                    'skipped_low_priority' => ['text' => 'مستبعد: منخفض الأهمية 🍃', 'color' => '#5C5443', 'bg' => '#F0EFEA'],
                    'suppressed_preference' => ['text' => 'مكتوم حسب رغبتك 🔕', 'color' => '#8C7B65', 'bg' => '#F5F5F5'],
                    default => ['text' => $log->status, 'color' => '#555', 'bg' => '#EEE'],
                };
            @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 12px;background:#FFF;border-radius:6px;border:1px solid #EFEAE0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-weight:600;color:#173D36;">{{ $log->channel === 'web_push' ? 'ويب بوش (Web Push)' : 'داخل التطبيق (In-App)' }}</span>
                    <span style="color:#8C7B65;">· فئة: {{ \App\Models\UserNotification::CATEGORIES[$log->category] ?? $log->category }}</span>
                    <span style="color:#8C7B65;">· أولوية: {{ \App\Models\UserNotification::PRIORITIES[$log->priority] ?? $log->priority }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;color:{{ $statusBadge['color'] }};background:{{ $statusBadge['bg'] }};">
                        {{ $statusBadge['text'] }}
                    </span>
                    <span class="muted" style="font-size:11px;">{{ $log->created_at->diffForHumans() }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

<style>
/* Priority and Category Tags */
.priority-tag {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.tag-urgent {
    background: #FFEBEE;
    color: #B71C1C;
    border: 1px solid #FFCDD2;
}
.tag-high {
    background: #FFF8E1;
    color: #B78103;
    border: 1px solid #FFE082;
}
.tag-normal {
    background: #E8F5E9;
    color: #1B5E20;
    border: 1px solid #C8E6C9;
}
.tag-low {
    background: #F5F5F5;
    color: #616161;
    border: 1px solid #E0E0E0;
}
.read-at-timestamp {
    font-size: 11px;
    color: #8C7B65;
}
.badge-communities, .chip-communities { background: rgba(30, 136, 229, 0.12); color: #1565C0; }
.badge-challenges, .chip-challenges { background: rgba(251, 140, 0, 0.12); color: #E65100; }
.badge-meetings, .chip-meetings { background: rgba(142, 36, 170, 0.12); color: #6A1B9A; }
.badge-loans, .chip-loans { background: rgba(46, 125, 50, 0.12); color: #1B5E20; }
.badge-comments, .chip-comments { background: rgba(0, 150, 136, 0.12); color: #00695C; }
.badge-badges, .chip-badges { background: rgba(245, 127, 23, 0.12); color: #F57F17; }
.badge-friendships, .chip-friendships { background: rgba(216, 27, 96, 0.12); color: #AD1457; }
.badge-system, .chip-system { background: rgba(100, 116, 139, 0.12); color: #334155; }
</style>
@endsection

@push('scripts')
<script>
let currentActiveFilter = 'all';

function toggleQuietHoursAmbientMode() {
    const banner = document.getElementById('quietHoursBanner');
    const panel = document.getElementById('notificationsPanel');
    const label = document.getElementById('quietToggleLabel');
    if (banner) {
        banner.classList.toggle('quiet-hours-night-fade');
        const isNight = banner.classList.contains('quiet-hours-night-fade');
        if (panel) {
            panel.classList.toggle('quiet-hours-night-fade', isNight);
        }
        if (label) {
            label.textContent = isNight ? 'إضاءة طبيعية' : 'تفعيل النمط الليلي';
        }
    }
}

function filterNotifs(filterType, btnEl) {
    currentActiveFilter = filterType;
    
    // Update active tab button
    document.querySelectorAll('.notif-tab-btn').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    applyNotifFilters();
}

function filterNotifsBySearch() {
    applyNotifFilters();
}

function applyNotifFilters() {
    const searchVal = (document.getElementById('notifSearchInput')?.value || '').trim().toLowerCase();
    const items = document.querySelectorAll('.notif-item');
    let visibleCount = 0;

    items.forEach(item => {
        const cat = item.getAttribute('data-category') || 'system';
        const isRead = item.getAttribute('data-read') === '1';
        const text = item.innerText.toLowerCase();

        let matchesTab = false;
        if (currentActiveFilter === 'all') {
            matchesTab = true;
        } else if (currentActiveFilter === 'unread') {
            matchesTab = !isRead;
        } else {
            // Category match (or legacy match)
            matchesTab = (cat === currentActiveFilter) || 
                         (currentActiveFilter === 'loans' && cat === 'loans') ||
                         (currentActiveFilter === 'meetings' && cat === 'meetings') ||
                         (currentActiveFilter === 'challenges' && cat === 'challenges');
        }

        const matchesSearch = !searchVal || text.includes(searchVal);

        if (matchesTab && matchesSearch) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    const filterEmpty = document.getElementById('filterEmptyState');
    if (filterEmpty) {
        if (visibleCount === 0 && items.length > 0) {
            filterEmpty.style.display = 'block';
        } else {
            filterEmpty.style.display = 'none';
        }
    }
}

function resetNotifFilters() {
    const input = document.getElementById('notifSearchInput');
    if (input) input.value = '';
    const allBtn = document.querySelector('.notif-tab-btn[data-filter="all"]');
    filterNotifs('all', allBtn);
}

// Seamless Ajax Mark As Read
function handleMarkReadAjax(event, notifId) {
    if (window.fetch) {
        event.preventDefault();
        const form = event.target;
        const url = form.action;
        const csrfToken = form.querySelector('input[name="_token"]')?.value;

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(res => res.json()).then(data => {
            if (data.success) {
                const row = document.getElementById(`notif-row-${notifId}`);
                if (row) {
                    row.classList.remove('is-unread');
                    row.classList.add('is-read');
                    row.setAttribute('data-read', '1');

                    // Replace button with chip
                    const actionsCol = row.querySelector('.notif-actions-col');
                    if (actionsCol) {
                        form.remove();
                        const chip = document.createElement('span');
                        chip.className = 'notif-read-chip';
                        chip.innerHTML = '<i data-lucide="check-check" style="width:14px;height:14px;color:#1A543B;"></i><span>تم الاطلاع</span>';
                        actionsCol.appendChild(chip);
                        if (window.lucide) window.lucide.createIcons();
                    }

                    // Decrement unread counter in tab if exists
                    const unreadBadge = document.querySelector('.notif-tab-btn[data-filter="unread"] .tab-counter');
                    if (unreadBadge) {
                        const currentVal = parseInt(unreadBadge.textContent, 10) || 0;
                        const newVal = Math.max(0, currentVal - 1);
                        unreadBadge.textContent = newVal;
                        if (newVal === 0) unreadBadge.classList.remove('counter-unread');
                    }
                }
            } else {
                form.submit();
            }
        }).catch(err => {
            form.submit();
        });
    }
}
</script>
@endpush
