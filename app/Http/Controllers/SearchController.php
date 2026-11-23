<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Community;
use App\Models\Library;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * البحث الموحد والشامل في أرجاء رواق المعرفة
     */
    public function index(Request $request)
    {
        $query = trim($request->input('q', ''));
        $type = $request->input('type', 'all'); // all, books, libraries, communities, readers

        $books = collect();
        $libraries = collect();
        $communities = collect();
        $readers = collect();

        if (! empty($query)) {
            // بحث الكتب
            if (in_array($type, ['all', 'books'])) {
                $bookLimit = $type === 'books' ? 30 : 6;
                $books = Book::where('title', 'like', "%{$query}%")
                    ->orWhere('author', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%")
                    ->orWhere('isbn', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->withCount('copies')
                    ->latest()
                    ->take($bookLimit)
                    ->get();
            }

            // بحث الخزائن والمكتبات
            if (in_array($type, ['all', 'libraries'])) {
                $libraryLimit = $type === 'libraries' ? 20 : 4;
                $libraries = Library::where('is_personal', false)
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%")
                            ->orWhere('type', 'like', "%{$query}%");
                    })
                    ->withCount(['copies', 'members'])
                    ->latest()
                    ->take($libraryLimit)
                    ->get();
            }

            // بحث المجتمعات والأندية
            if (in_array($type, ['all', 'communities'])) {
                $commLimit = $type === 'communities' ? 20 : 4;
                $communities = Community::visibleInSearch()
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%")
                            ->orWhere('category', 'like', "%{$query}%")
                            ->orWhere('city', 'like', "%{$query}%");
                    })
                    ->withCount('members')
                    ->latest()
                    ->take($commLimit)
                    ->get();
            }

            // بحث رفقاء القراءة والقراء
            if (in_array($type, ['all', 'readers'])) {
                $readerLimit = $type === 'readers' ? 20 : 6;
                $readers = User::where('name', 'like', "%{$query}%")
                    ->orWhere('bio', 'like', "%{$query}%")
                    ->take($readerLimit)
                    ->get();
            }
        } else {
            // عند فتح الصفحة بدون كلمة بحث، نقترح كتباً ومجتمعات وقراء نشطين
            $books = Book::withCount('copies')->latest()->take(6)->get();
            $libraries = Library::where('is_personal', false)->withCount('members')->take(4)->get();
            $communities = Community::visibleInSearch()->withCount('members')->latest()->take(4)->get();
            $readers = User::orderByDesc('points')->take(6)->get();
        }

        $totalResults = $books->count() + $libraries->count() + $communities->count() + $readers->count();

        return view('pages.search', compact(
            'query',
            'type',
            'books',
            'libraries',
            'communities',
            'readers',
            'totalResults'
        ));
    }
}
