@extends('layouts.app')

@section('title', 'الملف القرائي - ' . $user->name)
@section('crumb', 'الملف القرائي ورفقاء المعرفة')

@section('content')
<!-- Reader Profile Header Panel -->
<div class="panel animate-fade-in" style="background:linear-gradient(135deg, #173D36 0%, #1F4D3C 100%);color:#FFFDF8;padding:32px 28px;margin-bottom:24px;border:none;box-shadow:0 8px 24px rgba(23,61,54,0.18);position:relative;overflow:hidden;">
    <!-- Decorative Islamic Arches Ambient -->
    <div style="position:absolute;top:-40px;left:-40px;width:180px;height:180px;border-radius:50%;background:rgba(195,161,93,0.08);pointer-events:none;"></div>
    <div style="position:absolute;bottom:-60px;right:-30px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,0.04);pointer-events:none;"></div>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px;position:relative;z-index:1;">
        <div style="display:flex;gap:20px;align-items:center;">
            <!-- Avatar with level border -->
            <div style="position:relative;">
                <span class="avatar" style="width:76px;height:76px;font-size:28px;background:#FFFDF8;color:var(--green);border:3px solid var(--gold);box-shadow:0 4px 14px rgba(0,0,0,0.25);">
                    {{ $user->avatar ?? mb_substr($user->name, 0, 1) }}
                </span>
                <span style="position:absolute;bottom:-4px;right:-4px;background:{{ $user->level_data['color'] }};color:#FFFDF8;font-size:11px;padding:2px 6px;border-radius:10px;border:1px solid #FFFDF8;font-weight:700;">
                    L{{ $user->level_data['number'] }}
                </span>
            </div>

            <div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h1 style="color:#FFFDF8;font-size:24px;margin:0;font-family:'Noto Naskh Arabic', serif;">
                        {{ $user->name }}
                    </h1>
                    <span class="tag" style="background:rgba(255,226,163,0.2);color:#FFE2A3;border:1px solid rgba(195,161,93,0.4);font-size:12px;padding:3px 10px;">
                        {{ $user->level_data['title'] }}
                    </span>
                    @if($user->is_suspended)
                        <span class="tag" style="background:#8F3829;color:#FFFDF8;font-size:11px;">موقوف</span>
                    @endif
                </div>

                <p style="color:#D6C9AA;font-size:13.5px;margin:6px 0 10px;max-width:540px;line-height:1.5;">
                    {{ $user->bio ?: 'قارئ شغوف في رِواق المعرفة، يقتفي أثر الحرف ويسعى لتزكية العقل وبناء الألفة القرائية.' }}
                </p>

                <!-- Stats Badges -->
                <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;font-size:12.5px;color:#ECE6D8;">
                    <span><strong style="color:var(--gold);">{{ $user->points }}</strong> نقطة أثر</span>
                    <span>·</span>
                    <span><strong style="color:var(--gold);">{{ $friendsList->count() ?: $user->friends()->count() }}</strong> صديق قراءة 🤝</span>
                    <span>·</span>
                    <span><strong style="color:var(--gold);">{{ $user->followers->count() }}</strong> متابِع</span>
                    <span>·</span>
                    <span><strong style="color:var(--gold);">{{ $user->following->count() }}</strong> يتابعهم</span>
                    <span>·</span>
                    <span>عضو منذ {{ $user->created_at->locale('ar')->diffForHumans() }}</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            @if($isOwnProfile)
                <button type="button" class="secondary" onclick="document.getElementById('editProfileModal').style.display='flex'" style="background:rgba(255,255,255,0.15);color:#FFFDF8;border-color:rgba(255,255,255,0.3);font-size:12.5px;display:inline-flex;align-items:center;gap:6px;">
                    <i data-lucide="edit-3" style="width:14px;height:14px;color:var(--gold);"></i>
                    <span>تعديل الملف</span>
                </button>
                <button type="button" class="secondary" onclick="document.getElementById('manageFriendsModal').style.display='flex'" style="background:rgba(255,255,255,0.15);color:#FFFDF8;border-color:rgba(255,255,255,0.3);font-size:12.5px;display:inline-flex;align-items:center;gap:6px;position:relative;">
                    <i data-lucide="users" style="width:14px;height:14px;color:var(--gold);"></i>
                    <span>إدارة الصداقات والطلبات</span>
                    @if($incomingRequests->isNotEmpty())
                        <span style="position:absolute;top:-6px;right:-6px;background:#C84B31;color:#FFFDF8;font-size:10px;font-weight:700;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #173D36;">{{ $incomingRequests->count() }}</span>
                    @endif
                </button>
                <button type="button" class="secondary" onclick="document.getElementById('activityPrivacyModal').style.display='flex'" style="background:rgba(255,255,255,0.15);color:#FFFDF8;border-color:rgba(255,255,255,0.3);font-size:12.5px;display:inline-flex;align-items:center;gap:6px;">
                    <i data-lucide="shield" style="width:14px;height:14px;color:var(--gold);"></i>
                    <span>خصوصية الأنشطة</span>
                </button>
            @elseif($viewer)
                @if($isBlockedByViewer)
                    <!-- Unblock Button -->
                    <form method="POST" action="{{ route('friends.unblock', $user->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="secondary" style="background:rgba(255,255,255,0.2);color:#FFFDF8;border-color:rgba(255,255,255,0.3);font-size:12.5px;display:inline-flex;align-items:center;gap:6px;">
                            <i data-lucide="shield-check" style="width:14px;height:14px;color:var(--gold);"></i>
                            <span>إلغاء حظر المستخدم</span>
                        </button>
                    </form>
                @elseif(!$hasBlockedViewer)
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        @if($friendshipStatus === 'accepted')
                            <span class="tag" style="background:rgba(226,236,222,0.25);color:#D4EDDA;border:1px solid rgba(226,236,222,0.4);font-size:12px;padding:5px 12px;display:inline-flex;align-items:center;gap:5px;">
                                <i data-lucide="heart-handshake" style="width:14px;height:14px;color:var(--gold);"></i>
                                <span>صديق قراءة 🤝</span>
                            </span>
                            <form method="POST" action="{{ route('friends.unfriend', $user->id) }}" onsubmit="return confirm('هل أنت متأكد من إلغاء الصداقة القرائية؟');" style="margin:0;">
                                @csrf
                                <button type="submit" class="secondary btn-sm" style="background:rgba(255,255,255,0.15);color:#FFE2A3;border:1px solid rgba(255,255,255,0.2);font-size:11.5px;padding:5px 10px;">
                                    إلغاء الصداقة
                                </button>
                            </form>
                        @elseif($friendshipStatus === 'pending')
                            @if($friendship && $friendship->recipient_id === $viewer->id)
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <form method="POST" action="{{ route('friends.accept', $user->id) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="primary btn-sm" style="background:var(--gold);color:#173D36;border:none;font-size:12px;padding:6px 14px;font-weight:700;">
                                            قبول الصداقة 🤝
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('friends.reject', $user->id) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="secondary btn-sm" style="background:rgba(255,255,255,0.15);color:#FFFDF8;border-color:rgba(255,255,255,0.3);font-size:12px;padding:6px 10px;">
                                            رفض
                                        </button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('friends.unfriend', $user->id) }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="secondary btn-sm" style="background:rgba(255,255,255,0.15);color:#FFE2A3;border:1px solid rgba(255,255,255,0.25);font-size:12px;padding:6px 12px;" title="انقر لإلغاء طلب الصداقة المعلق">
                                        طلب صداقة مُرسل ⏳
                                    </button>
                                </form>
                            @endif
                        @else
                            <form method="POST" action="{{ route('friends.request', $user->id) }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="primary" style="background:var(--gold);color:#173D36;border:none;font-size:12.5px;display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:6px 14px;font-weight:700;">
                                    <i data-lucide="user-plus" style="width:14px;height:14px;"></i>
                                    <span>طلب صداقة 🤝</span>
                                </button>
                            </form>
                        @endif

                        <!-- Follow / Unfollow Button -->
                        <form method="POST" action="{{ route('profile.toggleFollow', $user->id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="secondary" style="background:rgba(255,255,255,0.15);color:#FFFDF8;border-color:rgba(255,255,255,0.3);font-size:12.5px;display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:6px 12px;">
                                <i data-lucide="{{ $isFollowing ? 'user-minus' : 'bell' }}" style="width:14px;height:14px;"></i>
                                <span>{{ $isFollowing ? 'إلغاء المتابعة' : 'متابعة' }}</span>
                            </button>
                        </form>

                        <!-- Block User Button -->
                        <form method="POST" action="{{ route('friends.block', $user->id) }}" onsubmit="return confirm('هل أنت متأكد من حظر «{{ $user->name }}»؟ سيتم إلغاء أي صداقة أو متابعة قائمة.');" style="margin:0;">
                            @csrf
                            <button type="submit" class="secondary btn-sm" style="background:transparent;color:rgba(255,255,255,0.6);border:1px solid rgba(255,255,255,0.2);padding:6px 8px;" title="حظر المستخدم">
                                <i data-lucide="shield-alert" style="width:14px;height:14px;"></i>
                            </button>
                        </form>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <!-- Level Progress Strip -->
    <div style="margin-top:22px;padding-top:18px;border-top:1px solid rgba(255,255,255,0.12);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="flex:1;min-width:240px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;color:#D6C9AA;">
                <span>المستوى الحالي: <strong>{{ $user->level_data['name'] }}</strong></span>
                @if($user->level_data['next_points'])
                    <span>متبقي <strong>{{ $user->level_data['points_needed'] }}</strong> نقطة للمستوى التالي</span>
                @else
                    <span style="color:var(--gold);">أعلى مرتبة قرائية 👑</span>
                @endif
            </div>
            <div style="height:6px;background:rgba(255,255,255,0.12);border-radius:10px;overflow:hidden;">
                <div style="width:{{ $user->level_data['progress_pct'] }}%;height:100%;background:linear-gradient(90deg, var(--gold), #FFE2A3);border-radius:10px;transition:width 0.4s ease;"></div>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:14px;font-size:12.5px;color:#ECE6D8;">
            <span>الهدف السنوي: <strong>{{ $user->books_finished }}</strong> من <strong>{{ $user->annual_goal ?? 24 }}</strong> كتاب</span>
        </div>
    </div>
