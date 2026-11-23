@extends('layouts.app')

@section('title', 'إنشاء حساب جديد')
@section('shell', 'public')
@section('bleed', 'rq-bleed')

@section('content')
<div class="au-wrap">
    <div class="au-side">
        <img src="{{ asset('images/logo.png') }}" alt="" class="au-logo">
        <h2>ابدأ رحلتك مع رِواق</h2>
        <p>أنشئ حسابك وأسّس مكتبتك الأولى في دقائق.</p>
        <ul>
            <li><i data-lucide="library-big"></i><span>مكتبتك الشخصية جاهزة فور التسجيل</span></li>
            <li><i data-lucide="target"></i><span>حدّد هدف قراءتك السنوي</span></li>
            <li><i data-lucide="users"></i><span>انضم إلى نوادي القراءة</span></li>
        </ul>
    </div>

    <div class="au-form-box">
        <h1>إنشاء حساب جديد</h1>
        <p class="au-sub">البيانات التالية كل ما تحتاجه للبدء.</p>

        @if($errors->any())
            <div class="au-error" role="alert"><i data-lucide="alert-circle"></i><span>{{ $errors->first() }}</span></div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="au-form">
            @csrf
            <label>
                <span>الاسم</span>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="مثال: أحمد محمد" autocomplete="name" required autofocus>
            </label>
            <label>
                <span>البريد الإلكتروني</span>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" autocomplete="email" required>
            </label>
            <label>
                <span>هدف القراءة السنوي (عدد الكتب)</span>
                <input type="number" name="annual_goal" min="1" max="365" value="{{ old('annual_goal', 24) }}">
            </label>
            <div class="au-row">
                <label>
                    <span>كلمة المرور</span>
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>
                <label>
                    <span>تأكيد كلمة المرور</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
            </div>
            <button type="submit" class="rq-btn rq-btn-primary" style="width:100%;min-height:48px;"><i data-lucide="user-plus"></i><span>إنشاء الحساب</span></button>
        </form>

        <p class="au-switch">لديك حساب بالفعل؟ <a href="{{ route('login') }}">سجّل الدخول</a></p>
    </div>
</div>
@endsection
