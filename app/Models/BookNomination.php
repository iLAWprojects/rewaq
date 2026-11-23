<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookNomination extends Model
{
    protected $fillable = [
        'community_id',
        'user_id',
        'book_id',
        'title',
        'author',
        'reason',
        'endorsements_count',
        'status',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function endorsements(): HasMany
    {
        return $this->hasMany(BookNominationEndorsement::class, 'nomination_id');
    }

    public function isEndorsedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->endorsements()->where('user_id', $user->id)->exists();
    }
}
