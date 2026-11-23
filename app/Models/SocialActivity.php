<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialActivity extends Model
{
    use HasFactory;

    // Curated Knowledge Events Only
    const TYPE_BOOK_COMPLETED = 'book_completed';

    const TYPE_READING_SESSION = 'reading_session';

    const TYPE_CHALLENGE_WON = 'challenge_won';

    const TYPE_MEETING_ATTENDED = 'meeting_attended';

    const TYPE_BADGE_EARNED = 'badge_earned';

    const TYPE_QUOTE_SHARED = 'quote_shared';

    const TYPE_REVIEW_PUBLISHED = 'review_published';

    const TYPE_FRIEND_CONNECTED = 'friend_connected';

    const TYPE_FRIEND_FOLLOWED = 'friend_followed';

    const TYPE_REFLECTION_POSTED = 'reflection_posted';

    const TYPE_REVIEW_POSTED = 'review_posted';

    public static array $curatedTypes = [
        self::TYPE_BOOK_COMPLETED,
        self::TYPE_READING_SESSION,
        self::TYPE_CHALLENGE_WON,
        self::TYPE_MEETING_ATTENDED,
        self::TYPE_BADGE_EARNED,
        self::TYPE_QUOTE_SHARED,
        self::TYPE_REVIEW_PUBLISHED,
        self::TYPE_FRIEND_CONNECTED,
        self::TYPE_FRIEND_FOLLOWED,
        self::TYPE_REFLECTION_POSTED,
        self::TYPE_REVIEW_POSTED,
    ];

    protected $fillable = [
        'user_id',
        'activity_type',
        'title',
        'content',
        'link',
        'cheers_count',
        'is_private',
    ];

    protected $casts = [
        'cheers_count' => 'integer',
        'is_private' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * تسجيل حدث معرفي منتقى
     */
    public static function log(User $user, string $type, string $title, ?string $content = null, ?string $link = null, bool $isPrivate = false): ?self
    {
        // Enforce curated knowledge events rule
        if (! in_array($type, static::$curatedTypes)) {
            // Reject non-curated noisy events
            return null;
        }

        // Check if user chose to hide this activity type
        $shouldHide = $isPrivate || ($user->hasHiddenActivityType($type));

        return static::create([
            'user_id' => $user->id,
            'activity_type' => $type,
            'title' => $title,
            'content' => $content,
            'link' => $link,
            'is_private' => $shouldHide,
        ]);
    }

    /**
     * Scope activities that are visible to a specific viewer.
     */
    public function scopeVisibleForViewer(Builder $query, ?User $viewer = null): Builder
    {
        if ($viewer) {
            // Exclude activities from blocked or blocking users
            $blockedIds = $viewer->blockedUsers()->pluck('users.id')
                ->merge($viewer->blockedByUsers()->pluck('users.id'))
                ->unique();

            if ($blockedIds->isNotEmpty()) {
                $query->whereNotIn('user_id', $blockedIds);
            }

            // Exclude private activities unless viewer is the author
            $query->where(function ($q) use ($viewer) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $viewer->id);
            });
        } else {
            // Guest viewer: only non-private activities
            $query->where('is_private', false);
        }

        return $query;
    }

    public function typeLabel(): string
    {
        return match ($this->activity_type) {
            self::TYPE_BOOK_COMPLETED => 'إتمام قراءة كتاب 📖',
            self::TYPE_READING_SESSION => 'جلسة قراءة وتأمل ⏳',
            self::TYPE_CHALLENGE_WON => 'إنجاز تحدٍ قرائي 🏆',
            self::TYPE_MEETING_ATTENDED => 'حضور لقاء معرفي 🤝',
            self::TYPE_BADGE_EARNED => 'تتويج بوسام شرفي 🏅',
            self::TYPE_QUOTE_SHARED => 'مشاركة اقتباس راقٍ ✍️',
            self::TYPE_REVIEW_PUBLISHED => 'نشر مراجعة نقدية 🌟',
            self::TYPE_FRIEND_CONNECTED => 'تواصل رفقاء القراءة 🌿',
            default => 'نشاط معرفي',
        };
    }
}
