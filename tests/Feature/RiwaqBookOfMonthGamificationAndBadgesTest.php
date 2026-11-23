<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Book;
use App\Models\BookNomination;
use App\Models\BookPoll;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqBookOfMonthGamificationAndBadgesTest extends TestCase
{
    use DatabaseTransactions;

    protected function getDemoUser(): User
    {
        return User::where('email', 'demo@riwaq.test')->first()
            ?? User::factory()->create(['email' => 'demo@riwaq.test', 'name' => 'قارئ تجريبي']);
    }

    public function test_member_can_nominate_book_and_receive_points(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();
        $initialPoints = $user->points;

        $response = $this->actingAs($user)->post("/communities/{$community->id}/nominations", [
            'title' => 'رسالة الغفران',
            'author' => 'أبو العلاء المعري',
            'reason' => 'رحلة أدبية وفلسفية رائدة في الأدب العربي تستحق القراءة والنقاش المعمق.',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('book_nominations', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'title' => 'رسالة الغفران',
            'author' => 'أبو العلاء المعري',
            'endorsements_count' => 1,
            'status' => 'pending',
        ]);

        $user->refresh();
        $this->assertGreaterThan($initialPoints, $user->points);
    }

    public function test_member_can_endorse_nomination_and_toggle_without_duplication(): void
    {
        $user = $this->getDemoUser();
        $otherUser = User::factory()->create(['name' => 'مؤيد الترشيح']);
        $community = Community::first();

        $nomination = BookNomination::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'title' => 'حي بن يقظان',
            'author' => 'ابن طفيل',
            'reason' => 'رواية فلسفية حول إدراك المعرفة بالنظر العقلي.',
            'endorsements_count' => 1,
            'status' => 'pending',
        ]);

        // Other user endorses
        $respEndorse = $this->actingAs($otherUser)->post("/nominations/{$nomination->id}/endorse");
        $respEndorse->assertSessionHas('success');

        $nomination->refresh();
        $this->assertEquals(2, $nomination->endorsements_count);
        $this->assertTrue($nomination->isEndorsedBy($otherUser));

        // Toggling again removes endorsement cleanly
        $respRemove = $this->actingAs($otherUser)->post("/nominations/{$nomination->id}/endorse");
        $respRemove->assertSessionHas('success');

        $nomination->refresh();
        $this->assertEquals(1, $nomination->endorsements_count);
        $this->assertFalse($nomination->isEndorsedBy($otherUser));
    }

    public function test_community_manager_can_add_nomination_to_active_poll(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();
        $community->update(['creator_id' => $user->id]);

        $nomination = BookNomination::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'title' => 'كليلة ودمنة',
            'author' => 'ابن المقفع',
            'reason' => 'حكايات رمزية وأمثال سياسية وأخلاقية خالدة.',
            'endorsements_count' => 5,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->post("/nominations/{$nomination->id}/add-to-poll");
        $response->assertSessionHas('success');

        $nomination->refresh();
        $this->assertEquals('added_to_poll', $nomination->status);

        $poll = $community->polls()->where('is_active', true)->first();
        $this->assertNotNull($poll);
        $this->assertTrue($poll->options()->where('custom_title', 'like', '%كليلة ودمنة%')->exists());
    }

    public function test_voting_prevents_duplicate_vote_and_switches_options_accurately(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();

        $poll = BookPoll::create([
            'community_id' => $community->id,
            'title' => 'استطلاع كتاب الشهر للاختبار '.uniqid(),
            'is_active' => true,
        ]);

        $option1 = $poll->options()->create(['custom_title' => 'الخيار الأول', 'votes_count' => 0]);
        $option2 = $poll->options()->create(['custom_title' => 'الخيار الثاني', 'votes_count' => 0]);

        // 1. Initial Vote for Option 1
        $resp1 = $this->actingAs($user)->post("/polls/{$poll->id}/vote", ['option_id' => $option1->id]);
        $resp1->assertSessionHas('success');

        $option1->refresh();
        $this->assertEquals(1, $option1->votes_count);
        $this->assertEquals(1, $poll->votes()->where('user_id', $user->id)->count());

        // 2. Voting for same Option 1 again -> Duplicate vote prevented, count does not change
        $respDuplicate = $this->actingAs($user)->post("/polls/{$poll->id}/vote", ['option_id' => $option1->id]);
        $respDuplicate->assertSessionHas('success');

        $option1->refresh();
        $this->assertEquals(1, $option1->votes_count);
        $this->assertEquals(1, $poll->votes()->where('user_id', $user->id)->count());

        // 3. Switching vote to Option 2 -> Option 1 decrements to 0, Option 2 increments to 1
        $respSwitch = $this->actingAs($user)->post("/polls/{$poll->id}/vote", ['option_id' => $option2->id]);
        $respSwitch->assertSessionHas('success');

        $option1->refresh();
        $option2->refresh();
        $this->assertEquals(0, $option1->votes_count);
        $this->assertEquals(1, $option2->votes_count);
        $this->assertEquals($option2->id, $poll->votes()->where('user_id', $user->id)->first()->book_poll_option_id);
    }

    public function test_manager_can_close_poll_and_crown_winning_book_of_the_month(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();
        $community->update(['creator_id' => $user->id]);

        $poll = BookPoll::create([
            'community_id' => $community->id,
            'title' => 'استطلاع تتويج الفائز '.uniqid(),
            'is_active' => true,
        ]);

        $winningBook = Book::create([
            'title' => 'كتاب الفائز بالتصويت '.uniqid(),
            'author' => 'مؤلف فائز',
            'category' => 'أدب',
            'total_pages' => 300,
            'cover_color' => '#173D36',
        ]);

        $winningOption = $poll->options()->create([
            'book_id' => $winningBook->id,
            'custom_title' => $winningBook->title,
            'votes_count' => 15,
        ]);

        $poll->options()->create([
            'custom_title' => 'كتاب منافس آخر',
            'votes_count' => 5,
        ]);

        // Manager closes poll
        $response = $this->actingAs($user)->post("/polls/{$poll->id}/close-and-set-winner");
        $response->assertSessionHas('success');

        $poll->refresh();
        $community->refresh();

        $this->assertFalse((bool) $poll->is_active);
        $this->assertEquals($winningBook->id, $poll->winner_book_id);
        $this->assertEquals($winningBook->id, $community->current_book_id);

        // Community announcement created
        $this->assertDatabaseHas('community_announcements', [
            'community_id' => $community->id,
            'is_pinned' => true,
        ]);
    }

    public function test_user_level_ladder_and_points_ledger(): void
    {
        $user = User::factory()->create(['points' => 0]);

        // Level 1: قارئ ناشئ
        $this->assertEquals(1, $user->level_data['number']);
        $this->assertEquals('قارئ ناشئ', $user->level_data['name']);

        // Award points -> Level 2: قارئ مطلع
        $user->addPoints(150, 'reading_session', 'جلسة قراءة مميزة');
        $user->refresh();
        $this->assertEquals(2, $user->level_data['number']);
        $this->assertEquals('قارئ مطلع', $user->level_data['name']);
        $this->assertEquals(2, $user->level);

        // Verify transaction logged in point_transactions
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $user->id,
            'points' => 150,
            'activity_type' => 'reading_session',
        ]);

        // Award points -> Level 4: باحث معرفي
        $user->addPoints(500, 'book_finished', 'إتمام قراءة 3 كتب');
        $user->refresh();
        $this->assertEquals(4, $user->level_data['number']);
        $this->assertEquals('باحث معرفي', $user->level_data['name']);

        // Award points -> Level 5: حكيم الرواق
        $user->addPoints(400, 'bonus', 'بلوغ قمة الأثر المعرفي');
        $user->refresh();
        $this->assertEquals(5, $user->level_data['number']);
        $this->assertEquals('حكيم الرواق', $user->level_data['name']);
    }

    public function test_community_manager_can_create_and_award_community_badge(): void
    {
        $manager = $this->getDemoUser();
        $community = Community::first();
        $community->update(['creator_id' => $manager->id]);

        $member = User::factory()->create(['name' => 'عضو مستحق للوسام', 'points' => 50]);

        // 1. Manager creates a community badge
        $createResp = $this->actingAs($manager)->post("/communities/{$community->id}/badges", [
            'name' => 'وسام فارس الحوار الفلسفي',
            'description' => 'يُمنح تقديراً للإسهامات المعرفية الرصينة في مناقشات فصول الفلسفة.',
            'icon' => 'crown',
            'color' => '#C3A15D',
        ]);
        $createResp->assertSessionHas('success');

        $badge = CommunityBadge::where('community_id', $community->id)->where('name', 'وسام فارس الحوار الفلسفي')->first();
        $this->assertNotNull($badge);

        // 2. Manager awards badge to member
        $awardResp = $this->actingAs($manager)->post("/communities/badges/{$badge->id}/award", [
            'user_id' => $member->id,
            'reason' => 'حضور متميز وطرح عميق خلال حلقات النقاش هذا الشهر.',
        ]);
        $awardResp->assertSessionHas('success');

        $this->assertTrue($badge->users()->where('user_id', $member->id)->exists());

        $member->refresh();
        $this->assertEquals(100, $member->points); // 50 + 50

        // In-app notification sent
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member->id,
            'type' => 'community_badge',
        ]);

        // Duplicate award prevention
        $awardDuplicate = $this->actingAs($manager)->post("/communities/badges/{$badge->id}/award", [
            'user_id' => $member->id,
            'reason' => 'محاولة تقليد مكررة.',
        ]);
        $awardDuplicate->assertSessionHas('info');
    }

    public function test_reading_achievements_view_renders_levels_points_and_badges(): void
    {
        $user = $this->getDemoUser();

        $response = $this->actingAs($user)->get('/reading?tab=achievements');
        $response->assertStatus(200);

        // Level ladder & title
        $response->assertSee('المرتبة القرائية الحالية');
        $response->assertSee('المستوى');

        // Global badges
        $response->assertSee('الأوسمة والإنجازات الشرفية');
        $response->assertSee('فاتحة الرواق');

        // Community Badges
        $response->assertSee('أوسمة المجالس الشرفية');

        // Points Ledger
        $response->assertSee('سجل حركات ونقاط الأثر القرائي');
    }

    public function test_communities_page_renders_nominations_past_books_and_community_badges(): void
    {
        $user = $this->getDemoUser();
        $community = Community::first();

        $response = $this->actingAs($user)->get("/communities?community_id={$community->id}");
        $response->assertStatus(200);

        // Book nominations section
        $response->assertSee('ترشيحات القراء لكتاب الشهر');
        $response->assertSee('اقترح كتاباً');

        // Past books archive button & modal
        $response->assertSee('أرشيف كتب الأشهر السابقة');

        // Community Badges & Honors
        $response->assertSee('أوسمة المجلس ولوحة الشرف');
    }
}
