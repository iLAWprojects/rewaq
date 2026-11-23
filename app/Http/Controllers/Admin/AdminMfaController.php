<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMfaController extends Controller
{
    public function __construct(
        protected MfaService $mfaService
    ) {}

    /**
     * Show MFA verification prompt
     */
    public function showVerify()
    {
        $user = Auth::user();

        if (! $user->hasMfaEnabled()) {
            return redirect()->route('admin.mfa.setup');
        }

        if (session('admin_mfa_verified')) {
            return redirect()->route('admin.dashboard');
        }

        return view('pages.admin.mfa-verify', compact('user'));
    }

    /**
     * Verify MFA code or backup recovery code
     */
    public function verify(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'code' => 'required|string',
        ], [
            'code.required' => 'يرجى إدخال رمز التحقق المكون من 6 أرقام أو أحد رموز الاسترداد.',
        ]);

        $code = trim($request->input('code'));
        $isValid = $this->mfaService->verifyCode($user, $code);

        if (! $isValid) {
            AuditLog::record(
                $user,
                'admin_mfa_failed',
                $user,
                "فشلت محاولة التحقق الثنائي للمشرف «{$user->name}» (رمز غير صحيح)."
            );

            return back()
                ->withErrors(['code' => 'رمز التحقق أو رمز الاسترداد المدخل غير صحيح، يرجى المحاولة مجدداً.'])
                ->with('error', 'رمز التحقق أو رمز الاسترداد المدخل غير صحيح، يرجى المحاولة مجدداً.');
        }

        session([
            'admin_mfa_verified' => true,
            'admin_last_activity' => now()->timestamp,
        ]);

        AuditLog::record(
            $user,
            'admin_mfa_success',
            $user,
            "تم التحقق الثنائي بنجاح للمشرف «{$user->name}»."
        );

        return redirect()->intended(route('admin.dashboard'))->with('success', 'تم التحقق الثنائي بنجاح ✦');
    }

    /**
     * Show MFA setup and enrollment page
     */
    public function showSetup()
    {
        $user = Auth::user();
        $pendingSecret = session('pending_mfa_secret') ?: $this->mfaService->generateSecret();
        session(['pending_mfa_secret' => $pendingSecret]);

        $otpAuthUrl = $this->mfaService->getOtpAuthUrl($user, $pendingSecret);

        return view('pages.admin.mfa-setup', compact('user', 'pendingSecret', 'otpAuthUrl'));
    }

    /**
     * Confirm and enable MFA
     */
    public function enable(Request $request)
    {
        $user = Auth::user();
        $secret = session('pending_mfa_secret') ?: $request->input('secret');

        $request->validate([
            'code' => 'required|string',
        ], [
            'code.required' => 'يرجى إدخال رمز التحقق لتأكيد التفعيل.',
        ]);

        if (! $secret || ! $this->mfaService->verifyCode($user, $request->input('code'), $secret)) {
            return back()->with('error', 'رمز التحقق غير متطابق مع المفتاح المعروض، يرجى إعادة المحاولة.');
        }

        $recoveryCodes = $this->mfaService->generateRecoveryCodes(8);

        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
            'recovery_codes' => $recoveryCodes,
        ]);

        session()->forget('pending_mfa_secret');
        session([
            'admin_mfa_verified' => true,
            'admin_last_activity' => now()->timestamp,
        ]);

        AuditLog::record(
            $user,
            'admin_mfa_enabled',
            $user,
            "تم تفعيل التحقق الثنائي (MFA) وتوليد رموز الاسترداد للمشرف «{$user->name}»."
        );

        return redirect()->route('admin.dashboard')->with('success', 'تم تفعيل التحقق الثنائي (MFA) لحسابك الإداري بنجاح 🛡️');
    }

    /**
     * Disable MFA (Requires confirmation)
     */
    public function disable(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'confirmed' => 'required|accepted',
        ]);

        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_confirmed_at' => null,
            'recovery_codes' => null,
        ]);

        session()->forget('admin_mfa_verified');

        AuditLog::record(
            $user,
            'admin_mfa_disabled',
            $user,
            "تم تعطيل التحقق الثنائي للمشرف «{$user->name}» بعد التأكيد الصريح."
        );

        return back()->with('info', 'تم تعطيل التحقق الثنائي لحسابك.');
    }
}
