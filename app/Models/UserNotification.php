<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserNotification extends Model
{
    use HasFactory;

    // Categories (فئات الإشعارات)
    public const CATEGORY_COMMUNITIES = 'communities';

    public const CATEGORY_CHALLENGES = 'challenges';

    public const CATEGORY_MEETINGS = 'meetings';

    public const CATEGORY_LOANS = 'loans';

    public const CATEGORY_COMMENTS = 'comments';

    public const CATEGORY_BADGES = 'badges';

    public const CATEGORY_FRIENDSHIPS = 'friendships';

    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORIES = [
        self::CATEGORY_COMMUNITIES => 'نوادي القراءة',
        self::CATEGORY_CHALLENGES => 'التحديات',
        self::CATEGORY_MEETINGS => 'الاجتماعات والتقويم',
        self::CATEGORY_LOANS => 'الإعارات والخزائن',
        self::CATEGORY_COMMENTS => 'النقاشات والتعليقات',
        self::CATEGORY_BADGES => 'الأوسمة والتحفيز',
        self::CATEGORY_FRIENDSHIPS => 'الصداقة والرفاق',
        self::CATEGORY_SYSTEM => 'النظام والتنبيهات',
    ];

    // Priorities (مستويات الأهمية)
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'منخفض',
        self::PRIORITY_NORMAL => 'عادي',
        self::PRIORITY_HIGH => 'هام',
        self::PRIORITY_URGENT => 'عاجل',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'icon',
        'link',
        'type',
        'category',
        'priority',
        'is_read',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class, 'notification_id');
    }

    protected static function booted()
    {
        static::creating(function (UserNotification $notification) {
            // Infer category if empty or default system
            if (empty($notification->category) || $notification->category === self::CATEGORY_SYSTEM) {
                $type = $notification->type ?? '';
                $title = $notification->title ?? '';

                if ($type === 'loan' || str_contains($title, 'إعارة') || str_contains($title, 'استعارة') || str_contains($title, 'كتاب')) {
                    $notification->category = self::CATEGORY_LOANS;
                } elseif ($type === 'event' || $type === 'meeting' || str_contains($title, 'اجتماع') || str_contains($title, 'لقاء') || str_contains($title, 'مجلس')) {
                    $notification->category = self::CATEGORY_MEETINGS;
                } elseif ($type === 'challenge' || str_contains($title, 'تحدي')) {
                    $notification->category = self::CATEGORY_CHALLENGES;
                } elseif ($type === 'badge' || str_contains($title, 'وسام') || str_contains($title, 'نقاط')) {
                    $notification->category = self::CATEGORY_BADGES;
                } elseif ($type === 'friend' || $type === 'social' || str_contains($title, 'صداقة') || str_contains($title, 'رفيق') || str_contains($title, 'متابع')) {
                    $notification->category = self::CATEGORY_FRIENDSHIPS;
                } elseif ($type === 'comment' || $type === 'discussion' || str_contains($title, 'نقاش') || str_contains($title, 'تعليق')) {
                    $notification->category = self::CATEGORY_COMMENTS;
                } elseif ($type === 'community' || str_contains($title, 'نادي')) {
                    $notification->category = self::CATEGORY_COMMUNITIES;
                } else {
                    $notification->category = self::CATEGORY_SYSTEM;
                }
            }

            if (empty($notification->priority)) {
                $notification->priority = self::PRIORITY_NORMAL;
            }

            if (empty($notification->icon)) {
                $notification->icon = 'bell';
            }

            if (empty($notification->type)) {
                $notification->type = $notification->category ?: 'system';
            }
        });

        static::created(function (UserNotification $notification) {
            // Automatically log in-app delivery if not already logged
            if (! NotificationDeliveryLog::where('notification_id', $notification->id)->where('channel', 'in_app')->exists()) {
                NotificationDeliveryLog::create([
                    'user_id' => $notification->user_id,
                    'notification_id' => $notification->id,
                    'channel' => 'in_app',
                    'category' => $notification->category ?: self::CATEGORY_SYSTEM,
                    'priority' => $notification->priority ?: self::PRIORITY_NORMAL,
                    'status' => 'delivered',
                    'response_code' => '200_AUTO_LOGGED',
                    'metadata' => ['auto_logged' => true],
                ]);
            }
        });
    }

    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopePriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? 'تنبيه';
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'عادي';
    }

    public function getUrlAttribute(): string
    {
        if (empty($this->link)) {
            return '#';
        }

        if (str_starts_with($this->link, 'http://') || str_starts_with($this->link, 'https://')) {
            $parsed = parse_url($this->link);
            $path = ($parsed['path'] ?? '/').(isset($parsed['query']) ? '?'.$parsed['query'] : '').(isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');

            return url($path);
        }

        return url($this->link);
    }
}
