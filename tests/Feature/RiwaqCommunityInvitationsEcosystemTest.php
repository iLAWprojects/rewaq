<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\QrCodeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiwaqCommunityInvitationsEcosystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $creator;

    protected User $invitee;

    protected User $secondInvitee;

    protected Community $community;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create([
            'name' => 'راعي المجلس',
            'email' => 'host@riwaq.test',
            'points' => 100,
        ]);

        $this->invitee = User::factory()->create([
            'name' => 'قارئ مدعو',
            'email' => 'guest@riwaq.test',
            'points' => 20,
        ]);

        $this->secondInvitee = User::factory()->create([
            'name' => 'قارئ ثان',
            'email' => 'guest2@riwaq.test',
            'points' => 15,
        ]);

        $this->community = Community::create([
            'name' => 'مجلس حكمة الفلاسفة',
            'category' => 'فكر وفلسفة',
            'description' => 'ملتقى رصين لقراءة ونقاش عيون الفلسفة الكلاسيكية والمعاصرة.',
            'cover_color' => '#173D36',
            'creator_id' => $this->creator->id,
            'type' => 'public',
            'language' => 'العربية',
            'city' => 'الرياض',
            'max_members' => 50,
            'allow_member_invites' => true,
        ]);

        // Add creator as owner member
        $this->community->members()->attach($this->creator->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);
    }

    /**
     * 1. دعوة باسم المستخدم أو البريد الإلكتروني مع Token عشوائي و Expiry و Max Uses
     */
    public function test_can_create_invitation_by_username_or_email_with_token_expiry_and_max_uses(): void
    {
        $response = $this->actingAs($this->creator)->post(route('communities.invitations.store', $this->community->id), [
            'recipient' => 'guest@riwaq.test',
            'expiry_days' => 7,
            'max_uses' => 1,
            'custom_message' => 'يسعدني انضمامك معنا لمناقشة كتاب الشهر في مجلس حكمة الفلاسفة.',
        ]);

        $response->assertSessionHas('success');

        $invitation = CommunityInvitation::where('community_id', $this->community->id)
            ->where('recipient_user_id', $this->invitee->id)
            ->first();

        $this->assertNotNull($invitation);
        $this->assertEquals(CommunityInvitation::STATUS_SENT, $invitation->status);
        $this->assertEquals(40, strlen($invitation->token));
        $this->assertEquals(1, $invitation->max_uses);
        $this->assertEquals(0, $invitation->uses_count);
        $this->assertNotNull($invitation->expires_at);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertEquals('يسعدني انضمامك معنا لمناقشة كتاب الشهر في مجلس حكمة الفلاسفة.', $invitation->custom_message);

        // Verification of in-app notification sent to the invited user
        $notification = UserNotification::where('user_id', $this->invitee->id)
            ->where('type', 'community_invite')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('مجلس حكمة الفلاسفة', $notification->body);
        $this->assertStringContainsString($invitation->token, $notification->link);
    }

    /**
     * 2. دعم تخزين رقم الهاتف للتفعيل لاحقاً (SMS / WhatsApp)
     */
    public function test_can_store_phone_number_for_future_integration(): void
    {
        $response = $this->actingAs($this->creator)->post(route('communities.invitations.store', $this->community->id), [
            'recipient' => 'قارئ خارجي',
            'recipient_phone' => '+966551234567',
            'expiry_days' => 30,
            'max_uses' => 5,
            'custom_message' => 'دعوة عبر رسالة نصية/واتساب.',
        ]);

        $response->assertSessionHas('success');

        $invitation = CommunityInvitation::where('recipient_phone', '+966551234567')->first();

        $this->assertNotNull($invitation);
        $this->assertEquals('+966551234567', $invitation->recipient_phone);
        $this->assertEquals('قارئ خارجي', $invitation->recipient_name);
        $this->assertEquals(5, $invitation->max_uses);
        $this->assertEquals(CommunityInvitation::STATUS_SENT, $invitation->status);
    }

    /**
     * 3. رمز QR Code يعكس رابط الدعوة نفسه بدقة
     */
    public function test_invitation_qr_code_matches_invitation_url(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'token' => 'test-unique-token-987654321',
            'status' => CommunityInvitation::STATUS_SENT,
            'max_uses' => 10,
        ]);

        $expectedUrl = route('communities.invitations.show', ['token' => 'test-unique-token-987654321']);
        $this->assertEquals($expectedUrl, $invitation->invite_url);

        // Generate SVG via service and model accessor
        $qrSvg = $invitation->qr_code_svg;

        $this->assertNotEmpty($qrSvg);
        $this->assertStringContainsString('<svg', $qrSvg);
        $this->assertStringContainsString('</svg>', $qrSvg);
        $this->assertStringContainsString('viewBox="0 0', $qrSvg);

        // Check QrCodeService standalone generation
        $serviceSvg = QrCodeService::generateSvg($expectedUrl, 250, 250);
        $this->assertStringContainsString('width="250"', $serviceSvg);
        $this->assertStringContainsString('height="250"', $serviceSvg);
    }

    /**
     * 4. انتقال الحالة من sent إلى opened عند زيارة الرابط
     */
    public function test_visiting_invite_link_updates_status_from_sent_to_opened(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'recipient_user_id' => $this->invitee->id,
            'token' => 'visit-test-token-112233',
            'status' => CommunityInvitation::STATUS_SENT,
            'max_uses' => 1,
        ]);

        $this->assertEquals(CommunityInvitation::STATUS_SENT, $invitation->status);
        $this->assertNull($invitation->opened_at);

        // Visit landing page
        $response = $this->get(route('communities.invitations.show', $invitation->token));

        $response->assertStatus(200);
        $response->assertSee('دعوة للانضمام إلى مجلس «مجلس حكمة الفلاسفة»');
        $response->assertSee('قبول الدعوة والانضمام للمجلس');

        $invitation->refresh();
        $this->assertEquals(CommunityInvitation::STATUS_OPENED, $invitation->status);
        $this->assertNotNull($invitation->opened_at);
    }

    /**
     * 5. قبول الدعوة يضم المستخدم للمجلس ويحدث الحالة إلى accepted
     */
    public function test_accepting_invitation_joins_user_and_updates_status_to_accepted(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'recipient_user_id' => $this->invitee->id,
            'token' => 'accept-test-token-445566',
            'status' => CommunityInvitation::STATUS_SENT,
            'max_uses' => 1,
            'uses_count' => 0,
        ]);

        $this->assertFalse($this->community->members()->where('user_id', $this->invitee->id)->exists());

        $response = $this->actingAs($this->invitee)
            ->post(route('communities.invitations.accept', $invitation->token));

        $response->assertRedirect(route('communities.index', ['community_id' => $this->community->id]));
        $response->assertSessionHas('success');

        // Verify membership
        $this->assertTrue($this->community->members()->where('user_id', $this->invitee->id)->exists());

        // Verify invitation status updated to accepted
        $invitation->refresh();
        $this->assertEquals(CommunityInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertEquals(1, $invitation->uses_count);
        $this->assertNotNull($invitation->accepted_at);

        // Verify points awarded
        $this->invitee->refresh();
        $this->assertEquals(35, $this->invitee->points); // 20 + 15

        // Verify Audit Log
        $audit = AuditLog::where('action', 'community_invite_accepted')
            ->where('user_id', $this->invitee->id)
            ->first();
        $this->assertNotNull($audit);
    }

    /**
     * 6. رفض الدعوة يحدث الحالة إلى rejected
     */
    public function test_rejecting_invitation_updates_status_to_rejected(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'recipient_user_id' => $this->invitee->id,
            'token' => 'reject-test-token-778899',
            'status' => CommunityInvitation::STATUS_OPENED,
            'max_uses' => 1,
        ]);

        $response = $this->actingAs($this->invitee)
            ->post(route('communities.invitations.reject', $invitation->token));

        $response->assertRedirect(route('communities.index'));
        $response->assertSessionHas('info');

        $invitation->refresh();
        $this->assertEquals(CommunityInvitation::STATUS_REJECTED, $invitation->status);
        $this->assertNotNull($invitation->rejected_at);
        $this->assertFalse($invitation->isValid());

        // Try accepting a rejected invitation -> should be rejected
        $acceptResponse = $this->actingAs($this->invitee)
            ->post(route('communities.invitations.accept', $invitation->token));

        $acceptResponse->assertSessionHas('error');
    }

    /**
     * 7. الدعوة منتهية الصلاحية (Expired) لا يمكن قبولها وتظهر كمنتهية
     */
    public function test_expired_invitation_cannot_be_accepted_and_status_is_expired(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'recipient_user_id' => $this->invitee->id,
            'token' => 'expired-test-token-000111',
            'status' => CommunityInvitation::STATUS_SENT,
            'expires_at' => now()->subDay(), // Expired yesterday
            'max_uses' => 1,
        ]);

        $this->assertTrue($invitation->isExpired());
        $this->assertFalse($invitation->isValid());

        // Attempt to accept
        $response = $this->actingAs($this->invitee)
            ->post(route('communities.invitations.accept', $invitation->token));

        $response->assertSessionHas('error');

        $invitation->refresh();
        $this->assertEquals(CommunityInvitation::STATUS_EXPIRED, $invitation->status);
        $this->assertFalse($this->community->members()->where('user_id', $this->invitee->id)->exists());
    }

    /**
     * 8. فرض حد مرات الاستخدام الأقصى (Max Uses) بدقة
     */
    public function test_max_uses_limit_is_strictly_enforced(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'token' => 'multi-use-token-limit-2',
            'status' => CommunityInvitation::STATUS_SENT,
            'max_uses' => 1, // Only 1 use allowed
            'uses_count' => 0,
        ]);

        // First user accepts successfully
        $res1 = $this->actingAs($this->invitee)
            ->post(route('communities.invitations.accept', $invitation->token));
        $res1->assertSessionHas('success');

        $invitation->refresh();
        $this->assertEquals(1, $invitation->uses_count);
        $this->assertEquals(CommunityInvitation::STATUS_ACCEPTED, $invitation->status);

        // Second user attempts to accept the exhausted link -> blocked
        $res2 = $this->actingAs($this->secondInvitee)
            ->post(route('communities.invitations.accept', $invitation->token));

        $res2->assertSessionHas('error');
        $this->assertFalse($this->community->members()->where('user_id', $this->secondInvitee->id)->exists());
    }

    /**
     * 9. إلغاء الدعوة (Revoke) بواسطة الإدارة أو المنشئ وتعطيل الرابط تماماً
     */
    public function test_manager_can_revoke_invitation_and_revoked_invitation_cannot_be_accepted(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'token' => 'revoke-test-token-555',
            'status' => CommunityInvitation::STATUS_SENT,
            'max_uses' => 5,
        ]);

        // Revoke the invitation
        $response = $this->actingAs($this->creator)
            ->post(route('communities.invitations.revoke', $invitation->id));

        $response->assertSessionHas('success');

        $invitation->refresh();
        $this->assertEquals(CommunityInvitation::STATUS_REVOKED, $invitation->status);
        $this->assertNotNull($invitation->revoked_at);
        $this->assertEquals($this->creator->id, $invitation->revoked_by);
        $this->assertTrue($invitation->isRevoked());
        $this->assertFalse($invitation->isValid());

        // Attempting to accept a revoked invitation
        $acceptResponse = $this->actingAs($this->invitee)
            ->post(route('communities.invitations.accept', $invitation->token));

        $acceptResponse->assertSessionHas('error');
        $this->assertFalse($this->community->members()->where('user_id', $this->invitee->id)->exists());
    }

    /**
     * 10. قيد الفرادة (Unique Constraint) يمنع تكرار العضوية لنفس المستخدم والمجلس
     */
    public function test_unique_constraint_strictly_prevents_duplicate_membership(): void
    {
        // Add invitee as member first
        $this->community->members()->attach($this->invitee->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->assertEquals(1, $this->community->members()->where('user_id', $this->invitee->id)->count());

        // Create an invitation
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'recipient_user_id' => $this->invitee->id,
            'token' => 'duplicate-member-token-999',
            'status' => CommunityInvitation::STATUS_SENT,
            'max_uses' => 1,
        ]);

        // Attempt to accept invite while already a member
        $response = $this->actingAs($this->invitee)
            ->post(route('communities.invitations.accept', $invitation->token));

        $response->assertRedirect(route('communities.index', ['community_id' => $this->community->id]));
        $response->assertSessionHas('info');

        // Still exactly 1 record
        $this->assertEquals(1, $this->community->members()->where('user_id', $this->invitee->id)->count());

        // Attempt raw direct duplicate insert to verify DB schema unique constraint
        $this->expectException(QueryException::class);
        DB::table('community_members')->insert([
            'community_id' => $this->community->id,
            'user_id' => $this->invitee->id,
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 11. اختبار علاقات النموذج Community و User والدوال المساعدة
     */
    public function test_community_and_user_invitation_relationships_and_accessors(): void
    {
        $invitation = CommunityInvitation::create([
            'community_id' => $this->community->id,
            'inviter_id' => $this->creator->id,
            'recipient_user_id' => $this->invitee->id,
            'token' => 'relationship-test-token-777',
            'status' => CommunityInvitation::STATUS_SENT,
            'max_uses' => 1,
        ]);

        // Community relationships
        $this->assertTrue($this->community->invitations->contains($invitation));
        $this->assertTrue($this->community->activeInvitations->contains($invitation));

        // User sent and received relationships
        $this->assertTrue($this->creator->sentCommunityInvitations->contains($invitation));
        $this->assertTrue($this->invitee->receivedCommunityInvitations->contains($invitation));

        // Accessors & localized status labels
        $this->assertEquals('مرسلة', $invitation->status_label);
        $this->assertEquals('badge-info', $invitation->status_badge_class);

        $invitation->status = CommunityInvitation::STATUS_OPENED;
        $this->assertEquals('تم الفتح', $invitation->status_label);

        $invitation->status = CommunityInvitation::STATUS_ACCEPTED;
        $this->assertEquals('مقبولة', $invitation->status_label);

        $invitation->status = CommunityInvitation::STATUS_REJECTED;
        $this->assertEquals('مرفوضة', $invitation->status_label);

        $invitation->status = CommunityInvitation::STATUS_EXPIRED;
        $this->assertEquals('منتهية الصلاحية', $invitation->status_label);

        $invitation->status = CommunityInvitation::STATUS_REVOKED;
        $this->assertEquals('ملغاة', $invitation->status_label);
    }

    public function test_owner_sees_founder_badge_instead_of_member_leave_button(): void
    {
        $response = $this->actingAs($this->creator)->get(route('communities.index', [
            'community_id' => $this->community->id,
            'scope' => 'joined',
        ]));

        $response->assertOk();
        $response->assertSee('أنت المؤسس');
        $response->assertDontSee('أنت عضو · مغادرة');
    }

    public function test_invitation_store_flashes_generated_invite_url_to_session(): void
    {
        $response = $this->actingAs($this->creator)->post(route('communities.invitations.store', $this->community->id), [
            'expiry_days' => 7,
            'max_uses' => 5,
        ]);

        $response->assertSessionHas('generated_invite_url');
        $response->assertSessionHas('generated_token');
    }
}
