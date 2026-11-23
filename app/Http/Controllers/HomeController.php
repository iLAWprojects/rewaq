<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Community;
use App\Models\Library;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function landing(): View
    {
        $booksCount = Book::count();
        $librariesCount = Library::count();
        $communitiesCount = Community::count();
        $readersCount = User::count();

        $featuredCommunities = Community::withCount('members')
            ->with('currentBook')
            ->take(3)
            ->get();

        $sliderBooks = Book::withCount('copies')
            ->orderBy('id')
            ->get()
            ->unique(fn (Book $book) => Book::normalizeTitle($book->title))
            ->values();

        $libraryBookIds = Auth::check()
            ? BookCopy::whereIn('library_id', Library::where('user_id', Auth::id())->pluck('id'))
                ->pluck('book_id')
                ->unique()
                ->all()
            : [];

        return view('pages.landing', compact(
            'booksCount',
            'librariesCount',
            'communitiesCount',
            'readersCount',
            'featuredCommunities',
            'sliderBooks',
            'libraryBookIds'
        ));
    }

    /**
     * أول صفحة بعد تسجيل الدخول هي صفحة المكتبات.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('libraries.index');
    }
}
