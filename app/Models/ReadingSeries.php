<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReadingSeries extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'badge_name',
        'cover_color',
    ];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'reading_series_books', 'series_id', 'book_id')
            ->withPivot('order_num')
            ->orderByPivot('order_num', 'asc')
            ->withTimestamps();
    }
}
