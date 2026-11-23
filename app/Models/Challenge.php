<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Challenge extends Model
{
    use HasFactory;

    // 6 Statuses
    const STATUS_DRAFT = 'draft';

    const STATUS_UPCOMING = 'upcoming';

    const STATUS_ACTIVE = 'active';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_ARCHIVED = 'archived';

    // 13 Supported Challenge Types
    const TYPE_BOOKS_COUNT = 'books_count';

    const TYPE_PAGES_COUNT = 'pages_count';

    const TYPE_HOURS_COUNT = 'hours_count';

    const TYPE_DAILY_STREAK = 'daily_streak';

    const TYPE_SPECIFIC_BOOK = 'specific_book';

    const TYPE_DIVERSE_CATEGORIES = 'diverse_categories';

    const TYPE_DIVERSE_AUTHORS = 'diverse_authors';

    const TYPE_ARABIC_LITERATURE = 'arabic_literature';

    const TYPE_SEASONAL = 'seasonal';

    const TYPE_COMMUNITY_VS_COMMUNITY = 'community_vs_community';

    const TYPE_INTERNAL_TEAMS = 'internal_teams';

    const TYPE_BOOK_SERIES = 'book_series';

    const TYPE_KNOWLEDGE_DIVERSITY = 'knowledge_diversity';

    // 4 Team Assignment Methods
    const ASSIGNMENT_MANUAL = 'manual';

    const ASSIGNMENT_RANDOM = 'random';

    const ASSIGNMENT_BY_LEVEL = 'by_level';

    const ASSIGNMENT_USER_CHOICE = 'user_choice';

    protected $fillable = [
        'community_id',
        'creator_id',
        'title',
        'description',
        'status',
        'category',
        'type',
        'challenge_type',
        'team_assignment_method',
        'target_count',
        'target_unit',
        'specific_book_id',
        'series_name',
        'season_name',
        'start_date',
        'end_date',
        'is_active',
        'is_archived',
        'is_team',
        'team_a_name',
        'team_b_name',
        'team_a_pages',
        'team_b_pages',
        'winner_team',
        'winner_user_id',
        'winner_team_id',
        'reward_badge',
        'reward_points',
        'milestones',
        'certificate_enabled',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_team' => 'boolean',
        'is_archived' => 'boolean',
        'certificate_enabled' => 'boolean',
        'milestones' => 'array',
        'target_count' => 'integer',
        'reward_points' => 'integer',
        'team_a_pages' => 'integer',
        'team_b_pages' => 'integer',
    ];

    /**
     * تحديات أُكملت خلال هذا الطلب (لإظهار رسالة فورية للمستخدم بعد حفظ جلسة القراءة).
     *
     * @var array<int, array{title: string, points: int}>
     */
    public static array $completedThisRequest = [];

    /**
     * @return array<int, array{title: string, points: int}>
     */
    public static function pullCompletions(): array
    {
        $done = self::$completedThisRequest;
        self::$completedThisRequest = [];

        return $done;
    }

    /**
     * عبارة جاهزة للعرض: «لقد أكملت التحدي «…» وحصلت على N نقطة».
     */
    public static function completionMessage(array $completions): string
    {
        return collect($completions)
            ->map(fn ($c) => "🏆 لقد أكملت التحدي «{$c['title']}» وحصلت على {$c['points']} نقطة!")
            ->implode(' ');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * في تحدي الكتاب المحدد: يُضاف الكتاب تلقائيًا إلى مكتبة المشارك الشخصية وإلى رفوف وسجل القراءة عنده.
     */
    public function ensureBookOnShelves(User $user): void
    {
        $book = $this->specific_book_id ? $this->specificBook : null;
        if (! $book) {
            return;
        }

        $record = UserCurrentBook::firstOrNew(['user_id' => $user->id, 'book_id' => $book->id]);
        if (! $record->exists) {
            $hasActive = UserCurrentBook::where('user_id', $user->id)->where('is_active', true)->exists();
            $record->fill([
                'status' => 'currently_reading',
                'current_page' => 0,
                'progress_percentage' => 0,
                'start_date' => Carbon::today(),
                'is_active' => ! $hasActive,
            ])->save();
        }

        $user->addBookToPersonalLibrary($book);
    }

    /**
     * إشعار منشئ التحدي بانضمام قارئ جديد.
     */
    public function notifyCreatorOfJoin(User $joiner): void
    {
        if (! $this->creator_id || $this->creator_id === $joiner->id) {
            return;
        }

        UserNotification::create([
            'user_id' => $this->creator_id,
            'title' => 'قارئ جديد انضم إلى تحدّيك 🎯',
            'body' => "انضم «{$joiner->name}» إلى تحدّي «{$this->title}». المشاركون الآن: {$this->participants()->count()}.",
            'icon' => 'user-plus',
            'link' => route('challenges.index'),
            'type' => 'challenge',
            'category' => UserNotification::CATEGORY_CHALLENGES,
        ]);
    }

    /**
     * إشعار الجمهور المناسب بتحدٍّ جديد: متابعو المُنشئ للتحدي العام،
     * وأعضاء النادي فقط لتحدي النادي. لا يُرسل للمُنشئ ولا للمسودات.
     */
    public function notifyAudience(User $actor): int
    {
        if ($this->status === self::STATUS_DRAFT) {
            return 0;
        }

        if ($this->community_id) {
            $community = $this->community;
            $recipientIds = $community->members()->pluck('users.id');
            $title = 'تحدٍّ جديد في نادي القراءة 🎯';
            $body = "أطلق «{$actor->name}» تحدّي «{$this->title}» في نادي «{$community->name}».";
        } else {
            $recipientIds = $actor->followers()->pluck('users.id');
            $title = 'تحدٍّ جديد من '.$actor->name.' 🎯';
            $body = "أطلق «{$actor->name}» الذي تتابعه تحدّيًا عامًا جديدًا: «{$this->title}».";
        }

        $recipientIds = $recipientIds->reject(fn ($id) => $id === $actor->id)->unique();

        foreach ($recipientIds as $userId) {
            UserNotification::create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'icon' => 'trophy',
                'link' => route('challenges.index'),
                'type' => 'challenge',
                'category' => UserNotification::CATEGORY_CHALLENGES,
            ]);
        }

        return $recipientIds->count();
    }

    public function winnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(ChallengeTeam::class, 'winner_team_id');
    }

    public function specificBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'specific_book_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(ChallengeTeam::class)->orderBy('rank', 'asc');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ChallengeProgressLog::class)->latest('logged_at');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'challenge_participants')
            ->withPivot([
                'team_id',
                'team',
                'progress_count',
                'points_earned',
                'last_milestone',
                'completed',
                'completed_at',
                'certificate_code',
                'rank',
                'joined_at',
            ])
            ->withTimestamps();
    }

    public function leaderboard()
    {
        return $this->participants()
            ->orderByPivot('progress_count', 'desc')
            ->orderByPivot('points_earned', 'desc');
    }

    // Scopes for Statuses
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_UPCOMING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Localized label for challenge type.
     */
    public function getChallengeTypeLabelAttribute(): string
    {
        return match ($this->challenge_type) {
            self::TYPE_BOOKS_COUNT => 'عدد كتب 📚',
            self::TYPE_PAGES_COUNT => 'عدد صفحات 📄',
            self::TYPE_HOURS_COUNT => 'ساعات قراءة ⏱️',
            self::TYPE_DAILY_STREAK => 'قراءة يومية مستمرة 🔥',
            self::TYPE_SPECIFIC_BOOK => 'كتاب محدد 📖',
            self::TYPE_DIVERSE_CATEGORIES => 'تصنيفات مختلفة 🧭',
            self::TYPE_DIVERSE_AUTHORS => 'مؤلفون مختلفون ✍️',
            self::TYPE_ARABIC_LITERATURE => 'أدب ولغة عربية 🌿',
            self::TYPE_SEASONAL => 'تحدي موسمي ☀️',
            self::TYPE_COMMUNITY_VS_COMMUNITY => 'نادٍ ضد نادٍ ⚔️',
            self::TYPE_INTERNAL_TEAMS => 'فرق داخل النادي 🛡️',
            self::TYPE_BOOK_SERIES => 'سلسلة كتب 📑',
            self::TYPE_KNOWLEDGE_DIVERSITY => 'تنوع معرفي 🌐',
            default => 'تحدي قراءة ✦',
        };
    }

    /**
     * Localized status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'مسودة',
            self::STATUS_UPCOMING => 'قادم',
            self::STATUS_ACTIVE => 'نشط',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED => 'ملغي',
            self::STATUS_ARCHIVED => 'مؤرشف',
            default => $this->status,
        };
    }

    /**
     * Status badge class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'badge-secondary',
            self::STATUS_UPCOMING => 'badge-info',
            self::STATUS_ACTIVE => 'badge-success',
            self::STATUS_COMPLETED => 'badge-primary',
            self::STATUS_CANCELLED => 'badge-danger',
            self::STATUS_ARCHIVED => 'badge-muted',
            default => 'badge-info',
        };
    }

    /**
     * Team distribution method label.
     */
    public function getTeamAssignmentLabelAttribute(): string
    {
        return match ($this->team_assignment_method) {
            self::ASSIGNMENT_MANUAL => 'توزيع يدوي',
            self::ASSIGNMENT_RANDOM => 'توزيع عشوائي متوازن',
            self::ASSIGNMENT_BY_LEVEL => 'توزيع حسب المستوى والنقاط',
            self::ASSIGNMENT_USER_CHOICE => 'اختيار المستخدم المباشر',
            default => 'اختيار المستخدم',
        };
    }

    /**
     * Log Event/Transaction-based reading progress for a user with audit tracking.
     */
    public function logUserProgress(
        User $user,
        int $incrementValue,
        string $eventType = 'pages_read',
        ?string $notes = null,
        ?int $bookId = null,
        ?int $sessionId = null
    ): ChallengeProgressLog {
        // Ensure participant is attached
        $participant = $this->participants()->where('user_id', $user->id)->first();
        if (! $participant) {
            $this->participants()->attach($user->id, [
                'progress_count' => 0,
                'points_earned' => 0,
                'completed' => false,
                'joined_at' => now(),
            ]);
            $participant = $this->participants()->where('user_id', $user->id)->first();
        }

        $oldProgress = (int) $participant->pivot->progress_count;
        $oldPoints = (int) $participant->pivot->points_earned;
        $oldRank = (int) $participant->pivot->rank;
        $teamId = $participant->pivot->team_id;

        $newProgress = $oldProgress + $incrementValue;
        $pointsEarned = (int) ceil($incrementValue / 5);
        if ($pointsEarned < 1 && $incrementValue > 0) {
            $pointsEarned = 1;
        }

        // 1. Record Transaction Log
        $log = ChallengeProgressLog::create([
            'challenge_id' => $this->id,
            'user_id' => $user->id,
            'team_id' => $teamId,
            'reading_session_id' => $sessionId,
            'book_id' => $bookId,
            'event_type' => $eventType,
            'increment_value' => $incrementValue,
            'progress_before' => $oldProgress,
            'progress_after' => $newProgress,
            'points_awarded' => $pointsEarned,
            'notes' => $notes,
            'logged_at' => now(),
        ]);

        // 2. Check if completion reached
        $isCompleted = ($newProgress >= $this->target_count);
        $certCode = $participant->pivot->certificate_code;

        if ($isCompleted && ! $participant->pivot->completed) {
            $certCode = 'CERT-RWQ-'.strtoupper(Str::random(8));
            $rewardPoints = (int) ($this->reward_points ?: 150);
            $user->increment('points', $rewardPoints);
            self::$completedThisRequest[] = ['title' => $this->title, 'points' => $rewardPoints];

            if ($this->creator_id && $this->creator_id !== $user->id) {
                UserNotification::create([
                    'user_id' => $this->creator_id,
                    'title' => 'قارئ أتمّ تحدّيك ✅',
                    'body' => "أتمّ «{$user->name}» تحدّي «{$this->title}».",
                    'icon' => 'award',
                    'link' => route('challenges.index'),
                    'type' => 'challenge',
                    'category' => UserNotification::CATEGORY_CHALLENGES,
                ]);
            }

            UserNotification::create([
                'user_id' => $user->id,
                'title' => 'مبارك! أتممت التحدي بنجاح 🏆',
                'body' => "لقد أكملت التحدي «{$this->title}» وحصلت على {$rewardPoints} نقطة ونلت {$this->reward_badge}. رمز شهادتك المعتمد: {$certCode}.",
                'icon' => 'award',
                'link' => route('challenges.index'),
                'type' => 'achievement',
            ]);
        }

        // 3. Update Participant Pivot
        $this->participants()->updateExistingPivot($user->id, [
            'progress_count' => $newProgress,
            'points_earned' => $oldPoints + $pointsEarned,
            'completed' => $isCompleted,
            'completed_at' => $isCompleted ? ($participant->pivot->completed_at ?? now()) : null,
            'certificate_code' => $certCode,
        ]);

        // 4. Update Team totals if in team
        if ($teamId) {
            $team = ChallengeTeam::find($teamId);
            $team?->recalculateTotals();
        }

        // Support backward compatibility for team_a / team_b
        if ($this->is_team && $participant->pivot->team) {
            if ($participant->pivot->team === 'team_a') {
                $this->increment('team_a_pages', $incrementValue);
            } else {
                $this->increment('team_b_pages', $incrementValue);
            }
        }

        // Award points to user profile
        $user->increment('points', $pointsEarned);

        // 5. Check Milestones (25%, 50%, 75%, 100%)
        $this->checkMilestones($user, $newProgress, (int) $participant->pivot->last_milestone);

        // 6. Recalculate Leaderboard and Check Rank Changes
        $this->recalculateRanksAndNotify($user, $oldRank);

        return $log;
    }

    /**
     * Synchronize uncredited reading sessions from user's reading log to this challenge.
     */
    public function syncUserReadingSessions(User $user): array
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        if (! $participant) {
            return [
                'success' => false,
                'synced_pages' => 0,
                'sessions_count' => 0,
                'message' => 'يجب الانضمام إلى التحدي أولاً لتتمكن من مزامنة قراءاتك.',
            ];
        }

        $creditedSessionIds = $this->progressLogs()
            ->where('user_id', $user->id)
            ->whereNotNull('reading_session_id')
            ->pluck('reading_session_id')
            ->filter()
            ->toArray();

        $query = $user->readingSessions()->valid()->whereNotIn('id', $creditedSessionIds);

        if ($this->start_date) {
            $query->where('started_at', '>=', $this->start_date->startOfDay());
        }
        if ($this->end_date) {
            $query->where('started_at', '<=', $this->end_date->endOfDay());
        }
        if ($this->specific_book_id) {
            $query->where('book_id', $this->specific_book_id);
        }

        $sessions = $query->with('book')->orderBy('started_at', 'asc')->get();

        if ($sessions->isEmpty()) {
            return [
                'success' => true,
                'synced_pages' => 0,
                'sessions_count' => 0,
                'message' => 'جميع جلسات القراءة في سجلك القرائي محسوبة وموثقة بالفعل في هذا التحدي ✓',
            ];
        }

        $totalSyncedPages = 0;
        foreach ($sessions as $session) {
            $pages = (int) ($session->pages_read ?: max(1, $session->end_page - $session->start_page));
            $bookTitle = $session->book ? $session->book->title : 'كتاب';

            $this->logUserProgress(
                $user,
                $pages,
                'pages_read',
                "مزامنة جلسة قراءة من سجل القراءة ({$pages} ص) في «{$bookTitle}»",
                $session->book_id,
                $session->id
            );

            $totalSyncedPages += $pages;
        }

        $teamName = $participant->pivot->team_id
            ? ($this->teams()->find($participant->pivot->team_id)?->name ?? 'فريقك')
            : ($participant->pivot->team === 'team_a' ? $this->team_a_name : ($participant->pivot->team === 'team_b' ? $this->team_b_name : 'رصيدك'));

        return [
            'success' => true,
            'synced_pages' => $totalSyncedPages,
            'sessions_count' => $sessions->count(),
            'message' => "تمت مزامنة {$totalSyncedPages} صفحة من {$sessions->count()} جلسة قراءة موثقة بنجاح لصالح {$teamName}! 🌿",
        ];
    }

    /**
     * Get the count of uncredited pages available in the user's reading log for this challenge.
     */
    public function getPendingReadingPagesForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $creditedSessionIds = $this->progressLogs()
            ->where('user_id', $user->id)
            ->whereNotNull('reading_session_id')
            ->pluck('reading_session_id')
            ->filter()
            ->toArray();

        $query = $user->readingSessions()->valid()->whereNotIn('id', $creditedSessionIds);

        if ($this->start_date) {
            $query->where('started_at', '>=', $this->start_date->startOfDay());
        }
        if ($this->end_date) {
            $query->where('started_at', '<=', $this->end_date->endOfDay());
        }
        if ($this->specific_book_id) {
            $query->where('book_id', $this->specific_book_id);
        }

        return (int) $query->sum('pages_read');
    }

    /**
     * Check milestone thresholds and trigger notifications.
     */
    protected function checkMilestones(User $user, int $currentProgress, int $lastMilestone): void
    {
        $target = max(1, $this->target_count);
        $percentage = min(100, (int) floor(($currentProgress / $target) * 100));

        $milestones = [25, 50, 75, 100];
        $reached = 0;

        foreach ($milestones as $m) {
            if ($percentage >= $m && $lastMilestone < $m) {
                $reached = $m;
            }
        }

        if ($reached > 0) {
            $this->participants()->updateExistingPivot($user->id, [
                'last_milestone' => $reached,
            ]);

            UserNotification::create([
                'user_id' => $user->id,
                'title' => "إنجاز مرحلي في التحدي 🎯 ({$reached}%)",
                'body' => "رائع! حققت نسبة {$reached}% من مستهدف «{$this->title}» بإجمالي {$currentProgress} من {$this->target_count} {$this->target_unit}.",
                'icon' => 'flag',
                'link' => route('challenges.index'),
                'type' => 'challenge_milestone',
            ]);
        }
    }

    /**
     * Recalculate ranks and notify on position changes.
     */
    public function recalculateRanksAndNotify(?User $currentUser = null, ?int $oldRank = null): void
    {
        $participants = DB::table('challenge_participants')
            ->where('challenge_id', $this->id)
            ->orderBy('progress_count', 'desc')
            ->orderBy('points_earned', 'desc')
            ->orderBy('joined_at', 'asc')
            ->get();

        $rank = 1;
        $currentUserNewRank = null;

        foreach ($participants as $p) {
            DB::table('challenge_participants')
                ->where('id', $p->id)
                ->update(['rank' => $rank]);

            if ($currentUser && $p->user_id === $currentUser->id) {
                $currentUserNewRank = $rank;
            }
            $rank++;
        }

        // Rank changed notification (if promoted)
        if ($currentUser && $oldRank !== null && $currentUserNewRank !== null) {
            if ($oldRank > 0 && $currentUserNewRank < $oldRank) {
                UserNotification::create([
                    'user_id' => $currentUser->id,
                    'title' => 'ارتقيت في ترتيب التحدي! 🚀',
                    'body' => "تقدمت إلى المركز ({$currentUserNewRank}) في صدارة «{$this->title}»!",
                    'icon' => 'trending-up',
                    'link' => route('challenges.index'),
                    'type' => 'challenge_rank_changed',
                ]);
            }
        }

        // Recalculate Team ranks if teams exist
        $teams = ChallengeTeam::where('challenge_id', $this->id)
            ->orderBy('total_progress', 'desc')
            ->orderBy('total_points', 'desc')
            ->get();

        $teamRank = 1;
        foreach ($teams as $team) {
            $team->update(['rank' => $teamRank++]);
        }
    }

    /**
     * Assign user to a team according to the challenge distribution method.
     */
    public function assignUserToTeam(User $user, ?ChallengeTeam $chosenTeam = null): ChallengeTeam
    {
        $teams = $this->teams()->get();
        if ($teams->isEmpty()) {
            // Auto create default teams if none exist
            $teamA = ChallengeTeam::create([
                'challenge_id' => $this->id,
                'name' => $this->team_a_name ?: 'فريق النخبة',
                'color' => '#173D36',
                'badge_icon' => 'shield',
                'leader_id' => $this->creator_id ?? $user->id,
            ]);
            $teamB = ChallengeTeam::create([
                'challenge_id' => $this->id,
                'name' => $this->team_b_name ?: 'فريق الرواد',
                'color' => '#C3A15D',
                'badge_icon' => 'flame',
                'leader_id' => $user->id,
            ]);
            $teams = collect([$teamA, $teamB]);
        }

        $assignedTeam = null;

        switch ($this->team_assignment_method) {
            case self::ASSIGNMENT_USER_CHOICE:
                if ($chosenTeam && $chosenTeam->challenge_id === $this->id) {
                    $assignedTeam = $chosenTeam;
                } else {
                    $assignedTeam = $teams->sortBy('members_count')->first();
                }
                break;

            case self::ASSIGNMENT_RANDOM:
                // Assign to team with fewest members, or random among minimums
                $minMembers = $teams->min('members_count');
                $candidates = $teams->where('members_count', $minMembers);
                $assignedTeam = $candidates->random();
                break;

            case self::ASSIGNMENT_BY_LEVEL:
                // Balance teams by cumulative user points
                $assignedTeam = $teams->sortBy('total_points')->first();
                break;

            case self::ASSIGNMENT_MANUAL:
            default:
                $assignedTeam = $chosenTeam ?: $teams->first();
                break;
        }

        // Attach or update participant
        if (! $this->participants()->where('user_id', $user->id)->exists()) {
            $this->participants()->attach($user->id, [
                'team_id' => $assignedTeam->id,
                'team' => ($assignedTeam->id === $teams->first()->id) ? 'team_a' : 'team_b',
                'progress_count' => 0,
                'points_earned' => 0,
                'completed' => false,
                'joined_at' => now(),
            ]);
            $user->increment('points', 30);
        } else {
            $this->participants()->updateExistingPivot($user->id, [
                'team_id' => $assignedTeam->id,
                'team' => ($assignedTeam->id === $teams->first()->id) ? 'team_a' : 'team_b',
            ]);
        }

        $assignedTeam->recalculateTotals();

        return $assignedTeam;
    }

    /**
     * Send 'ending_soon' notifications to participants within 48 hours of expiration.
     */
    public function checkAndNotifyEndingSoon(): int
    {
        if ($this->status !== self::STATUS_ACTIVE || ! $this->end_date) {
            return 0;
        }

        $now = now();
        $endDate = Carbon::parse($this->end_date)->endOfDay();
        $hoursLeft = $now->diffInHours($endDate, false);

        if ($hoursLeft <= 48 && $hoursLeft > 0) {
            $uncompleted = $this->participants()->wherePivot('completed', false)->get();
            $count = 0;

            foreach ($uncompleted as $participant) {
                // Prevent duplicate ending_soon notifications within 24 hours
                $alreadyNotified = UserNotification::where('user_id', $participant->id)
                    ->where('type', 'challenge_ending_soon')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->exists();

                if (! $alreadyNotified) {
                    UserNotification::create([
                        'user_id' => $participant->id,
                        'title' => 'أوشك التحدي على الانتهاء! ⏳',
                        'body' => "يتبقى أقل من {$hoursLeft} ساعة على اختتام «{$this->title}». سارع بتسجيل صفحاتك لحسم النتيجة!",
                        'icon' => 'clock',
                        'link' => route('challenges.index'),
                        'type' => 'challenge_ending_soon',
                    ]);
                    $count++;
                }
            }

            return $count;
        }

        return 0;
    }

    /**
     * Finalize challenge, determine winners, and send victory notifications.
     */
    public function completeAndFinalizeWinners(): array
    {
        $this->recalculateRanksAndNotify();

        // 1. Determine Individual Winner
        $topParticipant = $this->participants()
            ->orderByPivot('progress_count', 'desc')
            ->orderByPivot('points_earned', 'desc')
            ->first();

        if ($topParticipant) {
            $this->winner_user_id = $topParticipant->id;

            // Notify winner
            UserNotification::create([
                'user_id' => $topParticipant->id,
                'title' => 'ظفرت بالمركز الأول! 🏆',
                'body' => "تهانينا! حققت المركز الأول في «{$this->title}» بإجمالي {$topParticipant->pivot->progress_count} {$this->target_unit}.",
                'icon' => 'trophy',
                'link' => route('challenges.index'),
                'type' => 'challenge_won',
            ]);
        }

        // 2. Determine Winning Team
        $topTeam = $this->teams()->orderBy('rank', 'asc')->first();
        if ($topTeam) {
            $this->winner_team_id = $topTeam->id;
            $this->winner_team = $topTeam->name;

            // Notify all members of the winning team
            $teamMembers = $this->participants()->wherePivot('team_id', $topTeam->id)->get();
            foreach ($teamMembers as $member) {
                UserNotification::create([
                    'user_id' => $member->id,
                    'title' => "فريقك «{$topTeam->name}» ظفر بالبطولة! 🛡️🏆",
                    'body' => "ألف مبارك! فاز فريقك بالمركز الأول في معركة «{$this->title}».",
                    'icon' => 'trophy',
                    'link' => route('challenges.index'),
                    'type' => 'challenge_won',
                ]);
            }
        }

        $this->status = self::STATUS_COMPLETED;
        $this->is_active = false;
        $this->save();

        // إعلام باقي المشاركين (غير الفائز) بانتهاء التحدي
        foreach ($this->participants()->get() as $participant) {
            if ($topParticipant && $participant->id === $topParticipant->id) {
                continue;
            }
            UserNotification::create([
                'user_id' => $participant->id,
                'title' => 'انتهى التحدي «'.$this->title.'»',
                'body' => 'أنهى منشئ التحدي «'.$this->title.'». حصيلتك: '.$participant->pivot->progress_count.' '.$this->target_unit.'.',
                'icon' => 'flag',
                'link' => route('challenges.index', ['tab' => 'archive']),
                'type' => 'challenge',
                'category' => UserNotification::CATEGORY_CHALLENGES,
            ]);
        }

        return [
            'winner_user' => $topParticipant,
            'winner_team' => $topTeam,
        ];
    }
}
