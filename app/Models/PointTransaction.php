<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    const STATUS_VERIFIED = 'verified';

    const STATUS_FLAGGED = 'flagged_for_review';

    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'points',
        'is_valid',
        'status',
        'activity_type',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'points' => 'integer',
        'is_valid' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true);
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FLAGGED);
    }

    public function activityLabel(): string
    {
        return match ($this->activity_type) {
            'reading_session' => 'جلسة قراءة وتوثيق صفحات',
            'book_finished' => 'إتمام قراءة كتاب كامل',
            'review' => 'كتابة مراجعة ونقد أدبي',
            'discussion' => 'مشاركة وإثراء نقاش المجلس',
            'poll_vote' => 'تصويت لاختيار كتاب الشهر',
            'meeting_checkin' => 'توثيق حضور اللقاء برمز QR',
            'challenge_complete' => 'إنجاز تحدٍ قرائي وحصد وسامه',
            'badge_earned' => 'استحقاق وسام شرفي جديد',
            'nomination' => 'ترشيح كتاب للشهر القادم',
            'bonus' => 'أثر إضافي ومكافأة تميز',
            default => 'نشاط معرفي في الرواق',
        };
    }
}
