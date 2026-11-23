<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'author',
        'publisher',
        'category',
        'language',
        'total_pages',
        'cover_color',
        'cover_image',
        'isbn',
        'description',
    ];

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function readingSessions(): HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BookReview::class)->latest();
    }

    /**
     * Normalize an Arabic book title for deduplication and fuzzy matching.
     */
    public static function normalizeTitle(string $title): string
    {
        $title = trim($title);
        // Remove Arabic diacritics (tashkeel)
        $title = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $title) ?? $title;
        // Remove tatweel (kashida)
        $title = preg_replace('/[\x{0640}]/u', '', $title) ?? $title;
        // Normalize alef variants (أ, إ, آ, ٱ -> ا)
        $title = preg_replace('/[أإآٱ]/u', 'ا', $title) ?? $title;
        // Normalize taa marbouta (ة -> ه)
        $title = preg_replace('/[ة]/u', 'ه', $title) ?? $title;
        // Normalize alef maqsura (ى -> ي)
        $title = preg_replace('/[ى]/u', 'ي', $title) ?? $title;
        // Remove punctuation and special characters
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title) ?? $title;
        // Normalize multiple spaces
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return mb_strtolower(trim($title), 'UTF-8');
    }

    /**
     * Find an existing book by exact or normalized title and optional author.
     */
    public static function findMatching(string $title, ?string $author = null): ?self
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        // 1. Exact match with author (if author given)
        if ($author) {
            $exactWithAuthor = self::where('title', $title)
                ->where('author', trim($author))
                ->first();
            if ($exactWithAuthor) {
                return $exactWithAuthor;
            }
        }

        // 2. Exact match on title
        $exactTitle = self::where('title', $title)->first();
        if ($exactTitle) {
            return $exactTitle;
        }

        // 3. Match by normalized title
        $normalizedInput = self::normalizeTitle($title);

        return self::all()->first(function (self $book) use ($normalizedInput) {
            return self::normalizeTitle($book->title) === $normalizedInput;
        });
    }
}
