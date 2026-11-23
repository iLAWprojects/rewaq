<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discussion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_id',
        'user_id',
        'book_id',
        'title',
        'content',
        'is_spoiler',
        'is_pinned',
        'is_coordinator_question',
        'likes_count',
    ];

    protected $casts = [
        'is_spoiler' => 'boolean',
        'is_pinned' => 'boolean',
        'is_coordinator_question' => 'boolean',
        'likes_count' => 'integer',
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

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discussion_likes')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DiscussionComment::class)
            ->whereNull('parent_id')
            ->with(['user', 'replies'])
            ->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(DiscussionComment::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ContentReport::class, 'reportable_id')
            ->where('reportable_type', 'discussion');
    }

    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
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

        // Award points & notify discussion author
        if ($this->user_id !== $user->id) {
            UserNotification::create([
                'user_id' => $this->user_id,
                'title' => 'إعجاب جديد بنقاشك ❤️',
                'body' => "أبدى {$user->name} إعجابه بنقاشك «{$this->title}».",
                'icon' => 'heart',
                'link' => route('communities.index', ['community_id' => $this->community_id]),
                'type' => 'discussion_like',
            ]);
        }

        return true;
    }

    public function togglePin(): bool
    {
        $this->is_pinned = ! $this->is_pinned;
        $this->save();

        return $this->is_pinned;
    }

    /**
     * Parse mentions (@username or @name) and dispatch notifications.
     */
    public function parseAndNotifyMentions(?User $actor = null): array
    {
        $actor = $actor ?? $this->user;
        $mentionedUsers = [];

        // Match @words (both English and Arabic characters)
        if (preg_match_all('/@([\p{L}\p{N}_\-]+)/u', $this->content.' '.$this->title, $matches)) {
            $usernames = array_unique($matches[1]);
            foreach ($usernames as $name) {
                $targetUser = User::where('name', 'LIKE', "%{$name}%")
                    ->orWhere('email', 'LIKE', "{$name}@%")
                    ->first();

                if ($targetUser && $targetUser->id !== $actor->id) {
                    $mentionedUsers[] = $targetUser;
                    UserNotification::create([
                        'user_id' => $targetUser->id,
                        'title' => 'إشارة إليك في نقاش 📣',
                        'body' => "أشار إليك {$actor->name} في نقاش «{$this->title}» بمجلس «{$this->community->name}».",
                        'icon' => 'at-sign',
                        'link' => route('communities.index', ['community_id' => $this->community_id]),
                        'type' => 'mention',
                    ]);
                }
            }
        }

        return $mentionedUsers;
    }
}
