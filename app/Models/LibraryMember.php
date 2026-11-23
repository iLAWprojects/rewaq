<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryMember extends Model
{
    protected $fillable = [
        'library_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRoleNameAttribute(): string
    {
        return match ($this->role) {
            'owner' => 'مالك المكتبة ✦',
            'librarian' => 'أمين المكتبة 🗝️',
            'member' => 'عضو مستعير 📖',
            'viewer' => 'قارئ ومطّلع 👁️',
            default => 'عضو',
        };
    }

    public function getRoleBadgeClassAttribute(): string
    {
        return match ($this->role) {
            'owner' => 'role-badge-owner',
            'librarian' => 'role-badge-librarian',
            'member' => 'role-badge-member',
            'viewer' => 'role-badge-viewer',
            default => 'role-badge-member',
        };
    }
}
