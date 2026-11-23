<?php

namespace App\Models;

use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MeetingRsvp extends Model
{
    use HasFactory;

    // 6 RSVP Statuses
    const STATUS_GOING = 'going';

    const STATUS_MAYBE = 'maybe';

    const STATUS_NOT_GOING = 'not_going';

    const STATUS_WAITLIST = 'waitlist';

    const STATUS_ATTENDED = 'attended';

    const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'meeting_id',
        'user_id',
        'status', // going, maybe, not_going, waitlist, attended, no_show
        'qr_code',
        'qr_token',
        'qr_token_expires_at',
        'checked_in',
        'checked_in_at',
        'rating',
        'review',
        'notes',
    ];

    protected $casts = [
        'checked_in' => 'boolean',
        'checked_in_at' => 'datetime',
        'qr_token_expires_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate or refresh a secure meeting-scoped QR token (not exposing raw user ID).
     */
    public function generateOrRefreshQrToken(): string
    {
        // 64-character cryptographic token scoped strictly to this meeting
        $token = hash('sha256', "RWQ-MTG-{$this->meeting_id}-{$this->user_id}-".Str::random(24));

        $this->qr_token = $token;
        $this->qr_code = 'RWQ-MTG-'.strtoupper(substr($token, 0, 10));

        // Expiration: 24 hours after meeting starts, or +48h
        $meeting = $this->meeting;
        $expires = $meeting && $meeting->meeting_date
            ? $meeting->meeting_date->copy()->addHours(24)
            : now()->addDays(2);

        $this->qr_token_expires_at = $expires;
        $this->save();

        return $token;
    }

    /**
     * Check if the QR token is still valid and not expired.
     */
    public function isQrTokenValid(): bool
    {
        if ($this->qr_token_expires_at && $this->qr_token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Generate QR Code SVG for check-in using the secure token.
     */
    public function getQrSvgAttribute(): string
    {
        $tokenToEncode = $this->qr_token ?: $this->qr_code ?: "RWQ-MTG-{$this->meeting_id}-{$this->id}";

        return QrCodeService::generateSvg($tokenToEncode, 200, 200);
    }

    /**
     * Localized status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_GOING, 'attending' => 'مؤكد الحضور ✓',
            self::STATUS_MAYBE => 'ربما أحضر ⏳',
            self::STATUS_NOT_GOING, 'declined' => 'معتذر عن الحضور ✕',
            self::STATUS_WAITLIST => 'قائمة الانتظار (FIFO) 🕒',
            self::STATUS_ATTENDED => 'تم تسجيل الحضور 🏅',
            self::STATUS_NO_SHOW => 'لم يحضر (غياب) ⚠️',
            default => $this->status,
        };
    }

    /**
     * Status badge class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_GOING, 'attending' => 'badge-success',
            self::STATUS_MAYBE => 'badge-warning',
            self::STATUS_NOT_GOING, 'declined' => 'badge-secondary',
            self::STATUS_WAITLIST => 'badge-info',
            self::STATUS_ATTENDED => 'badge-primary',
            self::STATUS_NO_SHOW => 'badge-danger',
            default => 'badge-info',
        };
    }
}
