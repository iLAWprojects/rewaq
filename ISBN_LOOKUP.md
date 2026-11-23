# ISBN Lookup

When adding a book to a library, a reader can type an ISBN and press **Fetch data** to fill in the title, author, publisher, pages, category, language, and cover automatically.

## How it works

1. The ISBN is validated (ISBN-10 or ISBN-13, hyphens and spaces allowed, check digit verified) and normalized to ISBN-13.
2. **The internal catalog is checked first.** If a book with this ISBN already exists, it is returned immediately and no external service is called.
3. Otherwise the configured providers are tried in order. Providers without credentials are skipped. The first provider's values win, and later providers only fill fields that are still missing. Querying stops as soon as the essential fields (title, author, publisher, pages, cover) are all present.
4. Results are cached (24 hours by default) to save API quota. Negative results are cached for 30 minutes.
5. The reader reviews the pre-filled form and saves. The ISBN, publisher, language, description, and cover link are stored with the book.

## Providers

| Provider | Key in config | Credentials | Notes |
|---|---|---|---|
| Open Library | `openlibrary` | None | Free. Works out of the box. |
| Google Books | `googlebooks` | `GOOGLE_BOOKS_API_KEY` | Works without a key, but the shared anonymous quota is often exhausted (HTTP 429). A free key is recommended. |
| WorldCat | `worldcat` | `WORLDCAT_CLIENT_ID`, `WORLDCAT_CLIENT_SECRET` | WorldCat Search API v2 from OCLC. Requires an institutional (library) subscription. |
| Amazon | `amazon` | `AMAZON_PAAPI_ACCESS_KEY`, `AMAZON_PAAPI_SECRET_KEY`, `AMAZON_PAAPI_PARTNER_TAG` | Product Advertising API 5.0. Requires an active Amazon Associates account. |

Amazon optional settings: `AMAZON_PAAPI_HOST`, `AMAZON_PAAPI_REGION`, `AMAZON_PAAPI_MARKETPLACE` (defaults target the US marketplace; set them to match your Associates marketplace).

> Status of each integration: Open Library has been verified against the live service. Google Books requires a key to be reliable. The WorldCat and Amazon providers are implemented and covered by automated tests with mocked HTTP responses, but they have not been exercised against the live services without credentials. If you enable them, check the first real responses and adjust field mappings if needed.

## Configuration

In `.env`:

```dotenv
# Order matters. Remove a provider to disable it.
ISBN_LOOKUP_PROVIDERS=openlibrary,googlebooks,worldcat,amazon

GOOGLE_BOOKS_API_KEY=

WORLDCAT_CLIENT_ID=
WORLDCAT_CLIENT_SECRET=

AMAZON_PAAPI_ACCESS_KEY=
AMAZON_PAAPI_SECRET_KEY=
AMAZON_PAAPI_PARTNER_TAG=
AMAZON_PAAPI_HOST=webservices.amazon.com
AMAZON_PAAPI_REGION=us-east-1
AMAZON_PAAPI_MARKETPLACE=www.amazon.com

# Optional tuning
ISBN_LOOKUP_CACHE_MINUTES=1440
ISBN_LOOKUP_TIMEOUT=8
```

After changing `.env`, run `php artisan config:clear`.

## Getting API keys

### Google Books (free)

1. Open the [Google Cloud Console](https://console.cloud.google.com) and create a project.
2. Enable **Books API** (APIs & Services, Library).
3. Create an **API key** (APIs & Services, Credentials).
4. Under **API restrictions** choose **Books API** only. For **Application restrictions**, use **IP addresses** with your server's IP if the key is used from one server, or **None** if you need it in several places.
5. Put the key in `GOOGLE_BOOKS_API_KEY`.

Tip: create a separate key per environment (local, production) so one can be revoked without breaking the others.

### WorldCat (OCLC)

Request a WSKey through your library's OCLC membership. You receive a client ID and secret for the `wcapi` scope. Individuals usually cannot obtain access without an institution.

### Amazon (PA-API 5.0)

Join Amazon Associates for your marketplace. Product Advertising API access is normally enabled after qualifying sales. Then create credentials in Associates Central to receive the access key, secret key, and your partner tag. Check Amazon's current documentation, as the program terms change.

## Endpoint

```http
GET /books/isbn/{isbn}
```

Authenticated users only, throttled to 30 requests per minute.

Example response:

```json
{
  "status": "found",
  "isbn": "9780140328721",
  "sources": ["Open Library"],
  "message": "...",
  "book": {
    "title": "Fantastic Mr. Fox",
    "author": "Roald Dahl",
    "publisher": "Puffin",
    "pages": 96,
    "category": "أدب",
    "cover": "https://covers.openlibrary.org/b/id/15152634-L.jpg",
    "isbn": "9780140328721"
  }
}
```

`status` is one of:

| Status | Meaning |
|---|---|
| `found` | Data came from an external provider. |
| `catalog` | The book already exists in the Riwaq catalog (`book.id` is included). |
| `not_found` | No provider knew this ISBN. The user can enter data manually. |
| `invalid` | The ISBN is malformed. The response uses HTTP 422. |

## Security notes

- Cover links from lookups are only accepted from trusted hosts (Open Library, Archive.org, Google, Amazon image hosts) when a book is saved. Other URLs are ignored.
- Provider keys belong only in the server's `.env`.
- Amazon's terms limit how long product data may be cached, which is why the default cache is 24 hours.

## Code map

- `app/Services/BookLookup/IsbnLookupService.php`: orchestration, catalog-first, cache, merging
- `app/Services/BookLookup/Isbn.php`: validation and ISBN-10/13 conversion
- `app/Services/BookLookup/*Provider.php`: one class per provider
- `app/Http/Controllers/BookLookupController.php`: the endpoint
- `tests/Feature/IsbnLookupTest.php`: tests (mocked HTTP)
