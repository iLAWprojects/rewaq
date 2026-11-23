<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YearlyReadingGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'target_books',
        'target_pages',
        'target_hours',
        'achieved_books',
        'achieved_pages',
        'achieved_minutes',
        'knowledge_diversity_score',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'target_books' => 'integer',
        'target_pages' => 'integer',
        'target_hours' => 'integer',
        'achieved_books' => 'integer',
        'achieved_pages' => 'integer',
        'achieved_minutes' => 'integer',
        'knowledge_diversity_score' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPercentageBooksAttribute(): int
    {
        if (! $this->target_books || $this->target_books <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->achieved_books / $this->target_books) * 100));
    }

    public function getPercentagePagesAttribute(): int
    {
        if (! $this->target_pages || $this->target_pages <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->achieved_pages / $this->target_pages) * 100));
    }

    public function getPercentageHoursAttribute(): int
    {
        if (! $this->target_hours || $this->target_hours <= 0) {
            return 0;
        }
        $achievedHours = $this->achieved_minutes / 60;

        return min(100, (int) round(($achievedHours / $this->target_hours) * 100));
    }

    /**
     * Preserves and updates achievements for this year without resetting historical goals.
     */
    public function syncAchievements(): void
    {
        $year = $this->year;

        // Completed books in this year
        $finishedInYear = UserCurrentBook::where('user_id', $this->user_id)
            ->where('status', 'completed')
            ->where(function ($q) use ($year) {
                $q->whereYear('finish_date', $year)
                    ->orWhere(function ($sq) use ($year) {
                        $sq->whereNull('finish_date')
                            ->whereYear('updated_at', $year);
                    });
            })
            ->count();

        // Reading sessions in this year
        $sessionsQuery = ReadingSession::valid()->where('user_id', $this->user_id)
            ->whereYear('created_at', $year);

        $pagesInYear = (int) $sessionsQuery->sum('pages_read');
        $minutesInYear = (int) $sessionsQuery->sum('minutes');

        $this->update([
            'achieved_books' => $finishedInYear,
            'achieved_pages' => $pagesInYear,
            'achieved_minutes' => $minutesInYear,
        ]);
    }
}
