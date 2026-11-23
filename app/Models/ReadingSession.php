<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingSession extends Model
{
    protected $fillable = [
        'user_id',
        'book_id',
        'started_at',
        'ended_at',
        'start_page',
        'end_page',
        'current_page',
        'pages_read',
        'minutes',
        'is_valid',
        'invalid_reason',
        'device',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'start_page' => 'integer',
        'end_page' => 'integer',
        'current_page' => 'integer',
        'pages_read' => 'integer',
        'minutes' => 'integer',
        'is_valid' => 'boolean',
    ];

    /**
     * الجلسات الموثّقة فقط: هي التي تُحتسب في التحديات والنقاط والتتابع والهدف السنوي.
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Anti-tamper rule: A session is eligible towards the daily reading streak
     * only if it represents at least 5 minutes of focused reading or at least 2 pages read.
     */
    public function isEligibleForStreak(): bool
    {
        return $this->minutes >= 5 || $this->pages_read >= 2;
    }

    public function getDeviceIconAttribute(): string
    {
        return match ($this->device) {
            'كتاب ورقي' => 'book',
            'قارئ إلكتروني (Kindle)' => 'tablet',
            'جهاز لوحي (Tablet)' => 'tablet',
            'هاتف ذكي' => 'smartphone',
            'حاسوب' => 'monitor',
            default => 'book-open',
        };
    }
}
