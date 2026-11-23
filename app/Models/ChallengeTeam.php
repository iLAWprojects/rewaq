<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ChallengeTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'community_id',
        'name',
        'color',
        'badge_icon',
        'leader_id',
        'members_count',
        'total_progress',
        'total_points',
        'rank',
    ];

    protected $casts = [
        'members_count' => 'integer',
        'total_progress' => 'integer',
        'total_points' => 'integer',
        'rank' => 'integer',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ChallengeProgressLog::class, 'team_id');
    }

    /**
     * Members belonging to this team in the challenge.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'challenge_participants', 'team_id', 'user_id')
            ->where('challenge_participants.challenge_id', $this->challenge_id)
            ->withPivot('progress_count', 'points_earned', 'completed', 'completed_at', 'rank', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Recalculate and synchronize team totals and members count.
     */
    public function recalculateTotals(): self
    {
        $participants = DB::table('challenge_participants')
            ->where('challenge_id', $this->challenge_id)
            ->where('team_id', $this->id);

        $this->members_count = (clone $participants)->count();
        $this->total_progress = (clone $participants)->sum('progress_count') ?: 0;
        $this->total_points = (clone $participants)->sum('points_earned') ?: 0;
        $this->save();

        return $this;
    }
}
