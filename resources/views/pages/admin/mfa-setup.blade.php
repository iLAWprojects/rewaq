@extends('layouts.app')

@section('title', 'إعداد التحقق الثنائي للمشرفين (MFA Setup)')
@section('crumb', 'إلزامية التحقق الأمني للمشرفين')

@section('content')
<div style="max-width:540px;margin:30px auto;" class="animate-fade-in">
    <div class="panel" style="padding:32px 28px;border-top:4px solid var(--green);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(27,78,52,0.12);display:flex;align-items:center;justify-content:center;color:var(--green);flex-shrink:0;">
                <i data-lucide="smartphone" style="width:26px;height:26px;"></i>
            </div>
            <div>
                <h1 style="font-size:19px;margin:0 0 2px;color:#173D36;">إعداد التحقق الثنائي (MFA Enrollment)</h1>
                <small class="muted">وفق معايير أمن رِواق المعرفة الإلزامية للمشرفين والمديرين.</small>
            </div>
        </div>

        @if(session('warning'))
            <div style="background:#FFF8E1;color:#8C6A21;padding:12px;border-radius:8px;font-size:12.5px;margin-bottom:18px;border:1px solid #FFE082;line-height:1.5;">
                {{ session('warning') }}
            </div>
        @endif

        @if(session('error'))
            <div style="background:#FDE8E8;color:#9B1C1C;padding:12px;border-radius:8px;font-size:12.5px;margin-bottom:18px;">
                {{ session('error') }}
            </div>
        @endif

        <div style="background:#FAF8F3;border:1px solid #EAE3D2;border-radius:12px;padding:20px;text-align:center;margin-bottom:24px;">
            <div style="margin-bottom:14px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($otpAuthUrl) }}" alt="رمز المصادقة QR" style="border-radius:10px;border:4px solid #FFF;box-shadow:0 2px 8px rgba(0,0,0,0.06);width:160px;height:160px;" loading="lazy">
            </div>
            <p style="font-size:12.5px;color:#5C5443;margin:0 0 12px;line-height:1.5;">
                امسح الرمز أعلاه باستخدام تطبيق المصادقة لديك (مثل Google Authenticator أو 1Password أو Microsoft Authenticator).
            </p>

            <!-- Masked Secret Key to prevent shoulder surfing -->
            <div style="background:#FFF;padding:8px 14px;border-radius:8px;border:1px dashed #C3A15D;display:flex;align-items:center;justify-content:space-between;font-size:13px;">
                <div>
                    <span style="font-size:11px;color:#8C7B65;display:block;text-align:right;">المفتاح السري للإدخال اليدوي:</span>
                    <span id="secret-masked" style="font-family:monospace;letter-spacing:2px;font-weight:700;color:#173D36;">•••• •••• •••• ••••</span>
                    <span id="secret-plain" style="font-family:monospace;letter-spacing:2px;font-weight:700;color:#173D36;display:none;">{{ chunk_split($pendingSecret, 4, ' ') }}</span>
                </div>
                <button type="button" onclick="toggleSecretMask()" class="secondary btn-sm" style="font-size:11px;padding:4px 8px;">
                    <span id="secret-btn-label">إظهار</span>
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.mfa.enable') }}">
            @csrf
            <input type="hidden" name="secret" value="{{ $pendingSecret }}">

            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#173D36;">
                    أدخل رمز التحقق الظاهر في هاتفك (6 أرقام) للتأكيد:
                </label>
                <input type="text" name="code" placeholder="000000" required autocomplete="one-time-code" style="width:100%;font-size:18px;text-align:center;letter-spacing:4px;font-family:monospace;padding:10px;border:2px solid #D1C7B7;border-radius:8px;outline:none;">
            </div>

            <button type="submit" class="primary" style="width:100%;padding:12px;font-size:14.5px;font-weight:700;">
                <i data-lucide="shield-check"></i>
                <span>تأكيد التفعيل وحفظ إعدادات الأمان</span>
            </button>
        </form>
    </div>
</div>

<script>
function toggleSecretMask() {
    const masked = document.getElementById('secret-masked');
    const plain = document.getElementById('secret-plain');
    const label = document.getElementById('secret-btn-label');
    if (plain.style.display === 'none') {
        plain.style.display = 'inline';
        masked.style.display = 'none';
        label.textContent = 'إخفاء';
    } else {
        plain.style.display = 'none';
        masked.style.display = 'inline';
        label.textContent = 'إظهار';
    }
}
</script>
@endsection
