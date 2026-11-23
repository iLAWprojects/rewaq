<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reflection extends Model
{
    protected $fillable = [
        'user_id',
        'book_id',
        'community_id',
        'content',
        'page_number',
        'is_public',
        'privacy',
        'is_highlight',
        'is_spoiler',
        'likes_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_highlight' => 'boolean',
        'is_spoiler' => 'boolean',
        'page_number' => 'integer',
        'likes_count' => 'integer',
        'community_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reflection_likes')->withTimestamps();
    }

    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->relationLoaded('likes')) {
            return $this->likes->contains('id', $user->id);
        }

        return $this->likes()->where('users.id', $user->id)->exists();
    }

    public function toggleLike(User $user): bool
    {
        if ($this->isLikedBy($user)) {
            $this->likes()->detach($user->id);
            $this->decrement('likes_count');

            return false;
        }

        $this->likes()->attach($user->id);
        $this->increment('likes_count');

        if ($this->user_id && $this->user_id !== $user->id) {
            UserNotification::create([
                'user_id' => $this->user_id,
                'title' => 'أثر جديد على تأملك ✦',
                'body' => "ترك القارئ {$user->name} أثراً على خاطرتك.",
                'icon' => 'sparkles',
                'link' => route('communities.index'),
                'type' => 'reflection_like',
                'category' => UserNotification::CATEGORY_COMMUNITIES,
            ]);
        }

        return true;
    }

    public function isPrivate(): bool
    {
        return $this->privacy === 'private' || ! $this->is_public;
    }

    public function scopePublicOnly($query)
    {
        return $query->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('privacy')->orWhere('privacy', 'public');
            });
    }

    public function scopeVisibleTo($query, ?User $viewer)
    {
        if (! $viewer) {
            return $this->scopePublicOnly($query);
        }

        return $query->where(function ($q) use ($viewer) {
            $q->where('user_id', $viewer->id)
                ->orWhere(function ($sq) {
                    $sq->where('is_public', true)
                        ->where(function ($ssq) {
                            $ssq->whereNull('privacy')->orWhere('privacy', 'public');
                        });
                });
        });
    }
}
