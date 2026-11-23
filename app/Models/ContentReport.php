<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContentReport extends Model
{
    public const TYPES = ['discussion', 'comment', 'book', 'review', 'reflection', 'user'];

    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'moderator_notes',
        'moderated_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function target(): ?Model
    {
        return match ($this->reportable_type) {
            'discussion' => Discussion::find($this->reportable_id),
            'comment' => DiscussionComment::find($this->reportable_id),
            'book' => Book::find($this->reportable_id),
            'review' => BookReview::find($this->reportable_id),
            'reflection' => Reflection::find($this->reportable_id),
            'user' => User::find($this->reportable_id),
            default => null,
        };
    }

    public function typeLabel(): string
    {
        return match ($this->reportable_type) {
            'discussion' => 'مناقشة',
            'comment' => 'تعليق',
            'book' => 'كتاب',
            'review' => 'مراجعة',
            'reflection' => 'تأمل/اقتباس',
            'user' => 'حساب قارئ',
            default => 'محتوى',
        };
    }

    public function targetSummary(): string
    {
        $target = $this->target();
        if (! $target) {
            return 'المحتوى محذوف أو غير متاح';
        }

        $text = $target->title ?? $target->name ?? $target->quote ?? $target->content ?? $target->body ?? $target->comment ?? $target->text ?? '';

        return Str::limit((string) $text, 70);
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            'spoiler' => 'حرق أحداث غير معلن (Spoiler)',
            'inappropriate' => 'محتوى غير لائق أو مخالف',
            'copyright' => 'انتهاك حقوق النشر',
            'misinformation' => 'معلومات مضللة أو خاطئة',
            'spam' => 'إعلانات ورسائل متكررة (Spam)',
            'disrespect' => 'إساءة أو تنمر على القراء',
            default => 'مخالفة أخرى',
        };
    }
}
