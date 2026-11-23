<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Challenge;
use App\Models\ChallengeTeam;
use App\Models\Community;
use App\Models\ReadingSession;
use App\Models\User;
use App\Models\UserCurrentBook;
use App\Services\AntiGamingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ChallengeController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'active'); // active, teams, leaderboards, archive, upcoming
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');

        // تحديات النوادي لا يراها إلا أعضاء النادي؛ التحديات العامة يراها الجميع
        $myCommunityIds = $user ? $user->communities()->pluck('communities.id')->all() : [];
        $visibleToMe = fn ($q) => $q->where(fn ($w) => $w->whereNull('community_id')->orWhereIn('community_id', $myCommunityIds));

        // Query active individual challenges
        $challengeQuery = Challenge::where('is_archived', false)
            ->where($visibleToMe)
            ->with(['participants', 'teams.leader', 'specificBook', 'creator']);

        if ($typeFilter) {
            $challengeQuery->where('challenge_type', $typeFilter);
        }

        if ($statusFilter) {
            $challengeQuery->where('status', $statusFilter);
        }

        // Active Individual Challenge (First priority)
        $challenge = (clone $challengeQuery)
            ->where(fn ($q) => $q->where('is_team', false)->orWhereNull('is_team'))
            ->whereIn('status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_UPCOMING])
            ->first();

        // Team Challenge
        $teamChallenge = (clone $challengeQuery)
            ->where('is_team', true)
            ->whereIn('status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_UPCOMING])
            ->first();

        // All active challenges
        $allActiveChallenges = Challenge::whereIn('status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_UPCOMING])
            ->where($visibleToMe)
            ->with(['participants', 'teams', 'creator', 'community', 'specificBook'])
            ->latest()
            ->get();

        // Archived & Completed Challenges
        $archivedChallenges = Challenge::whereIn('status', [Challenge::STATUS_ARCHIVED, Challenge::STATUS_COMPLETED])
            ->with(['participants', 'teams', 'winnerUser', 'winnerTeam'])
            ->latest()
            ->get();

        // Upcoming Challenges
        $upcomingChallenges = Challenge::where('status', Challenge::STATUS_UPCOMING)
            ->where($visibleToMe)
            ->with(['participants', 'teams'])
            ->latest()
            ->get();

        $participant = null;
        $isJoined = false;
        $teamParticipant = null;
        $isTeamJoined = false;

        if ($challenge && $user) {
            $participant = $challenge->participants()->where('user_id', $user->id)->first();
            $isJoined = $participant !== null;
        }

        if ($teamChallenge && $user) {
            $teamParticipant = $teamChallenge->participants()->where('user_id', $user->id)->first();
            $isTeamJoined = $teamParticipant !== null;
        }

        // Recent Audit / Progress logs for the active challenge
        $recentLogs = $challenge
            ? $challenge->progressLogs()->with(['user', 'team', 'book'])->take(15)->get()
            : collect();

        // Top Readers
        $topReaders = User::orderBy('points', 'desc')->take(10)->get();

        // Challenge Leaderboard & Teams Leaderboard
        $challengeLeaderboard = $challenge ? $challenge->leaderboard()->take(15)->get() : collect();
        $teamLeaderboard = $teamChallenge
            ? $teamChallenge->teams()->orderBy('rank', 'asc')->orderBy('total_progress', 'desc')->get()
            : collect();

        // Books catalog for challenge creation
        $availableBooks = collect();

        // الأندية التي يحق لي إطلاق تحدٍّ فيها
        $myClubsForChallenge = $user
            ? $user->communities()->get()->filter(fn ($club) => Gate::forUser($user)->allows('createChallenge', $club))->values()
            : collect();

        // Supported Challenge Types Metadata
        $supportedTypes = [
            Challenge::TYPE_BOOKS_COUNT => 'عدد كتب 📚',
            Challenge::TYPE_PAGES_COUNT => 'عدد صفحات 📄',
            Challenge::TYPE_HOURS_COUNT => 'ساعات قراءة ⏱️',
            Challenge::TYPE_DAILY_STREAK => 'قراءة يومية مستمرة 🔥',
            Challenge::TYPE_SPECIFIC_BOOK => 'كتاب محدد 📖',
            Challenge::TYPE_DIVERSE_CATEGORIES => 'تصنيفات مختلفة 🧭',
            Challenge::TYPE_DIVERSE_AUTHORS => 'مؤلفون مختلفون ✍️',
            Challenge::TYPE_ARABIC_LITERATURE => 'أدب ولغة عربية 🌿',
            Challenge::TYPE_SEASONAL => 'تحدي موسمي ☀️',
            Challenge::TYPE_COMMUNITY_VS_COMMUNITY => 'نادٍ ضد نادٍ ⚔️',
            Challenge::TYPE_INTERNAL_TEAMS => 'فرق داخل النادي 🛡️',
            Challenge::TYPE_BOOK_SERIES => 'سلسلة كتب 📑',
            Challenge::TYPE_KNOWLEDGE_DIVERSITY => 'تنوع معرفي 🌐',
        ];

        // Supported Statuses Metadata
        $supportedStatuses = [
            Challenge::STATUS_DRAFT => 'مسودة',
            Challenge::STATUS_UPCOMING => 'قادم',
            Challenge::STATUS_ACTIVE => 'نشط',
            Challenge::STATUS_COMPLETED => 'مكتمل',
            Challenge::STATUS_CANCELLED => 'ملغي',
            Challenge::STATUS_ARCHIVED => 'مؤرشف',
        ];

        // Team Assignment Strategies Metadata
        $assignmentMethods = [
            Challenge::ASSIGNMENT_USER_CHOICE => 'اختيار المستخدم المباشر',
            Challenge::ASSIGNMENT_RANDOM => 'توزيع عشوائي متوازن',
            Challenge::ASSIGNMENT_BY_LEVEL => 'توزيع حسب المستوى والنقاط',
            Challenge::ASSIGNMENT_MANUAL => 'توزيع يدوي بواسطة القائد',
        ];

        // Pending uncredited pages from user's reading log
        $teamChallengePendingPages = ($teamChallenge && $user) ? $teamChallenge->getPendingReadingPagesForUser($user) : 0;
        $challengePendingPages = ($challenge && $user) ? $challenge->getPendingReadingPagesForUser($user) : 0;
        $userReadingBooks = $user ? $user->currentBooks()->with('book')->get() : collect();

        // كتبي في رفوف القراءة: لإظهار زر «ابدأ قراءة هذا الكتاب» في تحديات الكتب المحددة
        $myReadingBookIds = $user ? UserCurrentBook::where('user_id', $user->id)->pluck('book_id')->all() : [];

        // أوسمتي وشهاداتي الحقيقية فقط (لا بيانات تجريبية)
        $myBadges = $user ? $user->badges()->latest('user_badges.awarded_at')->get() : collect();
        $myCertificates = $user
            ? $user->challenges()->wherePivot('completed', true)->whereNotNull('challenge_participants.certificate_code')->get()
            : collect();

        return view('pages.challenges', compact(
            'myBadges',
            'myCertificates',
            'myReadingBookIds',
            'challenge',
            'teamChallenge',
            'allActiveChallenges',
            'archivedChallenges',
            'upcomingChallenges',
            'isJoined',
            'participant',
            'teamParticipant',
            'isTeamJoined',
            'topReaders',
            'challengeLeaderboard',
            'teamLeaderboard',
            'recentLogs',
            'availableBooks',
            'myClubsForChallenge',
            'supportedTypes',
            'supportedStatuses',
            'assignmentMethods',
            'teamChallengePendingPages',
            'challengePendingPages',
            'userReadingBooks',
            'tab'
        ));
    }

    /**
     * Create a new challenge (Individual or Team-based with any of the 13 supported types).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:1500',
            'challenge_type' => 'required|string|max:50',
            'target_count' => 'required|integer|min:1|max:100000',
            'target_unit' => 'nullable|string|max:30',
            'team_assignment_method' => 'nullable|string|in:manual,random,by_level,user_choice',
            'is_team' => 'nullable|boolean',
            'status' => 'nullable|string|in:draft,upcoming,active,completed,cancelled,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reward_badge' => 'nullable|string|max:100',
            'reward_points' => 'nullable|integer|min:10|max:2000',
            'specific_book_id' => 'nullable|exists:books,id',
            'series_name' => 'nullable|string|max:120',
            'season_name' => 'nullable|string|max:80',
            'community_id' => 'nullable|exists:communities,id',
            'team_a_name' => 'nullable|string|max:100',
            'team_b_name' => 'nullable|string|max:100',
        ]);

        // نوع «كتاب محدد» يستلزم اختيار الكتاب
        if ($validated['challenge_type'] === Challenge::TYPE_SPECIFIC_BOOK && empty($validated['specific_book_id'])) {
            return back()->withErrors(['specific_book_id' => 'اختر الكتاب الذي يخصّه التحدي.'])->withInput();
        }

        // تحدي النادي: يجب أن يحق لي إطلاقه في هذا النادي
        if (! empty($validated['community_id'])) {
            Gate::authorize('createChallenge', Community::findOrFail($validated['community_id']));
        }

        $isTeam = $request->boolean('is_team') || in_array($validated['challenge_type'], [
            Challenge::TYPE_COMMUNITY_VS_COMMUNITY,
            Challenge::TYPE_INTERNAL_TEAMS,
        ]);

        $status = $validated['status'] ?? Challenge::STATUS_ACTIVE;
        if (! empty($validated['start_date']) && Carbon::parse($validated['start_date'])->isFuture()) {
            $status = Challenge::STATUS_UPCOMING;
        }

        // Determine target unit if omitted
        $targetUnit = $validated['target_unit'] ?? match ($validated['challenge_type']) {
            Challenge::TYPE_PAGES_COUNT => 'صفحات',
            Challenge::TYPE_HOURS_COUNT => 'ساعات',
            Challenge::TYPE_DAILY_STREAK => 'أيام',
            Challenge::TYPE_DIVERSE_CATEGORIES => 'تصنيفات',
            Challenge::TYPE_DIVERSE_AUTHORS => 'مؤلفين',
            default => 'كتب',
        };

        $challenge = Challenge::create([
            'community_id' => $validated['community_id'] ?? null,
            'creator_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => $status,
            'category' => $validated['challenge_type'],
            'type' => $isTeam ? 'team' : 'individual',
            'challenge_type' => $validated['challenge_type'],
            'team_assignment_method' => $validated['team_assignment_method'] ?? Challenge::ASSIGNMENT_USER_CHOICE,
            'target_count' => $validated['target_count'],
            'target_unit' => $targetUnit,
            'specific_book_id' => $validated['specific_book_id'] ?? null,
            'series_name' => $validated['series_name'] ?? null,
            'season_name' => $validated['season_name'] ?? null,
            'start_date' => $validated['start_date'] ?? now()->toDateString(),
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => ($status === Challenge::STATUS_ACTIVE),
            'is_team' => $isTeam,
            'team_a_name' => $validated['team_a_name'] ?? 'فريق النخبة',
            'team_b_name' => $validated['team_b_name'] ?? 'فريق الرواد',
            'reward_badge' => $validated['reward_badge'] ?? 'وسام القراءة المعتمد',
            'reward_points' => $validated['reward_points'] ?? 150,
            'milestones' => [25, 50, 75, 100],
            'certificate_enabled' => true,
        ]);

        // If team challenge, automatically initialize the initial teams
        if ($isTeam) {
            ChallengeTeam::create([
                'challenge_id' => $challenge->id,
                'name' => $challenge->team_a_name,
                'color' => '#173D36',
                'badge_icon' => 'shield',
                'leader_id' => $user->id,
            ]);

            ChallengeTeam::create([
                'challenge_id' => $challenge->id,
                'name' => $challenge->team_b_name,
                'color' => '#C3A15D',
                'badge_icon' => 'flame',
                'leader_id' => null,
            ]);
        }

        // المنشئ ينضم تلقائيًا (وفي الفرق: إلى الفريق الأول)، ويُضاف كتاب التحدي المحدد إلى مكتبته ورفوفه
        $firstTeam = $isTeam ? $challenge->teams()->first() : null;
        $challenge->participants()->syncWithoutDetaching([$user->id => [
            'team_id' => $firstTeam?->id,
            'team' => $firstTeam ? 'team_a' : null,
            'progress_count' => 0,
            'points_earned' => 0,
            'completed' => false,
            'joined_at' => now(),
        ]]);
        $challenge->ensureBookOnShelves($user);

        $challenge->notifyAudience($user);

        // Audit Log
        AuditLog::record(
            $user,
            'challenge_created',
            $challenge,
            "أنشأ المستخدم «{$user->name}» تحدياً جديداً: «{$challenge->title}» من نوع ({$challenge->challenge_type_label})."
        );

        $msg = "تم إنشاء التحدي «{$challenge->title}» بنجاح!";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'challenge' => $challenge,
            ]);
        }

        return redirect()->to(route('challenges.index').'#all-challenges')
            ->with('success', $msg)
            ->with('new_challenge_id', $challenge->id);
    }

    /**
     * بعد أي انضمام جديد: إضافة كتاب التحدي المحدد لمكتبة المشارك ورفوفه، وإشعار منشئ التحدي.
     */
    private function afterJoin(Challenge $challenge, User $user, bool $wasJoined): void
    {
        if ($wasJoined) {
            return;
        }

        $challenge->ensureBookOnShelves($user);
        $challenge->notifyCreatorOfJoin($user);
    }

    /**
     * Join an individual or team challenge.
     */
    public function join(Challenge $challenge)
    {
        $user = Auth::user();
        $wasJoined = $challenge->participants()->where('user_id', $user->id)->exists();

        if ($challenge->is_team) {
            $assignedTeam = $challenge->assignUserToTeam($user);
            $msg = "انضممت بنجاح إلى «{$assignedTeam->name}» في تحدّي {$challenge->title}!";
        } else {
            if (! $challenge->participants()->where('user_id', $user->id)->exists()) {
                $challenge->participants()->attach($user->id, [
                    'progress_count' => 0,
                    'points_earned' => 0,
                    'completed' => false,
                    'joined_at' => now(),
                ]);
                $user->increment('points', 25);
                $challenge->recalculateRanksAndNotify();
                $msg = 'انضممت إلى تحدّي '.$challenge->title.'!';
            } else {
                $msg = 'أنت منضم بالفعل إلى هذا التحدي.';
            }
        }

        $this->afterJoin($challenge, $user, $wasJoined);

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Join or switch to a specific team (User Choice).
     */
    public function joinTeam(Request $request, Challenge $challenge, ChallengeTeam $team)
    {
        $user = Auth::user();

        if ($team->challenge_id !== $challenge->id) {
            abort(404, 'الفريق غير تابع لهذا التحدي.');
        }

        $wasJoined = $challenge->participants()->where('user_id', $user->id)->exists();
        $assigned = $challenge->assignUserToTeam($user, $team);
        $this->afterJoin($challenge, $user, $wasJoined);
        $msg = "تم تعيينك في «{$assigned->name}» بنجاح!";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'team' => $assigned]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Backwards-compatible team-join (team_a / team_b string).
     */
    public function joinWithTeam(Request $request, Challenge $challenge)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'team' => 'required|in:team_a,team_b',
        ]);

        $teamString = $validated['team'];
        $team = $challenge->teams()->where('name', $teamString === 'team_a' ? $challenge->team_a_name : $challenge->team_b_name)->first()
            ?? ($teamString === 'team_a' ? $challenge->teams()->first() : $challenge->teams()->skip(1)->first());

        $wasJoined = $challenge->participants()->where('user_id', $user->id)->exists();
        $assigned = $challenge->assignUserToTeam($user, $team);
        $this->afterJoin($challenge, $user, $wasJoined);
        $msg = "انضممت بنجاح إلى «{$assigned->name}» في {$challenge->title}!";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Synchronize uncredited reading sessions from user's reading log into the challenge.
     */
    public function syncReading(Request $request, Challenge $challenge)
    {
        $user = Auth::user();
        $result = $challenge->syncUserReadingSessions($user);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Log reading progress using Event / Transaction-based architecture backed by the reading log.
     */
    public function logProgress(Request $request, Challenge $challenge)
    {
        $user = Auth::user();

        // 1. If explicit sync request
        if ($request->input('action') === 'sync') {
            $syncResult = $challenge->syncUserReadingSessions($user);
            if ($request->wantsJson()) {
                return response()->json($syncResult);
            }

            return back()->with($syncResult['success'] ? 'success' : 'error', $syncResult['message']);
        }

        $validated = $request->validate([
            'pages' => 'nullable|integer|min:1|max:5000',
            'increment_value' => 'nullable|integer|min:1|max:5000',
            'minutes' => 'nullable|integer|min:1|max:1440',
            'event_type' => 'nullable|string|in:pages_read,book_completed,hours_logged,daily_streak,category_explored,author_explored,manual_log',
            'notes' => 'nullable|string|max:500',
            'book_id' => 'nullable|exists:books,id',
            'device' => 'nullable|string|max:60',
        ]);

        $val = $validated['increment_value'] ?? $validated['pages'] ?? 1;
        $eventType = $validated['event_type'] ?? 'pages_read';
        $notes = $validated['notes'] ?? null;
        $bookId = $validated['book_id'] ?? null;
        $minutes = $validated['minutes'] ?? null;
        $device = $validated['device'] ?? 'كتاب ورقي';

        $book = $bookId ? Book::find($bookId) : ($user->currentBook() ?? Book::first());

        // 2. Anti-Gaming Verification for pages read
        if ($eventType === 'pages_read' && $minutes !== null) {
            $antiGaming = app(AntiGamingService::class);
            $verification = $antiGaming->verifyReadingSession($user, $minutes, $val, $book);
            if ($verification['is_valid'] && ($paceReason = $antiGaming->paceViolation((int) $val, (int) $minutes))) {
                $verification = ['is_valid' => false, 'reason' => $paceReason];
            }
            if (! $verification['is_valid']) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $verification['reason'],
                    ], 422);
                }

                return back()->withErrors(['pages' => $verification['reason']])->with('error', $verification['reason']);
            }
        }

        // 3. Create or link underlying ReadingSession to ensure authentic reading log backing
        $session = null;
        if ($eventType === 'pages_read' && $book) {
            $actualMinutes = $minutes ?: max(1, (int) ceil($val / 2));
            $session = ReadingSession::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'started_at' => now()->subMinutes($actualMinutes),
                'ended_at' => now(),
                'start_page' => 1,
                'end_page' => $val,
                'current_page' => $val,
                'pages_read' => $val,
                'minutes' => $actualMinutes,
                'device' => $device,
                'note' => $notes ?: "جلسة قراءة موثقة عبر تحدّي «{$challenge->title}»",
            ]);

            // Update user's current book progress in reading log
            $userBook = UserCurrentBook::firstOrNew([
                'user_id' => $user->id,
                'book_id' => $book->id,
            ]);
            $userBook->current_page = max($userBook->current_page ?? 0, $val);
            $userBook->progress_percentage = min(100, (int) round(($userBook->current_page / max(1, $book->total_pages ?: 1)) * 100));
            $userBook->status = ($userBook->current_page >= ($book->total_pages ?: 1)) ? 'completed' : 'currently_reading';
            $userBook->save();
        }

        // 4. Execute transaction-based log via model method
        $log = $challenge->logUserProgress($user, $val, $eventType, $notes, $book?->id, $session?->id);

        $msg = "تم توثيق جلسة القراءة وحساب {$val} صفحة لصالح التحدي في سجلك القرائي بنجاح! 🌿";
        if ($completions = Challenge::pullCompletions()) {
            $msg .= ' '.Challenge::completionMessage($completions);
        }
        if ($log->progress_after >= $challenge->target_count) {
            $msg .= ' 🏆 مبارك إتمام كامل التحدي واستحقاق الشهادة المعتمدة!';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'log' => $log,
                'current_progress' => $log->progress_after,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Update challenge lifecycle status (draft, upcoming, active, completed, cancelled, archived).
     */
    public function updateStatus(Request $request, Challenge $challenge)
    {
        $user = Auth::user();

        // Authorization: Creator or SuperAdmin
        if ($challenge->creator_id && $challenge->creator_id !== $user->id && ! $user->isSuperAdmin()) {
            abort(403, 'غير مصرح لك بتغيير حالة هذا التحدي.');
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,upcoming,active,completed,cancelled,archived',
        ]);

        $newStatus = $validated['status'];

        if ($newStatus === Challenge::STATUS_COMPLETED) {
            $winners = $challenge->completeAndFinalizeWinners();
            $msg = 'تم اختتام التحدي وتتويج الفائزين بنجاح!';
        } else {
            $challenge->status = $newStatus;
            $challenge->is_active = ($newStatus === Challenge::STATUS_ACTIVE);
            $challenge->is_archived = ($newStatus === Challenge::STATUS_ARCHIVED);
            $challenge->save();
            $msg = "تم تحديث حالة التحدي إلى «{$challenge->status_label}».";
        }

        AuditLog::record(
            $user,
            'challenge_status_updated',
            $challenge,
            "غيّر «{$user->name}» حالة التحدي «{$challenge->title}» إلى ({$challenge->status_label})."
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'status' => $newStatus]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Create a new team within a challenge.
     */
    public function storeTeam(Request $request, Challenge $challenge)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:30',
            'badge_icon' => 'nullable|string|max:50',
            'leader_id' => 'nullable|exists:users,id',
        ]);

        $team = ChallengeTeam::create([
            'challenge_id' => $challenge->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#173D36',
            'badge_icon' => $validated['badge_icon'] ?? 'shield',
            'leader_id' => $validated['leader_id'] ?? $user->id,
        ]);

        $msg = "تم إنشاء الفريق «{$team->name}» بنجاح!";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'team' => $team]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Trigger 'ending_soon' notifications for active challenges.
     */
    public function notifyEndingSoon(Challenge $challenge)
    {
        $notifiedCount = $challenge->checkAndNotifyEndingSoon();

        $msg = $notifiedCount > 0
            ? "تم إرسال إشعارات قرب انتهاء التحدي إلى {$notifiedCount} مشاركاً بنجاح."
            : 'لم يتم العثور على مشاركين يستحقون إشعار قرب الانتهاء حالياً.';

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'notified_count' => $notifiedCount]);
        }

        return back()->with('info', $msg);
    }
}
