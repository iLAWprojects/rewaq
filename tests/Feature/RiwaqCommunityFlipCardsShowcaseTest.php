<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqCommunityFlipCardsShowcaseTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * اختبار ظهور معرض بطاقات المجالس ثلاثية الأبعاد (3D Flip Cards) مع بيانات الكتب النشطة
     */
    public function test_communities_page_renders_3d_flip_showcase_with_active_books(): void
    {
        $user = User::first() ?? User::factory()->create();

        $book = Book::first();
        $this->assertNotNull($book);

        $community = Community::first();
        $this->assertNotNull($community);

        $response = $this->actingAs($user)->get(route('communities.index', ['community_id' => $community->id]));
        $response->assertStatus(200);

        // Assert the communities strip renders with the selected community highlighted
        $response->assertSee('knowledge-councils-showcase', false);
        $response->assertSee('cm-card active', false);
        $response->assertSee('cm-tabs', false);

        // Assert old switcher bar was removed
        $response->assertDontSee('community-switcher-bar', false);

        // Assert Council Details & Active Book
        $response->assertSee($community->name);
        if ($community->currentBook) {
            $response->assertSee($community->currentBook->title);
        }

        // Assert another community has a working "الدخول إلى" navigation link
        $otherCommunity = Community::where('id', '!=', $community->id)->first();
        if ($otherCommunity) {
            // Verify navigating to that community switches active council
            $switchResponse = $this->actingAs($user)->get(route('communities.index', ['community_id' => $otherCommunity->id, 'scope' => 'all']));
            $switchResponse->assertStatus(200);
            $switchResponse->assertSee($otherCommunity->name);
        }
    }

    /**
     * اختبار أن المستخدم المنضم لمجالس تظهر له افتراضياً مجالسه فقط (Joined Scope)
     */
    public function test_authenticated_user_with_joined_communities_defaults_to_joined_scope_and_only_sees_joined(): void
    {
        $user = User::factory()->create();
        $joinedCommunity = Community::firstOrCreate(
            ['code' => 'TEST-JOINED'],
            ['name' => 'مجلس الاختبار المنضم', 'category' => 'أدب', 'type' => 'open', 'creator_id' => $user->id]
        );
        $unjoinedCommunity = Community::firstOrCreate(
            ['code' => 'TEST-UNJOINED'],
            ['name' => 'مجلس غير منضم إليه أبداً', 'category' => 'فكر', 'type' => 'open', 'creator_id' => $user->id]
        );

        $joinedCommunity->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
        $unjoinedCommunity->members()->detach($user->id);

        // Access communities index directly without parameters (like a fresh page visit or refresh)
        $response = $this->actingAs($user)->get(route('communities.index'));
        $response->assertStatus(200);

        // Must see joined community
        $response->assertSee($joinedCommunity->name);
        // Must NOT see unjoined community in the cards showcase
        $response->assertDontSee($unjoinedCommunity->name);
        // Must see Scope Capsule tabs
        $response->assertSee('نواديي');
        $response->assertSee('اكتشف');
    }

    /**
     * اختبار الانضمام لمجلس جديد وعند عمل Refresh يظهر المجلس الجديد في مجالسي
     */
    public function test_joining_a_community_and_refreshing_page_adds_it_to_joined_showcase(): void
    {
        $user = User::factory()->create();
        $commA = Community::firstOrCreate(
            ['code' => 'TEST-COM-A'],
            ['name' => 'مجلس البداية المنضم', 'category' => 'تاريخ', 'type' => 'open', 'creator_id' => $user->id]
        );
        $commB = Community::firstOrCreate(
            ['code' => 'TEST-COM-B'],
            ['name' => 'مجلس جديد للانضمام', 'category' => 'فلسفة', 'type' => 'open', 'creator_id' => $user->id]
        );

        $commA->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
        $commB->members()->detach($user->id);

        // Before joining: commB is NOT in joined showcase
        $resBefore = $this->actingAs($user)->get(route('communities.index'));
        $resBefore->assertSee($commA->name);
        $resBefore->assertDontSee($commB->name);

        // Join commB
        $joinRes = $this->actingAs($user)->post(route('communities.toggle', $commB->id));
        $joinRes->assertRedirect();

        // Refresh communities.index: now commB MUST appear among joined communities!
        $resAfter = $this->actingAs($user)->get(route('communities.index'));
        $resAfter->assertStatus(200);
        $resAfter->assertSee($commA->name);
        $resAfter->assertSee($commB->name);
    }

    /**
     * اختبار التبديل إلى استكشاف جميع المجالس (Scope = all) لرؤية كافة المجالس
     */
    public function test_switching_to_all_scope_shows_all_communities_for_discovery(): void
    {
        $user = User::factory()->create();
        $comm1 = Community::firstOrCreate(
            ['code' => 'TEST-ALL-1'],
            ['name' => 'مجلس استكشافي أول', 'category' => 'رواية', 'type' => 'open', 'creator_id' => $user->id]
        );
        $comm2 = Community::firstOrCreate(
            ['code' => 'TEST-ALL-2'],
            ['name' => 'مجلس استكشافي ثان', 'category' => 'شعر', 'type' => 'open', 'creator_id' => $user->id]
        );

        $comm1->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
        $comm2->members()->detach($user->id);

        // In scope=all, both communities MUST appear
        $response = $this->actingAs($user)->get(route('communities.index', ['scope' => 'all']));
        $response->assertStatus(200);
        $response->assertSee($comm1->name);
        $response->assertSee($comm2->name);
    }

    /**
     * اختبار زر "الانضمام لنادٍ" وعرض نوادي القراءة الموجودة غير المشترك فيها فقط
     */
    public function test_unjoined_scope_button_displays_only_unsubscribed_communities(): void
    {
        $user = User::factory()->create();

        $joinedComm = Community::firstOrCreate(
            ['code' => 'TEST-UNJ-JOINED'],
            ['name' => 'مجلس تم الاشتراك فيه مسبقاً', 'category' => 'أدب', 'type' => 'open', 'creator_id' => $user->id]
        );
        $unjoinedComm1 = Community::firstOrCreate(
            ['code' => 'TEST-UNJ-AVAIL-1'],
            ['name' => 'مجلس غير مشترك فيه 1', 'category' => 'فلسفة', 'type' => 'open', 'creator_id' => $user->id]
        );
        $unjoinedComm2 = Community::firstOrCreate(
            ['code' => 'TEST-UNJ-AVAIL-2'],
            ['name' => 'مجلس غير مشترك فيه 2', 'category' => 'فكر', 'type' => 'open', 'creator_id' => $user->id]
        );

        $joinedComm->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
        $unjoinedComm1->members()->detach($user->id);
        $unjoinedComm2->members()->detach($user->id);

        // Request with scope=unjoined (زر الانضمام لنادٍ)
        $response = $this->actingAs($user)->get(route('communities.index', ['scope' => 'unjoined']));
        $response->assertStatus(200);

        // Assert button 'الانضمام لنادٍ' is present
        $response->assertSee('اكتشف');

        // MUST see unjoined communities
        $response->assertSee($unjoinedComm1->name);
        $response->assertSee($unjoinedComm2->name);

        // MUST NOT see joined community in the unjoined showcase
        $response->assertDontSee($joinedComm->name);

        // Direct 1-click join action exists on the card back
        $response->assertSee('cm-card', false);
    }

    /**
     * اختبار استعادة آخر مجلس تمت زيارته عند الذهاب للرئيسية والعودة لصفحة نوادي القراءة
     */
    public function test_user_navigating_away_and_returning_to_communities_restores_last_active_council(): void
    {
        $user = User::factory()->create();

        $firstCouncil = Community::firstOrCreate(
            ['code' => 'COUNCIL-FIRST'],
            ['name' => 'مجلس الأدب والتراث الأول', 'category' => 'أدب', 'type' => 'open', 'creator_id' => $user->id]
        );
        $secondCouncil = Community::firstOrCreate(
            ['code' => 'COUNCIL-SECOND'],
            ['name' => 'مجلس الرواية العربية الحديثة', 'category' => 'رواية', 'type' => 'open', 'creator_id' => $user->id]
        );

        $firstCouncil->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
        $secondCouncil->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);

        // 1. User enters the second council (clicks "الدخول إلى مجلس الرواية العربية")
        $enterResponse = $this->actingAs($user)->get(route('communities.index', ['community_id' => $secondCouncil->id]));
        $enterResponse->assertStatus(200);
        $enterResponse->assertSee($secondCouncil->name);

        // 2. User navigates away to Home page (/home)
        $homeResponse = $this->actingAs($user)->get(route('libraries.index'));
        $homeResponse->assertStatus(200);

        // 3. User returns to Communities page directly (/communities without query params)
        $returnResponse = $this->actingAs($user)->get(route('communities.index'));
        $returnResponse->assertStatus(200);

        // Active council in masthead MUST be the second council (last visited), NOT reset to first council!
        $this->assertEquals($secondCouncil->id, $returnResponse->viewData('community')->id);
        $returnResponse->assertSee($secondCouncil->name);
    }
}
