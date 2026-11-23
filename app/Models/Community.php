<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Community extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'category',
        'type',
        'description',
        'announcement',
        'rules',
        'cover_color',
        'creator_id',
        'current_book_id',
        'featured_question',
        'logo',
        'cover_image',
        'language',
        'city',
        'interests',
        'max_members',
        'welcome_message',
        'is_visible_in_search',
        'requires_approval',
        'allow_member_invites',
        'allow_member_challenges',
        'allow_member_meetings',
        'linked_library_id',
        'institutional_domain',
    ];

    protected $casts = [
        'interests' => 'array',
        'max_members' => 'integer',
        'is_visible_in_search' => 'boolean',
        'requires_approval' => 'boolean',
        'allow_member_invites' => 'boolean',
        'allow_member_challenges' => 'boolean',
        'allow_member_meetings' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($community) {
            if (empty($community->code)) {
                $community->code = static::generateUniqueCode($community->name);
            }
        });
    }

    public static function generateUniqueCode(?string $name = 'RWQ'): string
    {
        $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name ?? 'RWQ')) ?: 'RWQ', 0, 4));
        do {
            $code = $clean.'-'.rand(1000, 9999);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function getInviteUrlAttribute(): string
    {
        return route('communities.joinWithCode', $this->code ?? 'RWQ');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function currentBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'current_book_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(CommunityJoinRequest::class)->latest();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class)->latest();
    }

    public function activeInvitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class)
            ->whereNotIn('status', ['revoked', 'expired', 'rejected'])
            ->latest();
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(CommunityAnnouncement::class)->latest();
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class)->latest();
    }

    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(Reflection::class)->where('is_highlight', true);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(BookPoll::class);
    }

    public function activePoll()
    {
        return $this->hasOne(BookPoll::class)->where('is_active', true)->with('options');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function userRole(?User $user): ?string
    {
        if (! $user) {
            return null;
        }
        if ($this->creator_id === $user->id) {
            return 'owner';
        }
        $member = $this->members()->where('user_id', $user->id)->first();

        return $member ? $member->pivot->role : null;
    }

    public function isMember(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->members()->where('community_members.user_id', $user->id)->exists();
    }

    public function isOwner(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->creator_id === $user->id || $this->userRole($user) === 'owner';
    }

    public function isOnlyOwner(User $user): bool
    {
        if (! $this->isOwner($user)) {
            return false;
        }

        $otherOwners = $this->members()
            ->where('community_members.user_id', '!=', $user->id)
            ->where('community_members.role', 'owner')
            ->count();

        return $this->creator_id === $user->id && $otherOwners === 0;
    }

    public function isManager(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin() || $this->isOwner($user)) {
            return true;
        }

        return in_array($this->userRole($user), ['admin', 'manager']);
    }

    public function isReadingCoordinator(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isManager($user)) {
            return true;
        }

        return in_array($this->userRole($user), ['coordinator', 'reading_coordinator']);
    }

    public function isSuspended(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->userRole($user) === 'suspended' || (bool) $user->is_suspended;
    }

    public function canPostDiscussion(?User $user): bool
    {
        if (! $user || $this->isSuspended($user)) {
            return false;
        }

        return $this->userRole($user) !== null || $this->type === 'public' || $user->isSuperAdmin();
    }

    public function canVotePoll(?User $user): bool
    {
        if (! $user || $this->isSuspended($user)) {
            return false;
        }

        return $this->userRole($user) !== null || $user->isSuperAdmin();
    }

    public function canManage(?User $user): bool
    {
        return $this->isManager($user);
    }

    public function transferOwnership(User $newOwner, User $actor): void
    {
        $oldOwnerId = $this->creator_id;

        $this->update(['creator_id' => $newOwner->id]);

        // Ensure new owner is in community_members as owner
        $this->members()->syncWithoutDetaching([
            $newOwner->id => ['role' => 'owner', 'joined_at' => now()],
        ]);

        // Demote previous owner to admin/manager
        $this->members()->updateExistingPivot($oldOwnerId, ['role' => 'admin']);

        // Log sensitive operation in AuditLog
        AuditLog::record(
            $actor,
            'transfer_community_ownership',
            $this,
            "تم نقل ملكية المجلس/النادي «{$this->name}» من «{$actor->name}» إلى «{$newOwner->name}».",
            ['creator_id' => $oldOwnerId],
            ['creator_id' => $newOwner->id]
        );
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'private' => 'مجلس خاص ومغلق 🗝️',
            'public' => 'مجلس عام (طلب وموافقة) 🌐',
            'open' => 'مجلس مفتوح (انضمام مباشر) 🌿',
            'institutional' => 'مجلس مؤسسي وأكاديمي 🏛️',
            'specialized' => 'صالون تخصصي ✦',
            'book_of_month' => 'نادي كتاب الشهر 📖',
            default => 'مجلس قراءة',
        };
    }

    public function typeLabel(): string
    {
        return $this->type_label;
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class)->latest();
    }

    public function activeChallenge()
    {
        return $this->hasOne(Challenge::class)->where('is_active', true)->latest();
    }

    public function linkedLibrary(): BelongsTo
    {
        return $this->belongsTo(Library::class, 'linked_library_id');
    }

    public function newestMembers(): BelongsToMany
    {
        return $this->members()->orderByPivot('joined_at', 'desc')->take(6);
    }

    public function leaderboard()
    {
        return $this->members()
            ->orderByDesc('points')
            ->take(10)
            ->get();
    }

    public function hasReachedCapacity(): bool
    {
        if ($this->max_members === null || $this->max_members <= 0) {
            return false;
        }

        return $this->members()->count() >= $this->max_members;
    }

    public function canDirectJoin(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->hasReachedCapacity()) {
            return false;
        }
        if ($this->type === 'open') {
            return true;
        }
        if ($this->type === 'institutional' && $this->institutional_domain) {
            return str_ends_with(strtolower($user->email), strtolower($this->institutional_domain));
        }

        return ! $this->requires_approval && $this->type !== 'private';
    }

    public function requiresJoinApproval(): bool
    {
        return $this->requires_approval || $this->type === 'public';
    }

    public function scopeVisibleInSearch($query)
    {
        return $query->where('is_visible_in_search', true)->where('type', '!=', 'private');
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(BookNomination::class)->latest();
    }

    public function booksHistory(): HasMany
    {
        return $this->hasMany(CommunityBookHistory::class)->orderByDesc('read_year')->orderByDesc('read_month');
    }

    public function badges(): HasMany
    {
        return $this->hasMany(CommunityBadge::class)->with('users');
    }
}
