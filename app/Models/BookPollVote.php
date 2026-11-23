<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookPollVote extends Model
{
    protected $fillable = [
        'book_poll_id',
        'book_poll_option_id',
        'user_id',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(BookPoll::class, 'book_poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(BookPollOption::class, 'book_poll_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
