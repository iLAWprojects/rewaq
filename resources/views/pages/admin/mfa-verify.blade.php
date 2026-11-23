@extends('layouts.app')

@section('title', 'التحقق الثنائي للمشرفين (MFA)')
@section('crumb', 'بوابة التحقق الأمني المتقدم')

@section('content')
<div style="max-width:480px;margin:40px auto;" class="animate-fade-in">
    <div class="panel" style="padding:32px 28px;text-align:center;border-top:4px solid var(--gold);">
        <div style="width:60px;height:60px;border-radius:50%;background:rgba(195,161,93,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#8C6A21;">
            <i data-lucide="shield-alert" style="width:30px;height:30px;"></i>
        </div>

        <h1 style="font-size:20px;margin:0 0 8px;color:#173D36;">تأكيد التحقق الثنائي (MFA)</h1>
        <p class="muted" style="font-size:13px;line-height:1.6;margin-bottom:24px;">
            مرحباً بك يا <strong>{{ $user->name }}</strong>. لحماية العمليات الإدارية وسجلات المنصة، يرجى إدخال رمز التحقق المكون من 6 أرقام من تطبيق المصادقة لديك (أو أحد رموز الاسترداد).
        </p>

        @if(session('error'))
            <div style="background:#FDE8E8;color:#9B1C1C;padding:12px;border-radius:8px;font-size:13px;margin-bottom:20px;text-align:right;">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.mfa.verify.post') }}">
            @csrf
            <div style="margin-bottom:20px;text-align:right;">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;color:#173D36;">رمز التحقق أو رمز الاسترداد:</label>
                <input type="text" name="code" placeholder="000000 أو RWQ-XXXX-XXXX" required autofocus autocomplete="one-time-code" style="width:100%;font-size:18px;text-align:center;letter-spacing:3px;font-family:monospace;padding:12px;border:2px solid #D1C7B7;border-radius:10px;outline:none;">
            </div>

            <button type="submit" class="primary" style="width:100%;padding:12px;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;">
                <i data-lucide="lock-open" style="width:16px;height:16px;"></i>
                <span>تأكيد والدخول للوحة التحكم</span>
            </button>
        </form>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #ECE4D0;display:flex;justify-content:space-between;align-items:center;font-size:12px;">
            <a href="{{ route('libraries.index') }}" class="muted" style="text-decoration:none;">العودة للرئيسية</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" style="background:none;border:none;color:#8F3829;cursor:pointer;font-size:12px;">تسجيل الخروج</button>
            </form>
        </div>
    </div>
</div>
@endsection
