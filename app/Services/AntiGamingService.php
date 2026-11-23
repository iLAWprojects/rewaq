<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Meeting;
use App\Models\PointTransaction;
use App\Models\User;
use Carbon\Carbon;

class AntiGamingService
{
    // Daily Point Limits per Activity Type
    const DAILY_CAP_READING_SESSION = 200;

    const DAILY_CAP_REVIEW = 60;

    const DAILY_CAP_DISCUSSION = 80;

    const DAILY_TOTAL_CAP = 1500;

    // Physical human reading threshold (pages per minute)
    const MAX_REALISTIC_PAGES_PER_MINUTE = 3.0;

    // الحد الأدنى المنطقي لقراءة الصفحة الواحدة (دقيقتان) مع تسامح 25% للقرّاء الأسرع
    const MIN_MINUTES_PER_PAGE = 2;

    const PACE_TOLERANCE = 0.25;

    /**
     * أقل عدد دقائق مقبول لقراءة هذا العدد من الصفحات.
     */
    public function minimumMinutesFor(int $pages): int
    {
        return (int) ceil($pages * self::MIN_MINUTES_PER_PAGE * (1 - self::PACE_TOLERANCE));
    }

    /**
     * هل وقت القراءة منطقي لعدد الصفحات؟ يرجع سبب الرفض أو null إن كان منطقيًا.
     */
    public function paceViolation(int $pages, int $minutes): ?string
    {
        $required = $this->minimumMinutesFor($pages);

        if ($pages > 0 && $minutes < $required) {
            return "وقت القراءة غير منطقي لعدد الصفحات: {$pages} صفحة تحتاج {$required} دقيقة على الأقل بينما سُجّلت {$minutes} دقيقة.";
        }

        return null;
    }

    /**
     * Check and enforce daily points caps
     */
    public function checkDailyCap(User $user, string $activityType, int $requestedPoints): array
    {
        $today = Carbon::today();

        // 1. Calculate today's total valid points
        $totalToday = (int) $user->pointTransactions()
            ->where('is_valid', true)
            ->whereDate('created_at', $today)
            ->sum('points');

        if ($totalToday >= self::DAILY_TOTAL_CAP) {
            return [
                'allowed_points' => 0,
                'capped' => true,
                'reason' => 'تم بلوغ الحد الأقصى اليومي الشامل للنقاط ('.self::DAILY_TOTAL_CAP.' نقطة). حفظ السجل متاح دون احتساب أثر إضافي.',
            ];
        }

        // 2. Specific activity daily cap
        $activityCap = match ($activityType) {
            'reading_session' => self::DAILY_CAP_READING_SESSION,
            'review' => self::DAILY_CAP_REVIEW,
            'discussion' => self::DAILY_CAP_DISCUSSION,
            default => null,
        };

        if ($activityCap !== null) {
            $activityToday = (int) $user->pointTransactions()
                ->where('is_valid', true)
                ->where('activity_type', $activityType)
                ->whereDate('created_at', $today)
                ->sum('points');

            $availableForActivity = max(0, $activityCap - $activityToday);
            if ($availableForActivity <= 0) {
                return [
                    'allowed_points' => 0,
                    'capped' => true,
                    'reason' => "تم بلوغ الحد اليومي لنشاط «{$activityType}» ({$activityCap} نقطة).",
                ];
            }

            $overallAvailable = max(0, self::DAILY_TOTAL_CAP - $totalToday);
            $allowed = min($requestedPoints, $availableForActivity, $overallAvailable);

            return [
                'allowed_points' => $allowed,
                'capped' => $allowed < $requestedPoints,
                'reason' => $allowed < $requestedPoints ? 'تم تقليص النقاط لتتوافق مع الحد الأقصى اليومي للنظام.' : null,
            ];
        }

        // General overall cap check
        $overallAvailable = max(0, self::DAILY_TOTAL_CAP - $totalToday);
        $allowed = min($requestedPoints, $overallAvailable);

        return [
            'allowed_points' => $allowed,
            'capped' => $allowed < $requestedPoints,
            'reason' => $allowed < $requestedPoints ? 'تم تقليص النقاط لتتوافق مع الحد الأقصى اليومي.' : null,
        ];
    }

