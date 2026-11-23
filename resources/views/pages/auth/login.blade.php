@extends('layouts.app')

@section('title', 'تسجيل الدخول')
@section('shell', 'public')
@section('bleed', 'rq-bleed')

@section('content')
<div class="au-wrap">
    <div class="au-side">
        <img src="{{ asset('images/logo.png') }}" alt="" class="au-logo">
        <h2>أهلًا بعودتك إلى رِواق</h2>
        <p>مكتباتك وقراءاتك ونواديك بانتظارك.</p>
        <ul>
            <li><i data-lucide="library-big"></i><span>مكتبات خاصة وعامة</span></li>
            <li><i data-lucide="users"></i><span>نوادي وتحديات قراءة</span></li>
            <li><i data-lucide="repeat"></i><span>استعارة وتتبّع للكتب</span></li>
        </ul>
    </div>

    <div class="au-form-box">
        <h1>تسجيل الدخول</h1>
        <p class="au-sub">أدخل بياناتك للمتابعة.</p>

        @if($errors->any())
            <div class="au-error" role="alert"><i data-lucide="alert-circle"></i><span>{{ $errors->first() }}</span></div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="au-form">
            @csrf
            <label>
                <span>البريد الإلكتروني</span>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" autocomplete="email" required autofocus>
            </label>
            <label>
                <span>كلمة المرور</span>
                <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
            </label>
            <label class="au-check">
                <input type="checkbox" name="remember" checked>
                <span>تذكرني على هذا الجهاز</span>
            </label>
            <button type="submit" class="rq-btn rq-btn-primary" style="width:100%;min-height:48px;"><i data-lucide="log-in"></i><span>تسجيل الدخول</span></button>
        </form>

        @if(app()->environment('local'))
            <div class="au-dev">
                <small>للتجربة المحلية فقط:</small>
                <a href="{{ route('auth.quickLogin', 'user') }}">قارئ تجريبي</a>
                <a href="{{ route('auth.quickLogin', 'admin') }}">مشرف عام</a>
            </div>
        @endif

        <p class="au-switch">ليس لديك حساب؟ <a href="{{ route('register') }}">أنشئ حسابًا جديدًا</a></p>
    </div>
</div>
@endsection
