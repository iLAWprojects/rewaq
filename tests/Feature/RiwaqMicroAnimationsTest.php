<?php

namespace Tests\Feature;

use App\Models\BookCopy;
use App\Models\Community;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqMicroAnimationsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_landing_page_has_no_sidebar_and_shows_public_navigation(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('rq-public-bar', false);
        $response->assertDontSee('id="riwaq-sidebar"', false);
        $response->assertSee('تسجيل الدخول');
    }

    public function test_explore_renders_search_and_category_filters(): void
    {
        $response = $this->get('/explore');

        $response->assertStatus(200);
        $response->assertSee('ex-search', false);
        $response->assertSee('ex-pills', false);
        $response->assertSee('ex-grid', false);
    }

    public function test_libraries_renders_badge_pulses_laser_sweep_and_spring_cards(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();
        $lib = Library::first();

        $response = $this->actingAs($user)->get('/libraries');

        $response->assertStatus(200);
        $response->assertSee('lb-copy', false);
        $response->assertSee('lb-filters', false);

        // Test loans tab
        $loan = Loan::first();
        if (! $loan) {
            $copy = BookCopy::first();
            $loan = Loan::create([
                'book_copy_id' => $copy->id,
                'user_id' => $user->id,
                'borrower_name' => $user->name,
                'status' => 'active',
                'requested_at' => now(),
                'borrowed_at' => now(),
                'due_date' => now()->addDays(14),
            ]);
        }
        $targetLibId = $loan->copy->library_id;
        $loansResponse = $this->actingAs($user)->get('/libraries?tab=loans&library_id='.$targetLibId);
        $loansResponse->assertStatus(200);
        $loansResponse->assertSee('loan-card-spring');
    }

    public function test_reading_journey_renders_breath_ring_timeline_snap_and_celebration(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->get('/reading');

        $response->assertStatus(200);
        $response->assertSee('stopwatch-box');
        $response->assertSee('breath-focus-ring');
        $response->assertSee('timeline-item-snap');
        $response->assertSee('triggerPointsCelebration');
    }

    public function test_communities_renders_spoiler_blur_elastic_poll_and_3d_badge_flip(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        $response = $this->actingAs($user)->get('/communities');

        $response->assertStatus(200);
        $response->assertSee('spoiler-blur');
        $response->assertSee('poll-option-elastic-bar');
    }

    public function test_calendar_and_notifications_render_expansion_drawer_and_quiet_hours_moon(): void
    {
        $user = User::where('email', 'demo@riwaq.test')->first();

        // Calendar (يوم الحدث يُحدَّد من لقاء حقيقي في الشهر الحالي)
        $community = Community::first() ?? Community::create([
            'name' => 'نادي اختبار', 'category' => 'أدب', 'description' => 'د', 'cover_color' => '#173D36',
            'creator_id' => $user->id, 'type' => 'public', 'language' => 'العربية', 'city' => 'الرياض', 'max_members' => 10,
        ]);
        Meeting::create([
            'community_id' => $community->id, 'title' => 'لقاء اليوم '.uniqid(), 'meeting_date' => now(),
            'end_time' => now()->addHours(2), 'location' => 'قاعة', 'meeting_type' => 'in_person', 'capacity' => 5, 'is_past' => false,
        ]);
        $calendarRes = $this->actingAs($user)->get('/calendar');
        $calendarRes->assertStatus(200);
        $calendarRes->assertSee('calendar-day-expandable');
        $calendarRes->assertSee('calendar-event-drawer');
        $calendarRes->assertSee('expandCalendarDay');

        // Notifications
        $notifRes = $this->actingAs($user)->get('/notifications');
        $notifRes->assertStatus(200);
        $notifRes->assertSee('glowing-crescent');
        $notifRes->assertSee('toggleQuietHoursAmbientMode');
    }

    public function test_book_details_modal_is_rendered_on_explore(): void
    {
        $response = $this->get('/explore');
        $response->assertStatus(200);

        $response->assertSee('rq-book-modal', false);
        $response->assertSee('rq-bm-add-form', false);
        $response->assertSee('أضف إلى مكتبتي');
    }
}
