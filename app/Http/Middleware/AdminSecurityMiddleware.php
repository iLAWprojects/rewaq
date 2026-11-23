<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminSecurityMiddleware
{
    /**
     * Handle an incoming request for Admin and Super Admin areas.
     * Enforces:
     * 1. Stricter, shorter inactivity session timeout for admins.
     * 2. Mandatory MFA / Two-Factor Authentication verification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->canManagePlatform()) {
            abort(403, 'غير مصرح بالدخول: هذه اللوحة مخصصة للإدارة والمشرفين فقط.');
        }

        // 1. Shorter Session Timeout for Admins (default: 15 minutes)
        $timeoutMinutes = (int) SystemSetting::get('admin_session_timeout_minutes', 15);
        $lastActivity = session('admin_last_activity');

        if ($lastActivity && (now()->timestamp - $lastActivity) > ($timeoutMinutes * 60)) {
            // Session expired due to inactivity
            AuditLog::record(
                $user,
                'admin_session_timeout',
                $user,
                "انتهت جلسة المشرف «{$user->name}» تلقائياً بسبب عدم النشاط لمدة {$timeoutMinutes} دقيقة لدواعي الأمان والحماية (Session Timeout)."
            );

            session()->forget(['admin_last_activity', 'admin_mfa_verified']);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'warning',
                "انتهت جلسة الإدارة بسبب عدم النشاط لمدة {$timeoutMinutes} دقيقة لدواعي الأمان والحماية (Session Timeout). يرجى تسجيل الدخول مجدداً."
            );
        }

        // Refresh admin activity timestamp
        session(['admin_last_activity' => now()->timestamp]);

        // 2. Mandatory MFA for Admins
        $mfaEnforced = (bool) SystemSetting::get('admin_mfa_enforced', false);
        $routeName = $request->route() ? $request->route()->getName() : '';
        $mfaBypassRoutes = [
            'admin.mfa.verify',
            'admin.mfa.verify.post',
            'admin.mfa.setup',
            'admin.mfa.enable',
            'logout',
        ];

        if (! in_array($routeName, $mfaBypassRoutes)) {
            if ($user->hasMfaEnabled()) {
                if (! session('admin_mfa_verified')) {
                    if ($request->wantsJson()) {
                        return response()->json([
                            'error' => 'mfa_required',
                            'message' => 'التحقق الثنائي مطلوب للوصول إلى لوحة التحكم.',
                        ], 403);
                    }

                    return redirect()->route('admin.mfa.verify');
                }
            } elseif ($mfaEnforced) {
                // Admin has not set up MFA yet -> force setup
                if ($request->wantsJson()) {
                    return response()->json([
                        'error' => 'mfa_setup_required',
                        'message' => 'إلزامية التحقق الثنائي (MFA) للمشرفين مفعلة؛ يرجى إعداد الرمز أولاً.',
                    ], 403);
                }

                return redirect()->route('admin.mfa.setup')->with(
                    'warning',
                    'وفقاً للسياسات الأمنية لمنصة رِواق المعرفة، يلزم تفعيل التحقق الثنائي (MFA) للمشرفين قبل متابعة إدارة النظام.'
                );
            }
        }

        return $next($request);
    }
}
