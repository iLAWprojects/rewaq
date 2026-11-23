<?php

namespace App\Models;

use App\Services\AntiGamingService;
use App\Services\MfaService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'bio',
        'hidden_activity_types',
        'annual_goal',
        'annual_goal_pages',
        'annual_goal_hours',
        'books_finished',
        'reading_streak',
        'last_read_date',
        'points',
        'level',
        'timezone',
        'notification_preferences',
        'push_opt_in',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'digest_enabled',
        'digest_time',
        'is_suspended',
        'suspension_reason',
        'mfa_enabled',
        'mfa_secret',
        'mfa_confirmed_at',
        'recovery_codes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'quiet_hours_enabled' => 'boolean',
            'digest_enabled' => 'boolean',
            'is_suspended' => 'boolean',
            'hidden_activity_types' => 'array',
            'push_opt_in' => 'boolean',
            'notification_preferences' => 'array',
            'mfa_enabled' => 'boolean',
            'mfa_confirmed_at' => 'datetime',
            'recovery_codes' => 'array',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function canManagePlatform(): bool
    {
        return $this->isAdmin();
    }

    public function libraries(): HasMany
    {
        return $this->hasMany(Library::class);
    }

    public function libraryMemberships(): HasMany
    {
        return $this->hasMany(LibraryMember::class);
    }

    public function joinedLibraries(): BelongsToMany
    {
        return $this->belongsToMany(Library::class, 'library_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function getPersonalLibrary(): Library
    {
        $lib = Library::firstOrCreate(
            ['user_id' => $this->id, 'is_personal' => true],
            [
                'name' => 'المكتبة الشخصية',
                'description' => 'مساحتك الخاصة لحفظ وإعارة وتتبع نسخ كتبك الورقية على مرأى منك.',
                'code' => 'LIB-'.strtoupper(Str::random(6)),
                'type' => 'personal',
                'cover_color' => '#173D36',
            ]
        );

        LibraryMember::firstOrCreate(
            ['library_id' => $lib->id, 'user_id' => $this->id],
            ['role' => 'owner', 'joined_at' => now()]
        );

        return $lib;
    }

    public function addBookToPersonalLibrary(Book $book, string $condition = 'good', ?string $shelfLocation = 'الخزانة الشخصية'): BookCopy
    {
        $canonical = Book::findMatching($book->title, $book->author) ?? $book;
        $library = $this->getPersonalLibrary();

        $existingCopy = BookCopy::where('book_id', $canonical->id)
            ->where('library_id', $library->id)
            ->first();

        if ($existingCopy) {
            return $existingCopy;
        }

        $nextCopyNum = BookCopy::where('book_id', $canonical->id)
            ->where('library_id', $library->id)
            ->count() + 1;

        return BookCopy::create([
            'book_id' => $canonical->id,
            'library_id' => $library->id,
            'copy_number' => (string) $nextCopyNum,
            'status' => 'available',
            'condition' => $condition,
            'shelf_location' => $shelfLocation ?: 'الخزانة الشخصية',
            'barcode' => 'RWQ-PERS-'.$this->id.'-'.$canonical->id.'-'.rand(100, 999),
            'cover_image' => $canonical->cover_image ?: $book->cover_image,
        ]);
    }

    public function hasBookInPersonalLibrary(int|Book $book): bool
    {
        $bookObj = $book instanceof Book ? $book : Book::find($book);
        if (! $bookObj) {
            return false;
        }

        $library = $this->libraries()->where('is_personal', true)->first();
        if (! $library) {
            return false;
        }

        // Direct check by ID
        if (BookCopy::where('library_id', $library->id)->where('book_id', $bookObj->id)->exists()) {
            return true;
        }

        // Resilient check by normalized title
        $normalized = Book::normalizeTitle($bookObj->title);

        return BookCopy::where('library_id', $library->id)
            ->with('book')
            ->get()
            ->contains(function ($copy) use ($normalized) {
                return $copy->book && Book::normalizeTitle($copy->book->title) === $normalized;
            });
    }

    public function syncReadingBooksToPersonalLibrary(): void
    {
        // الكتب المنتهية أو المتروكة لا تُعاد تلقائيًا، حتى يبقى حذفها من المكتبة نهائيًا
        $readingBookIds = UserCurrentBook::where('user_id', $this->id)
            ->whereIn('status', ['currently_reading', 'want_to_read', 'on_hold'])
            ->pluck('book_id')
            ->filter()
            ->unique();

        if ($readingBookIds->isEmpty()) {
            return;
        }

        $library = $this->getPersonalLibrary();

        $existingCopyBookIds = BookCopy::where('library_id', $library->id)
            ->whereIn('book_id', $readingBookIds)
            ->pluck('book_id')
            ->toArray();

        $missingBookIds = $readingBookIds->diff($existingCopyBookIds);

        foreach ($missingBookIds as $bookId) {
            $book = Book::find($bookId);
            if ($book) {
                $this->addBookToPersonalLibrary($book);
            }
        }
    }

    /**
     * Automatically synchronize reading session progress to all active challenges the user is enrolled in.
     */
    public function syncReadingToChallenges(int $pagesRead, ?Book $book = null, ?ReadingSession $session = null): int
    {
        if ($pagesRead <= 0) {
            return 0;
        }

        $activeChallenges = $this->challenges()
            ->where('challenges.is_active', true)
            ->where('challenges.status', Challenge::STATUS_ACTIVE)
            ->get();

        $syncedCount = 0;
        foreach ($activeChallenges as $challenge) {
            if ($challenge->start_date && now()->lt($challenge->start_date->startOfDay())) {
                continue;
            }
            if ($challenge->end_date && now()->gt($challenge->end_date->endOfDay())) {
                continue;
            }

            if ($challenge->specific_book_id && $book && $challenge->specific_book_id != $book->id) {
                continue;
            }

            // Prevent duplicate crediting of the same reading session
            if ($session && $challenge->progressLogs()->where('reading_session_id', $session->id)->exists()) {
                continue;
            }

            $bookTitle = $book ? $book->title : 'كتاب';
            $sessionNote = $session
                ? "جلسة قراءة موثقة ({$pagesRead} ص) في «{$bookTitle}» عبر سجل القراءة"
                : "تقدّم قراءة موثق ({$pagesRead} ص) في «{$bookTitle}» عبر سجل القراءة";

            $challenge->logUserProgress(
                $this,
                $pagesRead,
                'pages_read',
                $sessionNote,
                $book?->id,
                $session?->id
            );

            $syncedCount++;
        }

        return $syncedCount;
    }

    /**
     * Automatically synchronize book completion to all active book-based challenges.
     */
    public function syncBookCompletedToChallenges(Book $book, ?ReadingSession $session = null): int
    {
        $activeChallenges = $this->challenges()
            ->where('challenges.is_active', true)
            ->where('challenges.status', Challenge::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereIn('challenges.challenge_type', [
                    Challenge::TYPE_BOOKS_COUNT,
                    Challenge::TYPE_SPECIFIC_BOOK,
                ])->orWhereIn('challenges.type', [
                    Challenge::TYPE_BOOKS_COUNT,
                    Challenge::TYPE_SPECIFIC_BOOK,
                ]);
            })
            ->get();

        $syncedCount = 0;
        foreach ($activeChallenges as $challenge) {
            if ($challenge->start_date && now()->lt($challenge->start_date->startOfDay())) {
                continue;
            }
            if ($challenge->end_date && now()->gt($challenge->end_date->endOfDay())) {
                continue;
            }
            if ($challenge->specific_book_id && $challenge->specific_book_id != $book->id) {
                continue;
            }

            $alreadyLogged = $challenge->progressLogs()
                ->where('user_id', $this->id)
                ->where('book_id', $book->id)
                ->where('event_type', 'book_completed')
                ->exists();

            if ($alreadyLogged) {
                continue;
            }

            $challenge->logUserProgress(
                $this,
                1,
                'book_completed',
                "إتمام قراءة كتاب «{$book->title}» موثق عبر سجل القراءة",
                $book->id,
                $session?->id
            );

            $syncedCount++;
        }

        return $syncedCount;
    }

    public function waitlists(): HasMany
    {
        return $this->hasMany(BookWaitlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BookReview::class);
    }

    public function communityJoinRequests(): HasMany
    {
        return $this->hasMany(CommunityJoinRequest::class);
    }

    public function sentCommunityInvitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class, 'inviter_id');
    }

    public function receivedCommunityInvitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class, 'recipient_user_id');
    }

    public function readingSessions(): HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class);
    }

    public function currentBooks(): HasMany
    {
        return $this->hasMany(UserCurrentBook::class);
    }

    public function activeBook()
    {
        return $this->currentBooks()->where('is_active', true)->with('book')->first();
    }

    public function currentBook(): ?Book
    {
        return $this->activeBook()?->book;
    }

    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, 'community_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function challenges(): BelongsToMany
    {
        return $this->belongsToMany(Challenge::class, 'challenge_participants')
            ->withPivot('progress_count', 'points_earned', 'team_id', 'completed', 'completed_at', 'rank', 'certificate_code', 'last_milestone')
            ->withTimestamps();
    }

    public function challengeProgressLogs(): HasMany
    {
        return $this->hasMany(ChallengeProgressLog::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(MeetingRsvp::class);
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(UserPushSubscription::class);
    }

    public function notificationDeliveryLogs(): HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    public function userBadges(): BelongsToMany
    {
        return $this->badges();
    }

    public function communityBadges(): BelongsToMany
    {
        return $this->belongsToMany(CommunityBadge::class, 'community_badge_user')
            ->withPivot('awarded_by', 'reason', 'awarded_at')
            ->withTimestamps();
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class)->latest();
    }

    public function bookNominations(): HasMany
    {
        return $this->hasMany(BookNomination::class)->latest();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function socialActivities(): HasMany
    {
        return $this->hasMany(SocialActivity::class)->latest();
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function follow(User $user, bool $notify = true): void
    {
        if ($this->id !== $user->id && ! $this->isFollowing($user)) {
            $this->following()->attach($user->id);

            // إشعار القارئ بأن له متابعًا جديدًا (نتجنبه عند الإرسال مع طلب الصداقة لأن طلب الصداقة يحمل إشعاره)
            if ($notify) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'title' => 'متابِع جديد 🤝',
                    'body' => "بدأ «{$this->name}» بمتابعة رحلتك القرائية وأنشطتك في رواق المعرفة.",
                    'icon' => 'user-plus',
                    'link' => route('profile.show', $this->id),
                    'type' => 'social',
                ]);
            }

            // تسجيل نشاط اجتماعي
            SocialActivity::log(
                $this,
                'friend_followed',
                "بدأ بمتابعة «{$user->name}»",
                "انضم «{$this->name}» إلى دائرة متابعة القارئ «{$user->name}» لتبادل الأفكار والأنشطة القرائية.",
                route('profile.show', $user->id)
            );
        }
    }

    public function unfollow(User $user): void
    {
        $this->following()->detach($user->id);
    }

    // ==========================================
    // 6.8 Friend Requests, Unfriend & Block
    // ==========================================

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'recipient_id');
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id')->withTimestamps();
    }

    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'blocker_id')->withTimestamps();
    }

    public function hasBlocked(User $user): bool
    {
        return $this->blockedUsers()->where('blocked_id', $user->id)->exists();
    }

    public function isBlockedBy(User $user): bool
    {
        return $this->blockedByUsers()->where('blocker_id', $user->id)->exists();
    }

    public function isBlockedWith(User $user): bool
    {
        return $this->hasBlocked($user) || $this->isBlockedBy($user);
    }

    /**
     * Get all accepted friends for this user
     */
    public function friends()
    {
        $sent = Friendship::where('sender_id', $this->id)
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->pluck('recipient_id');

        $received = Friendship::where('recipient_id', $this->id)
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->pluck('sender_id');

        $friendIds = $sent->merge($received)->unique();

        return User::whereIn('id', $friendIds)->get();
    }

    public function friendsCount(): int
    {
        $sent = Friendship::where('sender_id', $this->id)
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->pluck('recipient_id');

        $received = Friendship::where('recipient_id', $this->id)
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->pluck('sender_id');

        return $sent->merge($received)->unique()->count();
    }

    public function friendshipWith(User $user): ?Friendship
    {
        return Friendship::where(function ($q) use ($user) {
            $q->where('sender_id', $this->id)->where('recipient_id', $user->id);
        })->orWhere(function ($q) use ($user) {
            $q->where('sender_id', $user->id)->where('recipient_id', $this->id);
        })->first();
    }

    public function friendshipStatusWith(User $user): ?string
    {
        if ($this->isBlockedWith($user)) {
            return 'blocked';
        }

        $friendship = $this->friendshipWith($user);
        if (! $friendship) {
            return null;
        }

        if ($friendship->status === Friendship::STATUS_ACCEPTED) {
            return 'friends';
        }

        if ($friendship->status === Friendship::STATUS_PENDING) {
            return $friendship->sender_id === $this->id ? 'pending_sent' : 'pending_received';
        }

        return $friendship->status; // 'rejected'
    }

    public function sendFriendRequest(User $recipient): Friendship
    {
        if ($this->id === $recipient->id) {
            throw new \InvalidArgumentException('لا يمكنك إرسال طلب صداقة لنفسك.');
        }

        if ($this->isBlockedWith($recipient)) {
            throw new \InvalidArgumentException('تعذر إرسال طلب الصداقة بسبب إعدادات الحظر.');
        }

        // إرسال طلب الصداقة يعني متابعة تلقائية للمستلم، وتبقى المتابعة حتى لو رُفض الطلب
        $this->follow($recipient, false);

        $existing = $this->friendshipWith($recipient);
        if ($existing) {
            if ($existing->isAccepted()) {
                return $existing;
            }

            // Mutual intent: if recipient already sent request, auto-accept!
            if ($existing->sender_id === $recipient->id && $existing->isPending()) {
                $existing->update(['status' => Friendship::STATUS_ACCEPTED]);

                return $existing;
            }

            $existing->update([
                'sender_id' => $this->id,
                'recipient_id' => $recipient->id,
                'status' => Friendship::STATUS_PENDING,
            ]);
            $friendship = $existing;
        } else {
            $friendship = Friendship::create([
                'sender_id' => $this->id,
                'recipient_id' => $recipient->id,
                'status' => Friendship::STATUS_PENDING,
            ]);
        }

        UserNotification::create([
            'user_id' => $recipient->id,
            'title' => 'طلب صداقة معرفية جديد 🤝',
            'body' => "أرسل لك «{$this->name}» طلب صداقة لمشاركتك الرحلة القرائية وتأملات الكتب.",
            'icon' => 'user-plus',
            'link' => route('profile.show', $this->id),
            'type' => 'friend_request',
        ]);

        return $friendship;
    }

    public function acceptFriendRequest(User $sender): bool
    {
        $friendship = Friendship::where('sender_id', $sender->id)
            ->where('recipient_id', $this->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->first();

        if (! $friendship) {
            return false;
        }

        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);

        // Also mutually ensure following relationship
        $this->follow($sender);
        $sender->follow($this);

        UserNotification::create([
            'user_id' => $sender->id,
            'title' => 'تم قبول طلب الصداقة! 🌿',
            'body' => "وافق «{$this->name}» على طلب الصداقة، وبدأت أواصر الأخوة المعرفية بينكما.",
            'icon' => 'user-check',
            'link' => route('profile.show', $this->id),
            'type' => 'friend_accepted',
        ]);

        SocialActivity::log(
            $this,
            SocialActivity::TYPE_FRIEND_CONNECTED,
            "ارتبط بصداقة معرفية مع «{$sender->name}» 🤝",
            "تآخت المسارات القرائية بين «{$this->name}» و «{$sender->name}» لتبادل الفكر والأنشطة.",
            route('profile.show', $sender->id)
        );

        return true;
    }

    public function rejectFriendRequest(User $sender): bool
    {
        $friendship = Friendship::where('sender_id', $sender->id)
            ->where('recipient_id', $this->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->first();

        if (! $friendship) {
            return false;
        }

        $friendship->update(['status' => Friendship::STATUS_REJECTED]);

        return true;
    }

    public function unfriend(User $friend): bool
    {
        $friendship = $this->friendshipWith($friend);
        if ($friendship && $friendship->isAccepted()) {
            $friendship->delete();

            return true;
        }

        return false;
    }

    public function blockUser(User $target, ?string $reason = null): void
    {
        if ($this->id === $target->id) {
            return;
        }

        // 1. Remove any friendship records
        $friendship = $this->friendshipWith($target);
        if ($friendship) {
            $friendship->delete();
        }

        // 2. Remove follows
        $this->following()->detach($target->id);
        $this->followers()->detach($target->id);

        // 3. Create block record
        UserBlock::firstOrCreate([
            'blocker_id' => $this->id,
            'blocked_id' => $target->id,
        ], [
            'reason' => $reason,
        ]);

        AuditLog::record(
            $this,
            'user_blocked',
            $target,
            "قام «{$this->name}» بحظر المستخدم «{$target->name}»."
        );
    }

    public function unblockUser(User $target): bool
    {
        $deleted = UserBlock::where('blocker_id', $this->id)
            ->where('blocked_id', $target->id)
            ->delete();

        if ($deleted) {
            AuditLog::record(
                $this,
                'user_unblocked',
                $target,
                "قام «{$this->name}» بإلغاء حظر المستخدم «{$target->name}»."
            );

            return true;
        }

        return false;
    }

    // ==========================================
    // Activity Privacy Preferences
    // ==========================================

    public function hasHiddenActivityType(string $type): bool
    {
        $hidden = (array) ($this->hidden_activity_types ?? []);

        return in_array($type, $hidden);
    }

    public function updateActivityPrivacy(array $hiddenTypes): void
    {
        $allowedCurated = SocialActivity::$curatedTypes;
        $validHidden = array_intersect($hiddenTypes, $allowedCurated);
        $this->update(['hidden_activity_types' => array_values($validHidden)]);
    }

    /**
     * حساب المستوى القرائي والرتبة المعرفية بناءً على رصيد نقاط الأثر
     */
    public function getLevelDataAttribute(): array
    {
        $pts = (int) ($this->points ?? 0);

        if ($pts >= 1000) {
            return [
                'number' => 5,
                'name' => 'حكيم الرواق',
                'title' => 'حكيم الرواق 👑',
                'icon' => 'crown',
                'color' => '#C3A15D',
                'min_points' => 1000,
                'next_points' => null,
                'points_needed' => 0,
                'progress_pct' => 100,
            ];
        } elseif ($pts >= 600) {
            $next = 1000;
            $min = 600;
            $progress = round((($pts - $min) / ($next - $min)) * 100);

            return [
                'number' => 4,
                'name' => 'باحث معرفي',
                'title' => 'باحث معرفي 📜',
                'icon' => 'scroll',
                'color' => '#235738',
                'min_points' => 600,
                'next_points' => 1000,
                'points_needed' => 1000 - $pts,
                'progress_pct' => min(100, max(0, $progress)),
            ];
        } elseif ($pts >= 300) {
            $next = 600;
            $min = 300;
            $progress = round((($pts - $min) / ($next - $min)) * 100);

            return [
                'number' => 3,
                'name' => 'قارئ متمرس',
                'title' => 'قارئ متمرس 🏛️',
                'icon' => 'landmark',
                'color' => '#1B4D3E',
                'min_points' => 300,
                'next_points' => 600,
                'points_needed' => 600 - $pts,
                'progress_pct' => min(100, max(0, $progress)),
            ];
        } elseif ($pts >= 100) {
            $next = 300;
            $min = 100;
            $progress = round((($pts - $min) / ($next - $min)) * 100);

            return [
                'number' => 2,
                'name' => 'قارئ مطلع',
                'title' => 'قارئ مطلع 📖',
                'icon' => 'book-open',
                'color' => '#785419',
                'min_points' => 100,
                'next_points' => 300,
                'points_needed' => 300 - $pts,
                'progress_pct' => min(100, max(0, $progress)),
            ];
        } else {
            $next = 100;
            $progress = round(($pts / 100) * 100);

            return [
                'number' => 1,
                'name' => 'قارئ ناشئ',
                'title' => 'قارئ ناشئ 🌿',
                'icon' => 'seedling',
                'color' => '#4A6B53',
                'min_points' => 0,
                'next_points' => 100,
                'points_needed' => 100 - $pts,
                'progress_pct' => min(100, max(0, $progress)),
            ];
        }
    }

    /**
     * حساب إجمالي النقاط الصحيحة والمحققة فقط من سجل المعاملات (غير القابل للتلاعب)
     */
    public function validPointsTotal(): int
    {
        return (int) $this->pointTransactions()
            ->where('is_valid', true)
            ->sum('points');
    }

    /**
     * حساب المستوى القرائي المشتق حصراً من إجمالي النقاط الصالحة
     */
    public function calculateLevel(): int
    {
        $validPts = $this->validPointsTotal();

        if ($validPts >= 1000) {
            return 5;
        }
        if ($validPts >= 600) {
            return 4;
        }
        if ($validPts >= 300) {
            return 3;
        }
        if ($validPts >= 100) {
            return 2;
        }

        return 1;
    }

    /**
     * مزامنة رصيد النقاط والمستوى المخزنين لأغراض الأداء مع دفتر المعاملات المحقق
     */
    public function syncPointsAndLevel(): void
    {
        $validPts = $this->validPointsTotal();
        $derivedLevel = $this->calculateLevel();

        $this->update([
            'points' => $validPts,
            'level' => $derivedLevel,
        ]);
    }

    /**
     * منح المستخدم نقاط أثر وتوثيقها في سجل الحركات وتطبيق قواعد مكافحة التلاعب (Anti-Gaming)
     */
    public function addPoints(
        int $points,
        string $activityType,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $metadata = []
    ): PointTransaction {
        $antiGaming = app(AntiGamingService::class);
        $capCheck = $antiGaming->checkDailyCap($this, $activityType, $points);

        $awardedPoints = $capCheck['allowed_points'];
        $isValid = $awardedPoints > 0;
        $status = $isValid ? PointTransaction::STATUS_VERIFIED : PointTransaction::STATUS_FLAGGED;

        if ($capCheck['capped'] && ! empty($capCheck['reason'])) {
            $metadata['cap_note'] = $capCheck['reason'];
            $metadata['requested_points'] = $points;
        }

        $transaction = PointTransaction::create([
            'user_id' => $this->id,
            'points' => $awardedPoints,
            'is_valid' => $isValid,
            'status' => $status,
            'activity_type' => $activityType,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
        ]);

        if ($isValid) {
            $this->increment('points', $awardedPoints);

            // اشتقاق المستوى من دفتر النقاط المحقق
            $derivedLevel = $this->calculateLevel();
            if ((int) $this->level !== $derivedLevel) {
                $this->update(['level' => $derivedLevel]);
            }

            // فحص الأوسمة العامة القائمة على القواعد (Rule-Based)
            $this->checkAndAwardBadges();
        }

        return $transaction;
    }

    /**
     * التحقق من استحقاق الأوسمة العامة وفق القواعد المعرفية الموثقة (Rule-based)
     */
    public function checkAndAwardBadges(): void
    {
        $badges = Badge::all();
        $earnedIds = $this->badges()->pluck('badges.id')->toArray();

        foreach ($badges as $badge) {
            if (! in_array($badge->id, $earnedIds) && $badge->evaluateRule($this)) {
                $this->badges()->attach($badge->id, [
                    'awarded_at' => now(),
                ]);

                UserNotification::create([
                    'user_id' => $this->id,
                    'title' => "استحققت وسام «{$badge->name}» 🏅",
                    'body' => "تهانينا! حققت متطلبات وسام «{$badge->name}» في رواق المعرفة: {$badge->description}",
                    'icon' => 'award',
                    'link' => route('reading.index', ['tab' => 'achievements']),
                    'type' => 'badge',
                ]);

                SocialActivity::log(
                    $this,
                    SocialActivity::TYPE_BADGE_EARNED,
                    "استحق وسام «{$badge->name}» 🏅",
                    $badge->description,
                    route('reading.index', ['tab' => 'achievements'])
                );
            }
        }
    }

    public function yearlyGoals(): HasMany
    {
        return $this->hasMany(YearlyReadingGoal::class)->orderByDesc('year');
    }

    /**
     * Preserves and fetches the yearly goal for the specified year.
     */
    public function getOrCreateCurrentYearlyGoal(?int $year = null): YearlyReadingGoal
    {
        $year = $year ?: (int) date('Y');

        $goal = YearlyReadingGoal::firstOrCreate(
            ['user_id' => $this->id, 'year' => $year],
            [
                'target_books' => $this->annual_goal ?: 24,
                'target_pages' => $this->annual_goal_pages ?: null,
                'target_hours' => $this->annual_goal_hours ?: null,
            ]
        );

        $goal->syncAchievements();

        return $goal;
    }

    /**
     * Anti-Tamper Reading Streak Rule (قاعدة حساب التتابع القرائي لمنع التلاعب):
     * 1. Evaluates unique calendar dates in chronological sequence from the database.
     * 2. Only counts eligible sessions: at least 5 minutes of focused reading OR at least 2 pages read.
     * 3. Prevents future timestamps. Multiple sessions in the same day count as 1 continuous day.
     * 4. Idempotently computes unbroken days of daily reading up to today/yesterday.
     */
    public function calculateReadingStreak(): int
    {
        $today = Carbon::today();

        $dates = $this->readingSessions()
            ->valid()
            ->where(function ($q) {
                $q->where('minutes', '>=', 5)
                    ->orWhere('pages_read', '>=', 2);
            })
            ->get()
            ->map(function ($s) {
                return ($s->started_at ?? $s->created_at)?->format('Y-m-d');
            })
            ->filter(function ($dateStr) use ($today) {
                return ! empty($dateStr) && Carbon::parse($dateStr)->startOfDay()->lte($today);
            })
            ->unique()
            ->sortDesc()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $latest = Carbon::parse($dates->first())->startOfDay();
        if (! $latest->isToday() && ! $latest->isYesterday()) {
            return 0;
        }

        $streak = 1;
        $currentDay = $latest;

        for ($i = 1; $i < $dates->count(); $i++) {
            $prevDay = Carbon::parse($dates[$i])->startOfDay();
            $diff = abs((int) round($currentDay->diffInDays($prevDay)));
            if ($diff === 1) {
                $streak++;
                $currentDay = $prevDay;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Knowledge Diversity Index (مؤشر التنوع المعرفي):
     * Calculates the reader's intellectual balance across:
     * 1. Categories (45% weight) using Simpson's Complementary Diversity Index: 1 - sum(p_i^2)
     * 2. Authors (35% weight) rewarding multi-author exposure
     * 3. Languages & Sources (20% weight) assessing original vs translated vs multilingual reading
     */
    public function calculateKnowledgeDiversityIndex(): array
    {
        $bookIds = $this->readingSessions()->valid()->pluck('book_id')
            ->merge($this->currentBooks()->whereIn('status', ['currently_reading', 'completed'])->pluck('book_id'))
            ->filter()
            ->unique()
            ->values();

        if ($bookIds->isEmpty()) {
            return [
                'score' => 0,
                'category_score' => 0,
                'author_score' => 0,
                'language_score' => 0,
                'books_count' => 0,
                'categories_count' => 0,
                'authors_count' => 0,
                'languages_count' => 0,
                'categories_distribution' => [],
                'authors_distribution' => [],
                'languages_distribution' => [],
                'formula' => 'KDI = (0.45 × D_cat + 0.35 × D_auth + 0.20 × D_lang) × 100',
                'formula_doc' => 'KDI = (0.45 × D_cat + 0.35 × D_auth + 0.20 × D_lang) × 100',
            ];
        }

        $books = Book::whereIn('id', $bookIds)->get();
        $totalBooks = $books->count();

        // 1. Categories Diversity
        $catCounts = $books->groupBy(fn ($b) => $b->category ?: 'عام')->map->count();
        $distinctCats = $catCounts->count();
        $sumCatP2 = 0;
        foreach ($catCounts as $cnt) {
            $p = $cnt / $totalBooks;
            $sumCatP2 += ($p * $p);
        }
        $simpsonCat = 1 - $sumCatP2;
        $scalingCat = min(1.0, $distinctCats / 3.5);
        $dCat = $simpsonCat * $scalingCat;

        // 2. Authors Diversity
        $authCounts = $books->groupBy(fn ($b) => $b->author ?: 'مؤلف')->map->count();
        $distinctAuths = $authCounts->count();
        $sumAuthP2 = 0;
        foreach ($authCounts as $cnt) {
            $p = $cnt / $totalBooks;
            $sumAuthP2 += ($p * $p);
        }
        $simpsonAuth = 1 - $sumAuthP2;
        $scalingAuth = min(1.0, $distinctAuths / 4);
        $dAuth = $simpsonAuth * $scalingAuth;

        // 3. Languages Diversity
        $langCounts = $books->groupBy(fn ($b) => $b->language ?: 'العربية')->map->count();
        $distinctLangs = $langCounts->count();
        $sumLangP2 = 0;
        foreach ($langCounts as $cnt) {
            $p = $cnt / $totalBooks;
            $sumLangP2 += ($p * $p);
        }
        $dLang = (1 - $sumLangP2) * min(1.0, $distinctLangs / 1.5);

        // Weighted Composite Score (Normalized)
        $compositeRaw = (0.45 * $dCat + 0.35 * $dAuth + 0.20 * $dLang);
        $normalizedScore = min(100, max(0, (int) round(($compositeRaw / 0.75) * 100)));

        return [
            'score' => $normalizedScore,
            'category_score' => min(100, (int) round(($dCat / 0.75) * 100)),
            'author_score' => min(100, (int) round(($dAuth / 0.75) * 100)),
            'language_score' => min(100, (int) round(($dLang / 0.75) * 100)),
            'books_count' => $totalBooks,
            'categories_count' => $distinctCats,
            'authors_count' => $distinctAuths,
            'languages_count' => $distinctLangs,
            'categories_distribution' => $catCounts->toArray(),
            'authors_distribution' => $authCounts->toArray(),
            'languages_distribution' => $langCounts->toArray(),
            'formula' => 'KDI = (0.45 × D_cat + 0.35 × D_auth + 0.20 × D_lang) × 100',
            'formula_doc' => 'KDI = (0.45 × D_cat + 0.35 × D_auth + 0.20 × D_lang) × 100',
        ];
    }

    public function readingDiversityScore(): float
    {
        $kdi = $this->calculateKnowledgeDiversityIndex();

        return (float) ($kdi['score'] ?? 0);
    }

    /**
     * Default notification preferences matrix
     */
    public function defaultNotificationPreferences(): array
    {
        return [
            'communities' => ['in_app' => true, 'push' => true],
            'challenges' => ['in_app' => true, 'push' => true],
            'meetings' => ['in_app' => true, 'push' => true],
            'loans' => ['in_app' => true, 'push' => true],
            'comments' => ['in_app' => true, 'push' => false],
            'badges' => ['in_app' => true, 'push' => true],
            'friendships' => ['in_app' => true, 'push' => false],
            'system' => ['in_app' => true, 'push' => true],
        ];
    }

    /**
     * Get consolidated notification preferences merged with defaults
     */
    public function notificationPreferences(): array
    {
        $defaults = $this->defaultNotificationPreferences();
        $saved = $this->notification_preferences;

        if (! is_array($saved)) {
            return $defaults;
        }

        foreach ($defaults as $cat => $channels) {
            if (isset($saved[$cat]) && is_array($saved[$cat])) {
                $defaults[$cat] = array_merge($channels, $saved[$cat]);
            }
        }

        return $defaults;
    }

    /**
     * Update user notification preferences
     */
    public function updateNotificationPreferences(array $prefs): void
    {
        $current = $this->notificationPreferences();
        foreach ($prefs as $cat => $channels) {
            if (isset($current[$cat]) && is_array($channels)) {
                $current[$cat] = array_merge($current[$cat], $channels);
            }
        }
        $this->update(['notification_preferences' => $current]);
    }

    /**
     * Determine if user wants notification for a specific category and channel
     */
    public function wantsNotification(string $category, string $channel = 'in_app'): bool
    {
        // Push notification strictly requires explicit Opt-in
        if ($channel === 'push' && ! $this->push_opt_in) {
            return false;
        }

        $prefs = $this->notificationPreferences();
        if (! isset($prefs[$category])) {
            return true;
        }

        return (bool) ($prefs[$category][$channel] ?? true);
    }

    /**
     * Determine whether the user is currently in their Quiet Hours based on their local Timezone
     */
    public function isInQuietHours(?CarbonInterface $at = null): bool
    {
        if (! $this->quiet_hours_enabled || empty($this->quiet_hours_start) || empty($this->quiet_hours_end)) {
            return false;
        }

        $tz = $this->timezone ?: 'Asia/Riyadh';

        try {
            $now = $at ? Carbon::parse($at)->setTimezone($tz) : Carbon::now($tz);
            $startParts = explode(':', $this->quiet_hours_start);
            $endParts = explode(':', $this->quiet_hours_end);

            $start = $now->copy()->setTime((int) $startParts[0], (int) ($startParts[1] ?? 0), 0);
            $end = $now->copy()->setTime((int) $endParts[0], (int) ($endParts[1] ?? 0), 0);

            if ($start->gt($end)) {
                // Cross-midnight range, e.g. 22:00 to 08:00
                return $now->gte($start) || $now->lte($end);
            } else {
                // Same-day range, e.g. 13:00 to 15:00
                return $now->gte($start) && $now->lte($end);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if user has Multi-Factor Authentication enabled and confirmed
     */
    public function hasMfaEnabled(): bool
    {
        return (bool) $this->mfa_enabled && ! empty($this->mfa_secret);
    }

    /**
     * Verify a 6-digit TOTP code or recovery code for this user
     */
    public function verifyMfaCode(string $code, ?string $secret = null): bool
    {
        return app(MfaService::class)->verifyCode($this, $code, $secret);
    }
}
