<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeProgressLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'user_id',
        'team_id',
        'reading_session_id',
        'book_id',
        'event_type',
        'increment_value',
        'progress_before',
        'progress_after',
        'points_awarded',
        'notes',
        'logged_at',
    ];

    protected $casts = [
        'increment_value' => 'integer',
        'progress_before' => 'integer',
        'progress_after' => 'integer',
        'points_awarded' => 'integer',
        'logged_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ChallengeTeam::class, 'team_id');
    }

    public function readingSession(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Localized description of the event type.
     */
    public function getEventLabelAttribute(): string
    {
        return match ($this->event_type) {
            'pages_read' => 'قراءة صفحات 📄',
            'book_completed' => 'إتمام قراءة كتاب 📖',
            'hours_logged' => 'تسجيل ساعات قراءة ⏱️',
            'daily_streak' => 'تثبيت وتيرة قراءة يومية 🔥',
            'category_explored' => 'استكشاف تصنيف جديد 🧭',
            'author_explored' => 'قراءة لمؤلف جديد ✍️',
            'milestone_bonus' => 'مكافأة إنجاز مرحلة 🎯',
            default => 'تسجيل تقدم في التحدي ✦',
        };
    }
}
