<?php

namespace App\Services\BookLookup;

use App\Models\Book;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IsbnLookupService
{
    /** @var array<string, class-string<IsbnProvider>> */
    private const REGISTRY = [
        'openlibrary' => OpenLibraryProvider::class,
        'googlebooks' => GoogleBooksProvider::class,
        'worldcat' => WorldCatProvider::class,
        'amazon' => AmazonProvider::class,
    ];

    /** الحقول التي إذا اكتملت لا نحتاج مزوّدًا إضافيًا. */
    private const ESSENTIAL = ['title', 'author', 'publisher', 'pages', 'cover', 'description', 'language'];

    /** @var array<int, IsbnProvider>|null */
    private ?array $providers;

    /**
     * @param  array<int, IsbnProvider>|null  $providers  للاختبار؛ افتراضيًا من إعدادات services.isbn_lookup.providers
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers;
    }

    /**
     * @return array<int, IsbnProvider>
     */
    public function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $keys = config('services.isbn_lookup.providers', array_keys(self::REGISTRY));

        return collect($keys)
            ->filter(fn ($key) => isset(self::REGISTRY[$key]))
            ->map(fn ($key) => app(self::REGISTRY[$key]))
            ->values()
            ->all();
    }

    /**
     * @return array{status: string, isbn: ?string, sources: array<int, string>, book: ?array<string, mixed>, message: string}
     */
    public function lookup(string $raw): array
    {
        $isbn = Isbn::normalize($raw);
        if (! $isbn) {
            return ['status' => 'invalid', 'isbn' => null, 'sources' => [], 'book' => null, 'message' => 'رقم ISBN غير صحيح. تأكد من الرقم (10 أو 13 خانة).'];
        }

        $isbn13 = Isbn::to13($isbn);

        // 1) الفهرس الداخلي أولًا: لا حاجة لمزوّد خارجي لكتاب موجود عندنا
        $existing = Book::whereIn('isbn', Isbn::variants($isbn))->first();
        if ($existing) {
            return [
                'status' => 'catalog',
                'isbn' => $isbn13,
                'sources' => ['فهرس رِواق'],
                'book' => $this->fromCatalog($existing),
                'message' => 'الكتاب موجود في فهرس رِواق.',
            ];
        }

        // 2) المزوّدون الخارجيون (مع تخزين مؤقت لتقليل الطلبات وتوفير حصص الـ API)
        $cacheKey = 'isbn.lookup.'.$isbn13;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->queryProviders($isbn13);
        $minutes = $result['status'] === 'found'
            ? (int) config('services.isbn_lookup.cache_minutes', 1440)
            : 30;
        Cache::put($cacheKey, $result, now()->addMinutes($minutes));

        return $result;
    }

    /**
     * @return array{status: string, isbn: string, sources: array<int, string>, book: ?array<string, mixed>, message: string}
     */
    private function queryProviders(string $isbn13): array
    {
        $merged = [];
        $sources = [];

        foreach ($this->providers() as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            try {
                $data = $provider->find($isbn13);
            } catch (ConnectionException $e) {
                Log::warning('ISBN lookup: '.$provider->key().' unreachable', ['isbn' => $isbn13]);

                continue;
            } catch (\Throwable $e) {
                Log::warning('ISBN lookup: '.$provider->key().' failed', ['isbn' => $isbn13, 'error' => $e->getMessage()]);

                continue;
            }

            if (! $data) {
                continue;
            }

            // الأول يغلب؛ المزوّدون اللاحقون يكمّلون الحقول الناقصة فقط
            $merged = $merged + $data;
            $sources[] = $provider->label();

            if (! array_diff(self::ESSENTIAL, array_keys($merged))) {
                break;
            }
        }

        if (! $merged) {
            return ['status' => 'not_found', 'isbn' => $isbn13, 'sources' => [], 'book' => null, 'message' => 'لم نعثر على هذا الكتاب. يمكنك إدخال بياناته يدويًا.'];
        }

        return [
            'status' => 'found',
            'isbn' => $isbn13,
            'sources' => $sources,
            'book' => $merged + ['isbn' => $isbn13],
            // حقول لم يعثر عليها أي مصدر: تُعرض للمستخدم ليكملها يدويًا
            'missing' => array_values(array_diff(['publisher', 'pages', 'cover', 'description'], array_keys($merged))),
            'message' => 'تم جلب بيانات الكتاب من '.implode(' + ', $sources).'. راجعها قبل الحفظ.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromCatalog(Book $book): array
    {
        $cover = $book->cover_image
            ? (str_starts_with($book->cover_image, 'http') || str_starts_with($book->cover_image, 'data:') ? $book->cover_image : asset($book->cover_image))
            : null;

        return Normalizer::clean([
            'id' => $book->id,
            'isbn' => $book->isbn,
            'title' => $book->title,
            'author' => $book->author,
            'publisher' => $book->publisher,
            'pages' => (int) $book->total_pages,
            'category' => $book->category,
            'language' => $book->language,
            'description' => $book->description,
            'cover' => $cover,
        ]);
    }
}
