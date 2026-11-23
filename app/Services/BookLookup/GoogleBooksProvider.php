<?php

namespace App\Services\BookLookup;

use Illuminate\Support\Facades\Http;

/**
 * Google Books: بلا مفتاح يستخدم حصة مشتركة تنفد سريعًا، لذا يُنصح بمفتاح مجاني (GOOGLE_BOOKS_API_KEY).
 */
class GoogleBooksProvider implements IsbnProvider
{
    public function key(): string
    {
        return 'googlebooks';
    }

    public function label(): string
    {
        return 'Google Books';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function find(string $isbn): ?array
    {
        $params = ['q' => 'isbn:'.$isbn, 'maxResults' => 1];
        if ($key = config('services.google_books.key')) {
            $params['key'] = $key;
        }

        $response = Http::timeout((int) config('services.isbn_lookup.timeout', 8))
            ->acceptJson()
            ->get('https://www.googleapis.com/books/v1/volumes', $params);

        // 429 (انتهاء الحصة) وأي خطأ آخر: نتجاوز هذا المزوّد
        if (! $response->successful()) {
            return null;
        }

        $info = $response->json('items.0.volumeInfo');
        if (! is_array($info) || empty($info['title'])) {
            return null;
        }

        return Normalizer::clean([
            'title' => $info['title'].(! empty($info['subtitle']) ? ': '.$info['subtitle'] : ''),
            'author' => implode('، ', $info['authors'] ?? []),
            'publisher' => $info['publisher'] ?? null,
            'pages' => (int) ($info['pageCount'] ?? 0),
            'category' => Normalizer::category($info['categories'] ?? []),
            'language' => Normalizer::language($info['language'] ?? null),
            'description' => $info['description'] ?? null,
            'cover' => Normalizer::cover($info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null),
        ]);
    }
}