    /**
     * Verify reading session credibility and detect speed anomalies or duplicates
     */
    public function verifyReadingSession(User $user, int $minutes, int $pages, ?Book $book = null, int $newPage = 0): array
    {
        // 1. Basic sanity checks
        if ($minutes <= 0) {
            return [
                'is_valid' => false,
                'anomalous' => true,
                'reason' => 'مدة جلسة القراءة يجب أن تكون أكبر من صفر دقيقة.',
            ];
        }

        if ($pages <= 0) {
            return [
                'is_valid' => false,
                'anomalous' => true,
                'reason' => 'عدد الصفحات المقروءة يجب أن يكون قيمة موجبة.',
            ];
        }

        // 2. Reading speed check (pages per minute)
        $speed = round($pages / max(1, $minutes), 2);
        if ($speed > self::MAX_REALISTIC_PAGES_PER_MINUTE) {
            $this->logAnomaly(
                $user,
                'reading_session',
                "تم رصد سرعة قراءة شاذة غير واقعية: {$speed} صفحة/دقيقة ({$pages} صفحة في {$minutes} دقيقة).",
                ['pages' => $pages, 'minutes' => $minutes, 'speed' => $speed]
            );

            return [
                'is_valid' => false,
                'anomalous' => true,
                'reason' => "سرعة القراءة المسجلة ({$speed} صفحة/دقيقة) تتجاوز الحد الطبيعي الموثوق (الحد الأقصى: ".self::MAX_REALISTIC_PAGES_PER_MINUTE.' صفحة/دقيقة). أُحيل النشاط للمراجعة والتدقيق.',
            ];
        }

        // 3. Duplicate event within 60 seconds check
        $recentIdentical = PointTransaction::where('user_id', $user->id)
            ->where('activity_type', 'reading_session')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->where('description', 'like', "%{$pages}%")
            ->exists();

        if ($recentIdentical) {
            return [
                'is_valid' => false,
                'anomalous' => true,
                'reason' => 'تم رصد تسجيل جلسة قراءة مكررة خلال نافذة زمنية وجيزة جداً (منع التكرار الآلي).',
            ];
        }

        return [
            'is_valid' => true,
            'anomalous' => false,
            'speed' => $speed,
        ];
    }

    /**
     * Prevent duplicate book finished points in the same day
     */
    public function verifyBookFinished(User $user, Book $book): array
    {
        $alreadyFinishedToday = PointTransaction::where('user_id', $user->id)
            ->where('activity_type', 'book_finished')
            ->where('reference_type', Book::class)
            ->where('reference_id', $book->id)
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($alreadyFinishedToday) {
            return [
                'is_valid' => false,
                'duplicate' => true,
                'reason' => "تم احتساب نقاط إتمام كتاب «{$book->title}» مسبقاً لهذا اليوم. يُمنع تكرار الإنجاز لنفس الكتاب في اليوم ذاته.",
            ];
        }

        return ['is_valid' => true, 'duplicate' => false];
    }

    /**
     * Prevent duplicate meeting check-in points
     */
    public function verifyMeetingCheckin(User $user, Meeting $meeting): array
    {
        $alreadyCheckedIn = PointTransaction::where('user_id', $user->id)
            ->where('activity_type', 'meeting_checkin')
            ->where('reference_type', Meeting::class)
            ->where('reference_id', $meeting->id)
            ->exists();

        if ($alreadyCheckedIn) {
            return [
                'is_valid' => false,
                'duplicate' => true,
                'reason' => "تم تسجيل حضورك مسبقاً في لقاء «{$meeting->title}» واحتساب أثره.",
            ];
        }

        return ['is_valid' => true, 'duplicate' => false];
    }

    /**
     * Log anomalous activity for moderator and audit review
     */
    public function logAnomaly(User $user, string $activityType, string $reason, array $metadata = []): void
    {
        AuditLog::record(
            $user,
            'anomalous_activity_detected',
            $user,
            "رصد نشاط شاذ ({$activityType}): {$reason}"
        );
    }
}
