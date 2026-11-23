<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\UserCurrentBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExploreController extends Controller
{
    /**
     * بحث فوري عن الكتب لمنتقي الكتب (AJAX). بدون كلمة بحث: كتبي أولًا ثم الأحدث.
     */
    public function searchBooks(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $user = Auth::user();
        $limit = 10;

        $myIds = $user
            ? UserCurrentBook::where('user_id', $user->id)->pluck('book_id')
                ->merge(BookCopy::whereHas('library', fn ($l) => $l->where('user_id', $user->id))->pluck('book_id'))
                ->unique()->values()->all()
            : [];

        $query = Book::query();
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('author', 'like', $like));
        }
        if ($myIds) {
            $query->orderByRaw('FIELD(id, '.implode(',', array_map('intval', $myIds)).') DESC');
        }
        $books = $query->orderBy('title')->limit($limit)->get(['id', 'title', 'author', 'total_pages', 'category', 'cover_image', 'cover_color']);

        return response()->json([
            'books' => $books->map(fn ($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'author' => $b->author,
                'pages' => $b->total_pages,
                'category' => $b->category,
                'color' => $b->cover_color,
                'cover' => $b->cover_image
                    ? (str_starts_with($b->cover_image, 'http') || str_starts_with($b->cover_image, 'data:') ? $b->cover_image : asset($b->cover_image))
                    : null,
                'mine' => in_array($b->id, $myIds, true),
            ])->values(),
        ]);
    }

    public function index(Request $request)
    {
        $category = $request->query('category', 'الكل');
        $query = $request->query('q', '');
        $titleFilter = trim((string) $request->query('title', ''));
        $authorFilter = trim((string) $request->query('author', ''));
        $publisherFilter = trim((string) $request->query('publisher', ''));
        $escape = fn (string $v) => '%'.str_replace(['%', '_'], ['\\%', '\\_'], $v).'%';

        $booksQuery = Book::query();

        if ($category && $category !== 'الكل') {
            $booksQuery->where('category', $category);
        }

        if ($query) {
            $booksQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('author', 'like', "%{$query}%");
            });
        }

        if ($titleFilter !== '') {
            $booksQuery->where('title', 'like', $escape($titleFilter));
        }
        if ($authorFilter !== '') {
            $booksQuery->where('author', 'like', $escape($authorFilter));
        }
        if ($publisherFilter !== '') {
            $booksQuery->where('publisher', 'like', $escape($publisherFilter));
        }

        $rawBooks = $booksQuery
            ->withCount([
                'copies',
                'copies as available_copies_count' => fn ($q) => $q->where('status', 'available'),
            ])
            ->orderByDesc('copies_count')
            ->orderBy('id', 'asc')
            ->get();

        $books = $rawBooks->unique(function ($b) {
            return Book::normalizeTitle($b->title);
        })->values();

        $editorialBook = Book::where('title', 'مقدمة ابن خلدون')->first() ?? $books->first();

        $categories = Book::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->prepend('الكل')
            ->all();

        $publishers = Book::query()->whereNotNull('publisher')->where('publisher', '!=', '')
            ->distinct()->orderBy('publisher')->pluck('publisher')->all();

        $hasFilters = $category !== 'الكل' || $query || $titleFilter !== '' || $authorFilter !== '' || $publisherFilter !== '';
        $totalBooks = $hasFilters ? $books->count() : Book::count();
        $totalCopies = BookCopy::count();
        $availableCopiesCount = BookCopy::where('status', 'available')->count();

        $userActiveLoanedBookIds = Auth::check()
            ? Loan::where('user_id', Auth::id())
                ->whereIn('status', ['pending', 'active'])
                ->with('copy')
                ->get()
                ->pluck('copy.book_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray()
            : [];

        $userPersonalBookIds = [];
        $userPersonalNormalizedTitles = [];
        if (Auth::check()) {
            $personalLib = Auth::user()->libraries()->where('is_personal', true)->first();
            if ($personalLib) {
                $userCopies = BookCopy::where('library_id', $personalLib->id)->with('book')->get();
                $userPersonalBookIds = $userCopies->pluck('book_id')->filter()->unique()->toArray();
                $userPersonalNormalizedTitles = $userCopies
                    ->map(fn ($c) => $c->book ? Book::normalizeTitle($c->book->title) : null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html' => view('pages.partials.explore-grid', compact(
                    'books',
                    'userActiveLoanedBookIds',
                    'userPersonalBookIds',
                    'userPersonalNormalizedTitles'
                ))->render(),
                'count' => $books->count(),
            ]);
        }

        return view('pages.explore', compact(
            'publishers',
            'titleFilter',
            'authorFilter',
            'publisherFilter',
            'books',
            'category',
            'query',
            'editorialBook',
            'categories',
            'userActiveLoanedBookIds',
            'userPersonalBookIds',
            'userPersonalNormalizedTitles',
            'totalBooks',
            'totalCopies',
            'availableCopiesCount'
        ));
    }

    public function show(Request $request, Book $book)
    {
        $book->loadMissing([
            'copies.library',
            'reviews.user',
            'reflections.user',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'category' => $book->category,
                'total_pages' => $book->total_pages,
                'cover_color' => $book->cover_color,
                'cover_image' => $book->cover_image ? asset($book->cover_image) : null,
                'description' => $book->description,
                'copies' => $book->copies->map(function ($copy) {
                    return [
                        'id' => $copy->id,
                        'copy_number' => $copy->copy_number,
                        'status' => $copy->status,
                        'library_name' => $copy->library->name ?? 'المكتبة الشخصية',
                        'shelf_location' => $copy->shelf_location,
                    ];
                }),
            ]);
        }

        $user = Auth::user();

        // Check if user has active loan for this book
        $hasActiveLoan = false;
        $activeLoan = null;
        if ($user) {
            $activeLoan = Loan::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'active'])
                ->whereIn('book_copy_id', $book->copies->pluck('id'))
                ->first();
            $hasActiveLoan = $activeLoan !== null;
        }

        // Available copy for loan
        $availableCopy = $book->copies
            ->where('status', 'available')
            ->first(fn ($copy) => ! $user || $copy->library?->user_id !== $user->id);

        // Check if current reading book
        $isCurrentBook = false;
        if ($user) {
            $isCurrentBook = UserCurrentBook::where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->where('is_active', true)
                ->exists();
        }

        // Related books in same category
        $relatedBooks = Book::where('category', $book->category)
            ->where('id', '!=', $book->id)
            ->withCount('copies')
            ->take(4)
            ->get();

        // Average rating
        $avgRating = $book->reviews->isNotEmpty() ? round($book->reviews->avg('rating'), 1) : 5.0;
        $ratingsCount = $book->reviews->count();

        // Check if book is already in user's personal library
        $inPersonalLibrary = false;
        if ($user) {
            $inPersonalLibrary = $user->hasBookInPersonalLibrary($book);
        }

        return view('pages.book', compact(
            'book',
            'hasActiveLoan',
            'activeLoan',
            'availableCopy',
            'isCurrentBook',
            'inPersonalLibrary',
            'relatedBooks',
            'avgRating',
            'ratingsCount'
        ));
    }
}
