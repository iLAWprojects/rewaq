<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookPollOption extends Model
{
    protected $fillable = [
        'book_poll_id',
        'book_id',
        'custom_title',
        'votes_count',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(BookPoll::class, 'book_poll_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(BookPollVote::class);
    }
}
