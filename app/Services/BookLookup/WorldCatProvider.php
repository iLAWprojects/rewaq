<?php

namespace App\Services\BookLookup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * WorldCat Search API v2 (OCLC). يتطلب WSKey/Client ID وSecret من OCLC (اشتراك مؤسسي).
 * يُتجاوز تلقائيًا إن لم تُضبط WORLDCAT_CLIENT_ID وWORLDCAT_CLIENT_SECRET.
 */
class WorldCatProvider implements IsbnProvider
{
    private const TOKEN_URL = 'https://oauth.oclc.org/token';

    private const SEARCH_URL = 'https://americas.discovery.api.oclc.org/worldcat/search/v2/bibs';

    public function key(): string
    {
        return 'worldcat';
    }

    public function label(): string
    {
        return 'WorldCat';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.worldcat.client_id')) && filled(config('services.worldcat.client_secret'));
    }

    public function find(string $isbn): ?array
    {
        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        $response = Http::timeout((int) config('services.isbn_lookup.timeout', 8))
            ->withToken($token)
            ->acceptJson()
            ->get(self::SEARCH_URL, ['q' => 'bn:'.$isbn, 'limit' => 1]);

        if (! $response->successful()) {
            return null;
        }

        $record = $response->json('bibRecords.0');
        if (! is_array($record)) {
            return null;
        }

        $title = data_get($record, 'title.mainTitles.0.text');
        if (! $title) {
            return null;
        }

        $creators = collect(data_get($record, 'contributor.creators', []))
            ->map(fn ($c) => trim((data_get($c, 'firstName.text') ?? '').' '.(data_get($c, 'secondName.text') ?? '')))
            ->filter()
            ->implode('، ');

        return Normalizer::clean([
            'title' => rtrim($title, ' /:;,'),
            'author' => $creators ?: rtrim((string) data_get($record, 'contributor.statementOfResponsibility.text'), ' .'),
            'publisher' => data_get($record, 'publishing.publisherName'),
            'language' => Normalizer::language(data_get($record, 'language.itemLanguage')),
        ]);
    }

    private function accessToken(): ?string
    {
        return Cache::remember('worldcat.token', now()->addMinutes(15), function () {
            $response = Http::timeout((int) config('services.isbn_lookup.timeout', 8))
                ->withBasicAuth((string) config('services.worldcat.client_id'), (string) config('services.worldcat.client_secret'))
                ->asForm()
                ->acceptJson()
                ->post(self::TOKEN_URL, ['grant_type' => 'client_credentials', 'scope' => 'wcapi']);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }
}
