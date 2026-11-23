<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    const RULE_POINTS_TOTAL = 'points_total';

    const RULE_BOOKS_COUNT = 'books_count';

    const RULE_PAGES_COUNT = 'pages_count';

    const RULE_STREAK_DAYS = 'streak_days';

    const RULE_CHALLENGES_WON = 'challenges_won';

    const RULE_MEETINGS_ATTENDED = 'meetings_attended';

    const RULE_DIVERSITY_SCORE = 'diversity_score';

    protected $fillable = [
        'name',
        'description',
        'icon',
        'category',
        'points_required',
        'rule_type',
        'rule_threshold',
    ];

    protected $casts = [
        'points_required' => 'integer',
        'rule_threshold' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /**
     * Rule-based evaluation of badge qualification
     */
    public function evaluateRule(User $user): bool
    {
        if (empty($this->rule_type)) {
            // Default points-based rule
            return $user->validPointsTotal() >= $this->points_required;
        }

        $threshold = $this->rule_threshold;

        return match ($this->rule_type) {
            self::RULE_POINTS_TOTAL => $user->validPointsTotal() >= $threshold,
            self::RULE_BOOKS_COUNT => (int) $user->books_finished >= $threshold,
            self::RULE_PAGES_COUNT => (int) $user->readingSessions()->valid()->sum('pages_read') >= $threshold,
            self::RULE_STREAK_DAYS => (int) $user->reading_streak >= $threshold,
            self::RULE_CHALLENGES_WON => $user->challenges()->wherePivot('completed', true)->count() >= $threshold,
            self::RULE_MEETINGS_ATTENDED => $user->meetingRsvps()->where('status', 'attended')->count() >= $threshold,
            self::RULE_DIVERSITY_SCORE => $user->readingDiversityScore() >= $threshold,
            default => $user->validPointsTotal() >= $this->points_required,
        };
    }
}
