<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqFollowOnFriendRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sending_friend_request_automatically_follows_recipient(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('friends.request', $b->id))->assertSessionHas('success');

        $this->assertTrue($a->fresh()->isFollowing($b));
        $this->assertFalse($b->fresh()->isFollowing($a));
        $this->assertSame(1, UserNotification::where('user_id', $b->id)->count());
    }

    public function test_follow_stays_even_if_friend_request_is_rejected(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('friends.request', $b->id));
        $this->actingAs($b)->post(route('friends.reject', $a->id))->assertSessionHas('info');

        $this->assertTrue($a->fresh()->isFollowing($b));
        $this->assertDatabaseHas('friendships', ['sender_id' => $a->id, 'recipient_id' => $b->id, 'status' => 'rejected']);
    }

    public function test_follow_toggle_uses_follow_wording_and_works_both_ways(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('profile.toggleFollow', $b->id))
            ->assertSessionHas('success', "بدأت الآن بمتابعة «{$b->name}» 🤝");
        $this->assertTrue($a->fresh()->isFollowing($b));

        $this->actingAs($a)->get(route('profile.show', $b->id))
            ->assertOk()
            ->assertSee('إلغاء المتابعة');

        $this->actingAs($a)->post(route('profile.toggleFollow', $b->id));
        $this->assertFalse($a->fresh()->isFollowing($b));
    }
}
