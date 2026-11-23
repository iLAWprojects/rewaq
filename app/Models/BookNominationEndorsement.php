<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookNominationEndorsement extends Model
{
    protected $fillable = [
        'nomination_id',
        'user_id',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(BookNomination::class, 'nomination_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
