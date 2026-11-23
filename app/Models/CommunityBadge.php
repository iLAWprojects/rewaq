<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CommunityBadge extends Model
{
    const AWARD_MANUAL = 'manual';

    const AWARD_AUTOMATIC = 'automatic';

    const RULE_DISCUSSIONS_COUNT = 'discussions_count';

    const RULE_COMMENTS_COUNT = 'comments_count';

    const RULE_MEETINGS_ATTENDED = 'meetings_attended';

    const RULE_CHALLENGES_WON = 'challenges_won';

    protected $fillable = [
        'community_id',
        'name',
        'description',
        'icon',
        'color',
        'award_type',
        'auto_rule_type',
        'auto_rule_threshold',
    ];

    protected $casts = [
        'auto_rule_threshold' => 'integer',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_badge_user')
            ->withPivot('awarded_by', 'reason', 'awarded_at')
            ->withTimestamps();
    }

    public function isAutomatic(): bool
    {
        return $this->award_type === self::AWARD_AUTOMATIC;
    }

    public function isManual(): bool
    {
        return $this->award_type === self::AWARD_MANUAL;
    }

    /**
     * Check if user qualifies for this automatic community badge
     */
    public function evaluateRule(User $user): bool
    {
        if (! $this->isAutomatic() || empty($this->auto_rule_type)) {
            return false;
        }

        $threshold = $this->auto_rule_threshold;
        $commId = $this->community_id;

        return match ($this->auto_rule_type) {
            self::RULE_DISCUSSIONS_COUNT => Discussion::where('community_id', $commId)->where('user_id', $user->id)->count() >= $threshold,
            self::RULE_COMMENTS_COUNT => DiscussionComment::whereHas('discussion', fn ($q) => $q->where('community_id', $commId))->where('user_id', $user->id)->count() >= $threshold,
            self::RULE_MEETINGS_ATTENDED => MeetingRsvp::whereHas('meeting', fn ($q) => $q->where('community_id', $commId))->where('user_id', $user->id)->where('status', 'attended')->count() >= $threshold,
            self::RULE_CHALLENGES_WON => Challenge::where('community_id', $commId)->whereHas('participants', fn ($q) => $q->where('user_id', $user->id)->where('completed', true))->count() >= $threshold,
            default => false,
        };
    }

    /**
     * Award this badge to a user (Manual or Automatic)
     */
    public function awardToUser(User $user, ?User $moderator = null, string $reason = 'استحقاق وتميز في المجلس'): bool
    {
        if ($this->users()->where('users.id', $user->id)->exists()) {
            return false; // Already awarded
        }

        $this->users()->attach($user->id, [
            'awarded_by' => $moderator ? $moderator->id : null,
            'reason' => $reason,
            'awarded_at' => Carbon::now(),
        ]);

        $user->addPoints(50, 'badge_earned', "نيل وسام «{$this->name}» في «{$this->community->name}»", self::class, $this->id);

        UserNotification::create([
            'user_id' => $user->id,
            'title' => "تتويج شرفي: نلت وسام «{$this->name}»! 🏅",
            'body' => "حصلت على وسام «{$this->name}» في «{$this->community->name}»: {$reason}",
            'icon' => 'award',
            'link' => route('communities.index', ['community_id' => $this->community_id]),
            'type' => 'community_badge',
        ]);

        SocialActivity::log(
            $user,
            SocialActivity::TYPE_BADGE_EARNED,
            "تُوّج القارئ بوسام «{$this->name}» في «{$this->community->name}» 🏅",
            $reason,
            route('communities.index', ['community_id' => $this->community_id])
        );

        return true;
    }
}
