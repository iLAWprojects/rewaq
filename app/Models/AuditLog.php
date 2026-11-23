<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * تسجيل عملية رقابية وتدقيقية جديدة مع تعتيم وتطهير أي بيانات حساسة
     */
    public static function record(
        ?User $actor,
        string $action,
        $target,
        string $description,
        ?array $old = null,
        ?array $new = null
    ): self {
        $targetType = is_object($target) ? get_class($target) : (is_string($target) ? $target : null);
        $targetId = is_object($target) && isset($target->id) ? $target->id : null;

        return static::create([
            'user_id' => $actor ? $actor->id : null,
            'action' => $action,
            'auditable_type' => $targetType,
            'auditable_id' => $targetId,
            'description' => $description,
            'old_values' => static::sanitizeValues($old),
            'new_values' => static::sanitizeValues($new),
            'ip_address' => request() ? request()->ip() : null,
            'created_at' => now(),
        ]);
    }

    /**
     * حجب وإخفاء أي كلمات مرور أو رموز سرية أو Tokens من سجل التدقيق للحفاظ على الأمان
     */
    public static function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'remember_token',
            'token',
            'auth_token',
            'p256dh',
            'secret',
            'mfa_secret',
            'recovery_codes',
            'api_token',
            'secret_key',
        ];

        $sanitized = [];
        foreach ($values as $key => $val) {
            $isSensitive = false;
            foreach ($sensitiveKeys as $sens) {
                if (stripos((string) $key, $sens) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '******** (محمي أمنياً)';
            } elseif (is_array($val)) {
                $sanitized[$key] = static::sanitizeValues($val);
            } else {
                $sanitized[$key] = $val;
            }
        }

        return $sanitized;
    }
}
