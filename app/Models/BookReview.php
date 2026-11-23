<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookReview extends Model
{
    protected $fillable = [
        'book_id',
        'user_id',
        'rating',
        'title',
        'content',
        'is_spoiler',
        'likes_count',
    ];

    protected $casts = [
        'is_spoiler' => 'boolean',
        'rating' => 'integer',
        'likes_count' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStarsDisplayAttribute(): string
    {
        $stars = str_repeat('★', $this->rating);
        $empty = str_repeat('☆', max(0, 5 - $this->rating));

        return $stars.$empty;
    }
}
