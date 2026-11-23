<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityJoinRequest extends Model
{
    protected $fillable = [
        'community_id',
        'user_id',
        'status',
        'notes',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'قيد المراجعة ⏳',
            'approved' => 'تمت الموافقة 🌿',
            'rejected' => 'مرفوض',
            default => $this->status,
        };
    }
}
