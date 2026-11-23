<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\SocialActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * عرض الملف المعرفي للقارئ
     */
    public function show(?User $user = null)
    {
        $viewer = Auth::user();

        // إذا لم يُحدد مستخدم، نعرض الملف الشخصي للمستخدم الحالي
        if (! $user || ! $user->exists) {
            if (! $viewer) {
                return redirect()->route('login');
            }
            $user = $viewer;
        }

        $isOwnProfile = $viewer && $viewer->id === $user->id;
        $isFollowing = $viewer && ! $isOwnProfile && $viewer->isFollowing($user);

        // فحص حالة الحظر
        $hasBlockedViewer = $viewer && ! $isOwnProfile && $user->hasBlocked($viewer);
        $isBlockedByViewer = $viewer && ! $isOwnProfile && $viewer->hasBlocked($user);
        $isBlockedWith = $hasBlockedViewer || $isBlockedByViewer;

        // حالة الصداقة
        $friendship = ($viewer && ! $isOwnProfile) ? $viewer->friendshipWith($user) : null;
        $friendshipStatus = $friendship ? $friendship->status : null;

        // بيانات الصداقة والطلبات في حال كان الملف الشخصي للمستخدم الحالي
        $incomingRequests = collect();
        $sentRequests = collect();
        $friendsList = collect();
        $blockedUsers = collect();

        if ($isOwnProfile) {
            $incomingRequests = $user->receivedFriendRequests()->where('status', Friendship::STATUS_PENDING)->with('sender')->get();
            $sentRequests = $user->sentFriendRequests()->where('status', Friendship::STATUS_PENDING)->with('recipient')->get();
            $friendsList = $user->friends();
            $blockedUsers = $user->blockedUsers()->get();
        }

        // تحميل العلاقات الضرورية
        $user->loadMissing([
            'badges',
            'communityBadges',
            'followers',
            'following',
            'libraries',
            'communities',
        ]);

        $activeBook = $user->activeBook();
        $reviews = $user->reviews()->with('book')->latest()->take(8)->get();
        $reflections = $user->reflections()->with('book')->latest()->take(8)->get();
        $readingSessions = $user->readingSessions()->with('book')->latest()->take(10)->get();

        // أنشطة القارئ المنظمة مع تطبيق فلترة الخصوصية ومراعاة الحظر
        $userActivities = ($hasBlockedViewer)
            ? collect()
            : $user->socialActivities()->visibleForViewer($viewer)->latest()->take(20)->get();

        // أنشطة رفقاء القراءة والأصدقاء الذين يتابعهم (تظهر في خلاصة الملف الخاص)
        $friendsActivities = collect();
        if ($isOwnProfile) {
            $feedUserIds = $user->friends()->pluck('id')
                ->merge($user->following->pluck('id'))
                ->unique();

            if ($feedUserIds->isNotEmpty()) {
                $friendsActivities = SocialActivity::whereIn('user_id', $feedUserIds)
                    ->visibleForViewer($user)
                    ->with('user')
                    ->latest()
                    ->take(20)
                    ->get();
            }
        }

        return view('pages.profile', compact(
            'user',
            'viewer',
            'isOwnProfile',
            'isFollowing',
            'hasBlockedViewer',
            'isBlockedByViewer',
            'isBlockedWith',
            'friendship',
            'friendshipStatus',
            'incomingRequests',
            'sentRequests',
            'friendsList',
            'blockedUsers',
            'activeBook',
            'reviews',
            'reflections',
            'readingSessions',
            'userActivities',
            'friendsActivities'
        ));
    }

    /**
     * تحديث خصوصية الأنشطة المعرفية المعروضة
     */
    public function updateActivityPrivacy(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $hiddenTypes = $request->input('hidden_types', []);
        if (! is_array($hiddenTypes)) {
            $hiddenTypes = [];
        }

        $user->updateActivityPrivacy($hiddenTypes);

        return back()->with('success', 'تم حفظ تفضيلات خصوصية أنشطتك المعرفية بنجاح ✦');
    }

    /**
     * متابعة أو إلغاء متابعة رفيق قراءة
     */
    public function toggleFollow(User $user)
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return redirect()->route('login');
        }

        if ($viewer->id === $user->id) {
            return back()->with('error', 'لا يمكنك متابعة نفسك.');
        }

        if ($viewer->isFollowing($user)) {
            $viewer->unfollow($user);

            return back()->with('info', "ألغيت متابعة «{$user->name}».");
        } else {
            $viewer->follow($user);

            return back()->with('success', "بدأت الآن بمتابعة «{$user->name}» 🤝");
        }
    }

    /**
     * تحديث النبذة الشخصية والهدف القرائي
     */
    public function updateBio(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'bio' => 'nullable|string|max:500',
            'annual_goal' => 'nullable|integer|min:1|max:365',
            'avatar' => 'nullable|string|max:10',
        ]);

        $user->update([
            'bio' => $validated['bio'] ?? $user->bio,
            'annual_goal' => $validated['annual_goal'] ?? $user->annual_goal,
            'avatar' => $validated['avatar'] ?? $user->avatar,
        ]);

        return back()->with('success', 'تم تحديث ملفك المعرفي بنجاح ✦');
    }

    /**
     * تشجيع وإبداء الإعجاب بنشاط رفيق قراءة
     */
    public function cheerActivity(SocialActivity $activity)
    {
        $activity->increment('cheers_count');

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'cheers_count' => $activity->cheers_count,
            ]);
        }

        return back()->with('success', 'أرسلت تشجيعاً طيباً إلى رفيق القراءة! ✨');
    }
}
