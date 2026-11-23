<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Community;
use App\Models\Loan;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $view = $request->query('view', 'monthly'); // daily, weekly, monthly, list
        $typeFilter = $request->query('type', 'all'); // all or any of the 8 types
        $selectedDate = $request->query('date', now()->toDateString());

        // Supported 8 Meeting Types
        $supportedTypes = [
            'all' => 'جميع اللقاءات',
            Meeting::TYPE_IN_PERSON => 'لقاءات حضورية',
            Meeting::TYPE_REMOTE => 'لقاءات عن بُعد',
            Meeting::TYPE_HYBRID => 'لقاءات هجينة',
            Meeting::TYPE_OPEN_DISCUSSION => 'نقاش مفتوح',
            Meeting::TYPE_AUTHOR_MEETUP => 'لقاء مؤلف',
            Meeting::TYPE_BOOK_REVIEW => 'مراجعة كتاب',
            Meeting::TYPE_READING_WORKSHOP => 'ورشة قراءة',
            Meeting::TYPE_MANAGERS_MEETING => 'اجتماع مديرين',
        ];
        $meetingTypes = $supportedTypes;

        // Supported Calendar Views
        $supportedViews = [
            'monthly' => 'عرض شهري 📅',
            'weekly' => 'عرض أسبوعي 📆',
            'daily' => 'عرض يومي 🗓️',
            'list' => 'عرض قائمة 📋',
        ];

        // Query for active/upcoming meetings
        $meetingsQuery = Meeting::with(['community', 'book', 'coordinator', 'nextBook', 'attendees.user', 'waitlist.user'])
            ->visibleOnCalendarOf($user)
            ->where('is_past', false)
            ->orderBy('meeting_date', 'asc');

        if ($typeFilter && $typeFilter !== 'all') {
            $meetingsQuery->where('meeting_type', $typeFilter);
        }

        $upcomingMeetings = $meetingsQuery->get();

        // Featured meeting (اللقاء الرئيسي المميز)
        $meeting = $upcomingMeetings->first();

        // Past meetings archive with audio, minutes, photos & reader ratings
        $pastMeetings = Meeting::visibleOnCalendarOf($user)->where('is_past', true)
            ->when($meeting, function ($q) use ($meeting) {
                $q->where('id', '!=', $meeting->id);
            })
            ->with(['community', 'book', 'coordinator', 'nextBook', 'attendees.user'])
            ->latest('meeting_date')
            ->get();

        $rsvp = null;
        $isRsvp = false;
        $isWaitlist = false;
        $isMaybe = false;
        $isAttended = false;

        if ($meeting && $user) {
            $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)
                ->where('user_id', $user->id)
                ->first();

            if ($rsvp) {
                $isRsvp = in_array($rsvp->status, [MeetingRsvp::STATUS_GOING, 'attending']);
                $isWaitlist = ($rsvp->status === MeetingRsvp::STATUS_WAITLIST);
                $isMaybe = ($rsvp->status === MeetingRsvp::STATUS_MAYBE);
                $isAttended = ($rsvp->status === MeetingRsvp::STATUS_ATTENDED || $rsvp->checked_in);
            }
        }

        // Active book commitments
        $activeLoans = $user ? Loan::where('user_id', $user->id)->with('copy.book')->get() : collect();

        // Calendar Event Days for current active month
        $eventDays = $upcomingMeetings->map(function ($m) {
            return $m->meeting_date ? $m->meeting_date->day : 1;
        })->unique()->values()->toArray();

        // التزاماتي القادمة الحقيقية: مواعيد إعادة الكتب، لقاءات سجلت حضورها، وتحديات أنضممت إليها
        $commitments = collect();
        if ($user) {
            foreach ($activeLoans->whereIn('status', ['active', 'overdue', 'approved']) as $loan) {
                if ($loan->due_date && ! $loan->returned_at) {
                    $commitments->push(['date' => Carbon::parse($loan->due_date), 'title' => 'إعادة «'.($loan->copy?->book?->title ?? 'كتاب').'»', 'sub' => 'إعارة نشطة', 'tag' => 'موعد إعادة', 'color' => '#FFF3D6', 'text' => '#785419']);
                }
            }
            foreach ($upcomingMeetings as $m) {
                $mine = $m->attendees->first(fn ($a) => $a->user_id === $user->id && in_array($a->status, [MeetingRsvp::STATUS_GOING, 'attending']));
                if ($mine && $m->meeting_date) {
                    $commitments->push(['date' => $m->meeting_date, 'title' => $m->title, 'sub' => ($m->community?->name ?? 'لقاء').' · '.$m->meeting_date->format('H:i'), 'tag' => 'لقاء', 'color' => '#E1ECE2', 'text' => '#235738']);
                }
            }
            foreach ($user->challenges()->wherePivot('completed', false)->whereNotNull('challenges.end_date')->where('challenges.end_date', '>=', now()->toDateString())->get() as $ch) {
                $commitments->push(['date' => Carbon::parse($ch->end_date), 'title' => 'ختام تحدّي «'.$ch->title.'»', 'sub' => $ch->target_count.' '.$ch->target_unit, 'tag' => 'تحدي قراءة', 'color' => '#EDE6D7', 'text' => '#655431']);
            }
        }
        $commitments = $commitments->sortBy('date')->values();

        // Available resources for scheduling modal
        $availableBooks = collect();
        $availableCommunities = Community::take(30)->get();

        return view('pages.calendar', compact(
            'commitments',
            'meeting',
            'upcomingMeetings',
            'isRsvp',
            'isWaitlist',
            'isMaybe',
            'isAttended',
            'rsvp',
            'activeLoans',
            'eventDays',
            'pastMeetings',
            'typeFilter',
            'view',
            'supportedTypes',
            'supportedViews',
            'availableBooks',
            'availableCommunities',
            'selectedDate',
            'meetingTypes'
        ));
    }

    /**
     * Store and schedule a new meeting.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'meeting_type' => 'required|string|in:in_person,remote,hybrid,open_discussion,author_meetup,book_review,reading_workshop,managers_meeting',
            'meeting_date' => 'required|date',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:meeting_date',
            'timezone' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'link' => 'nullable|url|max:255',
            'capacity' => 'nullable|integer|min:1|max:10000',
            'registration_deadline' => 'nullable|date',
            'community_id' => 'nullable|exists:communities,id',
            'book_id' => 'nullable|exists:books,id',
            'coordinator_id' => 'nullable|exists:users,id',
            'next_book_id' => 'nullable|exists:books,id',
            'discussion_questions' => 'nullable|string',
            'instructions' => 'nullable|string|max:1000',
        ]);

        $startsAt = $validated['starts_at'] ?? $validated['meeting_date'];
        $endsAt = $validated['ends_at'] ?? Carbon::parse($startsAt)->addHours(2);

        $questions = null;
        if (! empty($validated['discussion_questions'])) {
            $questions = array_filter(array_map('trim', explode("\n", $validated['discussion_questions'])));
        }

        $meeting = Meeting::create([
            'community_id' => $validated['community_id'] ?? null,
            'book_id' => $validated['book_id'] ?? null,
            'coordinator_id' => $validated['coordinator_id'] ?? $user->id,
            'next_book_id' => $validated['next_book_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'meeting_type' => $validated['meeting_type'],
            'meeting_date' => $validated['meeting_date'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'end_time' => $endsAt,
            'timezone' => $validated['timezone'] ?? 'Asia/Riyadh',
            'location' => $validated['location'] ?? null,
            'link' => $validated['link'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'discussion_questions' => $questions,
            'instructions' => $validated['instructions'] ?? null,
            'is_past' => false,
        ]);

        AuditLog::record(
            $user,
            'create_meeting',
            $meeting,
            "جدول المستخدم «{$user->name}» لقاءً جديداً: «{$meeting->title}» ({$meeting->meetingTypeLabel()})."
        );

        $msg = "تم جدولة اللقاء «{$meeting->title}» بنجاح!";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'meeting' => $meeting,
            ]);
        }

        return redirect()->route('calendar.index')->with('success', $msg);
    }

    /**
     * RSVP lifecycle: Going, Maybe, Not Going, Waitlist (FIFO).
     */
    public function toggleRsvp(Request $request, Meeting $meeting)
    {
        $user = Auth::user();
        Gate::authorize('rsvp', $meeting);

        $requestedStatus = $request->input('status'); // going, maybe, not_going

        $rsvp = MeetingRsvp::firstOrNew([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
        ]);

        $isCurrentlyGoing = $rsvp->exists && in_array($rsvp->status, [MeetingRsvp::STATUS_GOING, 'attending']);
        $isCurrentlyWaitlist = $rsvp->exists && ($rsvp->status === MeetingRsvp::STATUS_WAITLIST);

        // If explicitly requested 'not_going' or toggling off an active Going/Waitlist RSVP
        if ($requestedStatus === MeetingRsvp::STATUS_NOT_GOING || ($requestedStatus === null && ($isCurrentlyGoing || $isCurrentlyWaitlist))) {
            $wasGoing = $isCurrentlyGoing;
            $rsvp->status = ($requestedStatus === MeetingRsvp::STATUS_NOT_GOING) ? MeetingRsvp::STATUS_NOT_GOING : 'declined';
            $rsvp->save();

            // FIFO Automatic Promotion: if a confirmed attendee leaves, promote earliest in waitlist
            if ($wasGoing) {
                $promoted = $meeting->promoteNextInWaitlist();
                $msg = $promoted
                    ? 'أُلغي تأكيد الحضور، وتم تصعيد المشارك الأول من قائمة الانتظار (FIFO) وتأكيد مقعده!'
                    : 'أُلغي تأكيد حضورك للاجتماع بنجاح.';
            } else {
                $msg = 'أُلغي تسجيلك في قائمة الانتظار.';
            }

            $attending = false;
        } elseif ($requestedStatus === MeetingRsvp::STATUS_MAYBE) {
            $rsvp->status = MeetingRsvp::STATUS_MAYBE;
            $rsvp->save();
            $msg = 'تم تسجيل حالة حضورك كـ (ربما أحضر ⏳).';
            $attending = false;
        } else {
            // Status: Going / Confirming RSVP
            if (! $meeting->isRegistrationOpen()) {
                return back()->with('error', 'عذراً، انتهت فترة التسجيل لهذا اللقاء.');
            }

            // Check Capacity: If full, automatically divert to Waitlist!
            if ($meeting->isFull()) {
                $rsvp->status = MeetingRsvp::STATUS_WAITLIST;
                $rsvp->save();
                $msg = 'اكتملت المقاعد المتاحة في هذا اللقاء. تم إدراجك في قائمة الانتظار وسيتم تصعيدك تلقائياً فور توفر مقعد شاغر وفق سياسة الأسبقية (FIFO).';
                $attending = false;
            } else {
                $rsvp->status = ($requestedStatus === MeetingRsvp::STATUS_GOING) ? MeetingRsvp::STATUS_GOING : 'attending';
                $rsvp->generateOrRefreshQrToken();
                $rsvp->save();

                $user->addPoints(20, 'meeting_rsvp', "تأكيد الحضور للقاء «{$meeting->title}»", Meeting::class, $meeting->id);
                $msg = 'تم تأكيد حضورك للقاء بنجاح وحجز مقعدك (+20 أثر)! رمز الـ QR متاح لتأكيد الحضور.';
                $attending = true;
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'attending' => $attending,
                'status' => $rsvp->status,
                'qr_token' => $rsvp->qr_token,
                'qr_code' => $rsvp->qr_code,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * QR Check-in with short-lived / meeting-scoped token (not raw user ID).
     */
    public function checkIn(Request $request, Meeting $meeting)
    {
        $user = Auth::user();
        Gate::authorize('checkIn', $meeting);

        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $code = trim($validated['qr_code']);

        // Check against secure qr_token or short code
        $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)
            ->where(function ($q) use ($code) {
                $q->where('qr_token', $code)
                    ->orWhere('qr_code', $code)
                    ->orWhere('id', $code);
            })
            ->first();

        if (! $rsvp) {
            return back()->with('error', 'رمز التحقق غير صالح أو لا يخص هذا الاجتماع.');
        }

        if (! $rsvp->isQrTokenValid()) {
            return back()->with('error', 'عذراً، انتهت صلاحية رمز التحقق (QR Token) لهذا اللقاء.');
        }

        if ($rsvp->checked_in || $rsvp->status === MeetingRsvp::STATUS_ATTENDED) {
            return back()->with('info', "تم تسجيل حضور «{$rsvp->user->name}» مسبقاً.");
        }

        $rsvp->update([
            'status' => MeetingRsvp::STATUS_ATTENDED,
            'checked_in' => true,
            'checked_in_at' => Carbon::now(),
        ]);

        $rsvp->user->addPoints(15, 'meeting_attended', "حضور موثق للقاء «{$meeting->title}»", Meeting::class, $meeting->id);

        AuditLog::record(
            $user,
            'meeting_check_in',
            $meeting,
            "تم توثيق الحضور الفعلي للقارئ «{$rsvp->user->name}» عبر رمز التحقق المعتمد."
        );

        $msg = "تم توثيق الحضور الفعلي للقارئ «{$rsvp->user->name}» عبر رمز التحقق بنجاح (+15 نقطة)!";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'rsvp' => $rsvp]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Store post-meeting documentation: minutes, photos, key ideas, recommendations, next book.
     */
    public function storePostMeetingDetails(Request $request, Meeting $meeting)
    {
        $user = Auth::user();
        Gate::authorize('update', $meeting);

        $validated = $request->validate([
            'meeting_minutes' => 'nullable|string|max:8000',
            'summary_notes' => 'nullable|string|max:8000',
            'key_ideas' => 'nullable|string|max:4000',
            'recommendations' => 'nullable|string|max:4000',
            'next_book_id' => 'nullable|exists:books,id',
            'photos' => 'nullable|array',
            'photos.*' => 'nullable|string',
        ]);

        $minutes = $validated['meeting_minutes'] ?? $validated['summary_notes'] ?? $meeting->meeting_minutes;

        $meeting->update([
            'meeting_minutes' => $minutes,
            'summary_notes' => $minutes,
            'key_ideas' => $validated['key_ideas'] ?? $meeting->key_ideas,
            'recommendations' => $validated['recommendations'] ?? $meeting->recommendations,
            'next_book_id' => $validated['next_book_id'] ?? $meeting->next_book_id,
            'photos' => $validated['photos'] ?? $meeting->photos,
            'is_past' => true,
        ]);

        // Notify attended attendees
        foreach ($meeting->attendees as $att) {
            UserNotification::create([
                'user_id' => $att->user_id,
                'title' => "محضر وتوصيات لقاء «{$meeting->title}» 📝",
                'body' => 'نُشر المحضر الرسمي والتوصيات للقاء الذي شاركت فيه، بالإضافة إلى الكتاب المقترح للقاء القادم.',
                'icon' => 'file-text',
                'link' => route('calendar.index'),
                'type' => 'meeting_minutes',
            ]);
        }

        AuditLog::record(
            $user,
            'update_meeting_minutes',
            $meeting,
            "وثق «{$user->name}» محضر وتوصيات لقاء «{$meeting->title}»."
        );

        $msg = 'تم اعتماد ونشر محضر اللقاء والتوصيات وتوثيقها في الأرشيف بنجاح!';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'meeting' => $meeting]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Backwards-compatible minutes method.
     */
    public function storeMinutes(Request $request, Meeting $meeting)
    {
        return $this->storePostMeetingDetails($request, $meeting);
    }

    /**
     * Store reader rating and review for meeting.
     */
    public function storeRating(Request $request, Meeting $meeting)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
        if (! $rsvp) {
            return back()->with('error', 'يجب أن تكون مسجلاً في اللقاء لتقييمه.');
        }

        $rsvp->update([
            'rating' => $validated['rating'],
            'review' => $validated['review'] ? trim($validated['review']) : null,
        ]);

        // Recalculate average meeting rating
        $ratings = MeetingRsvp::where('meeting_id', $meeting->id)->whereNotNull('rating')->get();
        $avg = $ratings->avg('rating');
        $meeting->update([
            'rating' => round($avg, 2),
            'ratings_count' => $ratings->count(),
        ]);

        $user->addPoints(10, 'meeting_rating', "تقييم لقاء «{$meeting->title}»", Meeting::class, $meeting->id);

        return back()->with('success', 'شكراً لتقييمك ومشاركتك انطباعك حول اللقاء (+10 نقاط)!');
    }

    /**
     * Redirect to Google Calendar add event template.
     */
    public function googleCalendar(Meeting $meeting)
    {
        return redirect()->away($meeting->googleCalendarUrl());
    }

    /**
     * Export standard RFC 5545 iCalendar (ICS) file (usable by Apple Calendar, Google Calendar, Outlook).
     */
    public function exportIcs(Meeting $meeting)
    {
        $ics = $meeting->toIcsString();

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="meeting-'.$meeting->id.'.ics"',
        ]);
    }

    /**
     * Soft delete meeting with audit logging.
     */
    public function destroy(Meeting $meeting)
    {
        $user = Auth::user();
        Gate::authorize('delete', $meeting);

        $title = $meeting->title;
        $meeting->delete();

        AuditLog::record(
            $user,
            'delete_meeting',
            $meeting,
            "تمت أرشفة/حذف اللقاء «{$title}» (Soft Delete)."
        );

        return redirect()->route('calendar.index')->with('success', "تم حذف اللقاء «{$title}» بأمان.");
    }
}
