<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'discussion_id',
        'user_id',
        'parent_id',
        'content',
        'is_spoiler',
        'likes_count',
    ];

    protected $casts = [
        'is_spoiler' => 'boolean',
        'likes_count' => 'integer',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DiscussionComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionComment::class, 'parent_id')->with(['user', 'replies'])->latest();
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discussion_comment_likes')->withTimestamps();
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

        // Notify comment author
        if ($this->user_id !== $user->id) {
            UserNotification::create([
                'user_id' => $this->user_id,
                'title' => 'إعجاب جديد بتعليقك ❤️',
                'body' => "أبدى {$user->name} إعجابه بتعليقك في نقاش «{$this->discussion->title}».",
                'icon' => 'heart',
                'link' => route('communities.index', ['community_id' => $this->discussion->community_id]),
                'type' => 'comment_like',
            ]);
        }

        return true;
    }

    public function parseAndNotifyMentions(?User $actor = null): array
    {
        $actor = $actor ?? $this->user;
        $mentionedUsers = [];

        if (preg_match_all('/@([\p{L}\p{N}_\-]+)/u', $this->content, $matches)) {
            $usernames = array_unique($matches[1]);
            foreach ($usernames as $name) {
                $targetUser = User::where('name', 'LIKE', "%{$name}%")
                    ->orWhere('email', 'LIKE', "{$name}@%")
                    ->first();

                if ($targetUser && $targetUser->id !== $actor->id) {
                    $mentionedUsers[] = $targetUser;
                    UserNotification::create([
                        'user_id' => $targetUser->id,
                        'title' => 'إشارة إليك في تعليق 💬',
                        'body' => "أشار إليك {$actor->name} في تعليق ضمن نقاش «{$this->discussion->title}».",
                        'icon' => 'at-sign',
                        'link' => route('communities.index', ['community_id' => $this->discussion->community_id]),
                        'type' => 'mention',
                    ]);
                }
            }
        }

        return $mentionedUsers;
    }
}
