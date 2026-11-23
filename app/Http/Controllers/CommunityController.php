<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookNomination;
use App\Models\BookNominationEndorsement;
use App\Models\BookPoll;
use App\Models\BookPollOption;
use App\Models\BookPollVote;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\CommunityAnnouncement;
use App\Models\CommunityBadge;
use App\Models\CommunityBookHistory;
use App\Models\CommunityJoinRequest;
use App\Models\ContentReport;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\Library;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Reflection;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CommunityController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $code = $request->query('code');
        $communityId = $request->query('community_id');
        $typeFilter = $request->query('type', 'الكل');

        $joinedCommunityIds = $user ? $user->communities()->pluck('communities.id')->toArray() : [];
        $myCommunitiesCount = count($joinedCommunityIds);
        $totalCommunitiesCount = Community::count();
        $unjoinedCommunitiesCount = $user ? Community::whereNotIn('id', $joinedCommunityIds)->count() : $totalCommunitiesCount;

        // Scope resolution:
        // 'joined'   => Only user's joined communities
        // 'unjoined' => Only communities user has NOT joined (الانضمام لنادٍ جديد)
        // 'all'      => Explore all available communities
        // Default: If user is authenticated and is a member of at least 1 community, default to 'joined'
        // Otherwise default to 'all'
        $requestedScope = $request->query('scope');
        if ($requestedScope === 'all') {
            $scope = 'all';
        } elseif ($requestedScope === 'unjoined') {
            $scope = 'unjoined';
        } elseif ($requestedScope === 'joined') {
            $scope = 'joined';
        } else {
            $scope = ($user && $myCommunitiesCount > 0) ? 'joined' : 'all';
        }

        $lastVisitedId = session('last_visited_community_id');

        $query = Community::with(['currentBook', 'polls.options', 'discussions.user', 'announcements.user', 'linkedLibrary']);

        if ($code) {
            $community = $query->where('code', $code)->first();
        } elseif ($communityId) {
            $community = $query->where('id', $communityId)->first();
        } elseif ($lastVisitedId && ($lastCommunity = (clone $query)->find($lastVisitedId))) {
            // Prioritize last visited community remembered from user's interaction
            if ($scope === 'joined' && $user) {
                if (in_array($lastVisitedId, $joinedCommunityIds)) {
                    $community = $lastCommunity;
                } else {
                    $community = $user->communities()->orderByPivot('updated_at', 'desc')->with(['currentBook', 'polls.options', 'discussions.user', 'announcements.user', 'linkedLibrary'])->first();
                }
            } elseif ($scope === 'unjoined' && $user) {
                if (! in_array($lastVisitedId, $joinedCommunityIds)) {
                    $community = $lastCommunity;
                } else {
                    $community = Community::whereNotIn('id', $joinedCommunityIds)->with(['currentBook', 'polls.options', 'discussions.user', 'announcements.user', 'linkedLibrary'])->first();
                }
            } else {
                $community = $lastCommunity;
            }
        } elseif ($user && $scope === 'joined' && $myCommunitiesCount > 0) {
            $community = $user->communities()->orderByPivot('updated_at', 'desc')->with(['currentBook', 'polls.options', 'discussions.user', 'announcements.user', 'linkedLibrary'])->first();
        } elseif ($user && $scope === 'unjoined' && $unjoinedCommunitiesCount > 0) {
            $community = Community::whereNotIn('id', $joinedCommunityIds)->with(['currentBook', 'polls.options', 'discussions.user', 'announcements.user', 'linkedLibrary'])->first();
        } else {
            $community = $query->first();
        }

        if (! $community) {
            $community = Community::with(['currentBook', 'polls.options', 'discussions.user', 'announcements.user', 'linkedLibrary'])->first();
        }

        if (! $community) {
            return redirect()->route('libraries.index');
        }

        // Store active community in session so navigation to other pages and returning restores this exact council
        session(['last_visited_community_id' => $community->id]);
        if ($user && in_array($community->id, $joinedCommunityIds)) {
            $community->members()->updateExistingPivot($user->id, ['updated_at' => now()]);
        }

        if ($scope === 'joined' && $user) {
            $allCommunitiesQuery = $user->communities()
                ->with(['currentBook', 'linkedLibrary'])
                ->withCount(['members', 'discussions', 'meetings']);
        } elseif ($scope === 'unjoined' && $user) {
            $allCommunitiesQuery = Community::whereNotIn('id', $joinedCommunityIds)
                ->with(['currentBook', 'linkedLibrary'])
                ->withCount(['members', 'discussions', 'meetings']);
        } else {
            $allCommunitiesQuery = Community::with(['currentBook', 'linkedLibrary'])
                ->withCount(['members', 'discussions', 'meetings']);
        }

        if ($typeFilter && $typeFilter !== 'الكل') {
            $allCommunitiesQuery->where('type', $typeFilter);
        }
        $allCommunities = $allCommunitiesQuery->get();

        $community->loadCount(['members', 'discussions']);
        $isMember = $user ? $community->isMember($user) : false;
        $isOwner = $user ? $community->isOwner($user) : false;
        $userRole = $user ? $community->userRole($user) : null;
        $canManage = $user ? $community->canManage($user) : false;

        // Pending Join Request for current user
        $hasPendingRequest = $user ? CommunityJoinRequest::where('community_id', $community->id)->where('user_id', $user->id)->where('status', 'pending')->exists() : false;

        // Pending Join Requests for Manager
        $pendingRequests = $canManage
            ? CommunityJoinRequest::where('community_id', $community->id)->where('status', 'pending')->with('user')->get()
            : collect();

        // Active Announcements
        $announcements = CommunityAnnouncement::where('community_id', $community->id)->with('user')->latest()->get();

        // Active Poll
        $activePoll = BookPoll::where('community_id', $community->id)
            ->where('is_active', true)
            ->with('options')
            ->first();

        $userVotedOptionId = ($user && $activePoll) ? $activePoll->userVotedOptionId($user->id) : null;

        // Next Meeting
        $nextMeeting = Meeting::where('community_id', $community->id)
            ->orderBy('meeting_date', 'asc')
            ->first();

        // Selected book for discussion filtering (isolated discussion space per book in community)
        $selectedBookId = $request->query('book_id');
        $discussionsQuery = Discussion::where('community_id', $community->id)
            ->with(['user', 'book', 'likes', 'comments.user', 'comments.replies.user', 'comments.likes'])
            ->orderByDesc('is_pinned')
            ->latest();

        if ($selectedBookId) {
            $discussionsQuery->where('book_id', $selectedBookId);
        }

        $discussions = $discussionsQuery->get();

        // Pending Moderation Reports for Council Managers
        $pendingReports = $canManage
            ? ContentReport::where('status', 'pending')->with('user')->latest()->get()
            : collect();

        // Community Highlights (معجم أفكار المجلس المحدد لهذا المجلس حصراً)
        $highlights = Reflection::where('community_id', $community->id)
            ->where('is_highlight', true)
            ->with(['book', 'user', 'likes'])
            ->latest()
            ->take(6)
            ->get();

        if ($highlights->isEmpty()) {
            $highlights = Reflection::where('community_id', $community->id)
                ->with(['book', 'user', 'likes'])
                ->latest()
                ->take(6)
                ->get();
        }

        // Book Nominations for upcoming month
        $nominations = BookNomination::where('community_id', $community->id)
            ->with(['user', 'endorsements'])
            ->orderByDesc('endorsements_count')
            ->get();

        // Past Books History (أرشيف كتب الأشهر السابقة)
        $booksHistory = CommunityBookHistory::where('community_id', $community->id)
            ->with('book')
            ->orderByDesc('read_year')
            ->orderByDesc('read_month')
            ->get();

        // Community Specific Badges & Hall of Fame
        $communityBadges = CommunityBadge::where('community_id', $community->id)
            ->with('users')
            ->get();

        // 8 Key Hub Components Metrics & Elements
        $booksReadCount = $booksHistory->count() + ($community->current_book_id ? 1 : 0);
        $activeChallenge = $community->activeChallenge()->with('participants')->first();
        if (! $activeChallenge) {
            $activeChallenge = Challenge::where('community_id', $community->id)->latest()->first();
        }
        $newestMembers = $community->newestMembers()->get();
        $leaderboard = $community->leaderboard();
        $linkedLibrary = $community->linkedLibrary;
        $libraries = Library::where('is_personal', false)->get();
        $userFriends = $user ? $user->following : collect();

        $communityTypes = ['الكل', 'open', 'public', 'private', 'institutional', 'specialized', 'book_of_month'];
        $communityInvitations = $community->invitations()->with('inviter')->latest()->take(30)->get();

        $communityBooks = collect([$community->currentBook])
            ->filter()
            ->concat($booksHistory->pluck('book'))
            ->filter()
            ->unique('id')
            ->values();
        $selectedBook = $selectedBookId ? Book::find($selectedBookId) : $community->currentBook;

        return view('pages.communities', compact(
            'community',
            'allCommunities',
            'isMember',
            'isOwner',
            'userRole',
            'canManage',
            'hasPendingRequest',
            'pendingRequests',
            'pendingReports',
            'announcements',
            'activePoll',
            'userVotedOptionId',
            'nextMeeting',
            'discussions',
            'highlights',
            'typeFilter',
            'communityTypes',
            'nominations',
            'booksHistory',
            'communityBadges',
            'booksReadCount',
            'activeChallenge',
            'newestMembers',
            'leaderboard',
            'linkedLibrary',
            'libraries',
            'userFriends',
            'communityInvitations',
            'selectedBookId',
            'selectedBook',
            'communityBooks',
            'scope',
            'myCommunitiesCount',
            'totalCommunitiesCount',
            'unjoinedCommunitiesCount',
            'joinedCommunityIds'
        ));
    }

    public function joinWithCode(Request $request, $code)
    {
        $community = Community::where('code', $code)->firstOrFail();
        $user = Auth::user();

        if (! $user) {
            session(['url.intended' => route('communities.joinWithCode', $code)]);

            return redirect()->route('login')->with('info', "يرجى تسجيل الدخول أو إنشاء حساب للانضمام إلى «{$community->name}».");
        }

        $isMember = $community->members()->where('user_id', $user->id)->exists();

        if (! $isMember) {
            $community->members()->attach($user->id, ['role' => 'member', 'joined_at' => now(), 'updated_at' => now()]);
            $user->increment('points', 25);
            $msg = "أهلاً بك! انضممت بنجاح إلى «{$community->name}» عبر رابط الدعوة!";
        } else {
            $msg = "أنت عضو بالفعل في «{$community->name}».";
        }

        session(['last_visited_community_id' => $community->id]);

        return redirect()->route('communities.index', ['code' => $community->code])->with('success', $msg);
    }

    public function joinByCode(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'code' => 'required|string|max:40',
        ]);

        $code = strtoupper(trim($validated['code']));
        $community = Community::where('code', $code)->first();

        if (! $community) {
            return back()->with('error', 'كود النادي غير صحيح. يرجى التأكد من الرمز والمحاولة مجدداً.');
        }

        $isMember = $community->members()->where('user_id', $user->id)->exists();

        if (! $isMember) {
            $community->members()->attach($user->id, ['role' => 'member']);
            $user->increment('points', 25);
            $msg = "انضممت بنجاح إلى «{$community->name}» باستخدام كود النادي!";
        } else {
            $msg = "أنت عضو بالفعل في «{$community->name}».";
        }

        return redirect()->route('communities.index', ['code' => $community->code])->with('success', $msg);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:open,public,private,institutional,specialized,book_of_month',
            'language' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'interests' => 'nullable',
            'max_members' => 'nullable|integer|min:2|max:50000',
            'rules' => 'nullable|string|max:2000',
            'welcome_message' => 'nullable|string|max:1000',
            'cover_color' => 'nullable|string|max:20',
            'cover_image' => 'nullable|string|max:500',
            'logo' => 'nullable|string|max:255',
            'institutional_domain' => 'nullable|string|max:100',
            'linked_library_id' => 'nullable|exists:libraries,id',
            'requires_approval' => 'nullable|boolean',
            'allow_member_invites' => 'nullable|boolean',
            'allow_member_challenges' => 'nullable|boolean',
            'allow_member_meetings' => 'nullable|boolean',
            'is_visible_in_search' => 'nullable|boolean',
        ]);

        $type = $validated['type'];
        $code = 'RWQ-'.strtoupper(Str::random(6));

        $isVisible = match ($type) {
            'private' => false,
            'open', 'public' => true,
            'institutional' => $request->boolean('is_visible_in_search', true),
            default => true,
        };

        $requiresApproval = match ($type) {
            'open' => false,
            'public', 'private' => true,
            'institutional' => $request->boolean('requires_approval', true),
            default => false,
        };

        $interests = null;
        if (! empty($validated['interests'])) {
            $interests = is_array($validated['interests'])
                ? $validated['interests']
                : array_values(array_filter(array_map('trim', explode(',', $validated['interests']))));
        }

        $community = Community::create([
            'creator_id' => $user->id,
            'name' => trim($validated['name']),
            'code' => $code,
            'description' => $validated['description'] ?? null,
            'type' => $type,
            'category' => (! empty($interests) && is_array($interests)) ? $interests[0] : 'عام',
            'language' => $validated['language'] ?? 'العربية',
            'city' => $validated['city'] ?? null,
            'interests' => $interests,
            'max_members' => $validated['max_members'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'welcome_message' => $validated['welcome_message'] ?? null,
            'cover_color' => $validated['cover_color'] ?? '#173D36',
            'cover_image' => $validated['cover_image'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'institutional_domain' => $validated['institutional_domain'] ?? null,
            'linked_library_id' => $validated['linked_library_id'] ?? null,
            'is_visible_in_search' => $isVisible,
            'requires_approval' => $requiresApproval,
            'allow_member_invites' => $request->boolean('allow_member_invites', true),
            'allow_member_challenges' => $request->boolean('allow_member_challenges', true),
            'allow_member_meetings' => $request->boolean('allow_member_meetings', true),
        ]);

        $community->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        $user->increment('points', 50);

        AuditLog::record(
            $user,
            'create_community',
            $community,
            "أنشأ المستخدم «{$user->name}» مجلساً جديداً: «{$community->name}» من نوع ({$community->type_label})."
        );

        return redirect()->route('communities.index', ['community_id' => $community->id])
            ->with('success', "تم تأسيس المجلس «{$community->name}» بنجاح! كود الدعوة المخصص: {$community->code}");
    }

    public function updateSettings(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('manageMembers', $community);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:open,public,private,institutional,specialized,book_of_month',
            'language' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'interests' => 'nullable',
            'max_members' => 'nullable|integer|min:2|max:50000',
            'rules' => 'nullable|string|max:2000',
            'welcome_message' => 'nullable|string|max:1000',
            'cover_color' => 'nullable|string|max:20',
            'cover_image' => 'nullable|string|max:500',
            'logo' => 'nullable|string|max:255',
            'institutional_domain' => 'nullable|string|max:100',
            'linked_library_id' => 'nullable|exists:libraries,id',
            'requires_approval' => 'nullable|boolean',
            'allow_member_invites' => 'nullable|boolean',
            'allow_member_challenges' => 'nullable|boolean',
            'allow_member_meetings' => 'nullable|boolean',
            'is_visible_in_search' => 'nullable|boolean',
        ]);

        $type = $validated['type'];
        $isVisible = $type === 'private' ? false : $request->boolean('is_visible_in_search', true);
        $requiresApproval = $type === 'open' ? false : ($type === 'public' || $type === 'private' || $request->boolean('requires_approval'));

        $interests = null;
        if (! empty($validated['interests'])) {
            $interests = is_array($validated['interests'])
                ? $validated['interests']
                : array_values(array_filter(array_map('trim', explode(',', $validated['interests']))));
        }

        $oldData = $community->only(['name', 'type', 'max_members', 'requires_approval', 'linked_library_id']);

        $community->update([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $type,
            'language' => $validated['language'] ?? 'العربية',
            'city' => $validated['city'] ?? null,
            'interests' => $interests,
            'max_members' => $validated['max_members'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'welcome_message' => $validated['welcome_message'] ?? null,
            'cover_color' => $validated['cover_color'] ?? $community->cover_color,
            'cover_image' => $validated['cover_image'] ?? $community->cover_image,
            'logo' => $validated['logo'] ?? $community->logo,
            'institutional_domain' => $validated['institutional_domain'] ?? null,
            'linked_library_id' => $validated['linked_library_id'] ?? null,
            'is_visible_in_search' => $isVisible,
            'requires_approval' => $requiresApproval,
            'allow_member_invites' => $request->boolean('allow_member_invites'),
            'allow_member_challenges' => $request->boolean('allow_member_challenges'),
            'allow_member_meetings' => $request->boolean('allow_member_meetings'),
        ]);

        AuditLog::record(
            $user,
            'update_community_settings',
            $community,
            "تم تحديث إعدادات وبيانات مجلس «{$community->name}».",
            $oldData,
            $community->only(['name', 'type', 'max_members', 'requires_approval', 'linked_library_id'])
        );

        return back()->with('success', "تم تحديث بيانات وإعدادات «{$community->name}» بنجاح!");
    }

    public function requestJoin(Request $request, Community $community)
    {
        $user = Auth::user();

        if ($community->members()->where('user_id', $user->id)->exists()) {
            return back()->with('info', 'أنت بالفعل عضو في هذا المجلس.');
        }

        if ($community->hasReachedCapacity()) {
            return back()->with('error', 'عذراً، بلغ المجلس الحد الأقصى للأعضاء ولا يمكن استقبال طلبات انضمام جديدة حالياً.');
        }

        // Domain auto-join for institutional communities
        if ($community->type === 'institutional' && $community->institutional_domain) {
            if (str_ends_with(strtolower($user->email), strtolower($community->institutional_domain))) {
                $community->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);
                $user->increment('points', 25);

                return back()->with('success', "تم التحقق من بريدك المؤسسي والانضمام التلقائي إلى «{$community->name}» بنجاح!");
            }
        }

        $existing = CommunityJoinRequest::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return back()->with('info', 'طلب انضمامك قيد المراجعة لدى إدارة المجلس حالياً.');
        }

        $joinRequest = CommunityJoinRequest::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'notes' => $request->input('notes'),
        ]);

        // Notify creator
        UserNotification::create([
            'user_id' => $community->creator_id,
            'title' => 'طلب انضمام جديد للمجلس 🌿',
            'body' => "طلب «{$user->name}» الانضمام إلى «{$community->name}».",
            'icon' => 'user-check',
            'link' => route('communities.index', ['community_id' => $community->id]).'#pending-requests',
            'type' => 'community_join_request',
            'metadata' => [
                'community_id' => $community->id,
                'request_id' => $joinRequest->id,
                'applicant_id' => $user->id,
                'applicant_name' => $user->name,
            ],
        ]);

        return back()->with('success', 'أُرسل طلب الانضمام إلى إدارة المجلس بنجاح؛ ستصلك الموافقة فور اعتمادها.');
    }

    public function approveJoinRequest(CommunityJoinRequest $request)
    {
        $user = Auth::user();
        $community = $request->community;

        if (! $community->canManage($user)) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'غير مصرح لك بإدارة طلبات الانضمام.'], 403);
            }

            return back()->with('error', 'غير مصرح لك بإدارة طلبات الانضمام.');
        }

        if ($community->hasReachedCapacity()) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'لا يمكن قبول الطلب؛ لقد بلغ المجلس الحد الأقصى للأعضاء.'], 422);
            }

            return back()->with('error', 'لا يمكن قبول الطلب؛ لقد بلغ المجلس الحد الأقصى للأعضاء.');
        }

        $request->update(['status' => 'approved']);

        if (! $community->members()->where('user_id', $request->user_id)->exists()) {
            $community->members()->attach($request->user_id, ['role' => 'member', 'joined_at' => now()]);
        }

        // Notify member
        UserNotification::create([
            'user_id' => $request->user_id,
            'title' => 'تمت الموافقة على انضمامك للمجلس 🌿',
            'body' => "وافقت إدارة «{$community->name}» على طلب انضمامك. أهلاً بك في حواراتنا!".($community->welcome_message ? " · {$community->welcome_message}" : ''),
            'icon' => 'check-circle',
            'link' => route('communities.index', ['community_id' => $community->id]),
            'type' => 'community',
            'metadata' => [
                'community_id' => $community->id,
                'status' => 'approved',
            ],
        ]);

        $msg = "تمت الموافقة على انضمام «{$request->user->name}» للمجلس بنجاح.";
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function rejectJoinRequest(CommunityJoinRequest $request)
    {
        $user = Auth::user();
        $community = $request->community;

        if (! $community->canManage($user)) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'غير مصرح لك بإدارة طلبات الانضمام.'], 403);
            }

            return back()->with('error', 'غير مصرح لك بإدارة طلبات الانضمام.');
        }

        $request->update(['status' => 'rejected']);

        $msg = "تم رفض طلب انضمام «{$request->user->name}».";
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('info', $msg);
    }

    public function storeAnnouncement(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('manageMembers', $community);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'content' => 'required|string|max:3000',
            'is_pinned' => 'nullable|boolean',
        ]);

        CommunityAnnouncement::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'title' => trim($validated['title']),
            'content' => trim($validated['content']),
            'is_pinned' => $request->boolean('is_pinned', true),
        ]);

        $community->update(['announcement' => trim($validated['title']).': '.trim($validated['content'])]);

        return back()->with('success', 'تم نشر الإعلان وتثبيته في صدر المجلس بنجاح!');
    }

    public function sendInvitation(Request $request, Community $community)
    {
        $sender = Auth::user();
        Gate::authorize('invite', $community);

        $validated = $request->validate([
            'recipient' => 'required|string|max:255',
            'custom_message' => 'nullable|string|max:500',
        ]);

        $recipientQuery = trim($validated['recipient']);
        $recipientUser = User::where('email', $recipientQuery)
            ->orWhere('name', $recipientQuery)
            ->first();

        if ($recipientUser) {
            UserNotification::create([
                'user_id' => $recipientUser->id,
                'title' => 'دعوة انضمام لمجلس قراءة 💌',
                'body' => "وجّه إليك {$sender->name} دعوة للانضمام إلى «{$community->name}» (كود المجلس: {$community->code}). تنبيه: العضوية مفتوحة ولا تشترط الصداقة المسبقة.",
                'icon' => 'users',
                'link' => route('communities.joinWithCode', $community->code),
                'type' => 'community_invite',
                'is_read' => false,
            ]);

            $sender->increment('points', 10);
            $msg = "تم إرسال دعوة الانضمام بنجاح إلى القارئ «{$recipientUser->name}» في المنصة!";
        } else {
            $sender->increment('points', 5);
            $msg = "تم تجهيز وإرسال دعوة الانضمام ورابط المجلس إلى ({$recipientQuery}) بنجاح!";
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function toggleMembership(Community $community)
    {
        $user = Auth::user();
        $isMember = $community->members()->where('user_id', $user->id)->exists();

        if ($isMember) {
            Gate::authorize('leave', $community);

            if ($community->isOnlyOwner($user)) {
                return back()->with('error', 'يجب تعيين مالك جديد قبل مغادرة المجلس أو التنازل عن إدارته.');
            }

            $community->members()->detach($user->id);
            if (session('last_visited_community_id') == $community->id) {
                session()->forget('last_visited_community_id');
            }
            $msg = 'غادرت المجلس.';
            $joined = false;
        } else {
            // Check Capacity
            if ($community->hasReachedCapacity()) {
                $err = "عذراً، بلغ المجلس الحد الأقصى للأعضاء ({$community->max_members} عضو).";
                if (request()->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $err], 422);
                }

                return back()->with('error', $err);
            }

            // If private or requires approval -> forward to requestJoin
            if (! $community->canDirectJoin($user)) {
                return $this->requestJoin(request(), $community);
            }

            $community->members()->attach($user->id, ['role' => 'member', 'joined_at' => now(), 'updated_at' => now()]);
            session(['last_visited_community_id' => $community->id]);
            $user->increment('points', 25);
            $msg = "أهلاً بك! انضممت بنجاح إلى «{$community->name}»!".($community->welcome_message ? " · {$community->welcome_message}" : '');
            $joined = true;
        }

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'joined' => $joined]);
        }

        return back()->with('success', $msg);
    }

    public function importMembers(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('manageMembers', $community);

        $validated = $request->validate([
            'emails' => 'required|string',
        ]);

        $rawEmails = preg_split('/[\r\n,]+/', $validated['emails']);
        $importedCount = 0;
        $skippedCount = 0;

        foreach ($rawEmails as $rawEmail) {
            $email = strtolower(trim($rawEmail));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if ($community->hasReachedCapacity()) {
                return back()->with('warning', "تم استيراد {$importedCount} عضواً، ثم توقف الاستيراد لبلوغ المجلس الحد الأقصى للأعضاء.");
            }

            $memberUser = User::where('email', $email)->first();
            if ($memberUser) {
                if (! $community->members()->where('user_id', $memberUser->id)->exists()) {
                    $community->members()->attach($memberUser->id, ['role' => 'member', 'joined_at' => now()]);
                    $importedCount++;

                    UserNotification::create([
                        'user_id' => $memberUser->id,
                        'title' => "انضمام رسمي إلى «{$community->name}» 🏛️",
                        'body' => "تمت إضافتك إلى عضوية «{$community->name}» ضمن قائمة الأعضاء المعتمدة.",
                        'icon' => 'users',
                        'link' => route('communities.index', ['community_id' => $community->id]),
                        'type' => 'community',
                    ]);
                }
            } else {
                $skippedCount++;
            }
        }

        AuditLog::record(
            $user,
            'import_community_members',
            $community,
            "استورد المستخدم «{$user->name}» {$importedCount} عضواً إلى مجلس «{$community->name}»."
        );

        $msg = "تم استيراد وإضافة {$importedCount} عضواً بنجاح!";
        if ($skippedCount > 0) {
            $msg .= " (تخطى {$skippedCount} عناوين بريد غير مسجلة في المنصة بعد).";
        }

        return back()->with('success', $msg);
    }

    public function storeChallenge(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('createChallenge', $community);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'required|string|max:1000',
            'category' => 'nullable|string|max:50',
            'target_count' => 'required|integer|min:1',
            'end_date' => 'required|date|after_or_equal:today',
            'reward_points' => 'nullable|integer|min:0',
        ]);

        $challenge = Challenge::create([
            'community_id' => $community->id,
            'title' => trim($validated['title']),
            'description' => trim($validated['description']),
            'category' => $validated['category'] ?? 'تحدي قراءة',
            'type' => 'books',
            'target_count' => $validated['target_count'],
            'start_date' => now(),
            'end_date' => $validated['end_date'],
            'is_active' => true,
            'reward_points' => $validated['reward_points'] ?? 50,
            'reward_badge' => 'بطل التحدي',
        ]);

        $challenge->participants()->attach($user->id, [
            'progress_count' => 0,
            'completed' => false,
        ]);

        $challenge->notifyAudience($user);

        AuditLog::record(
            $user,
            'create_community_challenge',
            $community,
            "أطلق «{$user->name}» تحدياً جديداً في مجلس «{$community->name}»: «{$challenge->title}»."
        );

        return back()->with('success', "تم إطلاق التحدي «{$challenge->title}» بنجاح في المجلس!");
    }

    public function storeMeeting(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('createMeeting', $community);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'meeting_date' => 'required|date',
            'meeting_type' => 'required|in:remote,in_person,hybrid',
            'location' => 'nullable|string|max:255',
            'link' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'capacity' => 'nullable|integer|min:1',
            'book_id' => 'nullable|exists:books,id',
        ]);

        $meeting = Meeting::create([
            'community_id' => $community->id,
            'book_id' => $validated['book_id'] ?? $community->current_book_id,
            'title' => trim($validated['title']),
            'meeting_date' => $validated['meeting_date'],
            'meeting_type' => $validated['meeting_type'],
            'location' => $validated['location'] ?? null,
            'link' => $validated['link'] ?? null,
            'description' => $validated['description'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'is_past' => false,
        ]);

        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'status' => 'attending',
            'qr_code' => 'RWQ-MEET-'.$meeting->id.'-'.$user->id,
            'checked_in' => false,
        ]);

        AuditLog::record(
            $user,
            'create_community_meeting',
            $community,
            "جدول «{$user->name}» لقاءً جديداً في مجلس «{$community->name}»: «{$meeting->title}»."
        );

        return back()->with('success', "تمت جدولة اللقاء «{$meeting->title}» بنجاح!");
    }

    public function transferOwnership(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('transferOwnership', $community);

        $validated = $request->validate([
            'new_owner_id' => 'required|exists:users,id',
            'confirm' => 'required|accepted',
        ], [
            'confirm.accepted' => 'يجب تأكيد الموافقة الصريحة على نقل ملكية المجلس.',
        ]);

        $newOwner = User::findOrFail($validated['new_owner_id']);
        if ($newOwner->id === $community->creator_id) {
            return back()->with('info', 'المستخدم المحدد هو المالك الحالي بالفعل.');
        }

        $community->transferOwnership($newOwner, $user);

        return back()->with('success', "تم نقل ملكية المجلس بنجاح إلى القارئ «{$newOwner->name}» وتوثيق العملية في سجل التدقيق والرقابة.");
    }

    public function updateMemberRole(Request $request, Community $community, User $member)
    {
        $user = Auth::user();
        Gate::authorize('manageMembers', $community);

        if ($community->isOnlyOwner($member) && ! $user->isSuperAdmin()) {
            return back()->with('error', 'لا يمكن تعديل دور مؤسس ومالك المجلس الرئيسي قبل نقل الملكية.');
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,coordinator,member,suspended',
        ]);

        $oldRole = $community->userRole($member) ?? 'عضو';
        $community->members()->updateExistingPivot($member->id, ['role' => $validated['role']]);

        AuditLog::record(
            $user,
            'change_community_member_role',
            $community,
            "تم تعديل دور العضو «{$member->name}» في مجلس «{$community->name}» من «{$oldRole}» إلى «{$validated['role']}».",
            ['role' => $oldRole],
            ['role' => $validated['role']]
        );

        $roleArabic = match ($validated['role']) {
            'admin' => 'مدير المجلس 🛡️',
            'coordinator' => 'منسق القراءة 📖',
            'member' => 'عضو مشارك 🌿',
            'suspended' => 'موقوف / تحت المراجعة ⚠️',
            default => 'عضو',
        };

        return back()->with('success', "تم تحديث دور «{$member->name}» إلى {$roleArabic} بنجاح.");
    }

    public function removeMember(Community $community, User $member)
    {
        $user = Auth::user();
        $isSelf = $user && $user->id === $member->id;

        if ($isSelf) {
            Gate::authorize('leave', $community);
        } else {
            Gate::authorize('manageMembers', $community);
        }

        if ($community->isOnlyOwner($member)) {
            return back()->with('error', 'يجب تعيين مالك جديد قبل مغادرة المجلس أو إزالة المالك الوحيد.');
        }

        $community->members()->detach($member->id);

        AuditLog::record(
            $user,
            'remove_community_member',
            $community,
            "تمت إزالة «{$member->name}» من عضوية مجلس «{$community->name}».",
            ['user_id' => $member->id]
        );

        return back()->with('success', "تمت إزالة «{$member->name}» من عضوية المجلس.");
    }

    public function votePoll(Request $request, BookPoll $poll)
    {
        $user = Auth::user();
        Gate::authorize('votePoll', $poll->community);

        if ($poll->isClosed()) {
            return back()->with('error', 'عذراً، فترة التصويت لهذا الاستطلاع قد انتهت أو تم إغلاقه.');
        }

        $validated = $request->validate([
            'option_id' => 'required|exists:book_poll_options,id',
        ]);

        $optionId = $validated['option_id'];

        $isMember = $poll->community->members()->where('user_id', $user->id)->exists();
        if (! $isMember) {
            $poll->community->members()->attach($user->id, ['role' => 'member']);
        }

        $existingVote = BookPollVote::where('book_poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingVote) {
            if ($existingVote->book_poll_option_id == $optionId) {
                $msg = 'صوتك مسجل بالفعل لهذا الكتاب. يُمنع التصويت المتكرر لنفس الخيار.';
            } else {
                BookPollOption::where('id', $existingVote->book_poll_option_id)->decrement('votes_count');
                BookPollOption::where('id', $optionId)->increment('votes_count');
                $existingVote->update(['book_poll_option_id' => $optionId]);
                $msg = 'تم تعديل صوتك إلى الخيار الجديد بنجاح مع الحفاظ على قاعدة الصوت الواحد.';
            }
        } else {
            BookPollVote::create([
                'book_poll_id' => $poll->id,
                'book_poll_option_id' => $optionId,
                'user_id' => $user->id,
            ]);
            BookPollOption::where('id', $optionId)->increment('votes_count');
            $user->addPoints(10, 'poll_vote', "تصويت لاختيار كتاب الشهر في «{$poll->community->name}»");
            $msg = 'تم تسجيل صوتك بنجاح في اختيار كتاب الشهر (+10 أثر)!';
        }

        if ($request->wantsJson()) {
            $poll->load('options');

            return response()->json([
                'success' => true,
                'message' => $msg,
                'voted_option_id' => $optionId,
                'options' => $poll->options,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function storeDiscussion(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('postDiscussion', $community);

        $validated = $request->validate([
            'note' => 'nullable|string|max:4000',
            'content' => 'nullable|string|max:4000',
            'title' => 'nullable|string|max:200',
            'book_id' => 'nullable|exists:books,id',
            'is_spoiler' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'is_coordinator_question' => 'nullable|boolean',
        ]);

        $content = $validated['content'] ?? $validated['note'] ?? '';
        if (empty(trim($content))) {
            return back()->with('error', 'محتوى المشاركة لا يمكن أن يكون فارغاً.');
        }

        $bookId = $validated['book_id'] ?? $community->current_book_id;
        $title = $validated['title'] ?? Str::limit(trim($content), 60);

        $isCoordinator = $community->isReadingCoordinator($user) || $community->isManager($user) || $user->isSuperAdmin();
        $isCoordinatorQuestion = $request->boolean('is_coordinator_question') && $isCoordinator;
        $isPinned = $request->boolean('is_pinned') && $isCoordinator;

        $discussion = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'book_id' => $bookId,
            'title' => $title,
            'content' => trim($content),
            'is_spoiler' => $request->boolean('is_spoiler'),
            'is_pinned' => $isPinned,
            'is_coordinator_question' => $isCoordinatorQuestion,
        ]);

        $discussion->parseAndNotifyMentions($user);

        $user->increment('points', 15);

        $msg = 'أُضيفت مشاركتك إلى النقاش بنجاح (+15 أثر).';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'discussion' => $discussion->load('user', 'book')]);
        }

        return back()->with('success', $msg);
    }

    public function storeComment(Request $request, Discussion $discussion)
    {
        $user = Auth::user();
        Gate::authorize('postDiscussion', $discussion->community);

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:discussion_comments,id',
            'is_spoiler' => 'nullable|boolean',
        ]);

        $comment = DiscussionComment::create([
            'discussion_id' => $discussion->id,
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => trim($validated['content']),
            'is_spoiler' => $request->boolean('is_spoiler'),
        ]);

        $comment->parseAndNotifyMentions($user);

        $user->increment('points', 10);

        // Notify parent author or discussion author
        $notifyUserId = null;
        if ($comment->parent_id && $comment->parent && $comment->parent->user_id !== $user->id) {
            $notifyUserId = $comment->parent->user_id;
            $notifyTitle = 'رد جديد على تعليقك 💬';
            $notifyBody = "رد {$user->name} على تعليقك في نقاش «{$discussion->community->name}».";
        } elseif ($discussion->user_id !== $user->id) {
            $notifyUserId = $discussion->user_id;
            $notifyTitle = 'تعليق جديد على مشاركتك 💬';
            $notifyBody = "علّق {$user->name} على مشاركتك في مجلس «{$discussion->community->name}».";
        }

        if ($notifyUserId) {
            UserNotification::create([
                'user_id' => $notifyUserId,
                'title' => $notifyTitle,
                'body' => $notifyBody,
                'icon' => 'message-square',
                'link' => route('communities.index', ['community_id' => $discussion->community_id]),
                'type' => 'discussion_comment',
            ]);
        }

        $msg = 'نُشر تعليقك بنجاح (+10 أثر).';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'comment' => $comment->load('user')]);
        }

        return back()->with('success', $msg);
    }

    public function toggleDiscussionLike(Request $request, Discussion $discussion)
    {
        $user = Auth::user();
        $isLiked = $discussion->toggleLike($user);

        $msg = $isLiked ? 'تم تسجيل إعجابك بالنقاش ❤️' : 'تم إلغاء الإعجاب.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_liked' => $isLiked,
                'likes_count' => $discussion->likes_count,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function toggleCommentLike(Request $request, DiscussionComment $comment)
    {
        $user = Auth::user();
        $isLiked = $comment->toggleLike($user);

        $msg = $isLiked ? 'تم تسجيل إعجابك بالتعليق ❤️' : 'تم إلغاء الإعجاب.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_liked' => $isLiked,
                'likes_count' => $comment->likes_count,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function storeCommunityHighlight(Request $request, Community $community)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً.'], 401);
            }

            return redirect()->route('login');
        }

        if (! $community->isMember($user) && ! $community->isOwner($user) && ! $user->isSuperAdmin()) {
            if ($community->type === 'open' || $community->type === 'public' || empty($community->type)) {
                $community->members()->syncWithoutDetaching([$user->id => ['role' => 'member', 'joined_at' => now()]]);
            } else {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'يجب الانضمام للمجلس أولاً لإضافة اقتباس لمعجم الأفكار.'], 403);
                }

                return back()->with('error', 'يجب الانضمام للمجلس أولاً لإضافة اقتباس لمعجم الأفكار.');
            }
        }

        $validated = $request->validate([
            'content' => 'required|string|min:5|max:1000',
            'book_id' => 'nullable|exists:books,id',
            'page_number' => 'nullable|integer|min:1',
            'is_spoiler' => 'nullable|boolean',
        ]);

        $bookId = $validated['book_id'] ?? $community->current_book_id;
        $isSpoiler = $request->boolean('is_spoiler');

        $reflection = Reflection::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'book_id' => $bookId,
            'content' => trim($validated['content']),
            'page_number' => $validated['page_number'] ?? null,
            'is_public' => true,
            'privacy' => 'public',
            'is_highlight' => true,
            'is_spoiler' => $isSpoiler,
            'likes_count' => 0,
        ]);

        $user->addPoints(15, 'community_highlight', 'إثراء معجم أفكار المجلس باقتباس ملهم', Reflection::class, $reflection->id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تمت إضافة الاقتباس إلى معجم أفكار المجلس بنجاح ✦',
                'highlight' => $reflection->load(['user', 'book', 'likes']),
            ]);
        }

        return back()->with('success', 'تمت إضافة الاقتباس إلى معجم أفكار المجلس بنجاح ✦');
    }

    public function toggleReflectionLike(Request $request, Reflection $reflection)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً لتسجيل الأثر.'], 401);
            }

            return redirect()->route('login');
        }

        $isLiked = $reflection->toggleLike($user);

        $msg = $isLiked ? 'تم ترك الأثر بنجاح ✦' : 'تم سحب الأثر.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_liked' => $isLiked,
                'likes_count' => $reflection->fresh()->likes_count,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function toggleDiscussionPin(Request $request, Discussion $discussion)
    {
        $user = Auth::user();
        $community = $discussion->community;
        if (! $user->isSuperAdmin() && ! $community->isManager($user) && ! $community->isReadingCoordinator($user)) {
            abort(403, 'غير مصرح لك بتثبيت المشاركات.');
        }

        $isPinned = $discussion->togglePin();
        $msg = $isPinned ? 'تم تثبيت المنشور في أعلى مساحة نقاش الكتاب 📌' : 'تم إلغاء تثبيت المنشور.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_pinned' => $isPinned,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function bookDiscussions(Request $request, Community $community, Book $book)
    {
        return redirect()->route('communities.index', [
            'community_id' => $community->id,
            'book_id' => $book->id,
            'tab' => 'discussions',
        ]);
    }

    public function reportContent(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'reportable_type' => 'required|in:'.implode(',', ContentReport::TYPES),
            'reportable_id' => 'required|integer',
            'reason' => 'required|in:spoiler,inappropriate,copyright,misinformation,spam,disrespect,other',
            'details' => 'nullable|string|max:500',
        ]);

        $report = new ContentReport([
            'reportable_type' => $validated['reportable_type'],
            'reportable_id' => $validated['reportable_id'],
        ]);
        $target = $report->target();

        $fail = function (string $msg) use ($request) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        };

        if (! $target) {
            return $fail('تعذر العثور على المحتوى المراد الإبلاغ عنه.');
        }

        $authorId = $validated['reportable_type'] === 'user' ? $target->id : ($target->user_id ?? null);
        if ($authorId !== null && (int) $authorId === (int) $user->id) {
            return $fail('لا يمكنك الإبلاغ عن محتواك الخاص.');
        }

        $alreadyReported = ContentReport::where('user_id', $user->id)
            ->where('reportable_type', $validated['reportable_type'])
            ->where('reportable_id', $validated['reportable_id'])
            ->where('status', 'pending')
            ->exists();
        if ($alreadyReported) {
            return $fail('سبق أن أبلغت عن هذا المحتوى وهو قيد المراجعة لدى الإدارة.');
        }

        $created = ContentReport::create([
            'user_id' => $user->id,
            'reportable_type' => $validated['reportable_type'],
            'reportable_id' => $validated['reportable_id'],
            'reason' => $validated['reason'],
            'details' => ! empty($validated['details']) ? trim($validated['details']) : null,
            'status' => 'pending',
        ]);

        User::whereIn('role', ['super_admin', 'admin'])->get()->each(function (User $admin) use ($created, $user) {
            UserNotification::create([
                'user_id' => $admin->id,
                'title' => 'بلاغ جديد بانتظار المراجعة',
                'body' => "أبلغ «{$user->name}» عن {$created->typeLabel()}: {$created->reasonLabel()}.",
                'icon' => 'flag',
                'link' => route('admin.dashboard', ['tab' => 'reports']),
                'type' => 'moderation',
            ]);
        });

        $msg = 'شكرًا لك، تم استلام بلاغك وستراجعه الإدارة قريبًا. سنخبرك بالنتيجة عبر الإشعارات.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function resolveReport(Request $request, ContentReport $report)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'action' => 'required|in:dismiss,delete_content',
            'moderator_notes' => 'nullable|string|max:500',
        ]);

        $action = $validated['action'];
        $notes = $validated['moderator_notes'] ? trim($validated['moderator_notes']) : null;

        if ($action === 'dismiss') {
            $report->update([
                'status' => 'dismissed',
                'moderator_notes' => $notes,
                'moderated_by' => $user->id,
            ]);
            $msg = 'تم تجاهل البلاغ وحفظ القرار.';
        } else {
            // Delete offending target content
            $target = $report->target();
            if ($target) {
                $target->delete();
            }
            $report->update([
                'status' => 'action_taken',
                'moderator_notes' => $notes,
                'moderated_by' => $user->id,
            ]);
            $msg = 'تم حذف المحتوى المخالف بنجاح وتحديث حالة البلاغ.';
        }

        return back()->with('success', $msg);
    }

    public function closePollAndSetWinner(Request $request, BookPoll $poll)
    {
        $user = Auth::user();
        $community = $poll->community;
        Gate::authorize('coordinateReading', $community);

        $options = [
            'create_challenge' => $request->boolean('create_challenge', true),
            'create_meeting' => $request->boolean('create_meeting', true),
            'meeting_type' => $request->input('meeting_type', Meeting::TYPE_REMOTE),
        ];

        try {
            $result = $poll->closeAndExecuteWorkflow($user, $options);
            $winningBook = $result['winning_book'];
            $nextMonthName = $poll->month_name;
            $msg = "تم إغلاق التصويت واعتماد «{$winningBook->title}» كتاباً رسمياً لشهر {$nextMonthName} وتوثيق النتيجة بنجاح!";
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'poll' => $poll,
                'winning_book' => $result['winning_book'],
                'challenge' => $result['challenge'],
                'meeting' => $result['meeting'],
            ]);
        }

        return back()->with('success', $msg);
    }

    public function storeNomination(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('postDiscussion', $community);

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'author' => 'required|string|max:150',
            'reason' => 'required|string|max:1000',
            'book_id' => 'nullable|exists:books,id',
        ]);

        $nomination = BookNomination::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'book_id' => $validated['book_id'] ?? null,
            'title' => trim($validated['title']),
            'author' => trim($validated['author']),
            'reason' => trim($validated['reason']),
            'endorsements_count' => 1,
            'status' => 'pending',
        ]);

        // Auto-endorse by submitter
        BookNominationEndorsement::create([
            'nomination_id' => $nomination->id,
            'user_id' => $user->id,
        ]);

        $user->addPoints(10, 'nomination', "ترشيح كتاب «{$nomination->title}» لكتاب الشهر في «{$community->name}»");

        return back()->with('success', "تم تسجيل ترشيحك لكتاب «{$nomination->title}» بنجاح وإدراجه في قائمة مقترحات المجلس (+10 أثر)!");
    }

    public function endorseNomination(Request $request, BookNomination $nomination)
    {
        $user = Auth::user();

        $endorsement = BookNominationEndorsement::where('nomination_id', $nomination->id)
            ->where('user_id', $user->id)
            ->first();

        if ($endorsement) {
            $endorsement->delete();
            $nomination->decrement('endorsements_count');
            $msg = "أُلغي تأييدك لترشيح «{$nomination->title}».";
            $endorsed = false;
        } else {
            BookNominationEndorsement::create([
                'nomination_id' => $nomination->id,
                'user_id' => $user->id,
            ]);
            $nomination->increment('endorsements_count');
            $user->addPoints(5, 'bonus', "تأييد ترشيح كتاب «{$nomination->title}» في المجلس");
            $msg = "تم تسجيل تأييدك لترشيح «{$nomination->title}» ودعمه (+5 أثر)!";
            $endorsed = true;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'endorsed' => $endorsed,
                'endorsements_count' => $nomination->endorsements_count,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function addNominationToPoll(Request $request, BookNomination $nomination)
    {
        $user = Auth::user();
        $community = $nomination->community;
        Gate::authorize('coordinateReading', $community);

        $poll = $community->polls()->where('is_active', true)->first();
        if (! $poll) {
            $poll = BookPoll::create([
                'community_id' => $community->id,
                'title' => 'أيّ كتاب نقرأ في الشهر القادم؟',
                'is_active' => true,
                'closes_at' => Carbon::now()->addDays(14),
            ]);
        }

        $optionTitle = "{$nomination->title} · {$nomination->author}";

        $existingOption = $poll->options()->where('custom_title', $optionTitle)->first();
        if (! $existingOption) {
            $poll->options()->create([
                'book_id' => $nomination->book_id,
                'custom_title' => $optionTitle,
                'votes_count' => 0,
            ]);
        }

        $nomination->update(['status' => 'added_to_poll']);

        return back()->with('success', "تم إدراج «{$nomination->title}» كخيار رسمي في استطلاع كتاب الشهر القادم بنجاح!");
    }

    public function storeCommunityBadge(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('manageMembers', $community);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'award_type' => 'nullable|in:manual,automatic',
            'auto_rule_type' => 'nullable|string|max:50',
            'auto_rule_threshold' => 'nullable|integer|min:1',
        ]);

        CommunityBadge::create([
            'community_id' => $community->id,
            'name' => trim($validated['name']),
            'description' => trim($validated['description']),
            'icon' => $validated['icon'] ?? 'award',
            'color' => $validated['color'] ?? '#C3A15D',
            'award_type' => $validated['award_type'] ?? 'manual',
            'auto_rule_type' => $validated['auto_rule_type'] ?? null,
            'auto_rule_threshold' => $validated['auto_rule_threshold'] ?? 0,
        ]);

        return back()->with('success', "تم استحداث وسام «{$validated['name']}» الخاص بـ «{$community->name}» بنجاح!");
    }

    public function awardCommunityBadge(Request $request, CommunityBadge $badge)
    {
        $user = Auth::user();
        $community = $badge->community;
        Gate::authorize('manageMembers', $community);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:300',
        ]);

        $recipient = User::findOrFail($validated['user_id']);

        if ($badge->users()->where('user_id', $recipient->id)->exists()) {
            return back()->with('info', "القارئ «{$recipient->name}» يحمل هذا الوسام مسبقاً.");
        }

        $badge->users()->attach($recipient->id, [
            'awarded_by' => $user->id,
            'reason' => trim($validated['reason']),
            'awarded_at' => Carbon::now(),
        ]);

        $recipient->addPoints(50, 'badge_earned', "تقليد وسام «{$badge->name}» في «{$community->name}»");

        UserNotification::create([
            'user_id' => $recipient->id,
            'title' => "تتويج شرفي: نلت وسام «{$badge->name}»! 🏅",
            'body' => "منحتك إدارة «{$community->name}» وسام «{$badge->name}»: {$validated['reason']}",
            'icon' => 'award',
            'link' => route('communities.index', ['community_id' => $community->id]),
            'type' => 'community_badge',
        ]);

        return back()->with('success', "تم تتويج «{$recipient->name}» بوسام «{$badge->name}» وإشعاره وإضافة +50 نقطة أثر إلى رصيده بنجاح!");
    }

    public function destroy(Community $community)
    {
        $user = Auth::user();
        Gate::authorize('delete', $community);

        $name = $community->name;
        $community->delete();

        AuditLog::record(
            $user,
            'delete_community',
            $community,
            "تمت أرشفة/حذف المجلس «{$name}» (Soft Delete)."
        );

        return redirect()->route('communities.index')->with('success', "تمت أرشفة وحذف المجلس «{$name}» بأمان.");
    }
}
