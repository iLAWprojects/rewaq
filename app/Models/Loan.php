<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = [
        'book_copy_id',
        'user_id',
        'borrower_name',
        'status',
        'qr_token',
        'swap_book_title',
        'handshake_at',
        'requested_at',
        'borrowed_at',
        'due_date',
        'returned_at',
        'handover_condition',
        'handover_notes',
        'handover_inspector_id',
        'handover_at',
        'return_condition',
        'return_notes',
        'return_inspector_id',
        'closed_at',
        'closed_by',
        'rejection_reason',
        'renewal_count',
        'max_renewals',
        'notes',
        'notified_due_soon_at',
        'notified_overdue_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'borrowed_at' => 'datetime',
        'due_date' => 'date',
        'returned_at' => 'datetime',
        'handover_at' => 'datetime',
        'closed_at' => 'datetime',
        'handshake_at' => 'datetime',
        'notified_due_soon_at' => 'datetime',
        'notified_overdue_at' => 'datetime',
        'renewal_count' => 'integer',
        'max_renewals' => 'integer',
    ];

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handoverInspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_inspector_id');
    }

    public function returnInspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_inspector_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['active', 'overdue']) && $this->due_date && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function isDueSoon(): bool
    {
        if ($this->status !== 'active' || ! $this->due_date) {
            return false;
        }

        $today = Carbon::today();

        return $this->due_date->greaterThanOrEqualTo($today) && $today->diffInDays($this->due_date) <= 3;
    }

    public function daysRemaining(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        $today = Carbon::today();

        return (int) $today->diffInDays($this->due_date, false);
    }

    public function canRenew(): bool
    {
        $max = $this->max_renewals ?: ($this->copy?->library?->max_renewals ?: 2);

        return $this->status === 'active' && $this->renewal_count < $max;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'طلب استعارة جديد ⏳',
            'approved' => 'تمت الموافقة · جاهز للتسليم ✦',
            'active' => 'إعارة نشطة 🌿',
            'returned' => 'مُسترجع · قيد الفحص 📋',
            'closed' => 'مكتمل ومغلق ✓',
            'rejected' => 'طلب مرفوض ✕',
            'overdue' => 'متأخرة عن الاستحقاق ⚠️',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'badge-pending',
            'approved' => 'badge-approved',
            'active' => 'badge-active',
            'returned' => 'badge-returned',
            'closed' => 'badge-closed',
            'rejected' => 'badge-rejected',
            'overdue' => 'badge-overdue',
            default => 'badge-default',
        };
    }

    public function getHandoverConditionLabelAttribute(): string
    {
        return match ($this->handover_condition) {
            'mint' => 'ممتازة ✦',
            'good' => 'جيدة جداً 🌿',
            'fair' => 'مقبولة 📖',
            'damaged' => 'بها تلف أو انثناء ⚠️',
            default => $this->handover_condition ? $this->handover_condition : 'غير محددة',
        };
    }

    public function getReturnConditionLabelAttribute(): string
    {
        return match ($this->return_condition) {
            'mint' => 'ممتازة ✦',
            'good' => 'جيدة جداً 🌿',
            'fair' => 'مقبولة 📖',
            'damaged' => 'تالفة / تحتاج صيانة ⚠️',
            default => $this->return_condition ? $this->return_condition : 'غير محددة',
        };
    }

    public function isConditionDegraded(): bool
    {
        $levels = ['mint' => 4, 'good' => 3, 'fair' => 2, 'damaged' => 1];
        $initial = $levels[$this->handover_condition ?? $this->copy?->condition ?? 'good'] ?? 3;
        $final = $levels[$this->return_condition ?? 'good'] ?? 3;

        return $final < $initial;
    }

    /**
     * Dispatch due soon and overdue notifications based on library policy.
     */
    public static function checkAndSendDueNotifications(): int
    {
        $count = 0;
        $activeLoans = self::whereIn('status', ['active', 'overdue'])
            ->with(['copy.book', 'copy.library', 'user'])
            ->get();

        foreach ($activeLoans as $loan) {
            if (! $loan->user || ! $loan->copy || ! $loan->due_date) {
                continue;
            }

            $bookTitle = $loan->copy->book?->title ?? 'الكتاب';
            $libraryName = $loan->copy->library?->name ?? 'المكتبة';
            $libraryId = $loan->copy->library_id;

            // 1. Due Soon (Within 3 days and not already notified)
            if ($loan->isDueSoon() && ! $loan->notified_due_soon_at) {
                $days = $loan->daysRemaining();
                $dueStr = $loan->due_date->format('Y-m-d');
                $body = "تذكير: موعد إرجاع كتاب «{$bookTitle}» في «{$libraryName}» يقترب (متبقي {$days} أيام حتى {$dueStr}). يمكنك طلب تمديد الإعارة إن رغبت ولم تكن هناك قائمة انتظار.";

                UserNotification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'تذكير بقرب موعد استحقاق الإعارة ⏳',
                    'body' => $body,
                    'icon' => 'clock',
                    'link' => route('libraries.index', ['library_id' => $libraryId, 'tab' => 'loans']),
                    'type' => 'loan',
                ]);

                $loan->update(['notified_due_soon_at' => Carbon::now()]);
                $count++;
            }

            // 2. Overdue (Past due date and not notified in last 3 days)
            if ($loan->isOverdue()) {
                $shouldNotify = false;
                if (! $loan->notified_overdue_at) {
                    $shouldNotify = true;
                } elseif ($loan->notified_overdue_at->diffInDays(Carbon::now()) >= 3) {
                    $shouldNotify = true;
                }

                if ($shouldNotify) {
                    $daysLate = abs($loan->daysRemaining() ?? 1);
                    $body = "تنبيه تأخير: تجاوزت موعد إرجاع كتاب «{$bookTitle}» في «{$libraryName}» بـ {$daysLate} يوماً. يرجى المبادرة بإرجاع النسخة لتيسير إعارتها للقراء الآخرين.";

                    UserNotification::create([
                        'user_id' => $loan->user_id,
                        'title' => 'تنبيه تأخير استحقاق الإعارة ⚠️',
                        'body' => $body,
                        'icon' => 'alert-triangle',
                        'link' => route('libraries.index', ['library_id' => $libraryId, 'tab' => 'loans']),
                        'type' => 'loan',
                    ]);

                    $loan->update([
                        'notified_overdue_at' => Carbon::now(),
                        'status' => 'overdue',
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }
}