</div>

@if($hasBlockedViewer)
<div class="panel animate-fade-in" style="background:#FFF3CD;border:1px solid #FFEEBA;color:#856404;padding:16px 20px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
    <i data-lucide="shield-alert" style="width:24px;height:24px;flex-shrink:0;"></i>
    <div>
        <b style="font-size:14px;display:block;">الوصول إلى الأنشطة والمعالم المعرفية مقيّد</b>
        <span style="font-size:12.5px;">قام صاحب هذا الحساب بضبط خصوصيته أو تقييد التفاعل، لذلك تم حجب الأنشطة المباشرة.</span>
    </div>
</div>
@endif

<!-- Profile Tabs Navigation -->
<div class="ch-toolbar">
    <div class="rq-tabs pf-tabs" role="tablist">
        <button type="button" class="pf-tab active" onclick="switchProfileTab('activity', this)">
            <i data-lucide="activity" style="width:14px;height:14px;"></i>
            <span>النشاط القرائي والاجتماعي</span>
            <span class="tab-counter">{{ $userActivities->count() }}</span>
        </button>
        <button type="button" class="pf-tab" onclick="switchProfileTab('friends', this)">
            <i data-lucide="heart-handshake" style="width:14px;height:14px;"></i>
            <span>أصدقاء القراءة</span>
            <span class="tab-counter">{{ $isOwnProfile ? $friendsList->count() : $user->friends()->count() }}</span>
        </button>
        @if($isOwnProfile && $friendsActivities->isNotEmpty())
        <button type="button" class="pf-tab" onclick="switchProfileTab('friends-feed', this)">
            <i data-lucide="users" style="width:14px;height:14px;"></i>
            <span>خلاصة الرفقاء</span>
            <span class="tab-counter counter-unread">{{ $friendsActivities->count() }}</span>
        </button>
        @endif
        <button type="button" class="pf-tab" onclick="switchProfileTab('badges', this)">
            <i data-lucide="award" style="width:14px;height:14px;"></i>
            <span>الأوسمة والمعالم</span>
            <span class="tab-counter">{{ $user->badges->count() + $user->communityBadges->count() }}</span>
        </button>
        <button type="button" class="pf-tab" onclick="switchProfileTab('reflections', this)">
            <i data-lucide="quote" style="width:14px;height:14px;"></i>
            <span>المراجعات والتأملات</span>
            <span class="tab-counter">{{ $reviews->count() + $reflections->count() }}</span>
        </button>
        <button type="button" class="pf-tab" onclick="switchProfileTab('companions', this)">
            <i data-lucide="user-check" style="width:14px;height:14px;"></i>
            <span>المتابِعون والمتابَعون</span>
            <span class="tab-counter">{{ $user->followers->count() + $user->following->count() }}</span>
        </button>
    </div>
