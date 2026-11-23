<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Book;
use App\Models\BookReview;
use App\Models\Challenge;
use App\Models\ReadingSeries;
use App\Models\ReadingSession;
use App\Models\Reflection;
use App\Models\SocialActivity;
use App\Models\UserCurrentBook;
use App\Models\UserNotification;
use App\Services\AntiGamingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReadingController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'journey'); // journey, shelves, sessions, reviews, quotes, series, achievements, diversity

        // 1. Current Active Book
        $currentBookRecord = UserCurrentBook::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('book')
            ->first();

        $user->syncReadingBooksToPersonalLibrary();

        $allBooks = Book::all();

        // كتبي: ما في رفوف القراءة أو في مكتبتي الشخصية، لتظهر أولًا في قائمة الاختيار
        $myBookIds = $user->getPersonalLibrary()->copies()->pluck('book_id')
            ->merge(UserCurrentBook::where('user_id', $user->id)->pluck('book_id'))
            ->unique()
            ->values()
            ->all();

        // فلتر الكتاب: يقتصر على كتبي ويُطبَّق على كل التبويبات
        $bookFilterId = (int) $request->query('book', 0);
        if (! in_array($bookFilterId, $myBookIds, true)) {
            $bookFilterId = 0;
        }
        $myBooks = $allBooks->whereIn('id', $myBookIds)->values();

        // مع فلتر الكتاب: بطاقة «بين يديك الآن» تعرض الكتاب المصفّى لا الكتاب النشط
        if ($bookFilterId) {
            $currentBookRecord = UserCurrentBook::where('user_id', $user->id)
                ->where('book_id', $bookFilterId)
                ->with('book')
                ->first();
        }

        $percentage = $currentBookRecord && $currentBookRecord->book && $currentBookRecord->book->total_pages > 0
            ? (int) round(($currentBookRecord->current_page / $currentBookRecord->book->total_pages) * 100)
            : 0;

        // 2. Reading Shelves / Book Statuses
        $userBooks = UserCurrentBook::where('user_id', $user->id)->with('book')
            ->when($bookFilterId, fn ($q) => $q->where('book_id', $bookFilterId))
            ->get();
        $currentlyReading = $userBooks->where('status', 'currently_reading');
        $wantToRead = $userBooks->where('status', 'want_to_read');
        $completedBooks = $userBooks->where('status', 'completed');
        $onHoldBooks = $userBooks->where('status', 'on_hold');
        $dnfBooks = $userBooks->where('status', 'did_not_finish');

        // 3. Reading Sessions & Minutes
        $sessions = ReadingSession::where('user_id', $user->id)->with('book')
            ->when($bookFilterId, fn ($q) => $q->where('book_id', $bookFilterId))
            ->latest()->get();
        $totalMinutes = $sessions->where('is_valid', true)->sum('minutes');
        $totalSessionsCount = $sessions->where('is_valid', true)->where('minutes', '>', 0)->count();

        // 4. Reflections & Quotes (Visible to user: user's private + all public)
        $reflections = Reflection::visibleTo($user)->with(['book', 'user'])
            ->when($bookFilterId, fn ($q) => $q->where('book_id', $bookFilterId))
            ->latest()->get();

        // 5. Book Reviews (General platform reviews for books)
        $reviews = BookReview::with(['book', 'user'])
            ->when($bookFilterId, fn ($q) => $q->where('book_id', $bookFilterId))
            ->latest()->get();

        // 6. Reading Series / Curated Tracks
        $series = ReadingSeries::with('books')
            ->when($bookFilterId, fn ($q) => $q->whereHas('books', fn ($b) => $b->where('books.id', $bookFilterId)))
            ->get();

        // 7. Badges & Achievements
        $earnedBadges = $user->badges;
        $allBadges = Badge::all();

        // 8. Anti-Tamper Reading Streak
        $calculatedStreak = $user->calculateReadingStreak();
        if ($calculatedStreak !== (int) $user->reading_streak && $calculatedStreak > 0) {
            $user->update(['reading_streak' => $calculatedStreak]);
        }
        $streak = $user->reading_streak ?: $calculatedStreak;

        // 9. Annual Reading Goals & Historical Archive
        $currentYear = (int) date('Y');
        $yearlyGoal = $user->getOrCreateCurrentYearlyGoal($currentYear);
        $historicalGoals = $user->yearlyGoals()->where('year', '!=', $currentYear)->get();

        $goal = $yearlyGoal->target_books ?: ($user->annual_goal ?: 24);
        $finished = $yearlyGoal->achieved_books ?: ($user->books_finished ?: 0);
        $goalPercentage = min(100, (int) round(($finished / max(1, $goal)) * 100));

        // 10. Knowledge Diversity Index (مؤشر التنوع المعرفي)
        $kdi = $user->calculateKnowledgeDiversityIndex();

        // 11. Gamification: Levels & Points Ledger
        $levelData = $user->level_data;
        $pointTransactions = $user->pointTransactions()->take(15)->get();
        $communityBadges = $user->communityBadges()->with('community')->get();

        return view('pages.reading', compact(
            'user',
            'tab',
            'currentBookRecord',
            'allBooks',
            'myBookIds',
            'myBooks',
            'bookFilterId',
            'percentage',
            'userBooks',
            'currentlyReading',
            'wantToRead',
            'completedBooks',
            'onHoldBooks',
            'dnfBooks',
            'sessions',
            'totalMinutes',
            'totalSessionsCount',
            'reflections',
            'reviews',
            'series',
            'earnedBadges',
            'allBadges',
            'streak',
            'goal',
            'finished',
            'goalPercentage',
            'yearlyGoal',
            'historicalGoals',
            'kdi',
            'levelData',
            'pointTransactions',
            'communityBadges'
        ));
    }

    public function storeSession(Request $request)
    {
        $user = Auth::user();

        $bookId = $request->input('book_id');
        $currentBookRecord = null;

        if ($bookId) {
            $currentBookRecord = UserCurrentBook::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->with('book')
                ->first();
        }

        if (! $currentBookRecord) {
            $currentBookRecord = UserCurrentBook::where('user_id', $user->id)
                ->where('is_active', true)
                ->with('book')
                ->firstOrFail();
        }

        $totalPages = $currentBookRecord->book->total_pages ?: 1;
        $startPage = $request->input('start_page', $currentBookRecord->current_page);

        $validated = $request->validate([
            'page' => 'required|integer|min:1|max:'.$totalPages,
            'minutes' => 'required|integer|min:1|max:1440',
            'device' => 'nullable|string|max:60',
            'note' => 'nullable|string|max:1000',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date',
        ]);

        $newPage = $validated['page'];
        $pagesRead = max(1, $newPage - $startPage);
        $minutes = $validated['minutes'];
        $device = $validated['device'] ?? 'كتاب ورقي';

        $endedAt = ! empty($validated['ended_at']) ? Carbon::parse($validated['ended_at']) : Carbon::now();
        $startedAt = ! empty($validated['started_at']) ? Carbon::parse($validated['started_at']) : $endedAt->copy()->subMinutes($minutes);

        // فحص منطقية السرعة: الجلسة غير المنطقية تُحفظ موسومة «غير موثّقة» ولا تؤثر في أي شيء آخر
        $paceViolation = app(AntiGamingService::class)->paceViolation($pagesRead, $minutes);

        // 1. Create reading session record
        $session = ReadingSession::create([
            'is_valid' => $paceViolation === null,
            'invalid_reason' => $paceViolation,
            'user_id' => $user->id,
            'book_id' => $currentBookRecord->book_id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'start_page' => $startPage,
            'end_page' => $newPage,
            'current_page' => $newPage,
            'pages_read' => $pagesRead,
            'minutes' => $minutes,
            'device' => $device,
            'note' => $validated['note'] ?? null,
        ]);

        if ($paceViolation !== null) {
            UserNotification::create([
                'user_id' => $user->id,
                'title' => 'جلسة قراءة غير موثّقة',
                'body' => $paceViolation.' حُفظت الجلسة في سجلك لكنها لم تُحتسب في التحديات أو النقاط أو التتابع.',
                'icon' => 'alert-triangle',
                'link' => route('reading.index'),
                'type' => 'system',
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'unverified' => true, 'message' => $paceViolation], 200);
            }

            return redirect()->route('reading.index')->with('warning', $paceViolation.' حُفظت الجلسة غير موثّقة ولم تُحتسب.');
        }

        // 2. Anti-tamper Streak recalculation & verification
        if ($session->isEligibleForStreak()) {
            $today = Carbon::today();
            if ($user->last_read_date) {
                $last = Carbon::parse($user->last_read_date);
                if ($last->isYesterday()) {
                    $user->increment('reading_streak');
                } elseif (! $last->isToday()) {
                    $user->reading_streak = 1;
                }
            } else {
                $user->reading_streak = 1;
            }
            $user->last_read_date = $today;
        }

        // 3. Update Book progress & handle completion
        if ($newPage >= $totalPages) {
            $currentBookRecord->updateProgress($newPage, 'completed');
            $user->increment('books_finished');
            $user->addPoints(100, 'book_finished', "إتمام قراءة كتاب «{$currentBookRecord->book->title}»", Book::class, $currentBookRecord->book_id);
        } else {
            $currentBookRecord->updateProgress($newPage, 'currently_reading');
            $user->addPoints(15, 'reading_session', "جلسة قراءة في «{$currentBookRecord->book->title}» ({$pagesRead} صفحة)", Book::class, $currentBookRecord->book_id);
        }

        // 4. Sync Yearly Goal achievements and preserve historical archive
        $yearlyGoal = $user->getOrCreateCurrentYearlyGoal();
        $yearlyGoal->syncAchievements();

        // 5. Automatically sync progress with active reading challenges (Individual & Team vs Team)
        $user->syncReadingToChallenges($pagesRead, $currentBookRecord->book, $session);
        if ($newPage >= $totalPages) {
            $user->syncBookCompletedToChallenges($currentBookRecord->book, $session);
        }

        // 6. Social activity log
        SocialActivity::log(
            $user,
            'reading_session',
            "أتمّ جلسة قراءة في «{$currentBookRecord->book->title}»",
            "قرأ {$pagesRead} صفحة خلال {$minutes} دقيقة عبر ({$device}) ووصل إلى صفحة {$newPage}.",
            route('books.show', $currentBookRecord->book_id)
        );

        $msg = 'حُفظت جلسة القراءة وتحدّث تقدّمك والهدف السنوي ورصيد التحديات بنجاح.';
        $completions = Challenge::pullCompletions();
        if ($completions) {
            $msg .= ' '.Challenge::completionMessage($completions);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'current_page' => $newPage,
                'total_pages' => $totalPages,
                'percentage' => $currentBookRecord->percentage(),
                'points' => $user->points,
                'streak' => $user->reading_streak,
                'device' => $device,
            ]);
        }

        return redirect()->route('reading.index')->with('success', $msg);
    }

    public function updateBookStatus(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'book_id' => 'required_without:new_title|nullable|exists:books,id',
            'new_title' => 'required_without:book_id|nullable|string|max:255',
            'new_author' => 'nullable|string|max:255',
            'new_pages' => 'nullable|integer|min:1|max:5000',
            'new_category' => 'nullable|string|max:50',
            'status' => 'required|in:want_to_read,currently_reading,completed,on_hold,did_not_finish',
            'current_page' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'finish_date' => 'nullable|date',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (! empty($validated['book_id'])) {
            $book = Book::findOrFail($validated['book_id']);
        } else {
            // كتاب غير موجود في الفهرس: نعيد استخدام المطابق إن وُجد، وإلا نُنشئه
            $title = trim($validated['new_title']);
            $author = trim($validated['new_author'] ?? '') ?: 'غير معروف';
            $book = Book::findMatching($title, $author) ?? Book::create([
                'title' => $title,
                'author' => $author,
                'category' => $validated['new_category'] ?? 'أدب',
                'total_pages' => $validated['new_pages'] ?? 200,
                'cover_color' => ['#315749', '#a5654d', '#4b6068', '#655c45', '#245E50', '#795B44'][array_rand(['#315749', '#a5654d', '#4b6068', '#655c45', '#245E50', '#795B44'])],
            ]);
        }

        $record = UserCurrentBook::firstOrNew([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $status = $validated['status'];
        $page = $validated['current_page'] ?? ($record->current_page ?? 0);
        $totalPages = $book->total_pages ?: 1;

        if ($status === 'completed') {
            $page = $totalPages;
        }

        $record->status = $status;
        $record->current_page = $page;
        $record->progress_percentage = min(100, (int) round(($page / $totalPages) * 100));

        if (! empty($validated['start_date'])) {
            $record->start_date = Carbon::parse($validated['start_date']);
        } elseif ($status === 'currently_reading' && ! $record->start_date) {
            $record->start_date = Carbon::today();
        }

        if (! empty($validated['finish_date'])) {
            $record->finish_date = Carbon::parse($validated['finish_date']);
        } elseif ($status === 'completed' && ! $record->finish_date) {
            $record->finish_date = Carbon::today();
        }

        if (isset($validated['rating'])) {
            $record->rating = $validated['rating'];
        }

        if (isset($validated['notes'])) {
            $record->notes = $validated['notes'];
        }

        if ($status === 'currently_reading') {
            UserCurrentBook::where('user_id', $user->id)->update(['is_active' => false]);
            $record->is_active = true;
        }

        $record->save();

        // Ensure book is in user's personal library
        $user->addBookToPersonalLibrary($book);

        // Update yearly goal achievements
        $yearlyGoal = $user->getOrCreateCurrentYearlyGoal();
        $yearlyGoal->syncAchievements();

        // Sync completed book with active challenges
        if ($status === 'completed') {
            $user->syncBookCompletedToChallenges($book);
        }

        $msg = "تم تحديث حالة كتاب «{$book->title}» إلى: {$record->status_label}";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'status' => $record->status,
                'status_label' => $record->status_label,
                'progress_percentage' => $record->progress_percentage,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function storeReflection(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
            'book_id' => 'nullable|exists:books,id',
            'page_number' => 'nullable|integer|min:1',
            'privacy' => 'nullable|in:public,private',
            'is_public' => 'nullable|boolean',
        ]);

        $currentBook = UserCurrentBook::where('user_id', $user->id)->where('is_active', true)->first();
        $bookId = $validated['book_id'] ?? ($currentBook ? $currentBook->book_id : null);
        $pageNumber = $validated['page_number'] ?? ($currentBook ? $currentBook->current_page : null);

        $privacy = $validated['privacy'] ?? ($request->boolean('is_public', true) ? 'public' : 'private');
        $isPublic = ($privacy === 'public');

        $reflection = Reflection::create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'content' => trim($validated['note']),
            'page_number' => $pageNumber,
            'privacy' => $privacy,
            'is_public' => $isPublic,
        ]);

        // Record a 0-minute reflection session marker in reading timeline
        ReadingSession::create([
            'user_id' => $user->id,
            'book_id' => $bookId ?? 1,
            'current_page' => $pageNumber ?? 0,
            'pages_read' => 0,
            'minutes' => 0,
            'device' => 'اقتباس / تأمل',
            'note' => $validated['note'],
        ]);

        $user->addPoints(10, 'reflection', 'تدوين اقتباس أو تأمل معرفي', Reflection::class, $reflection->id);

        // Document in Social Feed ONLY if public/shared
        if ($isPublic) {
            SocialActivity::log(
                $user,
                'reflection_posted',
                'دوّن اقتباساً وتأملاً عاماً ✦',
                $validated['note'],
                route('reading.index', ['tab' => 'reflections'])
            );
        }

        $msg = $isPublic
            ? 'حُفظ الاقتباس ونُشر في الرواق بنجاح ✦'
            : 'حُفظ الاقتباس في سجلك الخاص وسيكون مرئياً لك فقط 🔒';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'privacy' => $privacy,
            ]);
        }

        return redirect()->route('reading.index', ['tab' => 'reflections'])->with('success', $msg);
    }

    public function storeReview(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'content' => 'required|string|max:3000',
            'is_spoiler' => 'nullable|boolean',
        ]);

        $review = BookReview::create([
            'book_id' => $validated['book_id'],
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ? trim($validated['title']) : null,
            'content' => trim($validated['content']),
            'is_spoiler' => $request->boolean('is_spoiler'),
        ]);

        $book = Book::find($validated['book_id']);
        $user->addPoints(25, 'review', 'مراجعة نقدية لكتاب «'.($book ? $book->title : 'كتاب').'»', BookReview::class, $review->id);
        SocialActivity::log(
            $user,
            'review_posted',
            'نشر مراجعة نقدية عامة لكتاب «'.($book ? $book->title : 'كتاب').'»',
            trim($validated['content']),
            route('books.show', $validated['book_id'])
        );

        return redirect()->route('reading.index', ['tab' => 'reviews'])
            ->with('success', 'نُشرت مراجعتك وتقييمك النقدي العام للكتاب بنجاح!');
    }

    public function likeReview(BookReview $review)
    {
        $review->increment('likes_count');

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'likes' => $review->likes_count]);
        }

        return back()->with('success', 'تم تسجيل إعجابك بالمراجعة.');
    }

    public function switchCurrentBook(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        UserCurrentBook::where('user_id', $user->id)->update(['is_active' => false]);

        $record = UserCurrentBook::where('user_id', $user->id)->where('book_id', $validated['book_id'])->first();
        if ($record) {
            $record->update(['is_active' => true, 'status' => 'currently_reading']);
        } else {
            UserCurrentBook::create([
                'user_id' => $user->id,
                'book_id' => $validated['book_id'],
                'current_page' => 1,
                'status' => 'currently_reading',
                'progress_percentage' => 0,
                'start_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        $targetBook = Book::find($validated['book_id']);
        if ($targetBook) {
            $user->addBookToPersonalLibrary($targetBook);
        }

        if ($request->boolean('stay')) {
            return back()->with('success', 'تم تعيين الكتاب كقراءتك النشطة الحالية.');
        }

        return redirect()->route('reading.index')->with('success', 'تم تعيين الكتاب كقراءتك النشطة الحالية.');
    }

    public function updateGoal(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'goal' => 'required|integer|min:1|max:1000',
            'target_pages' => 'nullable|integer|min:1|max:100000',
            'target_hours' => 'nullable|integer|min:1|max:5000',
        ]);

        $targetBooks = $validated['goal'];
        $targetPages = $validated['target_pages'] ?? null;
        $targetHours = $validated['target_hours'] ?? null;

        // 1. Update user profile target
        $user->update([
            'annual_goal' => $targetBooks,
            'annual_goal_pages' => $targetPages,
            'annual_goal_hours' => $targetHours,
        ]);

        // 2. Preserve annual record in yearly_reading_goals without resetting historical achievements
        $currentYear = (int) date('Y');
        $yearlyGoal = $user->getOrCreateCurrentYearlyGoal($currentYear);
        $yearlyGoal->update([
            'target_books' => $targetBooks,
            'target_pages' => $targetPages ?: $yearlyGoal->target_pages,
            'target_hours' => $targetHours ?: $yearlyGoal->target_hours,
        ]);
        $yearlyGoal->syncAchievements();

        $msg = 'تم تحديث هدفك السنوي مع حفظ كامل إنجازاتك السابقة في السجل المعرفي.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'goal' => $yearlyGoal->target_books,
                'achieved_books' => $yearlyGoal->achieved_books,
                'percentage' => $yearlyGoal->percentage_books,
            ]);
        }

        return back()->with('success', $msg);
    }
}
