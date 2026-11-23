<?php

namespace App\Services;

use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserPushSubscription;
use Carbon\CarbonInterface;

class NotificationService
{
    /**
     * Dispatch notification with category preferences, quiet hours timezone checks,
     * low-priority push filtering, and privacy-preserving delivery logging.
     */
    public function send(User $user, array $params): ?UserNotification
    {
        $category = $params['category'] ?? UserNotification::CATEGORY_SYSTEM;
        $priority = $params['priority'] ?? UserNotification::PRIORITY_NORMAL;
        $title = $params['title'] ?? 'إشعار جديد';
        $body = $params['body'] ?? '';
        $icon = $params['icon'] ?? null;
        $link = $params['link'] ?? null;
        $type = $params['type'] ?? $category;
        $metadata = $params['metadata'] ?? [];
        $forcePush = (bool) ($params['force_push'] ?? false);
        $skipInApp = (bool) ($params['skip_in_app'] ?? false);
        $referenceTime = $params['reference_time'] ?? null;

        // Ensure category and priority are canonical
        if (! array_key_exists($category, UserNotification::CATEGORIES)) {
            $category = UserNotification::CATEGORY_SYSTEM;
        }
        if (! array_key_exists($priority, UserNotification::PRIORITIES)) {
            $priority = UserNotification::PRIORITY_NORMAL;
        }

        $notification = null;

        // 1. In-App Notification Channel
        if (! $skipInApp) {
            if ($user->wantsNotification($category, 'in_app')) {
                $notification = UserNotification::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'body' => $body,
                    'icon' => $icon ?: 'bell',
                    'link' => $link,
                    'type' => $type,
                    'category' => $category,
                    'priority' => $priority,
                    'is_read' => false,
                    'read_at' => null,
                    'metadata' => $metadata,
                ]);

                $this->logDelivery(
                    user: $user,
                    notificationId: $notification->id,
                    channel: 'in_app',
                    category: $category,
                    priority: $priority,
                    status: 'delivered',
                    responseCode: '200_STORED',
                    metadata: ['has_link' => ! empty($link)]
                );
            } else {
                // User disabled in-app notifications for this category
                $this->logDelivery(
                    user: $user,
                    notificationId: null,
                    channel: 'in_app',
                    category: $category,
                    priority: $priority,
                    status: 'suppressed_preference',
                    responseCode: '403_MUTED',
                    metadata: ['reason' => 'category_in_app_disabled']
                );
            }
        }

        // 2. Web Push / FCM Notification Channel
        $this->evaluateAndSendPush(
            user: $user,
            notification: $notification,
            category: $category,
            priority: $priority,
            title: $title,
            body: $body,
            link: $link,
            forcePush: $forcePush,
            referenceTime: $referenceTime
        );

        return $notification;
    }

    /**
     * Evaluate and dispatch Web Push / FCM notifications
     */
    protected function evaluateAndSendPush(
        User $user,
        ?UserNotification $notification,
        string $category,
        string $priority,
        string $title,
        string $body,
        ?string $link,
        bool $forcePush = false,
        ?CarbonInterface $referenceTime = null
    ): void {
        $activeSubscriptions = $user->pushSubscriptions()->where('is_active', true)->get();

        // 1. Explicit Opt-in requirement
        if (! $user->push_opt_in) {
            if ($forcePush || $activeSubscriptions->isNotEmpty()) {
                $this->logDelivery(
                    user: $user,
                    notificationId: $notification?->id,
                    channel: 'web_push',
                    category: $category,
                    priority: $priority,
                    status: 'suppressed_preference',
                    responseCode: '403_NOT_OPTED_IN',
                    metadata: ['reason' => 'push_opt_in_false']
                );
            }

            return;
        }

        // No active endpoints registered
        if ($activeSubscriptions->isEmpty()) {
            return;
        }

        // 2. Suppress low-priority social pings by default
        // Requirement: "لا تُرسل Push للنشاط الاجتماعي منخفض الأهمية افتراضياً"
        if ($priority === UserNotification::PRIORITY_LOW && ! $forcePush) {
            $this->logDelivery(
                user: $user,
                notificationId: $notification?->id,
                channel: 'web_push',
                category: $category,
                priority: $priority,
                status: 'skipped_low_priority',
                responseCode: '200_SUPPRESSED_LOW_PRIORITY',
                metadata: ['reason' => 'low_priority_social_suppressed']
            );

            return;
        }

        // 3. Category preference for Push
        if (! $user->wantsNotification($category, 'push') && ! $forcePush) {
            $this->logDelivery(
                user: $user,
                notificationId: $notification?->id,
                channel: 'web_push',
                category: $category,
                priority: $priority,
                status: 'suppressed_preference',
                responseCode: '403_CATEGORY_MUTED',
                metadata: ['reason' => 'category_push_disabled']
            );

            return;
        }

        // 4. Quiet Hours evaluation according to user's timezone
        // Requirement: "Quiet Hours بحسب Timezone المستخدم"
        $inQuietHours = $user->isInQuietHours($referenceTime);
        $isUrgent = ($priority === UserNotification::PRIORITY_URGENT);

        if ($inQuietHours && ! $isUrgent && ! $forcePush) {
            $this->logDelivery(
                user: $user,
                notificationId: $notification?->id,
                channel: 'web_push',
                category: $category,
                priority: $priority,
                status: 'suppressed_quiet_hours',
                responseCode: '422_QUIET_HOURS',
                metadata: [
                    'timezone' => $user->timezone ?? 'Asia/Riyadh',
                    'quiet_start' => $user->quiet_hours_start,
                    'quiet_end' => $user->quiet_hours_end,
                ]
            );

            return;
        }

        // 5. Successful push dispatch simulation / delivery
        // Privacy: Record delivery outcome, device count, without storing sensitive title/body text.
        $this->logDelivery(
            user: $user,
            notificationId: $notification?->id,
            channel: 'web_push',
            category: $category,
            priority: $priority,
            status: 'delivered',
            responseCode: '200_DISPATCHED',
            metadata: [
                'devices_count' => $activeSubscriptions->count(),
                'bypassed_quiet_hours' => ($inQuietHours && $isUrgent),
                'urgent' => $isUrgent,
            ]
        );
    }

    /**
     * Privacy-preserving delivery logging: Records channel, category, priority, status and non-sensitive metadata.
     * Strictly omits sensitive content (title and body).
     */
    protected function logDelivery(
        User $user,
        ?int $notificationId,
        string $channel,
        string $category,
        string $priority,
        string $status,
        string $responseCode,
        array $metadata = []
    ): NotificationDeliveryLog {
        if ($notificationId && $channel === 'in_app') {
            $existing = NotificationDeliveryLog::where('notification_id', $notificationId)
                ->where('channel', 'in_app')
                ->first();
            if ($existing) {
                $existing->update([
                    'category' => $category,
                    'priority' => $priority,
                    'status' => $status,
                    'response_code' => $responseCode,
                    'metadata' => array_merge($existing->metadata ?? [], $metadata),
                ]);

                return $existing;
            }
        }

        return NotificationDeliveryLog::create([
            'user_id' => $user->id,
            'notification_id' => $notificationId,
            'channel' => $channel,
            'category' => $category,
            'priority' => $priority,
            'status' => $status,
            'response_code' => $responseCode,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Register or update a user's Web Push subscription and ensure opt-in is enabled.
     */
    public function subscribe(User $user, array $deviceData): UserPushSubscription
    {
        $user->update(['push_opt_in' => true]);

        return UserPushSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $deviceData['endpoint'],
            ],
            [
                'p256dh' => $deviceData['p256dh'] ?? null,
                'auth_token' => $deviceData['auth_token'] ?? null,
                'device_name' => $deviceData['device_name'] ?? 'متصفح الويب',
                'is_active' => true,
            ]
        );
    }

    /**
     * Unsubscribe a specific endpoint
     */
    public function unsubscribe(User $user, string $endpoint): bool
    {
        $sub = UserPushSubscription::where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->first();

        if ($sub) {
            $sub->update(['is_active' => false]);

            return true;
        }

        return false;
    }

    /**
     * Toggle or set the user's Push Opt-in status
     */
    public function setPushOptIn(User $user, bool $optIn): void
    {
        $user->update(['push_opt_in' => $optIn]);
    }
}
