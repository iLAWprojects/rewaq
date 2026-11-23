<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqSmartNotificationsPanelTest extends TestCase
{
    use DatabaseTransactions;

    private function notify(User $user, int $count, bool $read = false): void
    {
        for ($i = 1; $i <= $count; $i++) {
            UserNotification::create([
                'user_id' => $user->id,
                'title' => "إشعار {$i}",
                'body' => 'نص الإشعار',
                'icon' => 'bell',
                'type' => 'info',
                'is_read' => $read,
            ]);
        }
    }

    public function test_feed_returns_latest_notifications_with_unread_state(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->notify($user, 3);
        $this->notify($other, 2);

        $json = $this->actingAs($user)->getJson(route('notifications.feed'))->assertOk()->json();

        $this->assertCount(3, $json['items']);
        $this->assertSame(3, $json['unread_count']);
        $this->assertFalse($json['items'][0]['is_read']);
    }

    public function test_feed_is_limited_to_twenty_items(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 25);

        $this->actingAs($user)->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonCount(20, 'items')
            ->assertJsonPath('unread_count', 25);
    }

    public function test_marking_viewed_notifications_as_seen_reduces_counter(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 3);
        $ids = UserNotification::where('user_id', $user->id)->pluck('id')->all();

        $this->actingAs($user)->postJson(route('notifications.seen'), ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, UserNotification::where('user_id', $user->id)->where('is_read', false)->count());
        $this->assertNotNull(UserNotification::where('user_id', $user->id)->first()->read_at);
    }

    public function test_seen_only_affects_own_notifications_and_listed_ids(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->notify($user, 2);
        $this->notify($other, 1);
        $mine = UserNotification::where('user_id', $user->id)->pluck('id')->all();
        $theirs = UserNotification::where('user_id', $other->id)->pluck('id')->all();

        $this->actingAs($user)->postJson(route('notifications.seen'), ['ids' => [$mine[0], ...$theirs]])
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertSame(1, UserNotification::where('user_id', $other->id)->where('is_read', false)->count());
    }

    public function test_panel_markup_is_in_dashboard_and_bell_has_hidden_counter_when_all_read(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 2, true);

        $this->actingAs($user)->get(route('libraries.index'))
            ->assertOk()
            ->assertSee('id="rq-notif-panel"', false)
            ->assertSee('كل الإشعارات')
            ->assertSee('data-notif-open', false);
    }

    public function test_guests_cannot_use_feed_endpoints(): void
    {
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
        $this->postJson(route('notifications.seen'), ['ids' => [1]])->assertUnauthorized();
    }

    public function test_marking_single_notification_as_read_decrements_unread_count(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 3);
        $first = UserNotification::where('user_id', $user->id)->first();

        $response = $this->actingAs($user)->postJson(route('notifications.markAsRead', $first->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_read', true)
            ->assertJsonPath('unread_count', 2);

        $this->assertTrue($first->fresh()->is_read);
    }

    public function test_feed_includes_actions_for_community_join_request(): void
    {
        $manager = User::factory()->create();
        $applicant = User::factory()->create();

        $community = Community::create([
            'name' => 'مجلس الاختبار',
            'category' => 'عام',
            'type' => 'public',
            'creator_id' => $manager->id,
            'requires_approval' => true,
        ]);
        $community->members()->attach($manager->id, ['role' => 'owner']);

        $joinRequest = CommunityJoinRequest::create([
            'community_id' => $community->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $notification = UserNotification::create([
            'user_id' => $manager->id,
            'title' => 'طلب انضمام جديد للمجلس 🌿',
            'body' => "طلب «{$applicant->name}» الانضمام.",
            'icon' => 'user-check',
            'link' => route('communities.index', ['community_id' => $community->id]).'#pending-requests',
            'type' => 'community_join_request',
            'metadata' => [
                'community_id' => $community->id,
                'request_id' => $joinRequest->id,
            ],
        ]);

        $json = $this->actingAs($manager)->getJson(route('notifications.feed'))->assertOk()->json();

        $this->assertNotEmpty($json['items']);
        $item = $json['items'][0];
        $this->assertNotNull($item['actions']);
        $this->assertEquals('join_request', $item['actions']['type']);
        $this->assertStringContainsString('/communities/join-requests/'.$joinRequest->id.'/approve', $item['actions']['approve_url']);

        // Test approve via JSON
        $approveRes = $this->actingAs($manager)->postJson($item['actions']['approve_url']);
        $approveRes->assertOk()->assertJsonPath('success', true);
        $this->assertEquals('approved', $joinRequest->fresh()->status);
    }
}
