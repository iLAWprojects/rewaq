<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCurrentBook extends Model
{
    protected $fillable = [
        'user_id',
        'book_id',
        'status',
        'current_page',
        'progress_percentage',
        'start_date',
        'finish_date',
        'rating',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'finish_date' => 'date',
        'progress_percentage' => 'integer',
        'current_page' => 'integer',
        'rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function percentage(): int
    {
        if ($this->progress_percentage > 0) {
            return $this->progress_percentage;
        }
        if (! $this->book || $this->book->total_pages <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->current_page / $this->book->total_pages) * 100));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'want_to_read' => 'أرغب بقراءته 🔖',
            'currently_reading' => 'أقرأ الآن 📖',
            'completed' => 'مكتمل ✦',
            'on_hold' => 'متوقف / معلّق ⏸️',
            'did_not_finish' => 'لم أكمله (DNF) ⏹️',
            default => 'أقرأ الآن',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'want_to_read' => 'badge-want-read',
            'currently_reading' => 'badge-reading-now',
            'completed' => 'badge-completed',
            'on_hold' => 'badge-on-hold',
            'did_not_finish' => 'badge-dnf',
            default => 'badge-default',
        };
    }

    public function updateProgress(int $page, ?string $status = null): void
    {
        $this->current_page = $page;
        $total = $this->book?->total_pages ?: 1;
        $pct = min(100, (int) round(($page / $total) * 100));
        $this->progress_percentage = $pct;

        if ($status) {
            $this->status = $status;
        }

        if ($this->status === 'currently_reading' && ! $this->start_date) {
            $this->start_date = Carbon::today();
        }

        if ($page >= $total || $this->status === 'completed') {
            $this->status = 'completed';
            $this->progress_percentage = 100;
            if (! $this->finish_date) {
                $this->finish_date = Carbon::today();
            }
        }

        $this->save();
    }
}
