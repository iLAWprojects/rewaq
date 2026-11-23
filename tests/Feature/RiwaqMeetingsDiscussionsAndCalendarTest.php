<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Community;
use App\Models\ContentReport;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqMeetingsDiscussionsAndCalendarTest extends TestCase
{
    use DatabaseTransactions;

    protected function getDemoUser(): User
    {
        return User::where('email', 'demo@riwaq.test')->first()
            ?? User::factory()->create(['email' => 'demo@riwaq.test', 'name' => 'قارئ تجريبي']);
    }

    public function test_calendar_page_and_meeting_type_filters(): void
    {
        $user = $this->getDemoUser();

        // 1. All meetings
        $response = $this->actingAs($user)->get('/calendar');
        $response->assertStatus(200);
        $response->assertSee('التقويم الموحد والاجتماعات');
        $response->assertSee('جميع اللقاءات');
        $response->assertSee('لقاءات حضورية');
        $response->assertSee('لقاءات عن بُعد');
        $response->assertSee('لقاءات هجينة');

        // 2. In-person filter
        $resInPerson = $this->actingAs($user)->get('/calendar?type=in_person');
        $resInPerson->assertStatus(200);

        // 3. Remote filter
        $resRemote = $this->actingAs($user)->get('/calendar?type=remote');
        $resRemote->assertStatus(200);

        // 4. Hybrid filter
        $resHybrid = $this->actingAs($user)->get('/calendar?type=hybrid');
        $resHybrid->assertStatus(200);
    }

    public function test_meeting_rsvp_capacity_waitlist_and_automatic_promotion(): void
    {
        $community = Community::first();
        $book = Book::first();

        // Create meeting with capacity of 1 participant
        $meeting = Meeting::create([
            'community_id' => $community->id,
            'book_id' => $book->id,
            'title' => 'لقاء اختبار السعة وقائمة الانتظار '.uniqid(),
            'meeting_date' => Carbon::now()->addDays(5),
            'end_time' => Carbon::now()->addDays(5)->addHours(2),
            'location' => 'قاعة ابن رشد الثقافية',
            'meeting_type' => 'in_person',
            'capacity' => 1,
            'is_past' => false,
        ]);

        $user1 = User::factory()->create(['name' => 'مشارك أول']);
        $user2 = User::factory()->create(['name' => 'مشارك ثان']);

        // User 1 RSVPs -> should be attending
        $resp1 = $this->actingAs($user1)->post("/calendar/meetings/{$meeting->id}/rsvp");
        $resp1->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $user1->id,
            'status' => 'attending',
        ]);
        $this->assertEquals(1, $meeting->attendingCount());
        $this->assertTrue($meeting->isFull());

        // User 2 RSVPs -> should be placed in waitlist
        $resp2 = $this->actingAs($user2)->post("/calendar/meetings/{$meeting->id}/rsvp");
        $resp2->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $user2->id,
            'status' => 'waitlist',
        ]);
        $this->assertEquals(1, $meeting->waitlistCount());

        // User 1 cancels RSVP -> User 2 should be automatically promoted to attending
        $cancelResp = $this->actingAs($user1)->post("/calendar/meetings/{$meeting->id}/rsvp");
        $cancelResp->assertSessionHas('success');

        $rsvp1 = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $user1->id)->first();
        $this->assertEquals('declined', $rsvp1->status);

        $rsvp2 = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $user2->id)->first();
        $this->assertEquals('attending', $rsvp2->status);
        $this->assertNotNull($rsvp2->qr_code);

        // Verify notification sent to User 2
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user2->id,
            'type' => 'meeting_promoted',
        ]);
    }

    public function test_qr_code_attendance_check_in(): void
    {
        $community = Community::first();
        $book = Book::first();
        $host = $this->getDemoUser();

        $meeting = Meeting::create([
            'community_id' => $community->id,
            'book_id' => $book->id,
            'title' => 'لقاء فحص التحقق من الحضور بالـ QR '.uniqid(),
            'meeting_date' => Carbon::now()->addDays(2),
            'meeting_type' => 'in_person',
            'capacity' => 20,
            'is_past' => false,
        ]);

        $attendee = User::factory()->create(['points' => 50]);
        $qrCode = 'RWQ-MEET-'.$meeting->id.'-'.$attendee->id;

        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $attendee->id,
            'status' => 'attending',
            'qr_code' => $qrCode,
            'checked_in' => false,
        ]);

        // Host scans attendee QR code
        $response = $this->actingAs($host)->post("/calendar/meetings/{$meeting->id}/check-in", [
            'qr_code' => $qrCode,
        ]);

        $response->assertSessionHas('success');

        $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $attendee->id)->first();
        $this->assertTrue((bool) $rsvp->checked_in);
        $this->assertNotNull($rsvp->checked_in_at);

        $attendee->refresh();
        $this->assertEquals(65, $attendee->points); // 50 + 15
    }

    public function test_store_meeting_minutes_and_submit_rating(): void
    {
        $community = Community::first();
        $book = Book::first();
        $host = $this->getDemoUser();

        $meeting = Meeting::create([
            'community_id' => $community->id,
            'book_id' => $book->id,
            'title' => 'لقاء توثيق المحضر والتقييم '.uniqid(),
            'meeting_date' => Carbon::now()->subHours(3),
            'meeting_type' => 'remote',
            'capacity' => 15,
            'is_past' => false,
        ]);

        $reader = User::factory()->create(['name' => 'قارئ مشارك']);
        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $reader->id,
            'status' => 'attending',
            'checked_in' => true,
        ]);

        // 1. Host stores meeting minutes
        $minutesResponse = $this->actingAs($host)->post("/calendar/meetings/{$meeting->id}/minutes", [
            'meeting_minutes' => 'محضر رسمي: تمت مناقشة المحاور الثلاثة الأولى وتم الاتفاق على تلخيص الأفكار الرئيسية.',
        ]);
        $minutesResponse->assertSessionHas('success');

        $meeting->refresh();
        $this->assertTrue((bool) $meeting->is_past);
        $this->assertStringContainsString('محضر رسمي', $meeting->meeting_minutes);

        // Attendee receives minutes notification
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $reader->id,
            'type' => 'meeting_minutes',
        ]);

        // 2. Reader submits meeting rating
        $ratingResponse = $this->actingAs($reader)->post("/calendar/meetings/{$meeting->id}/rating", [
            'rating' => 5,
            'review' => 'تنظيم متميز ومناقشات ثرية للغاية!',
        ]);
        $ratingResponse->assertSessionHas('success');

        $meeting->refresh();
        $this->assertEquals(5.0, (float) $meeting->rating);
        $this->assertEquals(1, $meeting->ratings_count);
    }

    public function test_ics_export_and_google_calendar_link(): void
    {
        $meeting = Meeting::first();

        // 1. ICS Download
        $icsResponse = $this->get("/calendar/meetings/{$meeting->id}/ics");
        $icsResponse->assertStatus(200);
        $icsResponse->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsResponse->getContent());
        $this->assertStringContainsString('SUMMARY:'.$meeting->title, $icsResponse->getContent());

        // 2. Google Calendar Redirect
        $googleResponse = $this->get("/calendar/meetings/{$meeting->id}/google");
        $googleResponse->assertStatus(302);
        $this->assertStringContainsString('calendar.google.com/calendar/render', $googleResponse->headers->get('Location'));
    }

    public function test_discussion_nested_comments_with_spoiler(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();

        $discussion = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'content' => 'تأمل في البناء الدرامي ومصير الشخصيات',
        ]);

        // 1. Post top-level comment
        $commentResp = $this->actingAs($user)->post("/discussions/{$discussion->id}/comments", [
            'content' => 'تعليق عام حول أسلوب السرد الرائع.',
        ]);
        $commentResp->assertSessionHas('success');

        $parentComment = DiscussionComment::where('discussion_id', $discussion->id)->first();
        $this->assertNotNull($parentComment);
        $this->assertNull($parentComment->parent_id);
        $this->assertFalse((bool) $parentComment->is_spoiler);

        // 2. Post nested reply with spoiler protection
        $otherUser = User::factory()->create();
        $replyResp = $this->actingAs($otherUser)->post("/discussions/{$discussion->id}/comments", [
            'content' => 'نهاية البطل في الفصل الأخير كانت غير متوقعة تماماً.',
            'parent_id' => $parentComment->id,
            'is_spoiler' => 1,
        ]);
        $replyResp->assertSessionHas('success');

        $this->assertDatabaseHas('discussion_comments', [
            'discussion_id' => $discussion->id,
            'parent_id' => $parentComment->id,
            'user_id' => $otherUser->id,
            'is_spoiler' => true,
        ]);

        $this->assertEquals(1, $parentComment->replies()->count());
    }

    public function test_content_reporting_and_moderation_queue_actions(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();
        $community->update(['creator_id' => $user->id]);

        $reporter = User::factory()->create();
        $offender = User::factory()->create();

        $discussion = Discussion::create([
            'community_id' => $community->id,
            'user_id' => $offender->id,
            'content' => 'محتوى مخالف يتضمن حرقاً فجاً للرواية دون تنبيه',
        ]);

        // 1. Reporter flags the discussion
        $reportResp = $this->actingAs($reporter)->post('/content/report', [
            'reportable_type' => 'discussion',
            'reportable_id' => $discussion->id,
            'reason' => 'spoiler',
            'details' => 'حرق واضح لنهاية الكتاب في السطر الأول.',
        ]);
        $reportResp->assertSessionHas('success');

        $report = ContentReport::where('reportable_type', 'discussion')
            ->where('reportable_id', $discussion->id)
            ->first();
        $this->assertNotNull($report);
        $this->assertEquals('pending', $report->status);

        // 2. Community manager views moderation queue on communities page
        $viewResp = $this->actingAs($user)->get("/communities?community_id={$community->id}");
        $viewResp->assertStatus(200);
        $viewResp->assertSee('لوحة الإشراف ومراجعة البلاغات');
        $viewResp->assertSee('حذف المحتوى المخالف');

        // 3. Community manager resolves report by deleting content
        $resolveResp = $this->actingAs($user)->post("/moderation/reports/{$report->id}/resolve", [
            'action' => 'delete_content',
            'moderator_notes' => 'تم التحقق من الحرق وحذف المحتوى للحفاظ على تجربة القراء.',
        ]);
        $resolveResp->assertSessionHas('success');

        $report->refresh();
        $this->assertEquals('action_taken', $report->status);
        $this->assertEquals($user->id, $report->moderated_by);

        // Offending discussion is deleted (soft-deleted)
        $this->assertSoftDeleted('discussions', [
            'id' => $discussion->id,
        ]);
    }
}
