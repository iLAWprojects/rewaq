<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityBookHistory extends Model
{
    protected $table = 'community_books_history';

    protected $fillable = [
        'community_id',
        'book_id',
        'month_name',
        'read_year',
        'read_month',
        'notes',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
