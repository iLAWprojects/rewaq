<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookCopy extends Model
{
    protected $fillable = [
        'book_id',
        'library_id',
        'barcode',
        'copy_number',
        'status',
        'shelf_location',
        'cover_image',
        'readers_count',
        'cities_traveled',
        'margin_notes',
        'condition',
        'condition_notes',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoan()
    {
        return $this->hasOne(Loan::class)->whereIn('status', ['pending', 'active']);
    }

    public function getConditionLabelAttribute(): string
    {
        return match ($this->condition) {
            'mint' => 'ممتازة ✦',
            'good' => 'جيدة جداً 🌿',
            'fair' => 'مقبولة 📖',
            'damaged' => 'تحتاج صيانة ⚠️',
            default => 'جيدة',
        };
    }

    public function getConditionClassAttribute(): string
    {
        return match ($this->condition) {
            'mint' => 'cond-mint',
            'good' => 'cond-good',
            'fair' => 'cond-fair',
            'damaged' => 'cond-damaged',
            default => 'cond-good',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => 'متاح للإعارة 🌿',
            'borrowed' => 'معارة حالياً',
            'reserved' => 'محجوزة / قيد المراجعة',
            'maintenance' => 'قيد الصيانة والترميم 🛠️',
            'lost' => 'مفقودة',
            default => $this->status,
        };
    }
}
