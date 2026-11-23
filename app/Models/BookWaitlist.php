<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookWaitlist extends Model
{
    protected $fillable = [
        'library_id',
        'book_id',
        'user_id',
        'status',
        'priority',
        'notes',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'waiting' => 'قيد الانتظار ⏳',
            'notified' => 'النسخة متاحة لك الآن ✦',
            'fulfilled' => 'تمت الاستعارة بنجاح 🌿',
            'cancelled' => 'ملغي',
            default => $this->status,
        };
    }
}
