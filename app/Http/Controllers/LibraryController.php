<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookWaitlist;
use App\Models\Library;
use App\Models\LibraryMember;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserCurrentBook;
use App\Models\UserNotification;
use App\Services\BookLookup\Isbn;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'books'); // books, loans, waitlists, members, all_libraries
        $category = $request->query('category', 'الكل');
        $conditionFilter = $request->query('condition', 'الكل');
        $query = $request->query('q', '');
        $requestedLibId = $request->query('library_id');

        // 1. Ensure user has a default personal library & sync reading books
        $personalLibrary = $user->getPersonalLibrary();
        $user->syncReadingBooksToPersonalLibrary();

        // 2. Fetch all accessible libraries for the current user
        $ownedLibraries = Library::where('user_id', $user->id)->get();
        $joinedLibraries = Library::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->get();

        // «مكتباتي» = ما أملكه أو انضممت إليه فقط؛ مكتبات الآخرين العامة لا تُدرج هنا تلقائيًا
        $allAccessibleLibraries = $ownedLibraries
            ->merge($joinedLibraries)
            ->unique('id')
            ->values();

        // 3. Select active library
        $library = $personalLibrary;
        if ($requestedLibId) {
            $found = Library::find($requestedLibId);
            if ($found && ($user->isSuperAdmin() || $found->user_id === $user->id || $found->members()->where('user_id', $user->id)->exists() || $found->type === 'public')) {
                $library = $found;
            }
        }

        // 4. Determine user's role and capabilities in this library
        $userRole = $library->userRole($user) ?? ($library->type === 'public' ? 'viewer' : 'member');
        $canManage = $library->canManage($user);
        $canBorrow = $library->canBorrow($user);

        // 5. Query physical copies in this library
        $copiesQuery = BookCopy::where('library_id', $library->id)->with('book', 'activeLoan.user');

        if ($category && $category !== 'الكل') {
            $copiesQuery->whereHas('book', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }

        if ($conditionFilter && $conditionFilter !== 'الكل') {
            $copiesQuery->where('condition', $conditionFilter);
        }

        if ($query) {
            $copiesQuery->whereHas('book', function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('author', 'like', "%{$query}%");
            });
        }

        $copies = $copiesQuery->get();
        $totalTitles = $copies->pluck('book_id')->unique()->count();
        $totalCopies = $copies->count();
        $availableCopiesCount = $copies->where('status', 'available')->count();
        $borrowedCopiesCount = $copies->where('status', 'borrowed')->count();

        // 6. Query loans for this library
        if (in_array($tab, ['loans', 'waitlists']) && ! SystemSetting::isLendingEnabled()) {
            return redirect()->route('libraries.index', ['library_id' => $library->id]);
        }

        if ($tab === 'loans') {
            Loan::checkAndSendDueNotifications();
        }

        $loans = Loan::whereHas('copy', function ($q) use ($library) {
            $q->where('library_id', $library->id);
        })->orWhere(function ($q) use ($user, $library) {
            // Include user's own loans in this library
            $q->where('user_id', $user->id)
                ->whereHas('copy', function ($sq) use ($library) {
                    $sq->where('library_id', $library->id);
                });
        })->with(['copy.book', 'user', 'copy.library', 'handoverInspector', 'returnInspector', 'closedBy'])->latest()->get();

        $activeLoansCount = $loans->whereIn('status', ['pending', 'approved', 'active'])->count();
        $overdueLoansCount = $loans->filter(fn ($l) => $l->isOverdue())->count();

        // 7. Query Waitlists for this library
        $waitlists = BookWaitlist::where('library_id', $library->id)
            ->with(['book', 'user'])
            ->latest()
            ->get();

        $userActiveWaitlists = BookWaitlist::where('library_id', $library->id)
            ->where('user_id', $user->id)
            ->where('status', 'waiting')
            ->pluck('book_id')
            ->toArray();

        $userNotifiedWaitlists = BookWaitlist::where('library_id', $library->id)
            ->where('user_id', $user->id)
            ->where('status', 'notified')
            ->pluck('book_id')
            ->toArray();

        // 8. Query Members of this library
        $members = $library->members()->with('user')->get();
        $existingMemberUserIds = $members->pluck('user_id')->toArray();
        $availableUsersToInvite = User::whereNotIn('id', $existingMemberUserIds)->take(12)->get();

        $categories = ['الكل', 'أدب', 'سيرة', 'تاريخ', 'فكر'];
        $conditionsList = ['الكل', 'mint', 'good', 'fair', 'damaged'];

        // Get all book IDs the user currently has an active, pending, or approved loan for (prevent duplicate borrowing)
        $userActiveLoanedBookIds = Loan::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved', 'active', 'overdue'])
            ->with('copy')
            ->get()
            ->pluck('copy.book_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return view('pages.libraries', compact(
            'library',
            'allAccessibleLibraries',
            'userRole',
            'canManage',
            'canBorrow',
            'copies',
            'loans',
            'waitlists',
            'members',
            'availableUsersToInvite',
            'tab',
            'category',
            'conditionFilter',
            'query',
            'totalTitles',
            'totalCopies',
            'availableCopiesCount',
            'borrowedCopiesCount',
            'activeLoansCount',
            'overdueLoansCount',
            'categories',
            'conditionsList',
            'userActiveWaitlists',
            'userNotifiedWaitlists',
            'userActiveLoanedBookIds'
        ));
    }

    public function storeLibrary(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:personal,public,club,family',
            'cover_color' => 'nullable|string|max:20',
        ]);

        $code = 'LIB-'.strtoupper(Str::random(6));

        $library = Library::create([
            'user_id' => $user->id,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ? trim($validated['description']) : null,
            'type' => $validated['type'],
            'cover_color' => $validated['cover_color'] ?? '#173D36',
            'is_personal' => false,
            'code' => $code,
        ]);

        LibraryMember::create([
            'library_id' => $library->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $user->increment('points', 25);

        return redirect()->route('libraries.index', ['library_id' => $library->id])
            ->with('success', "تم تأسيس مكتبة «{$library->name}» بنجاح! كود الانضمام: {$library->code}");
    }

    public function joinByCode(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $code = strtoupper(trim($validated['code']));
        $library = Library::where('code', $code)->first();

        if (! $library) {
            return back()->with('error', 'كود المكتبة غير صحيح أو غير مسجل.');
        }

        $existing = LibraryMember::where('library_id', $library->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return redirect()->route('libraries.index', ['library_id' => $library->id])
                ->with('info', "أنت بالفعل عضو في مكتبة «{$library->name}»!");
        }

        LibraryMember::create([
            'library_id' => $library->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $user->increment('points', 15);

        // Notify Library Owner
        UserNotification::create([
            'user_id' => $library->user_id,
            'title' => 'عضو جديد انضم للمكتبة ✦',
            'body' => "انضم «{$user->name}» إلى مكتبة «{$library->name}» عبر كود الدعوة.",
            'icon' => 'user-plus',
            'link' => route('libraries.index', ['library_id' => $library->id, 'tab' => 'members']),
            'type' => 'library',
        ]);

        return redirect()->route('libraries.index', ['library_id' => $library->id])
            ->with('success', "أهلاً بك! انضممت بنجاح إلى مكتبة «{$library->name}».");
    }

    public function addMember(Request $request, Library $library)
    {
        $user = Auth::user();
        if (! $library->canManage($user)) {
            return back()->with('error', 'غير مصرح لك بإدارة أعضاء هذه المكتبة.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:librarian,member,viewer',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);

        $member = LibraryMember::updateOrCreate(
            ['library_id' => $library->id, 'user_id' => $targetUser->id],
            ['role' => $validated['role'], 'joined_at' => now()]
        );

        $roleArabic = match ($validated['role']) {
            'librarian' => 'أمين مكتبة 🗝️',
            'member' => 'عضو مستعير 📖',
            'viewer' => 'قارئ ومطّلع 👁️',
            default => 'عضو',
        };

        UserNotification::create([
            'user_id' => $targetUser->id,
            'title' => 'تمت إضافتك إلى مكتبة ✦',
            'body' => "أضافك «{$user->name}» إلى مكتبة «{$library->name}» بدور {$roleArabic}.",
            'icon' => 'library-big',
            'link' => route('libraries.index', ['library_id' => $library->id]),
            'type' => 'library',
        ]);

        return back()->with('success', "تم تعيين «{$targetUser->name}» كـ {$roleArabic} في المكتبة.");
    }

    public function updateMemberRole(Request $request, Library $library, LibraryMember $member)
    {
        $user = Auth::user();
        Gate::authorize('manageMembers', $library);

        if ($member->user_id === $library->user_id && ! $user->isSuperAdmin()) {
            return back()->with('error', 'لا يمكن تعديل دور مؤسس ومالك المكتبة الرئيسي.');
        }

        $validated = $request->validate([
            'role' => 'required|in:librarian,member,viewer',
        ]);

        $oldRole = $member->role;
        $member->update(['role' => $validated['role']]);

        // Audit Log
        AuditLog::record(
            $user,
            'change_library_member_role',
            $library,
            "تم تعديل دور العضو «{$member->user->name}» في مكتبة «{$library->name}» من «{$oldRole}» إلى «{$validated['role']}».",
            ['role' => $oldRole],
            ['role' => $validated['role']]
        );

        return back()->with('success', "تم تحديث دور «{$member->user->name}» بنجاح.");
    }

    public function removeMember(Library $library, LibraryMember $member)
    {
        $user = Auth::user();
        $isSelf = $user && $user->id === $member->user_id;

        if ($isSelf) {
            Gate::authorize('leave', $library);
        } else {
            Gate::authorize('manageMembers', $library);
        }

        if ($library->isOnlyOwner($member->user)) {
            return back()->with('error', 'يجب تعيين مالك جديد قبل مغادرة المكتبة أو إزالة المالك الوحيد.');
        }

        $name = $member->user->name ?? 'العضو';
        $member->delete();

        // Audit Log
        AuditLog::record(
            $user,
            'remove_library_member',
            $library,
            "تمت إزالة «{$name}» من عضوية مكتبة «{$library->name}».",
            ['user_id' => $member->user_id]
        );

        return back()->with('success', "تمت إزالة «{$name}» من عضوية المكتبة.");
    }

    public function transferOwnership(Request $request, Library $library)
    {
        $user = Auth::user();
        Gate::authorize('transferOwnership', $library);

        $validated = $request->validate([
            'new_owner_id' => 'required|exists:users,id',
            'confirm' => 'required|accepted',
        ], [
            'confirm.accepted' => 'يجب تأكيد الموافقة الصريحة على نقل ملكية المكتبة.',
        ]);

        $newOwner = User::findOrFail($validated['new_owner_id']);
        if ($newOwner->id === $library->user_id) {
            return back()->with('info', 'المستخدم المحدد هو المالك الحالي بالفعل.');
        }

        $library->transferOwnership($newOwner, $user);

        return back()->with('success', "تم نقل ملكية المكتبة بنجاح إلى القارئ «{$newOwner->name}» وتوثيق العملية في سجل التدقيق والرقابة.");
    }

    public function searchExisting(Request $request)
    {
        $query = trim($request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['books' => []]);
        }

        $books = Book::where('title', 'like', "%{$query}%")
            ->orWhere('author', 'like', "%{$query}%")
            ->take(6)
            ->get(['id', 'title', 'author', 'category', 'total_pages', 'cover_color', 'cover_image', 'description']);

        return response()->json([
            'books' => $books,
            'count' => $books->count(),
        ]);
    }

    private function isTrustedCoverUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        foreach (['openlibrary.org', 'archive.org', 'books.google.com', 'googleusercontent.com', 'media-amazon.com', 'ssl-images-amazon.com'] as $trusted) {
            if ($host === $trusted || str_ends_with($host, '.'.$trusted)) {
                return true;
            }
        }

        return false;
    }

    public function storeBook(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'publisher' => 'nullable|string|max:150',
            'isbn' => 'nullable|string|max:30',
            'cover_url' => 'nullable|url|max:500',
            'language' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:3000',
            'total_pages' => 'nullable|integer|min:1',
            'shelf_location' => 'nullable|string|max:100',
            'condition' => 'nullable|in:mint,good,fair,damaged',
            'condition_notes' => 'nullable|string|max:500',
            'library_id' => 'nullable|integer|exists:libraries,id',
            'existing_book_id' => 'nullable|integer|exists:books,id',
            'cover_image_enhanced' => 'nullable|string',
            'cover_image_file' => 'nullable|image|max:10240',
        ]);

        // ISBN اختياري: يُتحقق منه ويُوحَّد إلى ISBN-13
        $isbn = null;
        if (! empty($validated['isbn'])) {
            $isbn = Isbn::normalize($validated['isbn']);
            if (! $isbn) {
                return back()->withInput()->withErrors(['isbn' => 'رقم ISBN غير صحيح.']);
            }
            $isbn = Isbn::to13($isbn);
        }

        // Process CamScanner enhanced cover image (Base64) or direct file
        $coverPath = null;
        if (! empty($request->input('cover_image_enhanced')) && str_starts_with($request->input('cover_image_enhanced'), 'data:image/')) {
            $dataUrl = $request->input('cover_image_enhanced');
            if (preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $typeMatches)) {
                $rawBase64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
                $ext = strtolower($typeMatches[1]);
                if (! in_array($ext, ['jpeg', 'jpg', 'png', 'webp'])) {
                    $ext = 'jpg';
                }
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                $decoded = base64_decode($rawBase64);
                if ($decoded !== false) {
                    $dir = public_path('uploads/covers');
                    if (! file_exists($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $filename = 'cover_'.uniqid().'_'.time().'.'.$ext;
                    file_put_contents($dir.'/'.$filename, $decoded);
                    $coverPath = 'uploads/covers/'.$filename;
                }
            }
        } elseif ($request->hasFile('cover_image_file')) {
            $file = $request->file('cover_image_file');
            $dir = public_path('uploads/covers');
            if (! file_exists($dir)) {
                @mkdir($dir, 0755, true);
            }
            $filename = 'cover_'.uniqid().'_'.time().'.'.$file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $coverPath = 'uploads/covers/'.$filename;
        }

        // غلاف جاهز من نتيجة البحث بالـ ISBN (من مواقع الأغلفة الموثوقة فقط)
        if (! $coverPath && ! empty($validated['cover_url']) && $this->isTrustedCoverUrl($validated['cover_url'])) {
            $coverPath = $validated['cover_url'];
        }

        // Determine target library (specified or user's personal)
        $targetLibraryId = $validated['library_id'] ?? null;
        $library = null;
        if ($targetLibraryId) {
            $candidate = Library::find($targetLibraryId);
            if ($candidate && ($candidate->canManage($user) || $candidate->user_id === $user->id)) {
                $library = $candidate;
            }
        }

        if (! $library) {
            $library = Library::where('user_id', $user->id)->first() ?? Library::create([
                'user_id' => $user->id,
                'name' => 'المكتبة الشخصية',
                'is_personal' => true,
                'code' => 'LIB-'.strtoupper(Str::random(6)),
                'type' => 'personal',
            ]);
        }

        $book = null;
        $isExisting = false;

        // 1. If explicit existing_book_id was provided from autocomplete
        if (! empty($validated['existing_book_id'])) {
            $book = Book::find($validated['existing_book_id']);
            if ($book) {
                $isExisting = true;
            }
        }

        // 1b. نفس الـ ISBN موجود في الفهرس: نستخدم الكتاب نفسه دون تكرار
        if (! $book && $isbn) {
            $book = Book::whereIn('isbn', Isbn::variants($isbn))->first();
            if ($book) {
                $isExisting = true;
            }
        }

        // 2. If not selected explicitly, check if exact, trimmed, or normalized title/author already matches in DB
        if (! $book) {
            $book = Book::findMatching($validated['title'], $validated['author'] ?? null);

            if ($book) {
                $isExisting = true;
            }
        }

        // المكتبة الشخصية: لا تكرار لنفس الكتاب (النسخ المتعددة مخصّصة للمكتبات المشتركة لأغراض الإعارة)
        if ($book && $library->is_personal && BookCopy::where('library_id', $library->id)->where('book_id', $book->id)->exists()) {
            $dupMsg = "«{$book->title}» موجود في مكتبتك الشخصية بالفعل. لإضافة نسخة ثانية استخدم مكتبة مشتركة أو عامة.";

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $dupMsg], 422);
            }

            return back()->withInput()->with('warning', $dupMsg);
        }

        // 3. If book is new, create it in global catalog
        if (! $book) {
            $colors = ['#315749', '#a5654d', '#4b6068', '#655c45', '#245E50', '#795B44'];
            $randomColor = $colors[array_rand($colors)];

            $book = Book::create([
                'title' => trim($validated['title']),
                'author' => trim($validated['author']),
                'publisher' => ! empty($validated['publisher']) ? trim($validated['publisher']) : null,
                'isbn' => $isbn,
                'language' => ! empty($validated['language']) ? trim($validated['language']) : 'العربية',
                'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
                'category' => $validated['category'],
                'total_pages' => $validated['total_pages'] ?? 200,
                'cover_color' => $randomColor,
                'cover_image' => $coverPath,
            ]);
        } else {
            // Update book cover if new photo was uploaded and existing had none
            if ($coverPath && empty($book->cover_image)) {
                $book->update(['cover_image' => $coverPath]);
            }
            if (empty($book->publisher) && ! empty($validated['publisher'])) {
                $book->update(['publisher' => trim($validated['publisher'])]);
            }
            if (empty($book->isbn) && $isbn) {
                $book->update(['isbn' => $isbn]);
            }
        }

        // 4. Create physical copy for the target library
        $nextCopyNum = BookCopy::where('book_id', $book->id)->where('library_id', $library->id)->count() + 1;

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'library_id' => $library->id,
            'copy_number' => (string) $nextCopyNum,
            'status' => 'available',
            'condition' => $validated['condition'] ?? 'good',
            'condition_notes' => ! empty($validated['condition_notes']) ? trim($validated['condition_notes']) : null,
            'shelf_location' => ! empty($validated['shelf_location']) ? $validated['shelf_location'] : 'الرف الأول',
            'barcode' => 'RWQ-'.rand(1000, 9999),
            'cover_image' => $coverPath ?? $book->cover_image,
        ]);

        // Reward points
        $user->increment('points', 20);

        $message = $isExisting
            ? "تم اختيار «{$book->title}» من الفهرس وإضافة نسخة مادية جديدة لمكتبة «{$library->name}» بنجاح!"
            : "أُضيف الكتاب الجديد «{$book->title}» ونسخته المادية إلى مكتبة «{$library->name}» بنجاح!";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'book' => $book,
                'is_existing' => $isExisting,
                'copy' => $copy,
            ]);
        }

        $redirectParams = ['tab' => 'books'];
        if (! $library->is_personal) {
            $redirectParams['library_id'] = $library->id;
        }

        return redirect()->route('libraries.index', $redirectParams)->with('success', $message);
    }

    /**
     * إضافة كتاب إلى المكتبة الشخصية وربطه بسجل القراءة
     */
    public function addToPersonal(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'unauthenticated', 'message' => 'يرجى تسجيل الدخول أولاً.'], 401);
            }

            return redirect()->route('login')->with('warning', 'يرجى تسجيل الدخول أولاً لإضافة الكتاب إلى مكتبتك.');
        }

        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $book = Book::findOrFail($validated['book_id']);

        // 1. إضافة نسخة إلى المكتبة الشخصية للمستخدم
        $copy = $user->addBookToPersonalLibrary($book);

        // 2. ضمان وجود الكتاب في سجل القراءة (UserCurrentBook)
        UserCurrentBook::firstOrCreate(
            [
                'user_id' => $user->id,
                'book_id' => $book->id,
            ],
            [
                'status' => 'want_to_read',
                'current_page' => 0,
                'progress_percentage' => 0,
                'start_date' => null,
                'is_active' => false,
            ]
        );

        $user->increment('points', 10);

        AuditLog::record(
            $user,
            'add_book_to_personal_library',
            $book,
            "أضاف القارئ «{$user->name}» كتاب «{$book->title}» إلى مكتبته الشخصية وسجل قراءته."
        );

        $successMsg = "تمت إضافة كتاب «{$book->title}» إلى مكتبتك الشخصية وسجل قراءتك بنجاح ✦";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'book_id' => $book->id,
                'in_library' => true,
                'library_id' => $copy->library_id,
            ]);
        }

        return back()->with('success', $successMsg);
    }

    public function updateCopyCondition(Request $request, BookCopy $copy)
    {
        $user = Auth::user();
        Gate::authorize('manageBooks', $copy->library);

        $validated = $request->validate([
            'condition' => 'required|in:mint,good,fair,damaged',
            'status' => 'required|in:available,borrowed,reserved,maintenance,lost',
            'shelf_location' => 'nullable|string|max:100',
            'condition_notes' => 'nullable|string|max:500',
        ]);

        $copy->update([
            'condition' => $validated['condition'],
            'status' => $validated['status'],
            'shelf_location' => $validated['shelf_location'] ?: $copy->shelf_location,
            'condition_notes' => $validated['condition_notes'] ?: $copy->condition_notes,
        ]);

        return back()->with('success', "تم تحديث حالة وفحص النسخة «{$copy->barcode}» بنجاح.");
    }

    public function destroyCopy(BookCopy $copy)
    {
        $user = Auth::user();
        Gate::authorize('manageBooks', $copy->library);

        if ($copy->status === 'borrowed') {
            return back()->with('error', 'لا يمكن حذف نسخة معارة حالياً.');
        }

        $title = $copy->book->title;

        // حذف الكتاب من مكتبتي الشخصية يزيله من قائمة القراءة الجارية أيضًا،
        // وإلا أعادت المزامنة التلقائية إضافته عند فتح الصفحة (سجل الجلسات يبقى كما هو)
        if ($copy->library && $copy->library->user_id === $user->id && $copy->library->is_personal) {
            UserCurrentBook::where('user_id', $user->id)
                ->where('book_id', $copy->book_id)
                ->whereIn('status', ['currently_reading', 'want_to_read', 'on_hold'])
                ->delete();
        }

        $copy->delete();

        return back()->with('success', "تم حذف نسخة «{$title}» من المكتبة بنجاح.");
    }

    public function requestLoan(Request $request, BookCopy $copy)
    {
        if (! SystemSetting::isLendingEnabled()) {
            $msg = 'خدمة استعارة الكتب الورقية معطلة حالياً بقرار من إدارة المنصة.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }

            return back()->with('error', $msg);
        }

        $user = Auth::user();

        // لا يمكن استعارة نسخة من مكتبتك أنت
        if ($copy->library && $copy->library->user_id === $user->id) {
            $msg = 'هذه النسخة في مكتبتك أنت، ولا حاجة لاستعارتها.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        // Check if user has permission to borrow from this library via Policy
        Gate::authorize('borrow', $copy->library);

        // Check if this copy is reserved for this user via waitlist notification
        $isNotifiedWaitlist = BookWaitlist::where('library_id', $copy->library_id)
            ->where('book_id', $copy->book_id)
            ->where('user_id', $user->id)
            ->where('status', 'notified')
            ->first();

        // 1. Strict Availability Rule: Copy MUST be available (or reserved for this notified waitlist beneficiary)
        if ($copy->status !== 'available' && ! $isNotifiedWaitlist) {
            $msg = 'لا يمكن إعارة هذه النسخة لأن حالتها ليست متاحة (Available). الحالة الحالية: '.$copy->status_label;
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        // Also check physical condition: Cannot loan damaged copy or copy under maintenance
        if ($copy->status === 'maintenance' || $copy->condition === 'damaged') {
            $msg = 'عذراً، هذه النسخة غير متاحة للإعارة نظراً لحاجتها إلى الصيانة والترميم.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        // 2. Prevent user from borrowing the same book multiple times if they already have an active, pending, or approved loan
        $userAlreadyHasLoan = Loan::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved', 'active', 'overdue'])
            ->whereHas('copy', function ($q) use ($copy) {
                $q->where('book_id', $copy->book_id);
            })
            ->exists();

        if ($userAlreadyHasLoan) {
            $bookTitle = $copy->book->title ?? 'هذا الكتاب';
            $msg = "لا يمكنك استعارة نفس الكتاب أكثر من مرة! لديك بالفعل طلب أو إعارة نشطة لكتاب «{$bookTitle}».";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        $swapBookTitle = $request->input('swap_book_title');
        $qrToken = 'RWQ-QR-'.strtoupper(Str::random(8));
        $durationDays = $copy->library->loan_duration_days ?: 14;
        $maxRenewals = $copy->library->max_renewals ?: 2;

        $loan = Loan::create([
            'book_copy_id' => $copy->id,
            'user_id' => $user->id,
            'borrower_name' => $user->name,
            'status' => 'pending',
            'requested_at' => Carbon::now(),
            'due_date' => Carbon::now()->addDays($durationDays),
            'notes' => 'طلب استعارة من المنصة',
            'swap_book_title' => $swapBookTitle ? trim($swapBookTitle) : null,
            'qr_token' => $qrToken,
            'max_renewals' => $maxRenewals,
        ]);

        // If user came from waitlist, mark waitlist fulfilled
        if ($isNotifiedWaitlist) {
            $isNotifiedWaitlist->update(['status' => 'fulfilled']);
        }

        $copy->update(['status' => 'reserved']);

        // Notify Library Owner/Librarians
        if ($copy->library->user_id !== $user->id) {
            UserNotification::create([
                'user_id' => $copy->library->user_id,
                'title' => 'طلب استعارة نسخة مادية 📖',
                'body' => "طلب «{$user->name}» استعارة نسخة «{$copy->book->title}» من مكتبة «{$copy->library->name}».",
                'icon' => 'book-copy',
                'link' => route('libraries.index', ['library_id' => $copy->library_id, 'tab' => 'loans']),
                'type' => 'loan',
            ]);
        }

        $msg = $swapBookTitle
            ? "أُرسل طلب الإعارة التبادلية (مع عرض مبادلة «{$swapBookTitle}») إلى أمين المكتبة بنجاح!"
            : 'أُرسل طلب الاستعارة إلى أمين المكتبة؛ بانتظار المراجعة والموافقة.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'loan_id' => $loan->id,
                'qr_token' => $loan->qr_token,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function updateLoanStatus(Request $request, Loan $loan)
    {
        if (! SystemSetting::isLendingEnabled()) {
            return back()->with('error', 'خدمة إدارة الإعارات معطلة حالياً بقرار من إدارة المنصة.');
        }

        $user = Auth::user();
        Gate::authorize('manageBooks', $loan->copy->library);

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,active,returned,closed',
            'handover_condition' => 'nullable|in:mint,good,fair,damaged',
            'handover_notes' => 'nullable|string|max:500',
            'return_condition' => 'nullable|in:mint,good,fair,damaged',
            'return_notes' => 'nullable|string|max:500',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $status = $validated['status'];
        $copy = $loan->copy;
        $bookTitle = $copy->book->title ?? 'الكتاب';
        $libraryName = $copy->library->name ?? 'المكتبة';

        if ($status === 'approved') {
            $loan->update([
                'status' => 'approved',
            ]);
            $copy->update(['status' => 'reserved']);

            if ($loan->user_id) {
                UserNotification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'تمت الموافقة على طلب استعارتك ✦',
                    'body' => "تمت الموافقة على طلب استعارة «{$bookTitle}» في «{$libraryName}». النسخة جاهزة للتسليم.",
                    'icon' => 'check-circle',
                    'link' => route('libraries.index', ['library_id' => $copy->library_id, 'tab' => 'loans']),
                    'type' => 'loan',
                ]);
            }

            AuditLog::record($user, 'loan_approved', $loan, "موافقة على طلب إعارة النسخة #{$copy->barcode} لكتاب «{$bookTitle}»");
            $msg = 'تمت الموافقة على طلب الاستعارة، وأصبحت النسخة جاهزة للتسليم للمستفيد.';
        } elseif ($status === 'rejected') {
            $reason = $validated['rejection_reason'] ?? 'لم تتوفر شروط الاستعارة أو عدم توفر النسخة حالياً.';
            $loan->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            // Release copy: check waitlist or make available
            $this->releaseCopyOrNotifyWaitlist($copy);

            if ($loan->user_id) {
                UserNotification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'نعتذر، تم رفض طلب الاستعارة ✕',
                    'body' => "تم رفض طلب استعارة «{$bookTitle}» في «{$libraryName}». السبب: {$reason}",
                    'icon' => 'x-circle',
                    'link' => route('libraries.index', ['library_id' => $copy->library_id, 'tab' => 'loans']),
                    'type' => 'loan',
                ]);
            }

            AuditLog::record($user, 'loan_rejected', $loan, "رفض طلب إعارة النسخة #{$copy->barcode}. السبب: {$reason}");
            $msg = 'تم رفض طلب الاستعارة وإشعار المستفيد.';
        } elseif ($status === 'active') {
            $handoverCond = $validated['handover_condition'] ?? $copy->condition ?? 'good';
            $handoverNotes = $validated['handover_notes'] ?? null;
            $durationDays = $copy->library->loan_duration_days ?: 14;
            $dueDate = Carbon::now()->addDays($durationDays);

            $loan->update([
                'status' => 'active',
                'borrowed_at' => Carbon::now(),
                'handover_at' => Carbon::now(),
                'handover_inspector_id' => $user->id,
                'handover_condition' => $handoverCond,
                'handover_notes' => $handoverNotes,
                'due_date' => $dueDate,
            ]);

            $copy->update([
                'status' => 'borrowed',
                'condition' => $handoverCond,
            ]);
            $copy->increment('readers_count');

            if ($loan->user_id) {
                UserNotification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'تم اعتماد وتسليم طلب إعارتك 🌿',
                    'body' => "تم تسليمك نسخة «{$bookTitle}». موعد الإرجاع هو {$dueDate->format('Y-m-d')}.",
                    'icon' => 'book-open',
                    'link' => route('libraries.index', ['library_id' => $copy->library_id, 'tab' => 'loans']),
                    'type' => 'loan',
                ]);
            }

            AuditLog::record($user, 'loan_handover', $loan, "تسليم النسخة #{$copy->barcode} وبدء الإعارة النشطة. فحص التسليم: {$handoverCond}");
            $msg = 'تم تسليم النسخة بنجاح وتوثيق تقرير الفحص المبدئي وبدء الإعارة النشطة.';
        } elseif ($status === 'returned') {
            $returnCond = $validated['return_condition'] ?? $copy->condition ?? 'good';
            $returnNotes = $validated['return_notes'] ?? null;

            $loan->update([
                'status' => 'returned',
                'returned_at' => Carbon::now(),
                'return_inspector_id' => $user->id,
                'return_condition' => $returnCond,
                'return_notes' => $returnNotes,
            ]);

            $copy->update(['condition' => $returnCond]);
            if ($returnNotes) {
                $copy->condition_notes = $returnNotes;
            }

            // Release copy or reserve for waitlist
            $this->releaseCopyOrNotifyWaitlist($copy, $returnCond);

            if ($loan->user_id) {
                UserNotification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'تم استرجاع النسخة وتوثيق التقرير ✦',
                    'body' => "تم استلام نسخة «{$bookTitle}» في «{$libraryName}» وفحص حالتها بنجاح.",
                    'icon' => 'check-check',
                    'link' => route('libraries.index', ['library_id' => $copy->library_id, 'tab' => 'loans']),
                    'type' => 'loan',
                ]);
            }

            AuditLog::record($user, 'loan_returned', $loan, "استرجاع النسخة #{$copy->barcode} وتوثيق تقرير الحالة: {$returnCond}");
            $msg = 'تم تسجيل استرجاع النسخة وتوثيق تقرير الحالة بنجاح.';
        } elseif ($status === 'closed') {
            $loan->update([
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'closed_by' => $user->id,
            ]);

            AuditLog::record($user, 'loan_closed', $loan, "إغلاق وأرشفة ملف إعارة النسخة #{$copy->barcode} نهائياً");
            $msg = 'تم إغلاق ملف الإعارة واعتماد التقرير النهائي بنجاح.';
        }

        return back()->with('success', $msg ?? 'تم تحديث حالة الإعارة بنجاح.');
    }

    public function extendLoan(Request $request, Loan $loan)
    {
        if (! SystemSetting::isLendingEnabled()) {
            return back()->with('error', 'خدمة تمديد الإعارة معطلة حالياً بقرار من إدارة المنصة.');
        }

        $user = Auth::user();

        // Allowed for borrower or library manager
        if ($loan->user_id !== $user->id && ! $loan->copy->library->canManage($user)) {
            return back()->with('error', 'غير مصرح لك بتمديد هذه الإعارة.');
        }

        if (! in_array($loan->status, ['active', 'overdue'])) {
            return back()->with('error', 'لا يمكن تمديد إعارة غير نشطة.');
        }

        if (! $loan->canRenew()) {
            $max = $loan->max_renewals ?: ($loan->copy->library->max_renewals ?: 2);

            return back()->with('error', "تم استنفاد الحد الأقصى للتمديد ({$max} مرات).");
        }

        // Check if there are members waiting on the waitlist for this book
        $hasWaitlist = BookWaitlist::where('library_id', $loan->copy->library_id)
            ->where('book_id', $loan->copy->book_id)
            ->where('status', 'waiting')
            ->exists();

        if ($hasWaitlist) {
            return back()->with('error', 'عذراً، لا يمكن تمديد الإعارة لوجود قرّاء في قائمة الانتظار ينتظرون هذه النسخة!');
        }

        $durationDays = $loan->copy->library->loan_duration_days ?: 14;
        $newDueDate = Carbon::parse($loan->due_date ?? Carbon::now())->addDays($durationDays);

        $loan->update([
            'due_date' => $newDueDate,
            'renewal_count' => $loan->renewal_count + 1,
            'status' => 'active',
        ]);

        AuditLog::record($user, 'loan_renewed', $loan, "تمديد إعارة النسخة #{$loan->copy->barcode} حتى {$newDueDate->format('Y-m-d')}");

        $formattedDate = $newDueDate->format('Y-m-d');

        return back()->with('success', "تم تمديد الإعارة بنجاح لمدة {$durationDays} يوماً إضافية حتى تاريخ {$formattedDate}!");
    }

    public function handshake(Request $request, Loan $loan)
    {
        if (! SystemSetting::isLendingEnabled()) {
            return back()->with('error', 'خدمة توثيق تسليم الإعارة معطلة حالياً بقرار من إدارة المنصة.');
        }

        $user = Auth::user();
        Gate::authorize('manageBooks', $loan->copy->library);

        $token = $request->input('qr_token');
        if ($loan->qr_token && $token && trim(strtoupper($loan->qr_token)) !== trim(strtoupper($token))) {
            $errorMsg = 'رمز الاستجابة السريعة (QR Handshake Token) غير مطابق لهذا الحجز.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMsg], 422);
            }

            return back()->with('error', $errorMsg);
        }

        $handoverCond = $request->input('handover_condition') ?? $loan->copy->condition ?? 'good';
        $handoverNotes = $request->input('handover_notes') ?? 'تم التسليم الميداني عبر مسح QR Handshake بنجاح.';
        $durationDays = $loan->copy->library->loan_duration_days ?: 14;
        $dueDate = Carbon::now()->addDays($durationDays);

        $loan->update([
            'status' => 'active',
            'borrowed_at' => Carbon::now(),
            'handover_at' => Carbon::now(),
            'handover_inspector_id' => $user->id,
            'handover_condition' => $handoverCond,
            'handover_notes' => $handoverNotes,
            'due_date' => $dueDate,
            'handshake_at' => Carbon::now(),
        ]);

        $copy = $loan->copy;
        $copy->update(['status' => 'borrowed', 'condition' => $handoverCond]);
        $copy->increment('readers_count');

        AuditLog::record($user, 'loan_handshake', $loan, "تسليم النسخة #{$copy->barcode} عبر QR Handshake");

        $msg = 'تم التحقق من رمز QR Handshake بنجاح وتوثيق تقرير الاستلام وتسليم النسخة للمستعير!';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function conditionReport(Loan $loan)
    {
        if (! SystemSetting::isLendingEnabled()) {
            abort(403, 'خدمة تقارير فحص الإعارة معطلة حالياً بقرار من إدارة المنصة.');
        }

        $user = Auth::user();
        $isBorrower = $loan->user_id === $user->id;
        $canManage = $loan->copy->library->canManage($user);

        if (! $isBorrower && ! $canManage && ! $user->canManagePlatform()) {
            abort(403, 'غير مصرح لك بالاطلاع على تقرير فحص هذه النسخة.');
        }

        $loan->load(['copy.book', 'copy.library', 'user', 'handoverInspector', 'returnInspector', 'closedBy']);

        return view('pages.libraries.condition-report', compact('loan'));
    }

    protected function releaseCopyOrNotifyWaitlist(BookCopy $copy, ?string $condition = null): void
    {
        $cond = $condition ?? $copy->condition ?? 'good';

        if ($cond === 'damaged') {
            $copy->update([
                'status' => 'maintenance',
                'condition' => 'damaged',
            ]);

            return;
        }

        // Check waitlist for this book in this library!
        $nextWaiting = BookWaitlist::where('library_id', $copy->library_id)
            ->where('book_id', $copy->book_id)
            ->where('status', 'waiting')
            ->orderBy('priority')
            ->oldest()
            ->first();

        if ($nextWaiting) {
            // Reserve copy for the first in queue
            $copy->update(['status' => 'reserved', 'condition' => $cond]);
            $nextWaiting->update(['status' => 'notified']);

            UserNotification::create([
                'user_id' => $nextWaiting->user_id,
                'title' => 'أصبحت النسخة متاحة لك الآن! ✦',
                'body' => "تم توفير نسخة «{$copy->book->title}» في مكتبة «{$copy->library->name}» وهي الآن محجوزة لك بانتظار تأكيد استعارتك.",
                'icon' => 'bell-ring',
                'link' => route('libraries.index', ['library_id' => $copy->library_id, 'tab' => 'books']),
                'type' => 'waitlist',
            ]);
        } else {
            $copy->update(['status' => 'available', 'condition' => $cond]);
        }
    }

    public function joinWaitlist(Request $request, Library $library, Book $book)
    {
        if (! SystemSetting::isLendingEnabled()) {
            return back()->with('error', 'خدمة قوائم انتظار استعارة الكتب معطلة حالياً بقرار من إدارة المنصة.');
        }

        $user = Auth::user();

        if (! $library->canBorrow($user) && $library->type !== 'public') {
            return back()->with('error', 'يجب أن تكون عضواً في هذه المكتبة للانضمام لقائمة الانتظار.');
        }

        $existing = BookWaitlist::where('library_id', $library->id)
            ->where('book_id', $book->id)
            ->where('user_id', $user->id)
            ->where('status', 'waiting')
            ->first();

        if ($existing) {
            return back()->with('info', 'أنت بالفعل مسجل في قائمة الانتظار لهذا الكتاب!');
        }

        $priority = BookWaitlist::where('library_id', $library->id)
            ->where('book_id', $book->id)
            ->where('status', 'waiting')
            ->count() + 1;

        BookWaitlist::create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $user->id,
            'status' => 'waiting',
            'priority' => $priority,
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', "تم تسجيلك في قائمة الانتظار لكتاب «{$book->title}». ترتيبك الحالي: #{$priority}. سنشعرك فور توفر النسخة!");
    }

    public function cancelWaitlist(BookWaitlist $waitlist)
    {
        $user = Auth::user();

        if ($waitlist->user_id !== $user->id && ! $waitlist->library->canManage($user)) {
            return back()->with('error', 'غير مصرح لك بإلغاء هذا الحجز.');
        }

        $wasNotified = ($waitlist->status === 'notified');
        $libraryId = $waitlist->library_id;
        $bookId = $waitlist->book_id;
        $title = $waitlist->book->title ?? 'الكتاب';

        $waitlist->delete();

        if ($wasNotified) {
            $copy = BookCopy::where('library_id', $libraryId)
                ->where('book_id', $bookId)
                ->where('status', 'reserved')
                ->first();

            if ($copy) {
                $this->releaseCopyOrNotifyWaitlist($copy);
            }
        }

        return back()->with('success', "تم إلغاء حجزك في قائمة الانتظار لكتاب «{$title}».");
    }

    public function addPassportNote(Request $request, BookCopy $copy)
    {
        $validated = $request->validate([
            'margin_note' => 'required|string|max:500',
            'city' => 'nullable|string|max:100',
        ]);

        $userName = Auth::user() ? Auth::user()->name : 'قارئ رِواق';
        $newNote = '«'.trim($validated['margin_note']).'» — '.$userName;

        if (! empty($validated['city'])) {
            $city = trim($validated['city']);
            $newNote .= " ({$city})";

            $existingCities = array_filter(array_map('trim', explode('،', $copy->cities_traveled ?? '')));
            if (! in_array($city, $existingCities)) {
                $existingCities[] = $city;
                $copy->cities_traveled = implode('، ', $existingCities);
            }
        }

        $existingNotes = $copy->margin_notes ? trim($copy->margin_notes)."\n\n" : '';
        $copy->margin_notes = $existingNotes.$newNote;
        $copy->save();

        $msg = 'تم تسجيل انطباعك وبصمتك في جواز سفر النسخة الورقية بنجاح! 📖✨';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'copy' => $copy]);
        }

        return back()->with('success', $msg);
    }

    public function updateSettings(Request $request, Library $library)
    {
        $user = Auth::user();
        Gate::authorize('update', $library);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:personal,public',
            'cover_color' => 'nullable|string|max:20',
            'loan_duration_days' => 'nullable|integer|min:1|max:90',
            'max_renewals' => 'nullable|integer|min:0|max:5',
        ]);

        // المكتبة الشخصية تبقى خاصة دائمًا
        if ($library->is_personal) {
            $validated['type'] = 'personal';
        }

        $library->update([
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'type' => $validated['type'],
            'cover_color' => $validated['cover_color'] ?? $library->cover_color,
            'loan_duration_days' => $validated['loan_duration_days'] ?? $library->loan_duration_days,
            'max_renewals' => $validated['max_renewals'] ?? $library->max_renewals,
        ]);

        AuditLog::record($user, 'update_library_settings', $library, "عدّل إعدادات المكتبة «{$library->name}».");

        return redirect()->route('libraries.index', ['library_id' => $library->id])
            ->with('success', 'تم حفظ إعدادات المكتبة.');
    }

    public function forceDestroy(Request $request, Library $library)
    {
        $user = Auth::user();
        Gate::authorize('delete', $library);

        if ($library->is_personal) {
            return back()->with('error', 'لا يمكن حذف المكتبة الشخصية، يمكنك تغيير اسمها فقط.');
        }

        $request->validate(['confirm_name' => 'required|string']);
        if (trim($request->input('confirm_name')) !== $library->name) {
            return back()->with('error', 'اسم المكتبة المكتوب لا يطابق اسمها، لم يتم الحذف.');
        }

        $hasOpenLoans = Loan::whereIn('status', ['pending', 'approved', 'active', 'overdue'])
            ->whereHas('copy', fn ($q) => $q->where('library_id', $library->id))
            ->exists();
        if ($hasOpenLoans) {
            return back()->with('error', 'توجد إعارات جارية في هذه المكتبة، أنهِها أولًا ثم احذف المكتبة.');
        }

        $name = $library->name;
        AuditLog::record($user, 'force_delete_library', $library, "حذف المستخدم «{$user->name}» المكتبة «{$name}» حذفًا تامًا.");
        $library->forceDelete();

        return redirect()->route('libraries.index')->with('success', "تم حذف المكتبة «{$name}» حذفًا تامًا.");
    }

    public function destroy(Library $library)
    {
        $user = Auth::user();
        Gate::authorize('delete', $library);

        $name = $library->name;
        $library->delete();

        AuditLog::record(
            $user,
            'delete_library',
            $library,
            "تمت أرشفة/حذف المكتبة «{$name}» (Soft Delete)."
        );

        return redirect()->route('libraries.index')->with('success', "تمت أرشفة وحذف المكتبة «{$name}» بأمان.");
    }
}
