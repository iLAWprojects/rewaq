<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Library extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_personal',
        'code',
        'type',
        'cover_color',
        'loan_duration_days',
        'max_renewals',
        'grace_period_days',
    ];

    protected $casts = [
        'is_personal' => 'boolean',
        'loan_duration_days' => 'integer',
        'max_renewals' => 'integer',
        'grace_period_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(LibraryMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'library_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function waitlists(): HasMany
    {
        return $this->hasMany(BookWaitlist::class);
    }

    public function userRole(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($this->user_id === $user->id) {
            return 'owner';
        }

        $member = $this->members()->where('user_id', $user->id)->first();

        return $member ? $member->role : null;
    }

    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || $this->user_id === $user->id) {
            return true;
        }

        $role = $this->userRole($user);

        return in_array($role, ['owner', 'librarian']);
    }

    public function isOwner(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->user_id === $user->id || $this->userRole($user) === 'owner';
    }

    public function isOnlyOwner(User $user): bool
    {
        if (! $this->isOwner($user)) {
            return false;
        }

        $otherOwnersCount = $this->members()
            ->where('user_id', '!=', $user->id)
            ->where('role', 'owner')
            ->count();

        return $this->user_id === $user->id && $otherOwnersCount === 0;
    }

    public function transferOwnership(User $newOwner, User $actor): void
    {
        $oldOwnerId = $this->user_id;

        $this->update(['user_id' => $newOwner->id]);

        // Ensure new owner is in library_members with 'owner' role
        $newOwnerMember = $this->members()->where('user_id', $newOwner->id)->first();
        if ($newOwnerMember) {
            $newOwnerMember->update(['role' => 'owner']);
        } else {
            $this->members()->create([
                'user_id' => $newOwner->id,
                'role' => 'owner',
                'joined_at' => now(),
            ]);
        }

        // Demote previous owner to librarian in library_members
        $prevOwnerMember = $this->members()->where('user_id', $oldOwnerId)->first();
        if ($prevOwnerMember) {
            $prevOwnerMember->update(['role' => 'librarian']);
        }

        // Log sensitive operation in AuditLog
        AuditLog::record(
            $actor,
            'transfer_library_ownership',
            $this,
            "تم نقل ملكية الخزانة/المكتبة «{$this->name}» من «{$actor->name}» إلى «{$newOwner->name}».",
            ['owner_id' => $oldOwnerId],
            ['owner_id' => $newOwner->id]
        );
    }

    public function canViewCatalog(?User $user): bool
    {
        if ($this->type === 'public' || ($user && $user->isSuperAdmin())) {
            return true;
        }
        if (! $user) {
            return false;
        }

        return $this->userRole($user) !== null;
    }

    public function canBorrow(?User $user): bool
    {
        if (! $user || $user->is_suspended) {
            return false;
        }

        if ($user->isSuperAdmin() || $this->user_id === $user->id) {
            return true;
        }

        $role = $this->userRole($user);
        if ($role === 'viewer') {
            return false;
        }

        if ($role && in_array($role, ['owner', 'librarian', 'member'])) {
            return true;
        }

        return $this->type === 'public';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'personal' => 'مكتبة خاصة',
            'public' => 'مكتبة عامة',
            'club' => 'مكتبة صالون ونادي',
            'family' => 'مكتبة عائلية',
            default => 'مكتبة',
        };
    }
}
