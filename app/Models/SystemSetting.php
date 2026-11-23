<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * الحصول على قيمة إعداد معين مع تحويل النوع المناسب
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $setting->value,
            'float' => (float) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * تعيين قيمة إعداد معين
     */
    public static function set(string $key, $value, ?string $type = null, ?string $group = 'general', ?string $description = null): self
    {
        $storedValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $inferredType = $type ?? (is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_array($value) ? 'json' : 'string')));

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $inferredType,
                'group' => $group ?? 'general',
                'description' => $description,
            ]
        );
    }

    /**
     * التحقق مما إذا كان نظام إعارة واستعارة الكتب مفعلاً في المنصة
     */
    public static function isLendingEnabled(): bool
    {
        return (bool) static::get('enable_book_lending', true);
    }
}
