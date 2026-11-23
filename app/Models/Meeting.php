<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    // 8 Supported Meeting Types
    const TYPE_IN_PERSON = 'in_person';

    const TYPE_REMOTE = 'remote';

    const TYPE_HYBRID = 'hybrid';

    const TYPE_OPEN_DISCUSSION = 'open_discussion';

    const TYPE_AUTHOR_MEETUP = 'author_meetup';

    const TYPE_BOOK_REVIEW = 'book_review';

    const TYPE_READING_WORKSHOP = 'reading_workshop';

    const TYPE_MANAGERS_MEETING = 'managers_meeting';

    protected $fillable = [
        'community_id',
        'book_id',
        'coordinator_id',
        'next_book_id',
        'title',
        'description',
        'meeting_date',
        'starts_at',
        'ends_at',
        'end_time',
        'timezone',
        'meeting_type',
        'location',
        'link',
        'capacity',
        'registration_deadline',
        'cover_image',
        'discussion_questions',
        'instructions',
        'meeting_minutes',
        'photos',
        'key_ideas',
        'recommendations',
        'rating',
        'ratings_count',
        'is_past',
        'summary_notes',
        'audio_url',
    ];

    protected $casts = [
        'meeting_date' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'end_time' => 'datetime',
        'registration_deadline' => 'datetime',
        'capacity' => 'integer',
        'is_past' => 'boolean',
        'rating' => 'float',
        'ratings_count' => 'integer',
        'discussion_questions' => 'array',
        'photos' => 'array',
    ];

    /**
     * لقاءات تقويمي: اللقاءات العامة (بلا نادٍ) + لقاءات الأندية التي أنا عضو فيها + ما أنسّقه أنا.
     * الأدمن يرى الكل للإشراف.
     */
    public function scopeVisibleOnCalendarOf($query, ?User $user)
    {
        if ($user && $user->isSuperAdmin()) {
            return $query;
        }

        $clubIds = $user ? $user->communities()->pluck('communities.id')->all() : [];

        return $query->where(function ($w) use ($clubIds, $user) {
            $w->whereNull('community_id')->orWhereIn('community_id', $clubIds);
            if ($user) {
                $w->orWhere('coordinator_id', $user->id);
            }
        });
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function nextBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'next_book_id');
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(MeetingRsvp::class);
    }

    /**
     * Confirmed attendees (Going or Attended).
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingRsvp::class)
            ->whereIn('status', [MeetingRsvp::STATUS_GOING, 'attending', MeetingRsvp::STATUS_ATTENDED]);
    }

    /**
     * Waitlist attendees strictly ordered by FIFO (Earliest first).
     */
    public function waitlist(): HasMany
    {
        return $this->hasMany(MeetingRsvp::class)
            ->where('status', MeetingRsvp::STATUS_WAITLIST)
            ->orderBy('created_at', 'asc');
    }

    public function attendingCount(): int
    {
        return $this->attendees()->count();
    }

    public function waitlistCount(): int
    {
        return $this->waitlist()->count();
    }

    /**
     * Check if meeting has reached full seating capacity.
     */
    public function isFull(): bool
    {
        if (! $this->capacity) {
            return false;
        }

        return $this->attendingCount() >= $this->capacity;
    }

    /**
     * Check if registration is open based on status and deadline.
     */
    public function isRegistrationOpen(): bool
    {
        if ($this->is_past) {
            return false;
        }

        if ($this->registration_deadline && $this->registration_deadline->isPast()) {
            return false;
        }

        return true;
    }

    public function isUserAttending(int $userId): bool
    {
        return $this->rsvps()
            ->where('user_id', $userId)
            ->whereIn('status', [MeetingRsvp::STATUS_GOING, 'attending', MeetingRsvp::STATUS_ATTENDED])
            ->exists();
    }

    public function userRsvp(?User $user): ?MeetingRsvp
    {
        if (! $user) {
            return null;
        }

        return $this->rsvps()->where('user_id', $user->id)->first();
    }

    /**
     * FIFO Automatic Promotion: promotes the earliest user in the waitlist.
     */
    public function promoteNextInWaitlist(): ?MeetingRsvp
    {
        // First In First Out (FIFO)
        $nextInWaitlist = $this->waitlist()->first();

        if ($nextInWaitlist) {
            $nextInWaitlist->status = 'attending';
            $nextInWaitlist->generateOrRefreshQrToken();
            $nextInWaitlist->save();

            UserNotification::create([
                'user_id' => $nextInWaitlist->user_id,
                'title' => 'توفّر مقعد لك في الاجتماع! 🎟️',
                'body' => "أهلاً بك! توفر مقعد شاغر في «{$this->title}» وتم تأكيد حضورك تلقائياً وفق أسبقية قائمة الانتظار (FIFO).",
                'icon' => 'calendar-check',
                'link' => route('calendar.index'),
                'type' => 'meeting_promoted',
            ]);

            return $nextInWaitlist;
        }

        return null;
    }

    /**
     * Localized meeting type label.
     */
    public function meetingTypeLabel(): string
    {
        return match ($this->meeting_type) {
            self::TYPE_IN_PERSON => 'حضوري في المجلس 📍',
            self::TYPE_HYBRID => 'هجين (حضوري وعن بُعد) 🌐',
            self::TYPE_REMOTE => 'عن بُعد (جلسة مرئية) 💻',
            self::TYPE_OPEN_DISCUSSION => 'جلسة نقاش مفتوح 💬',
            self::TYPE_AUTHOR_MEETUP => 'لقاء خاص مع المؤلف ✍️',
            self::TYPE_BOOK_REVIEW => 'مراجعة كتاب الشهر 📖',
            self::TYPE_READING_WORKSHOP => 'ورشة قراءة تفاعلية 🛠️',
            self::TYPE_MANAGERS_MEETING => 'اجتماع مديري المجلس 🏛️',
            default => 'لقاء قرائي ✦',
        };
    }

    /**
     * Generate Google Calendar add URL.
     */
    public function googleCalendarUrl(): string
    {
        $startDate = $this->starts_at ?: $this->meeting_date;
        $endDate = $this->ends_at ?: $this->end_time ?: $startDate->copy()->addHours(2);

        $start = $startDate->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $end = $endDate->copy()->setTimezone('UTC')->format('Ymd\THis\Z');

        $params = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->title.' · رِواق المعرفة',
            'dates' => "{$start}/{$end}",
            'details' => ($this->description ?? '').($this->link ? "\nرابط الجلسة: ".$this->link : ''),
            'location' => $this->location ?? ($this->meeting_type === 'remote' ? 'جلسة مرئية عن بُعد' : 'رِواق المعرفة'),
        ]);

        return 'https://calendar.google.com/calendar/render?'.$params;
    }

    /**
     * Format as standard RFC 5545 iCalendar (ICS) string.
     */
    public function toIcsString(): string
    {
        $startDate = $this->starts_at ?: $this->meeting_date;
        $endDate = $this->ends_at ?: $this->end_time ?: $startDate->copy()->addHours(2);

        $startUtc = $startDate->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $endUtc = $endDate->copy()->setTimezone('UTC')->format('Ymd\THis\Z');

        $location = $this->location ?? ($this->meeting_type === 'remote' ? 'جلسة مرئية عن بُعد عبر رِواق' : 'مقر رِواق المعرفة');
        $description = str_replace(["\r\n", "\n"], '\\n', $this->description ?? 'لقاء قرائي في منصة رِواق المعرفة.');
        if ($this->link) {
            $description .= '\\nرابط الحضور: '.$this->link;
        }

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Riwaq Al-Maarifah//Knowledge Sanctuary//AR\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:meeting-{$this->id}@riwaq.test\r\n";
        $ics .= 'DTSTAMP:'.gmdate('Ymd\THis\Z')."\r\n";
        $ics .= "DTSTART:{$startUtc}\r\n";
        $ics .= "DTEND:{$endUtc}\r\n";
        $ics .= "SUMMARY:{$this->title}\r\n";
        $ics .= "DESCRIPTION:{$description}\r\n";
        $ics .= "LOCATION:{$location}\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }
}
