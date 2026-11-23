<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\BookLookup\AmazonProvider;
use App\Services\BookLookup\GoogleBooksProvider;
use App\Services\BookLookup\Isbn;
use App\Services\BookLookup\IsbnLookupService;
use App\Services\BookLookup\OpenLibraryProvider;
use App\Services\BookLookup\WorldCatProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnLookupTest extends TestCase
{
    use DatabaseTransactions;

    private const ISBN13 = '9780140328721';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.isbn_lookup.providers' => ['openlibrary', 'googlebooks', 'worldcat', 'amazon'],
            'services.worldcat.client_id' => null,
            'services.worldcat.client_secret' => null,
            'services.amazon.access_key' => null,
            'services.amazon.secret_key' => null,
            'services.amazon.partner_tag' => null,
        ]);
    }

    public function test_isbn_helper_validates_and_converts(): void
    {
        $this->assertSame(self::ISBN13, Isbn::normalize('978-0-14-032872-1'));
        $this->assertSame('0140328726', Isbn::normalize('0-14-032872-6'));
        $this->assertNull(Isbn::normalize('9780140328722'));
        $this->assertNull(Isbn::normalize('123'));
        $this->assertSame(self::ISBN13, Isbn::to13('0140328726'));
        $this->assertSame('0140328726', Isbn::to10(self::ISBN13));
        $this->assertNull(Isbn::to10('9791234567896'));
    }

    public function test_endpoint_requires_login_and_rejects_invalid_isbn(): void
    {
        $this->getJson(route('books.isbn', '9780140328721'))->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('books.isbn', '1234567890'))
            ->assertStatus(422)->assertJsonPath('status', 'invalid');
    }

    public function test_open_library_result_is_returned_and_stops_when_complete(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response(['ISBN:'.self::ISBN13 => [
                'title' => 'Fantastic Mr. Fox',
                'authors' => [['name' => 'Roald Dahl']],
                'publishers' => [['name' => 'Puffin']],
                'number_of_pages' => 96,
                'subjects' => [['name' => 'Fiction']],
                'cover' => ['large' => 'https://covers.openlibrary.org/b/id/1-L.jpg'],
            ]]),
            'googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
                'title' => 'Ignored', 'description' => 'A story about a clever fox.', 'language' => 'en',
                'imageLinks' => ['thumbnail' => 'http://books.google.com/small.jpg'],
            ]]]]),
        ]);

        $res = $this->actingAs(User::factory()->create())->getJson(route('books.isbn', self::ISBN13))
            ->assertOk()->assertJsonPath('status', 'found')->json();

        // Open Library يغلب في الحقول المشتركة (الغلاف الكبير)، وGoogle يكمّل الوصف واللغة
        $this->assertSame('Fantastic Mr. Fox', $res['book']['title']);
        $this->assertSame('Roald Dahl', $res['book']['author']);
        $this->assertSame('Puffin', $res['book']['publisher']);
        $this->assertSame(96, $res['book']['pages']);
        $this->assertSame('أدب', $res['book']['category']);
        $this->assertSame('https://covers.openlibrary.org/b/id/1-L.jpg', $res['book']['cover']);
        $this->assertSame('A story about a clever fox.', $res['book']['description']);
        $this->assertSame('الإنجليزية', $res['book']['language']);
        $this->assertSame(['Open Library', 'Google Books'], $res['sources']);
        $this->assertSame([], $res['missing']);

        // اكتملت كل الحقول، فلا حاجة لـ WorldCat أو Amazon
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'oclc.org') || str_contains($request->url(), 'amazon.com'));
    }

    public function test_later_providers_fill_missing_fields_and_failed_provider_is_skipped(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response(['ISBN:'.self::ISBN13 => ['title' => 'Fantastic Mr. Fox', 'authors' => [['name' => 'Roald Dahl']]]]),
            'googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
                'title' => 'Ignored Title', 'publisher' => 'Puffin Books', 'pageCount' => 96, 'language' => 'en',
                'imageLinks' => ['thumbnail' => 'http://books.google.com/x.jpg&edge=curl'],
            ]]]]),
        ]);

        $res = (new IsbnLookupService([new OpenLibraryProvider, app(GoogleBooksProvider::class)]))->lookup(self::ISBN13);

        $this->assertSame('found', $res['status']);
        $this->assertSame('Fantastic Mr. Fox', $res['book']['title']);
        $this->assertSame('Puffin Books', $res['book']['publisher']);
        $this->assertSame('الإنجليزية', $res['book']['language']);
        $this->assertSame('https://books.google.com/x.jpg', $res['book']['cover']);
        $this->assertSame(['Open Library', 'Google Books'], $res['sources']);
    }

    public function test_partial_result_reports_missing_fields(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response([]),
            'googleapis.com/*' => Http::response(['items' => [['volumeInfo' => ['title' => 'الايام', 'authors' => ['Taha Husain'], 'pageCount' => 0, 'language' => 'ar']]]]),
        ]);

        $res = (new IsbnLookupService)->lookup(self::ISBN13);

        $this->assertSame('found', $res['status']);
        $this->assertSame(['publisher', 'pages', 'cover', 'description'], $res['missing']);
        $this->assertSame('العربية', $res['book']['language']);
    }

    public function test_quota_error_from_google_is_not_fatal(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response([]),
            'googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429),
        ]);

        $res = (new IsbnLookupService)->lookup(self::ISBN13);

        $this->assertSame('not_found', $res['status']);
    }

    public function test_unconfigured_worldcat_and_amazon_are_skipped_and_configured_ones_are_called(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response([]),
            'googleapis.com/*' => Http::response([], 500),
            'oauth.oclc.org/*' => Http::response(['access_token' => 'tok']),
            'americas.discovery.api.oclc.org/*' => Http::response(['bibRecords' => [[
                'title' => ['mainTitles' => [['text' => 'Fantastic Mr. Fox /']]],
                'contributor' => ['creators' => [['firstName' => ['text' => 'Roald'], 'secondName' => ['text' => 'Dahl']]]],
                'publishing' => ['publisherName' => 'Puffin'],
            ]]]),
            'webservices.amazon.com/*' => Http::response(['ItemsResult' => ['Items' => [[
                'ItemInfo' => ['Title' => ['DisplayValue' => 'Amazon Title'], 'ByLineInfo' => ['Contributors' => [['Name' => 'Roald Dahl']]], 'ContentInfo' => ['PagesCount' => ['DisplayValue' => 96]]],
                'Images' => ['Primary' => ['Large' => ['URL' => 'http://m.media-amazon.com/img.jpg']]],
            ]]]]),
        ]);

        // بلا مفاتيح: لا يُستدعى WorldCat ولا Amazon
        $this->assertSame('not_found', (new IsbnLookupService)->lookup(self::ISBN13)['status']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'oclc.org') || str_contains($r->url(), 'amazon.com'));

        Cache::flush();
        config([
            'services.worldcat.client_id' => 'id', 'services.worldcat.client_secret' => 'secret',
            'services.amazon.access_key' => 'AK', 'services.amazon.secret_key' => 'SK', 'services.amazon.partner_tag' => 'tag-20',
        ]);

        $res = (new IsbnLookupService)->lookup(self::ISBN13);

        $this->assertSame('found', $res['status']);
        $this->assertSame('Fantastic Mr. Fox', $res['book']['title']);
        $this->assertSame('Roald Dahl', $res['book']['author']);
        $this->assertSame('Puffin', $res['book']['publisher']);
        $this->assertSame(96, $res['book']['pages']);
        $this->assertSame('https://m.media-amazon.com/img.jpg', $res['book']['cover']);
        $this->assertSame(['WorldCat', 'Amazon'], $res['sources']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'americas.discovery.api.oclc.org') && $r->hasHeader('Authorization', 'Bearer tok') && str_contains($r->url(), 'bn%3A'.self::ISBN13));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'webservices.amazon.com/paapi5/getitems')
            && str_starts_with($r->header('Authorization')[0] ?? '', 'AWS4-HMAC-SHA256 Credential=AK/')
            && ($r->data()['ItemIds'] ?? null) === ['0140328726']
            && ($r->data()['PartnerTag'] ?? null) === 'tag-20');
    }

    public function test_amazon_signature_is_deterministic_for_aws_sigv4(): void
    {
        config(['services.amazon.access_key' => 'AK', 'services.amazon.secret_key' => 'SK']);
        $provider = new AmazonProvider;
        $now = new \DateTimeImmutable('2026-01-02 03:04:05', new \DateTimeZone('UTC'));

        $a = $provider->signedHeaders('webservices.amazon.com', 'us-east-1', '/paapi5/getitems', '{"a":1}', $now);
        $b = $provider->signedHeaders('webservices.amazon.com', 'us-east-1', '/paapi5/getitems', '{"a":1}', $now);

        $this->assertSame($a, $b);
        $this->assertSame('20260102T030405Z', $a['X-Amz-Date']);
        $this->assertMatchesRegularExpression('#Credential=AK/20260102/us-east-1/ProductAdvertisingAPI/aws4_request, SignedHeaders=content-encoding;content-type;host;x-amz-date;x-amz-target, Signature=[0-9a-f]{64}$#', $a['Authorization']);
    }

    public function test_catalog_is_checked_first_without_calling_external_apis(): void
    {
        Http::fake();
        Book::create(['title' => 'كتاب بالفهرس', 'author' => 'م', 'category' => 'أدب', 'total_pages' => 120, 'isbn' => self::ISBN13]);

        $res = $this->actingAs(User::factory()->create())->getJson(route('books.isbn', '0-14-032872-6'))
            ->assertOk()->assertJsonPath('status', 'catalog')->json();

        $this->assertSame('كتاب بالفهرس', $res['book']['title']);
        $this->assertNotEmpty($res['book']['id']);
        Http::assertNothingSent();
    }

    public function test_store_book_saves_isbn_and_trusted_cover_url_and_ignores_untrusted_host(): void
    {
        $user = User::factory()->create();
        $payload = fn (string $title, string $cover, string $isbn) => [
            'title' => $title, 'author' => 'مؤلف', 'category' => 'أدب', 'publisher' => 'دار تجريبية', 'language' => 'الإنجليزية',
            'isbn' => $isbn, 'cover_url' => $cover, 'description' => 'وصف', 'library_id' => $user->getPersonalLibrary()->id,
        ];

        $this->actingAs($user)->post(route('libraries.storeBook'), $payload('كتاب ISBN', 'https://covers.openlibrary.org/b/id/1-L.jpg', '978-0-14-032872-1'))
            ->assertSessionHasNoErrors();
        $book = Book::where('title', 'كتاب ISBN')->firstOrFail();
        $this->assertSame(self::ISBN13, $book->isbn);
        $this->assertSame('https://covers.openlibrary.org/b/id/1-L.jpg', $book->cover_image);
        $this->assertSame('دار تجريبية', $book->publisher);

        $this->actingAs($user)->post(route('libraries.storeBook'), $payload('كتاب غلاف مشبوه', 'https://evil.example.com/x.jpg', '9780306406157'));
        $this->assertNull(Book::where('title', 'كتاب غلاف مشبوه')->firstOrFail()->cover_image);

        $this->actingAs($user)->post(route('libraries.storeBook'), $payload('كتاب رقم خاطئ', 'https://covers.openlibrary.org/b/id/2-L.jpg', '9780306406158'))
            ->assertSessionHasErrors('isbn');
    }

    public function test_add_book_modal_has_isbn_lookup_controls(): void
    {
        $this->actingAs(User::factory()->create())->get(route('libraries.index'))
            ->assertOk()->assertSee('id="btn-isbn-lookup"', false)->assertSee('name="isbn"', false);
    }

    public function test_worldcat_provider_reports_not_configured_without_keys(): void
    {
        $this->assertFalse((new WorldCatProvider)->isConfigured());
        $this->assertFalse((new AmazonProvider)->isConfigured());
    }
}
