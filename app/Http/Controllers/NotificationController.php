<?php

namespace App\Http\Controllers;

use App\Models\CommunityJoinRequest;
use App\Models\NotificationDeliveryLog;
use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = UserNotification::where('user_id', $user->id);

        // Status Filter
        if ($request->filled('status')) {
            if ($request->input('status') === 'unread') {
                $query->where('is_read', false);
            } elseif ($request->input('status') === 'read') {
                $query->where('is_read', true);
            }
        }

        // Category Filter
        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        // Priority Filter
        if ($request->filled('priority') && $request->input('priority') !== 'all') {
            $query->where('priority', $request->input('priority'));
        }

        // Search Query
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            });
        }

        $notifications = $query->latest()->get();
        $allUserNotifications = UserNotification::where('user_id', $user->id)->get();
        $activeBook = $user->activeBook();

        // Calculate counts by category
        $categoryCounts = [];
        foreach (UserNotification::CATEGORIES as $key => $label) {
            $categoryCounts[$key] = $allUserNotifications->where('category', $key)->count();
        }

        $unreadCount = $allUserNotifications->where('is_read', false)->count();
        $isInQuietHours = $user->isInQuietHours();

        // Recent Delivery Logs summary (privacy preserving: channel, status, timestamp)
        $recentDeliveryLogs = NotificationDeliveryLog::where('user_id', $user->id)
            ->latest()
            ->take(8)
            ->get();

        return view('pages.notifications', compact(
            'notifications',
            'user',
            'activeBook',
            'categoryCounts',
            'unreadCount',
            'isInQuietHours',
            'recentDeliveryLogs'
        ));
    }

    /**
     * أحدث الإشعارات لقائمة الإشعارات الذكية في الهيدر.
     */
    public function feed(): JsonResponse
    {
        $userId = Auth::id();

        $items = UserNotification::where('user_id', $userId)
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (UserNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'icon' => $n->icon ?: 'bell',
                'link' => $n->url !== '#' ? $n->url : null,
                'is_read' => (bool) $n->is_read,
                'time' => $n->created_at?->locale('ar')->diffForHumans(),
                'actions' => $this->getNotificationActions($n),
            ]);

        return response()->json([
            'items' => $items,
            'unread_count' => UserNotification::where('user_id', $userId)->where('is_read', false)->count(),
        ]);
    }

    /**
     * إجراءات سريعة لطلبات الانضمام والتنبيهات التفاعلية داخل شريط الإشعارات.
     */
    protected function getNotificationActions(UserNotification $n): ?array
    {
        if (str_contains($n->title, 'طلب انضمام') || $n->type === 'community_join_request') {
            $communityId = $n->metadata['community_id'] ?? null;
            $requestId = $n->metadata['request_id'] ?? null;

            if (! $communityId && $n->link) {
                parse_str(parse_url($n->link, PHP_URL_QUERY) ?? '', $queryParams);
                $communityId = $queryParams['community_id'] ?? null;
            }

            $joinRequest = null;
            if ($requestId) {
                $joinRequest = CommunityJoinRequest::where('id', $requestId)->where('status', 'pending')->first();
            } elseif ($communityId) {
                $joinRequest = CommunityJoinRequest::where('community_id', $communityId)->where('status', 'pending')->latest()->first();
            }

            if ($joinRequest) {
                return [
                    'type' => 'join_request',
                    'request_id' => $joinRequest->id,
                    'approve_url' => route('communities.approveJoinRequest', $joinRequest->id),
                    'reject_url' => route('communities.rejectJoinRequest', $joinRequest->id),
                    'user_name' => $joinRequest->user?->name,
                ];
            }
        }

        return null;
    }

    /**
     * تعليم الإشعارات المعروضة كمشاهَدة (تقليل العدّاد بمجرد فتح القائمة).
     */
    public function markSeen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|max:50',
            'ids.*' => 'integer',
        ]);

        $userId = Auth::id();

        UserNotification::where('user_id', $userId)
            ->whereIn('id', $validated['ids'])
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'unread_count' => UserNotification::where('user_id', $userId)->where('is_read', false)->count(),
        ]);
    }

    public function markAsRead(UserNotification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        $notification->markAsRead();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_read' => true,
                'read_at' => $notification->read_at?->toIso8601String(),
                'unread_count' => UserNotification::where('user_id', Auth::id())->where('is_read', false)->count(),
            ]);
        }

        return back()->with('success', 'تم تعيين الإشعار كمقروء.');
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        UserNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'تم تعيين كافة الإشعارات كمقروءة بنجاح.');
    }

    public function updatePreferences(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'timezone' => 'nullable|string|max:64',
            'quiet_hours_enabled' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|string',
            'quiet_hours_end' => 'nullable|string',
            'push_opt_in' => 'nullable|boolean',
            'preferences' => 'nullable|array',
        ]);

        $user->update([
            'timezone' => $validated['timezone'] ?? $user->timezone ?? 'Asia/Riyadh',
            'quiet_hours_enabled' => $request->has('quiet_hours_enabled'),
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? '22:00',
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? '08:00',
            'push_opt_in' => $request->has('push_opt_in') || $request->boolean('push_opt_in'),
        ]);

        if ($request->has('preferences') && is_array($request->input('preferences'))) {
            $user->updateNotificationPreferences($request->input('preferences'));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ تفضيلات الإشعارات بنجاح.',
                'preferences' => $user->notificationPreferences(),
            ]);
        }

        return back()->with('success', 'تم حفظ تفضيلات الإشعارات بنجاح.');
    }

    public function subscribePush(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'p256dh' => 'nullable|string',
            'auth_token' => 'nullable|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $sub = $this->notificationService->subscribe(Auth::user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل اشتراك إشعارات المتصفح والـ Push بنجاح.',
            'subscription_id' => $sub->id,
            'opt_in' => true,
        ]);
    }

    public function unsubscribePush(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $result = $this->notificationService->unsubscribe(Auth::user(), $validated['endpoint']);

        return response()->json([
            'success' => $result,
            'message' => 'تم إلغاء تفعيل هذا الجهاز من إشعارات المتصفح.',
        ]);
    }

    public function togglePushOptIn(Request $request)
    {
        $user = Auth::user();
        $newOptIn = $request->has('push_opt_in') ? $request->boolean('push_opt_in') : ! $user->push_opt_in;

        $this->notificationService->setPushOptIn($user, $newOptIn);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'push_opt_in' => $newOptIn,
                'message' => $newOptIn ? 'تم تفعيل إشعارات الـ Push بنجاح.' : 'تم إيقاف إشعارات الـ Push.',
            ]);
        }

        return back()->with('success', $newOptIn ? 'تم تفعيل إشعارات الويب والـ Push.' : 'تم إيقاف إشعارات الـ Push.');
    }
}