</div>

<!-- Tab 1: User Social Activity Feed -->
<div id="tab-activity" class="profile-tab-content animate-fade-in">
    <div class="grid2" style="margin-top:0;">
        <div style="display:flex;flex-direction:column;gap:14px;">
            <h2 style="font-size:16px;margin:0 0 4px;">سجل الحركات والمعالم المعرفية</h2>
            @forelse($userActivities as $act)
                <div class="panel card-tilt" style="padding:16px;background:#FFFDF8;border:1px solid var(--line);">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:8px;background:#FAF4E6;color:#785419;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i data-lucide="sparkles" style="width:16px;height:16px;"></i>
                            </div>
                            <div>
                                <b style="font-size:14px;color:var(--green);display:block;">{{ $act->title }}</b>
                                <small class="muted" style="font-size:11.5px;">{{ $act->created_at->locale('ar')->diffForHumans() }}</small>
                            </div>
                        </div>

                        <!-- Cheer Action Button -->
                        <form method="POST" action="{{ route('activities.cheer', $act->id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="secondary btn-sm" title="أرسل تشجيعاً طيباً" style="font-size:11.5px;padding:3px 10px;display:inline-flex;align-items:center;gap:5px;">
                                <i data-lucide="heart" style="width:12px;height:12px;color:var(--gold);"></i>
                                <span>{{ $act->cheers_count ?: 0 }}</span>
                            </button>
                        </form>
                    </div>

                    @if($act->content)
                        <p style="font-size:13px;color:var(--ink);line-height:1.6;margin:0 0 10px;background:#F9F7F2;padding:10px 12px;border-radius:8px;border-inline-start:3px solid var(--gold);">
                            {{ $act->content }}
                        </p>
                    @endif

                    @if($act->link)
                        <a href="{{ $act->link }}" style="font-size:12px;color:var(--green);text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                            <span>الانتقال للمصدر</span>
                            <i data-lucide="arrow-left" style="width:12px;height:12px;"></i>
                        </a>
                    @endif
                </div>
            @empty
                <div class="panel" style="text-align:center;padding:34px 16px;">
                    <i data-lucide="clock" style="width:32px;height:32px;color:#B5AFA2;margin-bottom:8px;"></i>
                    <p class="muted" style="margin:0;font-size:13px;">لا توجد أنشطة مسجلة مؤخراً.</p>
                </div>
            @endforelse
        </div>

        <!-- Sidebar / Summary column -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <!-- Currently Reading Card -->
            @if($activeBook && $activeBook->book)
                <div class="panel" style="padding:18px;background:linear-gradient(135deg, #FAF8F2 0%, #FFFDF9 100%);border:1px solid #C3A15D;">
                    <span class="eyebrow" style="color:var(--gold);">يقرأ الآن ✦</span>
                    <h3 style="font-size:16px;margin:4px 0 6px;font-family:'Noto Naskh Arabic', serif;">
                        {{ $activeBook->book->title }}
                    </h3>
                    <div class="muted" style="font-size:12.5px;margin-bottom:12px;">
                        تأليف: {{ $activeBook->book->author }} · صفحة {{ $activeBook->current_page }}
                    </div>
                    <a href="{{ route('books.show', $activeBook->book_id) }}" class="secondary btn-sm" style="font-size:12px;padding:4px 12px;display:inline-flex;align-items:center;gap:5px;">
                        <i data-lucide="book-open" style="width:13px;height:13px;"></i>
                        <span>بطاقة الكتاب</span>
                    </a>
                </div>
            @endif

            <!-- Recent Reading Sessions -->
            <div class="panel" style="padding:18px;">
                <h3 style="font-size:15px;margin:0 0 12px;border-bottom:1px solid var(--line);padding-bottom:8px;">
                    أحدث جلسات القراءة
                </h3>
                @forelse($readingSessions as $session)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line);font-size:12.5px;">
                        <div>
                            <b>{{ $session->book->title ?? 'كتاب' }}</b>
                            <div class="muted" style="font-size:11px;">{{ $session->created_at->locale('ar')->diffForHumans() }}</div>
                        </div>
                        <span class="tag" style="background:#E2ECDE;color:#214F34;font-size:11px;">
                            {{ $session->minutes }} دقيقة
                        </span>
                    </div>
                @empty
                    <p class="muted" style="font-size:12px;text-align:center;padding:12px 0;">لا توجد جلسات مسجلة بعد.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Tab 2: Friends Feed (if own profile) -->
