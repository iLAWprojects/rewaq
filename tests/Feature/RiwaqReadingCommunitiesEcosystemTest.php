<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\CommunityAnnouncement;
use App\Models\CommunityJoinRequest;
use App\Models\Discussion;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqReadingCommunitiesEcosystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $creator;

    protected User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create([
            'name' => 'مؤسس المجلس',
            'email' => 'founder@riwaq.test',
            'points' => 200,
        ]);

        $this->reader = User::factory()->create([
            'name' => 'قارئ باحث',
            'email' => 'reader@riwaq.test',
            'points' => 50,
        ]);
    }

    public function test_community_creation_stores_all_profile_fields_and_grants_creator_owner_role_with_points(): void
    {
        $payload = [
            'name' => 'مجلس الفكر الأندلسي',
            'description' => 'مدارسة عيون الفكر والأدب الأندلسي في بيئة نقاشية راقية.',
            'type' => 'open',
            'language' => 'العربية',
            'city' => 'غرناطة المعرفية',
            'interests' => 'تاريخ, أدب, فلسفة',
            'max_members' => 75,
            'rules' => 'الالتزام بأدب الحوار واحترام الآراء واستخدام وسوم الحرق.',
            'welcome_message' => 'أهلاً بك في مجلس الفكر الأندلسي بين أروقة البيان!',
            'cover_color' => '#1A3C34',
            'allow_member_invites' => '1',
            'allow_member_challenges' => '1',
            'allow_member_meetings' => '1',
        ];

        $initialPoints = $this->creator->points;

        $response = $this->actingAs($this->creator)->post(route('communities.store'), $payload);

        $response->assertRedirect();

        $community = Community::where('name', 'مجلس الفكر الأندلسي')->first();
        $this->assertNotNull($community);
        $this->assertEquals('open', $community->type);
        $this->assertEquals('العربية', $community->language);
        $this->assertEquals('غرناطة المعرفية', $community->city);
        $this->assertEquals(75, $community->max_members);
        $this->assertEquals('أهلاً بك في مجلس الفكر الأندلسي بين أروقة البيان!', $community->welcome_message);
        $this->assertTrue($community->allow_member_invites);
        $this->assertTrue($community->allow_member_challenges);
        $this->assertTrue($community->allow_member_meetings);

        // Verify creator is attached as owner
        $this->assertTrue($community->isOwner($this->creator));
        $this->assertEquals('owner', $community->userRole($this->creator));

        // Verify creator was awarded 50 points
        $this->creator->refresh();
        $this->assertEquals($initialPoints + 50, $this->creator->points);

        // Verify AuditLog record
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create_community',
            'user_id' => $this->creator->id,
            'auditable_id' => $community->id,
        ]);
    }

    public function test_four_community_types_visibility_and_search_behavior(): void
    {
        // 1. Private community
        $privateComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس السرية الأكاديمية الخاص',
            'code' => 'PRIV-001',
            'type' => 'private',
            'is_visible_in_search' => false,
            'requires_approval' => true,
        ]);

        // 2. Public community
        $publicComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس الفلسفة العام',
            'code' => 'PUBL-001',
            'type' => 'public',
            'is_visible_in_search' => true,
            'requires_approval' => true,
        ]);

        // 3. Open community
        $openComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس القراءة الحرة المفتوح',
            'code' => 'OPEN-001',
            'type' => 'open',
            'is_visible_in_search' => true,
            'requires_approval' => false,
        ]);

        // 4. Institutional community
        $instComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس جامعة الملك سعود المؤسسي',
            'code' => 'INST-001',
            'type' => 'institutional',
            'institutional_domain' => '@ksu.edu.sa',
            'is_visible_in_search' => true,
            'requires_approval' => true,
        ]);

        // Test search exclusion: Private community must NOT appear in search results
        $searchPrivateResponse = $this->get(route('search.index', ['q' => 'السرية الأكاديمية', 'type' => 'communities']));
        $searchPrivateResponse->assertStatus(200);
        $searchPrivateResponse->assertDontSee($privateComm->name);

        // Test search inclusion: Public, Open, Institutional must appear
        $searchPublicResponse = $this->get(route('search.index', ['q' => 'الفلسفة العام', 'type' => 'communities']));
        $searchPublicResponse->assertStatus(200);
        $searchPublicResponse->assertSee($publicComm->name);

        $searchOpenResponse = $this->get(route('search.index', ['q' => 'القراءة الحرة', 'type' => 'communities']));
        $searchOpenResponse->assertStatus(200);
        $searchOpenResponse->assertSee($openComm->name);

        $searchInstResponse = $this->get(route('search.index', ['q' => 'جامعة الملك سعود', 'type' => 'communities']));
        $searchInstResponse->assertStatus(200);
        $searchInstResponse->assertSee($instComm->name);
    }

    public function test_joining_open_community_is_immediate_without_approval(): void
    {
        $openComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس الرواية العالمية المفتوح',
            'code' => 'OPEN-RWQ-22',
            'type' => 'open',
            'is_visible_in_search' => true,
            'requires_approval' => false,
        ]);

        $initialPoints = $this->reader->points;

        $response = $this->actingAs($this->reader)->post(route('communities.toggle', $openComm->id));

        $response->assertSessionHas('success');
        $this->assertTrue($openComm->members()->where('user_id', $this->reader->id)->exists());

        $this->reader->refresh();
        $this->assertEquals($initialPoints + 25, $this->reader->points);

        // Ensure no pending join request was created
        $this->assertDatabaseMissing('community_join_requests', [
            'community_id' => $openComm->id,
            'user_id' => $this->reader->id,
        ]);
    }

    public function test_joining_public_community_routes_to_join_request_requiring_approval(): void
    {
        $publicComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس الدراسات النقدية العام',
            'code' => 'PUBL-RWQ-33',
            'type' => 'public',
            'is_visible_in_search' => true,
            'requires_approval' => true,
        ]);

        $response = $this->actingAs($this->reader)->post(route('communities.toggle', $publicComm->id));

        // Should route to join request
        $this->assertFalse($publicComm->members()->where('user_id', $this->reader->id)->exists());

        $joinRequest = CommunityJoinRequest::where('community_id', $publicComm->id)
            ->where('user_id', $this->reader->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($joinRequest);

        // Manager approves the request
        $approveResponse = $this->actingAs($this->creator)->post(route('communities.approveJoinRequest', $joinRequest->id));
        $approveResponse->assertSessionHas('success');

        $joinRequest->refresh();
        $this->assertEquals('approved', $joinRequest->status);
        $this->assertTrue($publicComm->members()->where('user_id', $this->reader->id)->exists());
    }

    public function test_institutional_community_auto_approves_members_matching_domain(): void
    {
        $instComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'نادي القراءة بجامعة الملك فهد',
            'code' => 'INST-KFUPM',
            'type' => 'institutional',
            'institutional_domain' => '@kfupm.edu.sa',
            'is_visible_in_search' => true,
            'requires_approval' => true,
        ]);

        $kfupmStudent = User::factory()->create([
            'name' => 'طالب جامعي',
            'email' => 'scholar@kfupm.edu.sa',
            'points' => 10,
        ]);

        $externalUser = User::factory()->create([
            'name' => 'قارئ خارجي',
            'email' => 'outsider@gmail.com',
            'points' => 10,
        ]);

        // 1. KFUPM student requests join -> Auto-approved immediately!
        $response1 = $this->actingAs($kfupmStudent)->post(route('communities.requestJoin', $instComm->id));
        $response1->assertSessionHas('success');
        $this->assertTrue($instComm->members()->where('user_id', $kfupmStudent->id)->exists());

        // 2. External user requests join -> Pending approval
        $response2 = $this->actingAs($externalUser)->post(route('communities.requestJoin', $instComm->id));
        $response2->assertSessionHas('success');
        $this->assertFalse($instComm->members()->where('user_id', $externalUser->id)->exists());
        $this->assertDatabaseHas('community_join_requests', [
            'community_id' => $instComm->id,
            'user_id' => $externalUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_community_capacity_limit_blocks_new_joins_and_approvals(): void
    {
        $cappedComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس النخبة المحدود',
            'code' => 'CAP-002',
            'type' => 'open',
            'max_members' => 2,
        ]);

        // Creator is 1st member
        $cappedComm->members()->attach($this->creator->id, ['role' => 'owner']);

        // Reader is 2nd member -> Capacity reached (2 / 2)
        $cappedComm->members()->attach($this->reader->id, ['role' => 'member']);
        $this->assertTrue($cappedComm->hasReachedCapacity());

        // 3rd user tries to join
        $thirdUser = User::factory()->create(['email' => 'third@riwaq.test']);

        $response = $this->actingAs($thirdUser)->post(route('communities.toggle', $cappedComm->id));
        $response->assertSessionHas('error');
        $this->assertFalse($cappedComm->members()->where('user_id', $thirdUser->id)->exists());

        // Approval attempt also blocked
        $fakeRequest = CommunityJoinRequest::create([
            'community_id' => $cappedComm->id,
            'user_id' => $thirdUser->id,
            'status' => 'pending',
        ]);

        $approveResponse = $this->actingAs($this->creator)->post(route('communities.approveJoinRequest', $fakeRequest->id));
        $approveResponse->assertSessionHas('error');
        $this->assertFalse($cappedComm->members()->where('user_id', $thirdUser->id)->exists());
    }

    public function test_membership_is_completely_independent_from_friendship(): void
    {
        $openComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس الصداقة المستقلة',
            'code' => 'INDEP-001',
            'type' => 'open',
        ]);

        $solitaryUser = User::factory()->create(['name' => 'قارئ منعزل', 'email' => 'solitary@riwaq.test']);

        // Verify zero following/friends
        $this->assertEquals(0, $solitaryUser->following()->count());
        $this->assertEquals(0, $solitaryUser->followers()->count());
        $this->assertFalse($solitaryUser->isFollowing($this->creator));

        // Solitary user joins without any friendship prerequisite
        $response = $this->actingAs($solitaryUser)->post(route('communities.toggle', $openComm->id));
        $response->assertSessionHas('success');
        $this->assertTrue($openComm->members()->where('user_id', $solitaryUser->id)->exists());
    }

    public function test_community_settings_and_permissions_enforcement(): void
    {
        $comm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس إعدادات القراءة',
            'code' => 'SETT-001',
            'type' => 'open',
            'allow_member_challenges' => false,
            'allow_member_meetings' => false,
        ]);
        $comm->members()->attach($this->creator->id, ['role' => 'owner']);
        $comm->members()->attach($this->reader->id, ['role' => 'member']);

        // Non-manager reader cannot create challenge when disabled
        $challengePayload = [
            'title' => 'تحدي قراءة 500 صفحة',
            'description' => 'تحدي مكثف',
            'target_count' => 5,
            'end_date' => now()->addDays(20)->format('Y-m-d'),
        ];

        $challengeDenyResponse = $this->actingAs($this->reader)->post(route('communities.storeChallenge', $comm->id), $challengePayload);
        $challengeDenyResponse->assertStatus(403);

        // Manager updates settings to allow member challenges & meetings
        $settingsPayload = [
            'name' => 'مجلس إعدادات القراءة المحدث',
            'type' => 'open',
            'language' => 'العربية',
            'allow_member_challenges' => '1',
            'allow_member_meetings' => '1',
            'allow_member_invites' => '1',
        ];

        $settingsResponse = $this->actingAs($this->creator)->put(route('communities.updateSettings', $comm->id), $settingsPayload);
        $settingsResponse->assertSessionHas('success');

        $comm->refresh();
        $this->assertTrue($comm->allow_member_challenges);
        $this->assertTrue($comm->allow_member_meetings);

        // Now reader can successfully launch a challenge!
        $challengeAllowResponse = $this->actingAs($this->reader)->post(route('communities.storeChallenge', $comm->id), $challengePayload);
        $challengeAllowResponse->assertSessionHas('success');

        $this->assertDatabaseHas('challenges', [
            'community_id' => $comm->id,
            'title' => 'تحدي قراءة 500 صفحة',
        ]);
    }

    public function test_batch_import_members_for_institutional_community(): void
    {
        $instComm = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'نادي القراءة المؤسسي المعتمد',
            'code' => 'INST-IMPORT',
            'type' => 'institutional',
            'max_members' => 100,
        ]);
        $instComm->members()->attach($this->creator->id, ['role' => 'owner']);

        $user1 = User::factory()->create(['email' => 'import1@riwaq.test']);
        $user2 = User::factory()->create(['email' => 'import2@riwaq.test']);

        $emails = "import1@riwaq.test\nimport2@riwaq.test\nnonexistent@riwaq.test";

        $response = $this->actingAs($this->creator)->post(route('communities.importMembers', $instComm->id), [
            'emails' => $emails,
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue($instComm->members()->where('user_id', $user1->id)->exists());
        $this->assertTrue($instComm->members()->where('user_id', $user2->id)->exists());
    }

    public function test_community_hub_page_renders_all_eight_components(): void
    {
        $book = Book::first() ?? Book::create([
            'title' => 'وحي القلم',
            'author' => 'مصطفى صادق الرافعي',
            'category' => 'أدب',
            'total_pages' => 300,
            'cover_color' => '#173D36',
        ]);

        $community = Community::create([
            'creator_id' => $this->creator->id,
            'name' => 'مجلس الرواق الشامل',
            'code' => 'HUB-TEST-01',
            'type' => 'public',
            'current_book_id' => $book->id,
            'welcome_message' => 'مرحباً بجميع رواد الكلمة في مجلس الرواق الشامل!',
        ]);

        $community->members()->attach($this->creator->id, ['role' => 'owner']);
        $community->members()->attach($this->reader->id, ['role' => 'member']);

        // 1. Meeting
        Meeting::create([
            'community_id' => $community->id,
            'book_id' => $book->id,
            'title' => 'اللقاء الحواري المباشر لكتاب الشهر',
            'meeting_date' => now()->addDays(5),
            'meeting_type' => 'remote',
        ]);

        // 2. Announcement
        CommunityAnnouncement::create([
            'community_id' => $community->id,
            'user_id' => $this->creator->id,
            'title' => 'تنبيه هام حول القراءة القادمة',
            'content' => 'نرجو من جميع الأعضاء إنهاء الفصول الثلاثة الأولى.',
            'is_pinned' => true,
        ]);

        // 3. Discussion
        Discussion::create([
            'community_id' => $community->id,
            'user_id' => $this->reader->id,
            'book_id' => $book->id,
            'content' => 'تأملات بديعة في بلاغة الأسلوب وجمال الصياغة المعنوية.',
        ]);

        // 4. Challenge
        Challenge::create([
            'community_id' => $community->id,
            'title' => 'تحدي إكمال كتاب الشهر',
            'description' => 'اقرأ كتاب الشهر كاملاً وشارك في النقاش.',
            'target_count' => 1,
            'start_date' => now(),
            'end_date' => now()->addDays(15),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->reader)->get(route('communities.index', ['community_id' => $community->id]));

        $response->assertStatus(200);

        // 1. Indicators / Metrics
        $response->assertSee('cm-mast-stats', false);
        $response->assertSee('كتب منجزة');

        // 2. Book of the month
        $response->assertSee('وحي القلم');
        $response->assertSee('مصطفى صادق الرافعي');

        // 3. Active Challenge
        $response->assertSee('التحدي القرائي النشط للمجلس');
        $response->assertSee('تحدي إكمال كتاب الشهر');

        // 4. Upcoming Meeting
        $response->assertSee('اللقاء الحواري المباشر لكتاب الشهر');

        // 5. Announcements
        $response->assertSee('تنبيه هام حول القراءة القادمة');

        // 6. Reviews & Discussions
        $response->assertSee('تأملات بديعة في بلاغة الأسلوب');

        // 7. New Members & Welcome Message
        $response->assertSee('الأعضاء الجدد في المجلس');
        $response->assertSee('مرحباً بجميع رواد الكلمة في مجلس الرواق الشامل!');

        // 8. Leaderboard
        $response->assertSee('لوحة المتصدرين');
        $response->assertSee('مؤسس المجلس');
    }
}
