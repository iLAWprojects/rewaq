<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqMeetingsAndCalendarEcosystemTest extends TestCase
{
    use DatabaseTransactions;

    protected function getAdminUser(): User
    {
        return User::where('role', 'super_admin')->first()
            ?? User::factory()->create(['role' => 'super_admin', 'email' => 'admin_test_'.uniqid().'@riwaq.test']);
    }

    protected function getRegularUser(): User
    {
        return User::factory()->create(['role' => 'user', 'points' => 100]);
    }

    /**
     * 1. Test all 8 Meeting Types supported in scheduling, validation, and listing.
     */
    public function test_all_eight_meeting_types_supported_and_filtered(): void
    {
        $admin = $this->getAdminUser();
        $community = Community::first();
        $book = Book::first();

        $types = [
            Meeting::TYPE_IN_PERSON,
            Meeting::TYPE_REMOTE,
            Meeting::TYPE_HYBRID,
            Meeting::TYPE_OPEN_DISCUSSION,
            Meeting::TYPE_AUTHOR_MEETUP,
            Meeting::TYPE_BOOK_REVIEW,
            Meeting::TYPE_READING_WORKSHOP,
            Meeting::TYPE_MANAGERS_MEETING,
        ];

        foreach ($types as $type) {
            $meeting = Meeting::create([
                'title' => "لقاء تجريبي للنوع {$type}",
                'meeting_type' => $type,
                'meeting_date' => Carbon::now()->addDays(3),
                'community_id' => $community->id,
                'book_id' => $book->id,
                'capacity' => 30,
            ]);

            $this->assertDatabaseHas('meetings', [
                'id' => $meeting->id,
                'meeting_type' => $type,
            ]);

            $this->assertNotEmpty($meeting->meetingTypeLabel());

            // Test filtering by each meeting type in list view
            $res = $this->actingAs($admin)->get("/calendar?type={$type}&view=list");
            $res->assertStatus(200);
            $res->assertSee($meeting->title);
        }
    }

    /**
     * 2. Test comprehensive meeting creation with all attributes.
     */
    public function test_schedule_new_meeting_with_all_rich_attributes(): void
    {
        $admin = $this->getAdminUser();
        $coordinator = $this->getRegularUser();
        $community = Community::first();
        $book = Book::first();
        $nextBook = Book::skip(1)->first() ?? $book;

        $meetingDate = Carbon::now()->addDays(7)->setTime(19, 0);
        $endDate = Carbon::now()->addDays(7)->setTime(21, 0);
        $deadline = Carbon::now()->addDays(6)->setTime(23, 59);

        $payload = [
            'title' => 'أمسية نقدية استثنائية: رواق المعرفة',
            'description' => 'جلسة تحليلية متعمقة تتناول البعد اللغوي والفلسفي للنص.',
            'meeting_type' => Meeting::TYPE_HYBRID,
            'community_id' => $community->id,
            'book_id' => $book->id,
            'coordinator_id' => $coordinator->id,
            'meeting_date' => $meetingDate->toDateTimeString(),
            'starts_at' => $meetingDate->toDateTimeString(),
            'ends_at' => $endDate->toDateTimeString(),
            'timezone' => 'Asia/Riyadh',
            'location' => 'مقر الرواق، الرياض - قاعة الأندلس',
            'link' => 'https://meet.google.com/abc-defg-hij',
            'capacity' => 45,
            'registration_deadline' => $deadline->toDateTimeString(),
            'discussion_questions' => "1. كيف عبّر الكاتب عن الاغتراب؟\n2. ما رمزية العنوان؟",
            'instructions' => "يُرجى قراءة الفصل الأول والثاني قبل اللقاء.\nالحضور قبل الموعد بـ 10 دقائق.",
            'next_book_id' => $nextBook->id,
        ];

        $response = $this->actingAs($admin)->post('/calendar/meetings', $payload);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('meetings', [
            'title' => 'أمسية نقدية استثنائية: رواق المعرفة',
            'meeting_type' => 'hybrid',
            'timezone' => 'Asia/Riyadh',
            'capacity' => 45,
            'coordinator_id' => $coordinator->id,
            'next_book_id' => $nextBook->id,
        ]);
    }

    /**
     * 3. Test the 4 Calendar Views (Monthly, Weekly, Daily, List).
     */
    public function test_four_calendar_views_render_successfully(): void
    {
        $user = $this->getRegularUser();

        $views = ['monthly', 'weekly', 'daily', 'list'];

        foreach ($views as $view) {
            $response = $this->actingAs($user)->get("/calendar?view={$view}");
            $response->assertStatus(200);
            $response->assertViewHas('view', $view);
            $response->assertSee('التقويم الموحد والاجتماعات');
        }
    }

    /**
     * 4. Test RSVP lifecycle: Going, Maybe, Not Going, and capacity overflow to Waitlist.
     */
    public function test_rsvp_lifecycle_and_capacity_overflow_to_waitlist(): void
    {
        $community = Community::first();
        $book = Book::first();

        // Meeting with strict capacity of 2
        $meeting = Meeting::create([
            'title' => 'جلسة نقاش بسعة محدودة '.uniqid(),
            'community_id' => $community->id,
            'book_id' => $book->id,
            'meeting_type' => Meeting::TYPE_REMOTE,
            'meeting_date' => Carbon::now()->addDays(4),
            'capacity' => 2,
            'is_past' => false,
        ]);

        $user1 = User::factory()->create(['name' => 'قارئ 1']);
        $user2 = User::factory()->create(['name' => 'قارئ 2']);
        $user3 = User::factory()->create(['name' => 'قارئ 3']);
        $userMaybe = User::factory()->create(['name' => 'قارئ متردد']);

        // User Maybe posts 'maybe'
        $respMaybe = $this->actingAs($userMaybe)->post("/calendar/meetings/{$meeting->id}/rsvp", [
            'status' => MeetingRsvp::STATUS_MAYBE,
        ]);
        $respMaybe->assertSessionHas('success');
        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $userMaybe->id,
            'status' => 'maybe',
        ]);
        // 'maybe' does NOT count towards capacity!
        $this->assertEquals(0, $meeting->attendingCount());

        // User 1 RSVPs 'going' -> Seat 1 taken
        $resp1 = $this->actingAs($user1)->post("/calendar/meetings/{$meeting->id}/rsvp", [
            'status' => MeetingRsvp::STATUS_GOING,
        ]);
        $resp1->assertSessionHas('success');
        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $user1->id,
            'status' => 'going',
        ]);
        $this->assertEquals(1, $meeting->attendingCount());

        // User 2 RSVPs 'going' -> Seat 2 taken, meeting is FULL
        $resp2 = $this->actingAs($user2)->post("/calendar/meetings/{$meeting->id}/rsvp", [
            'status' => MeetingRsvp::STATUS_GOING,
        ]);
        $resp2->assertSessionHas('success');
        $this->assertEquals(2, $meeting->attendingCount());
        $this->assertTrue($meeting->isFull());

        // User 3 RSVPs 'going' -> Exceeds capacity -> Auto-Waitlisted!
        $resp3 = $this->actingAs($user3)->post("/calendar/meetings/{$meeting->id}/rsvp", [
            'status' => MeetingRsvp::STATUS_GOING,
        ]);
        $resp3->assertSessionHas('success');
        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $user3->id,
            'status' => 'waitlist',
        ]);
        $this->assertEquals(1, $meeting->waitlistCount());
    }

    /**
     * 5. Test FIFO Automatic Waitlist Promotion and instant notification when a seat opens.
     */
    public function test_fifo_waitlist_automatic_promotion_and_notification(): void
    {
        $community = Community::first();
        $meeting = Meeting::create([
            'title' => 'لقاء اختبار أسبقية FIFO '.uniqid(),
            'community_id' => $community->id,
            'meeting_type' => Meeting::TYPE_IN_PERSON,
            'meeting_date' => Carbon::now()->addDays(5),
            'capacity' => 1,
            'is_past' => false,
        ]);

        $attendee = User::factory()->create(['name' => 'حاضر أولي']);
        $waitlistFirst = User::factory()->create(['name' => 'الأول في الانتظار']);
        $waitlistSecond = User::factory()->create(['name' => 'الثاني في الانتظار']);

        // Attendee occupies the only seat
        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $attendee->id,
            'status' => MeetingRsvp::STATUS_GOING,
        ]);

        // First waitlist user joins earlier
        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $waitlistFirst->id,
            'status' => MeetingRsvp::STATUS_WAITLIST,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        // Second waitlist user joins later
        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $waitlistSecond->id,
            'status' => MeetingRsvp::STATUS_WAITLIST,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->assertEquals(2, $meeting->waitlistCount());

        // Attendee cancels their RSVP ('not_going')
        $cancelResp = $this->actingAs($attendee)->post("/calendar/meetings/{$meeting->id}/rsvp", [
            'status' => MeetingRsvp::STATUS_NOT_GOING,
        ]);
        $cancelResp->assertSessionHas('success');

        // FIFO Check: Earliest waitlist user MUST be promoted!
        $rsvpFirst = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $waitlistFirst->id)->first();
        $this->assertTrue(in_array($rsvpFirst->status, [MeetingRsvp::STATUS_GOING, 'attending']));
        $this->assertNotNull($rsvpFirst->qr_token);
        $this->assertNotNull($rsvpFirst->qr_code);

        // Later waitlist user remains in waitlist
        $rsvpSecond = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $waitlistSecond->id)->first();
        $this->assertEquals(MeetingRsvp::STATUS_WAITLIST, $rsvpSecond->status);

        // Verify instant UserNotification sent to the promoted user
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $waitlistFirst->id,
            'type' => 'meeting_promoted',
        ]);
    }

    /**
     * 6. Test QR Check-in with short-lived / meeting-scoped token without raw user ID exposure.
     */
    public function test_qr_checkin_with_scoped_token_awards_points_and_logs_audit(): void
    {
        $admin = $this->getAdminUser();
        $community = Community::first();
        $attendee = User::factory()->create(['points' => 80]);

        $meeting = Meeting::create([
            'title' => 'لقاء توثيق الحضور بالرمز السري '.uniqid(),
            'community_id' => $community->id,
            'meeting_type' => Meeting::TYPE_HYBRID,
            'meeting_date' => Carbon::now()->addDays(1),
            'capacity' => 20,
            'is_past' => false,
        ]);

        $rsvp = MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $attendee->id,
            'status' => MeetingRsvp::STATUS_GOING,
            'checked_in' => false,
        ]);

        $token = $rsvp->generateOrRefreshQrToken();

        // 1. Verify token is cryptographically long and does NOT contain raw user_id
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
        $this->assertStringNotContainsString('user_id', $token);
        $this->assertTrue($rsvp->isQrTokenValid());

        // 2. Host scans and checks in attendee using qr_token
        $response = $this->actingAs($admin)->post("/calendar/meetings/{$meeting->id}/check-in", [
            'qr_code' => $token,
        ]);
        $response->assertSessionHas('success');

        $rsvp->refresh();
        $this->assertTrue($rsvp->checked_in);
        $this->assertEquals(MeetingRsvp::STATUS_ATTENDED, $rsvp->status);
        $this->assertNotNull($rsvp->checked_in_at);

        // Points incremented (+15)
        $attendee->refresh();
        $this->assertEquals(95, $attendee->points);

        // AuditLog recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'meeting_check_in',
            'auditable_type' => Meeting::class,
            'auditable_id' => $meeting->id,
        ]);
    }

    /**
     * 7. Test expired QR token rejection.
     */
    public function test_expired_qr_token_is_rejected(): void
    {
        $admin = $this->getAdminUser();
        $meeting = Meeting::create([
            'title' => 'لقاء منتهي الصلاحية '.uniqid(),
            'meeting_type' => Meeting::TYPE_IN_PERSON,
            'meeting_date' => Carbon::now()->subDays(5),
            'capacity' => 10,
        ]);

        $attendee = $this->getRegularUser();
        $rsvp = MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $attendee->id,
            'status' => MeetingRsvp::STATUS_GOING,
            'qr_token' => hash('sha256', 'sample-token-'.uniqid()),
            'qr_token_expires_at' => Carbon::now()->subHour(), // Expired!
            'checked_in' => false,
        ]);

        $this->assertFalse($rsvp->isQrTokenValid());

        $response = $this->actingAs($admin)->post("/calendar/meetings/{$meeting->id}/check-in", [
            'qr_code' => $rsvp->qr_token,
        ]);
        $response->assertSessionHas('error');

        $rsvp->refresh();
        $this->assertFalse($rsvp->checked_in);
    }

    /**
     * 8. Test Post-Meeting Documentation: Minutes, Photos, Key Ideas, Recommendations, Next Book.
     */
    public function test_post_meeting_documentation_and_notifications(): void
    {
        $admin = $this->getAdminUser();
        $community = Community::first();
        $book = Book::first();
        $nextBook = Book::skip(1)->first() ?? $book;

        $meeting = Meeting::create([
            'title' => 'لقاء التوثيق المعرفي والمحضر '.uniqid(),
            'community_id' => $community->id,
            'book_id' => $book->id,
            'meeting_type' => Meeting::TYPE_IN_PERSON,
            'meeting_date' => Carbon::now()->subHours(2),
            'capacity' => 15,
            'is_past' => false,
        ]);

        $participant = User::factory()->create();
        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $participant->id,
            'status' => MeetingRsvp::STATUS_ATTENDED,
            'checked_in' => true,
        ]);

        $postData = [
            'meeting_minutes' => 'اجتمع الأعضاء وناقشوا محاور الكتاب بالتفصيل وتم الاتفاق على النتائج الرئيسية.',
            'key_ideas' => "1. أصالة اللغة العربية في التعبير الفلسفي.\n2. أهمية السياق التاريخي.",
            'recommendations' => "1. قراءة مؤلفات الجاحظ.\n2. كتابة مراجعة شاملة في المنصة.",
            'next_book_id' => $nextBook->id,
        ];

        $resp = $this->actingAs($admin)->post("/calendar/meetings/{$meeting->id}/post-details", $postData);
        $resp->assertSessionHas('success');

        $meeting->refresh();
        $this->assertTrue((bool) $meeting->is_past);
        $this->assertEquals($postData['meeting_minutes'], $meeting->meeting_minutes);
        $this->assertEquals($postData['key_ideas'], $meeting->key_ideas);
        $this->assertEquals($postData['recommendations'], $meeting->recommendations);
        $this->assertEquals($nextBook->id, $meeting->next_book_id);

        // Attendee received notification about published minutes & next book
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $participant->id,
            'type' => 'meeting_minutes',
        ]);
    }

    /**
     * 9. Test Calendar Exports: ICS (Apple Calendar / Outlook) and Google Calendar link.
     */
    public function test_ics_export_and_google_calendar_link(): void
    {
        $user = $this->getRegularUser();
        $meeting = Meeting::create([
            'title' => 'لقاء اختبار تصدير التقويم '.uniqid(),
            'description' => 'جلسة نقاش لتجربة تصدير ملفات التقويم الموحدة.',
            'meeting_type' => Meeting::TYPE_HYBRID,
            'meeting_date' => Carbon::now()->addDays(3)->setTime(18, 30),
            'location' => 'مقر الرواق',
            'link' => 'https://meet.google.com/test-cal',
            'timezone' => 'Asia/Riyadh',
        ]);

        // 1. ICS Export
        $icsResponse = $this->actingAs($user)->get("/calendar/meetings/{$meeting->id}/export-ics");
        $icsResponse->assertStatus(200);
        $icsResponse->assertHeader('content-type', 'text/calendar; charset=UTF-8');

        $icsContent = $icsResponse->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('VERSION:2.0', $icsContent);
        $this->assertStringContainsString('SUMMARY:'.$meeting->title, $icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $icsContent);

        // 2. Google Calendar URL
        $gCalUrl = $meeting->googleCalendarUrl();
        $this->assertStringContainsString('calendar.google.com/calendar/render', $gCalUrl);
        $this->assertStringContainsString('action=TEMPLATE', $gCalUrl);
        $this->assertStringContainsString(urlencode($meeting->title), $gCalUrl);
    }
}
