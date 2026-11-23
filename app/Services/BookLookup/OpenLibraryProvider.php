<?php

namespace App\Services\BookLookup;

use Illuminate\Support\Facades\Http;

/**
 * Open Library: مجاني بدون مفتاح.
 */
class OpenLibraryProvider implements IsbnProvider
{
    public function key(): string
    {
        return 'openlibrary';
    }

    public function label(): string
    {
        return 'Open Library';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function find(string $isbn): ?array
    {
        $response = Http::timeout((int) config('services.isbn_lookup.timeout', 8))
            ->acceptJson()
            ->get('https://openlibrary.org/api/books', [
                'bibkeys' => 'ISBN:'.$isbn,
                'format' => 'json',
                'jscmd' => 'data',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $book = $response->json('ISBN:'.$isbn);
        if (! is_array($book) || empty($book['title'])) {
            return null;
        }

        $subjects = collect($book['subjects'] ?? [])->pluck('name')->filter()->values()->all();
        $title = $book['title'].(! empty($book['subtitle']) ? ': '.$book['subtitle'] : '');

        return Normalizer::clean([
            'title' => $title,
            'author' => collect($book['authors'] ?? [])->pluck('name')->filter()->implode('، '),
            'publisher' => data_get($book, 'publishers.0.name'),
            'pages' => (int) ($book['number_of_pages'] ?? 0),
            'category' => Normalizer::category($subjects),
            'cover' => Normalizer::cover(data_get($book, 'cover.large') ?? data_get($book, 'cover.medium')),
        ]);
    }
}
