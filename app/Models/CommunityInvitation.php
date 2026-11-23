<?php

namespace App\Models;

use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityInvitation extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_SENT = 'sent';

    const STATUS_OPENED = 'opened';

    const STATUS_ACCEPTED = 'accepted';

    const STATUS_REJECTED = 'rejected';

    const STATUS_EXPIRED = 'expired';

    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'community_id',
        'inviter_id',
        'recipient_user_id',
        'token',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'custom_message',
        'status',
        'expires_at',
        'max_uses',
        'uses_count',
        'opened_at',
        'accepted_at',
        'rejected_at',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Check if the invitation has expired by date, explicit status, or reached max uses.
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return true;
        }

        return false;
    }

    /**
     * Check if the invitation has been manually revoked.
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED || $this->revoked_at !== null;
    }

    /**
     * Determine if invitation is still valid for acceptance.
     */
    public function isValid(): bool
    {
        if ($this->isRevoked() || $this->isExpired()) {
            return false;
        }

        if ($this->status === self::STATUS_REJECTED) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return in_array($this->status, [self::STATUS_SENT, self::STATUS_OPENED]);
    }

    /**
     * Mark status as opened if currently sent.
     */
    public function markAsOpened(): bool
    {
        if ($this->status === self::STATUS_SENT) {
            $this->status = self::STATUS_OPENED;
            $this->opened_at = now();

            return $this->save();
        }

        return false;
    }

    /**
     * Mark status as accepted and increment usage count.
     */
    public function markAsAccepted(User $user): bool
    {
        $this->uses_count++;
        $this->accepted_at = now();

        // If single use or max uses reached, transition to accepted
        if ($this->max_uses === null || $this->uses_count >= $this->max_uses || $this->recipient_user_id !== null) {
            $this->status = self::STATUS_ACCEPTED;
        }

        return $this->save();
    }

    /**
     * Mark status as rejected.
     */
    public function markAsRejected(): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejected_at = now();

        return $this->save();
    }

    /**
     * Revoke the invitation.
     */
    public function markAsRevoked(User $revokedBy): bool
    {
        $this->status = self::STATUS_REVOKED;
        $this->revoked_at = now();
        $this->revoked_by = $revokedBy->id;

        return $this->save();
    }

    /**
     * Check and update status to expired if the expiration time has elapsed.
     */
    public function checkAndMarkExpired(): bool
    {
        if ($this->status !== self::STATUS_REVOKED && $this->status !== self::STATUS_EXPIRED) {
            if ($this->isExpired()) {
                $this->status = self::STATUS_EXPIRED;

                return $this->save();
            }
        }

        return false;
    }

    /**
     * Get the full invitation web URL.
     */
    public function getInviteUrlAttribute(): string
    {
        return route('communities.invitations.show', ['token' => $this->token]);
    }

    /**
     * Generate standard QR Code SVG for this exact invitation URL.
     */
    public function getQrCodeSvgAttribute(): string
    {
        return QrCodeService::generateSvg($this->invite_url, 220, 220);
    }

    /**
     * Localized status label in Arabic.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'مرسلة',
            self::STATUS_OPENED => 'تم الفتح',
            self::STATUS_ACCEPTED => 'مقبولة',
            self::STATUS_REJECTED => 'مرفوضة',
            self::STATUS_EXPIRED => 'منتهية الصلاحية',
            self::STATUS_REVOKED => 'ملغاة',
            default => $this->status,
        };
    }

    /**
     * Badge CSS class for status styling.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'badge-info',
            self::STATUS_OPENED => 'badge-warning',
            self::STATUS_ACCEPTED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_EXPIRED => 'badge-secondary',
            self::STATUS_REVOKED => 'badge-muted',
            default => 'badge-info',
        };
    }
}
