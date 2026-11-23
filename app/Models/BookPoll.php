<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookPoll extends Model
{
    const SHOW_RESULTS_ALWAYS = 'always';

    const SHOW_RESULTS_AFTER_VOTE = 'after_vote';

    const SHOW_RESULTS_AFTER_CLOSE = 'after_close';

    protected $fillable = [
        'community_id',
        'title',
        'is_active',
        'winner_book_id',
        'month_name',
        'closes_at',
        'show_results',
        'starts_at',
        'allow_nominations',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'closes_at' => 'date',
        'starts_at' => 'datetime',
        'allow_nominations' => 'boolean',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function winnerBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'winner_book_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(BookPollOption::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(BookPollVote::class);
    }

    public function hasUserVoted(int $userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    public function userVotedOptionId(int $userId): ?int
    {
        return $this->votes()->where('user_id', $userId)->value('book_poll_option_id');
    }

    public function isClosed(): bool
    {
        return ! $this->is_active || ($this->closes_at && $this->closes_at->copy()->endOfDay()->isPast());
    }

    /**
     * Check if user is authorized to see vote counts and percentages based on setting.
     */
    public function canUserSeeResults(?User $user): bool
    {
        if ($this->show_results === self::SHOW_RESULTS_ALWAYS) {
            return true;
        }

        if ($this->isClosed()) {
            return true;
        }

        if ($user && ($user->isSuperAdmin() || $this->community->isManager($user))) {
            return true;
        }

        if ($this->show_results === self::SHOW_RESULTS_AFTER_VOTE) {
            return $user ? $this->hasUserVoted($user->id) : false;
        }

        // SHOW_RESULTS_AFTER_CLOSE
        return false;
    }

    /**
     * One-click workflow: Close poll, set winning book of the month, and auto-create linked Challenge & Meeting.
     */
    public function closeAndExecuteWorkflow(?User $actor, array $options = []): array
    {
        $community = $this->community;

        // 1. Find leading winning option
        $winningOption = $this->options()->orderByDesc('votes_count')->first();
        if (! $winningOption) {
            throw new \RuntimeException('لا توجد خيارات تصويت لاعتماد الفائز.');
        }

        // 2. Resolve or create winning book
        $winningBook = null;
        if ($winningOption->book_id) {
            $winningBook = Book::find($winningOption->book_id);
        }

        if (! $winningBook) {
            $parts = explode('·', $winningOption->custom_title);
            $title = trim($parts[0]);
            $author = isset($parts[1]) ? trim($parts[1]) : 'مؤلف معتمد';

            $winningBook = Book::firstOrCreate(
                ['title' => $title],
                [
                    'author' => $author,
                    'category' => $community->category ?? 'أدب',
                    'total_pages' => 250,
                    'cover_color' => $community->cover_color ?? '#173D36',
                ]
            );
        }

        // 3. Archive previous book into CommunityBookHistory
        if ($community->current_book_id && $community->current_book_id !== $winningBook->id) {
            $prevBook = $community->currentBook;
            if ($prevBook) {
                CommunityBookHistory::firstOrCreate(
                    [
                        'community_id' => $community->id,
                        'book_id' => $prevBook->id,
                        'read_year' => Carbon::now()->year,
                        'read_month' => Carbon::now()->month,
                    ],
                    [
                        'month_name' => Carbon::now()->translatedFormat('F Y'),
                        'notes' => "كتاب الشهر السابق في «{$community->name}».",
                    ]
                );
            }
        }

        // 4. Update community's current book of the month
        $community->update(['current_book_id' => $winningBook->id]);

        $nextMonthName = Carbon::now()->addMonth()->translatedFormat('F Y');
        $this->update([
            'is_active' => false,
            'winner_book_id' => $winningBook->id,
            'month_name' => $nextMonthName,
        ]);

        // 5. One-Click Challenge Creation Workflow
        $challenge = null;
        $shouldCreateChallenge = $options['create_challenge'] ?? true;
        if ($shouldCreateChallenge) {
            $challenge = Challenge::create([
                'title' => "تحدي قراءة كتاب الشهر: «{$winningBook->title}»",
                'description' => "تحدي جماعي مخصص لأعضاء مجلس «{$community->name}» لإتمام ومناقشة كتاب الشهر «{$winningBook->title}» للمؤلف {$winningBook->author}.",
                'community_id' => $community->id,
                'specific_book_id' => $winningBook->id,
                'challenge_type' => 'book_specific',
                'category' => $community->category ?? 'تنوع معرفي',
                'target_count' => 1,
                'target_unit' => 'كتب',
                'start_date' => Carbon::now()->toDateString(),
                'end_date' => Carbon::now()->addMonth()->toDateString(),
                'status' => 'active',
                'is_active' => true,
                'creator_id' => $actor ? $actor->id : $community->creator_id,
                'reward_points' => 100,
            ]);

            $challenge->notifyAudience($actor ?? $community->creator);
        }

        // 6. One-Click Meeting Creation Workflow
        $meeting = null;
        $shouldCreateMeeting = $options['create_meeting'] ?? true;
        if ($shouldCreateMeeting) {
            $meetingDate = Carbon::now()->addWeeks(3)->setTime(19, 0);
            $meeting = Meeting::create([
                'title' => "جلسة نقاش كتاب الشهر: «{$winningBook->title}»",
                'description' => "اللقاء الرسمي المجدول لمجلس «{$community->name}» لمناقشة محاور وأفكار كتاب الشهر «{$winningBook->title}».",
                'community_id' => $community->id,
                'book_id' => $winningBook->id,
                'coordinator_id' => $actor ? $actor->id : $community->creator_id,
                'meeting_type' => $options['meeting_type'] ?? Meeting::TYPE_REMOTE,
                'meeting_date' => $meetingDate,
                'starts_at' => $meetingDate,
                'ends_at' => $meetingDate->copy()->addHours(2),
                'timezone' => 'Asia/Riyadh',
                'capacity' => 35,
                'is_past' => false,
            ]);
        }

        // 7. Post Announcement
        CommunityAnnouncement::create([
            'community_id' => $community->id,
            'user_id' => $actor ? $actor->id : $community->creator_id,
            'title' => "كتاب الشهر المعتمد: «{$winningBook->title}» 🏆",
            'content' => "بناءً على تصويت الأعضاء، أُعلن كتاب «{$winningBook->title}» للمؤلف {$winningBook->author} كتاباً رسمياً لشهر {$nextMonthName}، وتم إطلاق التحدي المخصص وجدولة لقاء المناقشة الرسمي!",
            'is_pinned' => true,
        ]);

        // 8. Notify All Community Members
        foreach ($community->members as $member) {
            UserNotification::create([
                'user_id' => $member->id,
                'title' => 'كتاب الشهر الجديد والتحدي المجدول 📚',
                'body' => "فاز «{$winningBook->title}» ككتاب الشهر القادم في «{$community->name}»! انضم للتحدي وسجّل حضورك في جلسة النقاش.",
                'icon' => 'book-open',
                'link' => route('communities.index', ['community_id' => $community->id]),
                'type' => 'community',
            ]);
        }

        // 9. Audit Logging
        if ($actor) {
            AuditLog::record(
                $actor,
                'close_poll_workflow',
                $this,
                "أغلق «{$actor->name}» تصويت كتاب الشهر واعتمد «{$winningBook->title}» مع إنشاء التحدي واللقاء المرتبطين."
            );
        }

        return [
            'winning_book' => $winningBook,
            'challenge' => $challenge,
            'meeting' => $meeting,
        ];
    }
}
