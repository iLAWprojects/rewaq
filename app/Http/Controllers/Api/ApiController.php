<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\ReadingSession;
use App\Models\User;
use App\Models\UserCurrentBook;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    /**
     * المستخدم المصادَق عليه فقط. حساب الديمو بديل في البيئة المحلية والاختبار، وفي غيرها 401.
     */
    private function resolveUser(Request $request): User
    {
        $user = $request->user()
            ?? (app()->environment(['local', 'testing']) ? User::where('email', 'demo@riwaq.test')->first() : null);

        abort_unless($user, 401, 'Unauthenticated.');

        return $user;
    }

    public function profile(Request $request)
    {
        $user = $this->resolveUser($request);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'annual_goal' => $user->annual_goal,
                'books_finished' => $user->books_finished,
                'points' => $user->points,
                'level' => $user->level,
                'quiet_hours_enabled' => $user->quiet_hours_enabled,
                'quiet_hours_start' => $user->quiet_hours_start,
                'quiet_hours_end' => $user->quiet_hours_end,
            ],
        ]);
    }

    public function books(Request $request)
    {
        $category = $request->query('category');
        $query = $request->query('q');

        $booksQuery = Book::with('copies');

        if ($category && $category !== 'الكل') {
            $booksQuery->where('category', $category);
        }

        if ($query) {
            $booksQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('author', 'like', "%{$query}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $booksQuery->get(),
        ]);
    }

    public function currentBook(Request $request)
    {
        $user = $this->resolveUser($request);
        $record = UserCurrentBook::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('book')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'current_page' => $record ? $record->current_page : 0,
                'total_pages' => $record ? $record->book->total_pages : 0,
                'percentage' => $record ? $record->percentage() : 0,
                'book' => $record ? $record->book : null,
            ],
        ]);
    }

    public function communities()
    {
        $communities = Community::with(['currentBook', 'activePoll.options'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $communities,
        ]);
    }

    public function challenges()
    {
        $challenges = Challenge::where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $challenges,
        ]);
    }

    public function readingSessions(Request $request)
    {
        $user = $this->resolveUser($request);
        $sessions = ReadingSession::where('user_id', $user->id)->with('book')->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $sessions,
        ]);
    }
}