@if($isOwnProfile && $friendsActivities->isNotEmpty())
<div id="tab-friends-feed" class="profile-tab-content animate-fade-in" style="display:none;">
    <h2 style="font-size:16px;margin:0 0 14px;">خلاصة من تتابعهم 🤝</h2>
    <div style="display:flex;flex-direction:column;gap:14px;">
        @foreach($friendsActivities as $act)
            <div class="panel card-tilt" style="padding:16px;background:#FFFDF8;border:1px solid var(--line);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <a href="{{ route('profile.show', $act->user_id) }}" style="text-decoration:none;">
                            <span class="avatar" style="width:34px;height:34px;font-size:14px;">
                                {{ $act->user->avatar ?? mb_substr($act->user->name, 0, 1) }}
                            </span>
                        </a>
                        <div>
                            <div style="font-size:13.5px;">
                                <a href="{{ route('profile.show', $act->user_id) }}" style="text-decoration:none;color:var(--green);font-weight:700;">
                                    {{ $act->user->name }}
                                </a>
                                <span class="muted" style="font-size:12px;">· {{ $act->title }}</span>
                            </div>
                            <small class="muted" style="font-size:11px;">{{ $act->created_at->locale('ar')->diffForHumans() }}</small>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('activities.cheer', $act->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="secondary btn-sm" title="تشجيع رفيق القراءة" style="font-size:11.5px;padding:3px 10px;display:inline-flex;align-items:center;gap:5px;">
                            <i data-lucide="sparkles" style="width:12px;height:12px;color:var(--gold);"></i>
                            <span>تشجيع ({{ $act->cheers_count ?: 0 }})</span>
                        </button>
                    </form>
                </div>

                @if($act->content)
                    <p style="font-size:13px;color:var(--ink);line-height:1.6;margin:0 0 8px;background:#F9F7F2;padding:10px 12px;border-radius:8px;">
                        {{ $act->content }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Tab 3: Badges & Honors -->
<div id="tab-badges" class="profile-tab-content animate-fade-in" style="display:none;">
    <div style="margin-bottom:24px;">
        <h2 style="font-size:16px;margin:0 0 14px;">الأوسمة العامة والإنجازات الشرفية (Global Badges)</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:16px;">
            @forelse($user->badges as $b)
                <div class="panel" style="padding:16px;background:#FFFDF8;border:1px solid #C3A15D;display:flex;gap:12px;align-items:center;">
                    <div style="width:44px;height:44px;border-radius:50%;background:#FAF4E6;color:#785419;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                        🏅
                    </div>
                    <div>
                        <b style="font-size:14px;color:var(--green);display:block;">{{ $b->name }}</b>
                        <small class="muted" style="font-size:12px;display:block;line-height:1.4;">{{ $b->description }}</small>
                    </div>
                </div>
            @empty
                <p class="muted" style="font-size:13px;">لا توجد أوسمة عامة مستحقة بعد. واصل القراءة لجمع نقاط الأثر!</p>
            @endforelse
        </div>
    </div>

    @if($user->communityBadges->isNotEmpty())
    <div>
        <h2 style="font-size:16px;margin:0 0 14px;">أوسمة نوادي القراءة (Community Badges)</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:16px;">
            @foreach($user->communityBadges as $cb)
                <div class="panel" style="padding:16px;background:#FFFDF8;border:1px solid var(--line);display:flex;gap:12px;align-items:center;">
                    <div style="width:44px;height:44px;border-radius:50%;background:#E2ECDE;color:#214F34;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                        🎖️
                    </div>
                    <div>
                        <b style="font-size:14px;color:var(--green);display:block;">{{ $cb->name }}</b>
                        <small class="muted" style="font-size:12px;display:block;line-height:1.4;">{{ $cb->description }}</small>
                        @if($cb->pivot->reason)
                            <small style="color:var(--gold);font-size:11px;display:block;margin-top:2px;">السبب: {{ $cb->pivot->reason }}</small>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<!-- Tab 4: Reviews & Reflections -->
<div id="tab-reflections" class="profile-tab-content animate-fade-in" style="display:none;">
    <div class="grid2" style="margin-top:0;">
        <!-- Reviews -->
        <div>
            <h2 style="font-size:16px;margin:0 0 12px;">المراجعات والتقييمات</h2>
            @forelse($reviews as $rev)
                <div class="panel" style="padding:16px;background:#FFFDF8;border:1px solid var(--line);margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <b>{{ $rev->book->title ?? 'كتاب' }}</b>
                        <span style="color:var(--gold);font-size:13px;">{{ str_repeat('★', $rev->rating) }}</span>
                    </div>
                    <p style="font-size:13px;line-height:1.6;color:var(--ink);margin:0 0 8px;">{{ $rev->content }}</p>
                    <small class="muted" style="font-size:11px;">{{ $rev->created_at->locale('ar')->diffForHumans() }}</small>
                </div>
            @empty
                <p class="muted" style="font-size:13px;">لا توجد مراجعات مكتوبة بعد.</p>
            @endforelse
        </div>

        <!-- Reflections & Quotes -->
        <div>
            <h2 style="font-size:16px;margin:0 0 12px;">التأملات والاقتباسات</h2>
            @forelse($reflections as $ref)
                <div class="panel" style="padding:16px;background:#FFFDF8;border:1px solid var(--line);margin-bottom:14px;">
                    <div class="muted" style="font-size:12px;margin-bottom:6px;">
                        من كتاب: <b>{{ $ref->book->title ?? 'كتاب' }}</b> · صفحة {{ $ref->page_number ?? '-' }}
                    </div>
                    <blockquote style="margin:0 0 8px;font-style:italic;font-family:'Noto Naskh Arabic', serif;font-size:14px;color:var(--green);border-inline-start:3px solid var(--gold);padding-inline-start:10px;">
                        «{{ $ref->quote }}»
                    </blockquote>
                    @if($ref->thought)
                        <p style="font-size:12.5px;color:var(--ink);margin:0 0 6px;">{{ $ref->thought }}</p>
                    @endif
                    <small class="muted" style="font-size:11px;">{{ $ref->created_at->locale('ar')->diffForHumans() }}</small>
                </div>
            @empty
                <p class="muted" style="font-size:13px;">لا توجد اقتباسات موثقة بعد.</p>
            @endforelse
        </div>
    </div>
</div>

<!-- Tab 5: Companions & Followers -->
<div id="tab-companions" class="profile-tab-content animate-fade-in" style="display:none;">
    <div class="grid2" style="margin-top:0;">
        <!-- Followers -->
        <div class="panel" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;">
                <h2 style="font-size:16px;margin:0;">رفقاء يتابعون رحلته ({{ $user->followers->count() }})</h2>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                @forelse($user->followers as $flw)
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <a href="{{ route('profile.show', $flw->id) }}" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;">
                            <span class="avatar" style="width:34px;height:34px;font-size:13px;">
                                {{ $flw->avatar ?? mb_substr($flw->name, 0, 1) }}
                            </span>
                            <div>
                                <b style="font-size:13.5px;display:block;">{{ $flw->name }}</b>
                                <small class="muted" style="font-size:11px;">{{ $flw->level_data['title'] }}</small>
                            </div>
                        </a>
                        <a href="{{ route('profile.show', $flw->id) }}" class="secondary btn-sm" style="font-size:11px;padding:2px 8px;text-decoration:none;">
                            الملف
                        </a>
                    </div>
                @empty
                    <p class="muted" style="font-size:12.5px;text-align:center;padding:14px 0;">لا يوجد متابعون بعد.</p>
                @endforelse
            </div>
        </div>

        <!-- Following -->
        <div class="panel" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;">
                <h2 style="font-size:16px;margin:0;">يتابعهم ({{ $user->following->count() }})</h2>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                @forelse($user->following as $flwing)
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <a href="{{ route('profile.show', $flwing->id) }}" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;">
                            <span class="avatar" style="width:34px;height:34px;font-size:13px;">
                                {{ $flwing->avatar ?? mb_substr($flwing->name, 0, 1) }}
                            </span>
                            <div>
                                <b style="font-size:13.5px;display:block;">{{ $flwing->name }}</b>
                                <small class="muted" style="font-size:11px;">{{ $flwing->level_data['title'] }}</small>
                            </div>
                        </a>
                        <a href="{{ route('profile.show', $flwing->id) }}" class="secondary btn-sm" style="font-size:11px;padding:2px 8px;text-decoration:none;">
                            الملف
                        </a>
                    </div>
                @empty
                    <p class="muted" style="font-size:12.5px;text-align:center;padding:14px 0;">لا يتابع أي قارئ حالياً.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Tab 6: Friends (أصدقاء القراءة والمؤاخاة) -->
<div id="tab-friends" class="profile-tab-content animate-fade-in" style="display:none;">
    <div class="panel" style="padding:20px;background:#FFFDF8;border:1px solid var(--line);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;">
            <h2 style="font-size:16px;margin:0;display:flex;align-items:center;gap:8px;">
                <i data-lucide="heart-handshake" style="width:18px;height:18px;color:var(--gold);"></i>
                <span>أصدقاء القراءة الموثقون ({{ $isOwnProfile ? $friendsList->count() : $user->friends()->count() }})</span>
            </h2>
            @if($isOwnProfile)
                <button type="button" class="primary btn-sm" onclick="document.getElementById('manageFriendsModal').style.display='flex'" style="font-size:12px;padding:4px 12px;background:var(--gold);color:#173D36;border:none;cursor:pointer;">
                    إدارة الطلبات والرفقاء
                </button>
            @endif
        </div>

        @php
            $displayFriends = $isOwnProfile ? $friendsList : $user->friends();
        @endphp

        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:14px;">
            @forelse($displayFriends as $frnd)
                <div class="card-tilt" style="background:#FAF8F3;border:1px solid var(--line);border-radius:12px;padding:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <a href="{{ route('profile.show', $frnd->id) }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;flex:1;min-width:0;">
                        <span class="avatar" style="width:42px;height:42px;font-size:16px;background:#FFFDF8;border:2px solid var(--gold);color:var(--green);">
                            {{ $frnd->avatar ?? mb_substr($frnd->name, 0, 1) }}
                        </span>
                        <div style="overflow:hidden;">
                            <b style="font-size:14px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $frnd->name }}</b>
                            <div class="muted" style="font-size:11.5px;">{{ $frnd->level_data['title'] }} · {{ $frnd->points }} نقطة</div>
                        </div>
                    </a>

                    @if($isOwnProfile)
                        <form method="POST" action="{{ route('friends.unfriend', $frnd->id) }}" onsubmit="return confirm('إلغاء الصداقة مع «{{ $frnd->name }}»؟');" style="margin:0;">
                            @csrf
                            <button type="submit" class="secondary btn-sm" style="font-size:11px;padding:4px 8px;color:#8F3829;" title="إلغاء الصداقة">
                                إلغاء
                            </button>
                        </form>
                    @else
                        <a href="{{ route('profile.show', $frnd->id) }}" class="secondary btn-sm" style="font-size:11.5px;padding:3px 10px;text-decoration:none;">
                            الملف
                        </a>
                    @endif
                </div>
            @empty
                <div style="grid-column:1/-1;text-align:center;padding:30px 10px;" class="muted">
                    <i data-lucide="users" style="width:36px;height:36px;opacity:0.4;margin-bottom:8px;"></i>
                    <p style="margin:0;font-size:13px;">لا توجد صداقات قرائية مسجلة حتى الآن.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Edit Profile Modal (for owner) -->
@if($isOwnProfile)
<div id="editProfileModal" class="overlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:480px;background:#FFFDF8;border-radius:16px;padding:24px;border:1px solid var(--line);box-shadow:0 12px 36px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h3 style="margin:0;font-size:18px;font-family:'Noto Naskh Arabic', serif;">تعديل الملف القرائي</h3>
            <button type="button" onclick="document.getElementById('editProfileModal').style.display='none'" style="border:none;background:transparent;font-size:20px;cursor:pointer;">&times;</button>
        </div>

        <form method="POST" action="{{ route('profile.updateBio') }}">
            @csrf
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">النبذة الشخصية (Bio):</label>
                <textarea name="bio" rows="4" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:10px;font-size:13px;font-family:inherit;outline:none;" placeholder="صف اهتماماتك القرائية وتطلعاتك المعرفية...">{{ $user->bio }}</textarea>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">الهدف القرائي السنوي (عدد الكتب):</label>
                <input type="number" name="annual_goal" value="{{ $user->annual_goal ?? 24 }}" min="1" max="365" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:13px;font-family:inherit;outline:none;">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button type="button" class="secondary" onclick="document.getElementById('editProfileModal').style.display='none'">إلغاء</button>
                <button type="submit" class="primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Friends, Pending Requests & Blocked Users Modal -->
<div id="manageFriendsModal" class="overlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:580px;background:#FFFDF8;border-radius:16px;padding:24px;border:1px solid var(--line);box-shadow:0 12px 36px rgba(0,0,0,0.2);max-height:85vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h3 style="margin:0;font-size:18px;font-family:'Noto Naskh Arabic', serif;display:flex;align-items:center;gap:8px;">
                <i data-lucide="heart-handshake" style="width:20px;height:20px;color:var(--gold);"></i>
                <span>إدارة الصداقات والطلبات والمحظورين</span>
            </h3>
            <button type="button" onclick="document.getElementById('manageFriendsModal').style.display='none'" style="border:none;background:transparent;font-size:20px;cursor:pointer;">&times;</button>
        </div>

        <!-- Incoming Requests Section -->
        <div style="margin-bottom:24px;">
            <h4 style="font-size:14px;margin:0 0 10px;color:var(--green);display:flex;align-items:center;justify-content:space-between;">
                <span>طلبات الصداقة الواردة المعلقة</span>
                <span class="tag" style="background:#FAF4E6;color:#785419;font-size:11px;">{{ $incomingRequests->count() }}</span>
            </h4>
            @forelse($incomingRequests as $req)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#FAF8F3;border-radius:10px;border:1px solid var(--line);margin-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="avatar" style="width:34px;height:34px;font-size:13px;">
                            {{ $req->sender->avatar ?? mb_substr($req->sender->name, 0, 1) }}
                        </span>
                        <div>
                            <b style="font-size:13.5px;display:block;">{{ $req->sender->name }}</b>
                            <small class="muted" style="font-size:11px;">أُرسل {{ $req->created_at->locale('ar')->diffForHumans() }}</small>
                        </div>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <form method="POST" action="{{ route('friends.accept', $req->sender_id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="primary btn-sm" style="font-size:11.5px;padding:4px 12px;background:var(--green);">
                                قبول 🤝
                            </button>
                        </form>
                        <form method="POST" action="{{ route('friends.reject', $req->sender_id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="secondary btn-sm" style="font-size:11.5px;padding:4px 10px;">
                                رفض
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted" style="font-size:12.5px;margin:0;padding:8px 0;">لا توجد أي طلبات صداقة واردة معلقة حالياً.</p>
            @endforelse
        </div>

        <!-- Sent Requests Section -->
        @if($sentRequests->isNotEmpty())
        <div style="margin-bottom:24px;">
            <h4 style="font-size:14px;margin:0 0 10px;color:var(--text);display:flex;align-items:center;justify-content:space-between;">
                <span>طلبات الصداقة المُرسلة (قيد الانتظار)</span>
                <span class="tag" style="font-size:11px;">{{ $sentRequests->count() }}</span>
            </h4>
            @foreach($sentRequests as $sReq)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#FAF8F3;border-radius:10px;border:1px solid var(--line);margin-bottom:6px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="avatar" style="width:30px;height:30px;font-size:12px;">
                            {{ $sReq->recipient->avatar ?? mb_substr($sReq->recipient->name, 0, 1) }}
                        </span>
                        <div>
                            <span style="font-size:13px;font-weight:600;">{{ $sReq->recipient->name }}</span>
                            <small class="muted" style="font-size:10.5px;display:block;">أُرسل {{ $sReq->created_at->locale('ar')->diffForHumans() }}</small>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('friends.unfriend', $sReq->recipient_id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="secondary btn-sm" style="font-size:11px;padding:3px 8px;">
                            إلغاء الطلب
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
        @endif

        <!-- Blocked Users Section -->
        <div style="margin-top:20px;border-top:1px solid var(--line);padding-top:16px;">
            <h4 style="font-size:14px;margin:0 0 10px;color:#8F3829;display:flex;align-items:center;justify-content:space-between;">
                <span>المستخدمون المحظورون ({{ $blockedUsers->count() }})</span>
                <small class="muted" style="font-size:11px;font-weight:normal;">المحظور لا يمكنه رؤية أنشطتك أو التفاعل معك</small>
            </h4>
            @forelse($blockedUsers as $blocked)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#FFF8F7;border-radius:8px;border:1px solid #ECC4BC;margin-bottom:6px;">
                    <div>
                        <b style="font-size:13px;color:#8F3829;">{{ $blocked->name }}</b>
                        <small class="muted" style="display:block;font-size:10.5px;">{{ $blocked->email }}</small>
                    </div>
                    <form method="POST" action="{{ route('friends.unblock', $blocked->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="secondary btn-sm" style="font-size:11px;padding:3px 10px;">
                            إلغاء الحظر
                        </button>
                    </form>
                </div>
            @empty
                <p class="muted" style="font-size:12px;margin:0;">قائمتك خالية من أي مستخدمين محظورين.</p>
            @endforelse
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:20px;">
            <button type="button" class="secondary" onclick="document.getElementById('manageFriendsModal').style.display='none'">إغلاق</button>
        </div>
    </div>
</div>

<!-- Curated Activity Privacy Preferences Modal -->
<div id="activityPrivacyModal" class="overlay" style="display:none;" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal" style="max-width:520px;background:#FFFDF8;border-radius:16px;padding:24px;border:1px solid var(--line);box-shadow:0 12px 36px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h3 style="margin:0;font-size:18px;font-family:'Noto Naskh Arabic', serif;display:flex;align-items:center;gap:8px;">
                <i data-lucide="shield" style="width:20px;height:20px;color:var(--gold);"></i>
                <span>تفضيلات خصوصية الأنشطة المعرفية</span>
            </h3>
            <button type="button" onclick="document.getElementById('activityPrivacyModal').style.display='none'" style="border:none;background:transparent;font-size:20px;cursor:pointer;">&times;</button>
        </div>

        <p class="muted" style="font-size:12.5px;line-height:1.6;margin-bottom:18px;">
            النشاط الاجتماعي في رِواق لا يعرض إلا الأحداث المعرفية الرصينة. يمكنك هنا اختيار أي من هذه الأنشطة ترغب في إخفائه عن رفقائك وعن الخلاصة العامة:
        </p>

        <form method="POST" action="{{ route('profile.updateActivityPrivacy') }}">
            @csrf
            @php
                $curatedOptions = [
                    \App\Models\SocialActivity::TYPE_BOOK_COMPLETED => 'إتمام قراءة الكتب (إنجاز كتاب كامل)',
                    \App\Models\SocialActivity::TYPE_READING_SESSION => 'جلسات القراءة وملاحظات التقدم',
                    \App\Models\SocialActivity::TYPE_CHALLENGE_WON => 'الفوز بالتحديات القرائية والمراحل المكتملة',
                    \App\Models\SocialActivity::TYPE_MEETING_ATTENDED => 'حضور الندوات والاجتماعات القرائية',
                    \App\Models\SocialActivity::TYPE_BADGE_EARNED => 'الأوسمة والمعالم المكتسبة',
                    \App\Models\SocialActivity::TYPE_QUOTE_SHARED => 'الاقتباسات والتأملات المشاركة',
                    \App\Models\SocialActivity::TYPE_REVIEW_PUBLISHED => 'المراجعات النقدية للكتب',
                    \App\Models\SocialActivity::TYPE_FRIEND_CONNECTED => 'المؤاخاة القرائية وبدء الصداقات',
                ];
                $hidden = $user->hidden_activity_types ?? [];
            @endphp

            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
                @foreach($curatedOptions as $typeKey => $typeLabel)
                    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#FAF8F3;border-radius:8px;border:1px solid var(--line);cursor:pointer;font-size:13px;">
                        <input type="checkbox" name="hidden_types[]" value="{{ $typeKey }}" {{ in_array($typeKey, $hidden) ? 'checked' : '' }} style="accent-color:#8F3829;width:16px;height:16px;">
                        <span>إخفاء <strong>{{ $typeLabel }}</strong></span>
                    </label>
                @endforeach
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="secondary" onclick="document.getElementById('activityPrivacyModal').style.display='none'">إلغاء</button>
                <button type="submit" class="primary">حفظ إعدادات الخصوصية</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
function switchProfileTab(tabName, btn) {
    document.querySelectorAll('.profile-tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.pf-tabs .pf-tab').forEach(el => el.classList.remove('active'));
    
    const target = document.getElementById('tab-' + tabName);
    if (target) {
        target.style.display = 'block';
    }
    if (btn) {
        btn.classList.add('active');
    }
}
</script>
@endsection
