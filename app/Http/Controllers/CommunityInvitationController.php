<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CommunityInvitationController extends Controller
{
    /**
     * Display a specific invitation landing page via its unique token.
     */
    public function show(Request $request, string $token)
    {
        $invitation = CommunityInvitation::with(['community.creator', 'community.currentBook', 'inviter'])
            ->where('token', $token)
            ->firstOrFail();

        // Check and mark expiration if date passed or uses exhausted
        $invitation->checkAndMarkExpired();

        // If newly accessed and still marked 'sent', transition to 'opened'
        if ($invitation->status === CommunityInvitation::STATUS_SENT && ! $invitation->isExpired()) {
            $invitation->markAsOpened();
        }

        $community = $invitation->community;
        $currentUser = Auth::user();
        $isMember = $currentUser ? $community->members()->where('user_id', $currentUser->id)->exists() : false;

        // Generate matching QR code SVG for this exact URL
        $inviteUrl = $invitation->invite_url;
        $qrSvg = QrCodeService::generateSvg($inviteUrl, 240, 240);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'invitation' => $invitation,
                'community' => $community,
                'is_member' => $isMember,
                'is_valid' => $invitation->isValid(),
                'invite_url' => $inviteUrl,
                'qr_code_svg' => $qrSvg,
            ]);
        }

        return view('pages.community-invitation', compact(
            'invitation',
            'community',
            'isMember',
            'inviteUrl',
            'qrSvg'
        ));
    }

    /**
     * Create a new invitation (by username, email, phone, or general invite link).
     */
    public function store(Request $request, Community $community)
    {
        $user = Auth::user();
        Gate::authorize('invite', $community);

        $validated = $request->validate([
            'recipient' => 'nullable|string|max:150',
            'recipient_phone' => 'nullable|string|max:50',
            'expiry_days' => 'nullable|integer|min:0|max:365',
            'max_uses' => 'nullable|integer|min:1|max:10000',
            'custom_message' => 'nullable|string|max:500',
        ]);

        $recipientUser = null;
        $recipientQuery = trim($validated['recipient'] ?? '');
        $recipientPhone = trim($validated['recipient_phone'] ?? '');

        if (! empty($recipientQuery)) {
            $recipientUser = User::where('email', $recipientQuery)
                ->orWhere('name', $recipientQuery)
                ->first();
        }

        // Calculate expiration date
        $expiresAt = null;
        if (! empty($validated['expiry_days']) && (int) $validated['expiry_days'] > 0) {
            $expiresAt = now()->addDays((int) $validated['expiry_days']);
        }

        // Max uses (default null for multi-use unless specified, or 1 if targeted recipient)
        $maxUses = null;
        if (isset($validated['max_uses']) && (int) $validated['max_uses'] > 0) {
            $maxUses = (int) $validated['max_uses'];
        } elseif ($recipientUser) {
            $maxUses = 1;
        }

        // Generate random secure token
        $token = Str::random(40);

        $invitation = CommunityInvitation::create([
            'community_id' => $community->id,
            'inviter_id' => $user->id,
            'recipient_user_id' => $recipientUser?->id,
            'token' => $token,
            'recipient_name' => $recipientUser?->name ?? ($recipientQuery ?: null),
            'recipient_email' => $recipientUser?->email ?? (filter_var($recipientQuery, FILTER_VALIDATE_EMAIL) ? $recipientQuery : null),
            'recipient_phone' => ! empty($recipientPhone) ? $recipientPhone : null,
            'custom_message' => $validated['custom_message'] ?? null,
            'status' => CommunityInvitation::STATUS_SENT,
            'expires_at' => $expiresAt,
            'max_uses' => $maxUses,
            'uses_count' => 0,
        ]);

        // Send internal notification if recipient user exists
        if ($recipientUser) {
            UserNotification::create([
                'user_id' => $recipientUser->id,
                'title' => 'دعوة انضمام لمجلس قراءة 💌',
                'body' => "وجّه إليك «{$user->name}» دعوة خاصة للانضمام إلى مجلس «{$community->name}».",
                'icon' => 'users',
                'link' => route('communities.invitations.show', ['token' => $token]),
                'type' => 'community_invite',
                'is_read' => false,
            ]);
        }

        // Award points to inviter
        $user->increment('points', 10);

        // Audit log
        AuditLog::record(
            $user,
            'community_invite_created',
            $invitation,
            "أنشأ المستخدم «{$user->name}» رابط دعوة جديد لمجلس «{$community->name}» (رمز الدعوة: {$token})."
        );

        $msg = $recipientUser
            ? "تم إرسال دعوة الانضمام بنجاح إلى «{$recipientUser->name}»!"
            : 'تم إنشاء رابط الدعوة ورمز QR بنجاح!';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'invitation' => $invitation,
                'invite_url' => $invitation->invite_url,
                'qr_code_svg' => $invitation->qr_code_svg,
            ]);
        }

        return back()->with('success', $msg)
            ->with('generated_invite_url', $invitation->invite_url)
            ->with('generated_token', $invitation->token);
    }

    /**
     * Accept the invitation and join the community.
     */
    public function accept(Request $request, string $token)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->guest(route('login'))
                ->with('warning', 'يرجى تسجيل الدخول أولاً لقبول الدعوة والانضمام.');
        }

        $invitation = CommunityInvitation::where('token', $token)->firstOrFail();
        $community = $invitation->community;

        // Verify status & validity
        if ($invitation->isRevoked()) {
            return redirect()->route('communities.invitations.show', $token)
                ->with('error', 'عذراً، هذه الدعوة تم إلغاؤها من قبل إدارة المجلس.');
        }

        $invitation->checkAndMarkExpired();

        if ($invitation->isExpired()) {
            return redirect()->route('communities.invitations.show', $token)
                ->with('error', 'عذراً، انتهت صلاحية هذا الرابط أو تم استنفاد الحد الأقصى المسموح للاستخدام.');
        }

        if ($invitation->status === CommunityInvitation::STATUS_REJECTED) {
            return redirect()->route('communities.invitations.show', $token)
                ->with('error', 'عذراً، تم تسجيل رفض هذه الدعوة سابقاً.');
        }

        // Check if already a member (Unique constraint enforcement)
        if ($community->members()->where('user_id', $user->id)->exists()) {
            return redirect()->route('communities.index', ['community_id' => $community->id])
                ->with('info', "أنت بالفعل عضو نشط في مجلس «{$community->name}».");
        }

        // Check maximum capacity
        if ($community->max_members && $community->members()->count() >= $community->max_members) {
            return redirect()->route('communities.invitations.show', $token)
                ->with('error', 'عذراً، وصل المجلس للحد الأقصى المسموح به من الأعضاء.');
        }

        // Attach user to community members with duplicate protection
        $community->members()->syncWithoutDetaching([
            $user->id => [
                'role' => 'member',
                'joined_at' => now(),
            ],
        ]);

        // Mark invitation accepted
        $invitation->markAsAccepted($user);

        // Award points to the joining user
        $user->increment('points', 15);

        // Notify community owner/creator
        if ($community->creator_id && $community->creator_id !== $user->id) {
            UserNotification::create([
                'user_id' => $community->creator_id,
                'title' => 'عضو جديد انضم عبر الدعوة 🎉',
                'body' => "انضم القارئ «{$user->name}» إلى مجلسك «{$community->name}» بواسطة رابط دعوة.",
                'icon' => 'user-plus',
                'link' => route('communities.index', ['community_id' => $community->id]),
                'type' => 'community_member_joined',
                'is_read' => false,
            ]);
        }

        // Audit log
        AuditLog::record(
            $user,
            'community_invite_accepted',
            $invitation,
            "قبل المستخدم «{$user->name}» دعوة الانضمام إلى مجلس «{$community->name}»."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "مرحباً بك في مجلس «{$community->name}»! تم قبول الدعوة بنجاح 🎉",
                'community_id' => $community->id,
            ]);
        }

        return redirect()->route('communities.index', ['community_id' => $community->id])
            ->with('success', "مرحباً بك في مجلس «{$community->name}»! تم قبول الدعوة بنجاح 🎉");
    }

    /**
     * Decline / reject the invitation.
     */
    public function reject(Request $request, string $token)
    {
        $invitation = CommunityInvitation::where('token', $token)->firstOrFail();
        $community = $invitation->community;

        if ($invitation->isValid()) {
            $invitation->markAsRejected();

            $user = Auth::user();
            if ($user) {
                AuditLog::record(
                    $user,
                    'community_invite_rejected',
                    $invitation,
                    "رفض المستخدم «{$user->name}» دعوة مجلس «{$community->name}»."
                );
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم رفض الدعوة بنجاح.',
            ]);
        }

        return redirect()->route('communities.index')
            ->with('info', "تم رفض دعوة الانضمام إلى مجلس «{$community->name}».");
    }

    /**
     * Revoke an active invitation link.
     */
    public function revoke(Request $request, CommunityInvitation $invitation)
    {
        $user = Auth::user();
        $community = $invitation->community;

        // Authorization: Inviter, Community Manager, or SuperAdmin
        if ($invitation->inviter_id !== $user->id && ! $community->isManager($user) && ! $user->isSuperAdmin()) {
            abort(403, 'غير مصرح لك بإلغاء هذه الدعوة.');
        }

        $invitation->markAsRevoked($user);

        AuditLog::record(
            $user,
            'community_invite_revoked',
            $invitation,
            "ألغى «{$user->name}» رابط الدعوة الخاص بمجلس «{$community->name}» (رمز: {$invitation->token})."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء رابط الدعوة بنجاح.',
            ]);
        }

        return back()->with('success', 'تم إلغاء رابط الدعوة بنجاح.');
    }
}
