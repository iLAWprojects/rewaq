<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#173D36">
    <meta name="description" content="رِواق المعرفة: المنصة العربية المتكاملة لإدارة المعرفة والقراءة ونوادي القراءة والمكتبات.">
    <title>@yield('title', 'رِواق المعرفة') | مساحتك للمعرفة</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

    <script src="https://unpkg.com/lucide@1.17.0/dist/umd/lucide.js"></script>

    <link rel="stylesheet" href="{{ asset('css/riwaq.css') }}?v={{ filemtime(public_path('css/riwaq.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/riwaq-ui.css') }}?v={{ filemtime(public_path('css/riwaq-ui.css')) }}">
    @stack('styles')
</head>
@php
    $user = Auth::user();
    $isPublicShell = ! $user || trim($__env->yieldContent('shell')) === 'public';
    $unreadNotificationsCount = $user ? $user->userNotifications()->where('is_read', false)->count() : 0;
@endphp
<body class="rq-body @yield('body-class')">
<div id="riwaq" dir="rtl">

@if($isPublicShell)
    {{-- ===================== الواجهة العامة: بدون قائمة جانبية ===================== --}}
    <div class="rq-public-bar">
        <a href="{{ route('landing') }}" class="rq-brand">
            <img src="{{ asset('images/logo.png') }}" alt="شعار رِواق المعرفة">
            <span><b>رِواق</b><small>مساحتك للمعرفة</small></span>
        </a>

        <div class="rq-public-links" role="navigation" aria-label="التنقل الرئيسي">
            <a href="{{ route('landing') }}" class="{{ request()->routeIs('landing') ? 'active' : '' }}">الرئيسية</a>
            <a href="{{ route('explore') }}" class="{{ request()->routeIs('explore', 'books.*') ? 'active' : '' }}">تصفح الكتب</a>
            <a href="{{ route('communities.index') }}" class="{{ request()->routeIs('communities.*') ? 'active' : '' }}">نوادي القراءة</a>
            <a href="{{ route('challenges.index') }}" class="{{ request()->routeIs('challenges.*') ? 'active' : '' }}">التحديات</a>
        </div>

        <div class="rq-public-actions">
            @auth
                <a href="{{ route('libraries.index') }}" class="rq-btn rq-btn-primary rq-btn-sm">
                    <i data-lucide="library-big"></i><span>مكتباتي</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="rq-btn rq-btn-ghost rq-btn-sm"><span>تسجيل الدخول</span></a>
                <a href="{{ route('register') }}" class="rq-btn rq-btn-primary rq-btn-sm"><span>إنشاء حساب</span></a>
            @endauth
        </div>
    </div>

    <main id="screen" class="rq-main">
        <div class="rq-public-content @yield('bleed')">
            @yield('content')
        </div>
    </main>

    <footer class="rq-footer">
        <div class="rq-footer-top">
            <div>
                <a href="{{ route('landing') }}" class="rq-brand">
                    <img src="{{ asset('images/logo.png') }}" alt="">
                    <span><b>رِواق</b><small>مساحتك للمعرفة</small></span>
                </a>
                <p>لكل كتاب أثر، ولكل قارئ مسار. منصة عربية لإدارة مكتباتك وقراءاتك ونواديك.</p>
            </div>
            <div class="rq-footer-links">
                <a href="{{ route('explore') }}">تصفح الكتب</a>
                <a href="{{ route('communities.index') }}">نوادي القراءة</a>
                <a href="{{ route('challenges.index') }}">التحديات</a>
                <a href="{{ route('calendar.index') }}">التجمعات</a>
            </div>
        </div>
        <div class="rq-footer-bottom">© {{ date('Y') }} رِواق المعرفة. جميع الحقوق محفوظة.</div>
    </footer>
@else
    {{-- ===================== لوحة المستخدم: قائمة جانبية + شريط علوي ===================== --}}
    <div class="rq-app">
        <div class="rq-sidebar" id="riwaq-sidebar">
            <a href="{{ route('libraries.index') }}" class="rq-brand">
                <img src="{{ asset('images/logo.png') }}" alt="شعار رِواق المعرفة">
                <span><b>رِواق</b><small>مساحتك للمعرفة</small></span>
            </a>

            <div class="rq-menu" role="navigation" aria-label="التنقل الرئيسي">
                <a href="{{ route('libraries.index') }}" class="rq-menu-link {{ request()->routeIs('libraries.*') ? 'active' : '' }}">
                    <i data-lucide="library-big"></i><span>مكتباتي</span>
                </a>

                <a href="{{ route('communities.index') }}" class="rq-menu-link {{ request()->routeIs('communities.*') ? 'active' : '' }}">
                    <i data-lucide="users"></i><span>نوادي القراءة</span>
                </a>
                <div class="rq-menu-sub">
                    <a href="{{ route('challenges.index') }}" class="rq-menu-link {{ request()->routeIs('challenges.*') ? 'active' : '' }}">
                        <i data-lucide="flag"></i><span>التحديات</span>
                    </a>
                    <a href="{{ route('calendar.index') }}" class="rq-menu-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}">
                        <i data-lucide="calendar-days"></i><span>التجمعات</span>
                    </a>
                </div>

                <a href="{{ route('explore') }}" class="rq-menu-link {{ request()->routeIs('explore', 'books.*', 'search.*') ? 'active' : '' }}">
                    <i data-lucide="compass"></i><span>تصفح الكتب</span>
                </a>
            </div>

            <div class="rq-menu-spacer"></div>
            <div class="rq-menu-divider"></div>
            <div class="rq-menu">
                <a href="{{ route('settings.index') }}" class="rq-menu-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i data-lucide="settings"></i><span>الإعدادات</span>
                </a>
            </div>
        </div>
        <div class="rq-scrim" id="rq-scrim"></div>

        <div class="rq-main-wrap">
            <div class="rq-topbar">
                <button type="button" class="rq-burger" id="menu-toggle" aria-expanded="false" aria-controls="riwaq-sidebar" aria-label="القائمة">
                    <i data-lucide="menu"></i>
                </button>

                <form action="{{ route('search.index') }}" method="GET" class="rq-search" role="search">
                    <i data-lucide="search"></i>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="ابحث عن كتاب، نادٍ، أو قارئ…" aria-label="بحث">
                </form>

                <div class="rq-topbar-actions">
                    <a href="{{ route('reading.index', ['tab' => 'achievements']) }}" class="rq-points" id="rq-points" title="رصيد نقاطك — اضغط لعرض إنجازاتك" aria-label="رصيد نقاطك">
                        <i data-lucide="sparkles"></i><b>{{ number_format((int) $user->points) }}</b><span>نقطة</span>
                    </a>
                    <button type="button" class="rq-icon-btn rq-search-trigger" id="rq-search-open" title="البحث الشامل" aria-label="البحث الشامل">
                        <i data-lucide="search"></i>
                    </button>
                    <button type="button" class="rq-icon-btn rq-bell" id="rq-notif-open" data-notif-open title="الإشعارات" aria-label="الإشعارات" aria-haspopup="dialog" aria-expanded="false">
                        <i data-lucide="bell"></i>
                        <span class="rq-dot" data-notif-count @if($unreadNotificationsCount < 1) hidden @endif>{{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}</span>
                    </button>

                    <details class="rq-user">
                        <summary class="rq-icon-btn rq-avatar-btn" title="حسابي" aria-label="قائمة المستخدم">
                            <i data-lucide="user-round"></i>
                        </summary>
                        <div class="rq-user-menu">
                            <div class="rq-user-head">
                                <b>{{ $user->name }}</b>
                                <small>{{ $user->email }}</small>
                            </div>
                            <a href="{{ route('settings.index') }}"><i data-lucide="settings"></i><span>إعدادات الحساب</span></a>
                            <a href="{{ route('profile.show') }}"><i data-lucide="user-check"></i><span>ملفي الشخصي</span></a>
                            <a href="{{ route('reading.index') }}"><i data-lucide="book-open"></i><span>سجل القراءة</span></a>
                            @if($user->isSuperAdmin())
                                <a href="{{ route('admin.dashboard') }}"><i data-lucide="shield-check"></i><span>لوحة الإدارة</span></a>
                            @endif
                            <a href="{{ route('landing') }}"><i data-lucide="house"></i><span>الصفحة الرئيسية</span></a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rq-danger"><i data-lucide="log-out"></i><span>تسجيل الخروج</span></button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>

            <main id="screen" class="rq-main">
                <div class="rq-page">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
@endif

    {{-- Feedback toasts --}}
    <div id="toast-container" class="toast-container">
        @if(session('success'))
            <div class="toast"><i data-lucide="check-circle"></i><span>{{ session('success') }}</span></div>
        @endif
        @if(session('info'))
            <div class="toast" style="background:#EBE6DB;color:#4F493D;border-color:#DDD5C5;"><i data-lucide="info"></i><span>{{ session('info') }}</span></div>
        @endif
        @if(session('warning'))
            <div class="toast" style="background:#FFF3D6;color:#785419;border-color:#F0DDA8;"><i data-lucide="alert-triangle"></i><span>{{ session('warning') }}</span></div>
        @endif
        @if(session('error'))
            <div class="toast" style="background:#FBEAE5;color:#8F3829;border-color:#F5CAC2;"><i data-lucide="alert-circle"></i><span>{{ session('error') }}</span></div>
        @endif
    </div>

    @unless($isPublicShell)
        @include('pages.partials.search-sheet')
        @include('pages.partials.notif-panel')
    @endunless

    @include('pages.partials.book-modal')
    @include('pages.partials.report-modal')
    @auth
        @unless($isPublicShell)
            @include('pages.partials.reading-widget')
            @include('pages.partials.bottom-nav')
        @endunless
    @endauth

    @yield('modals')
</div>

<script>
    window.RQ = {
        auth: @json((bool) $user),
        loginUrl: @json(route('login')),
        addToLibraryUrl: @json(route('libraries.addToPersonal')),
        bookUrl: @json(url('/books')),
        bookSearchUrl: @json(route('books.search')),
        csrf: @json(csrf_token()),
    };
</script>
<script src="{{ asset('js/riwaq.js') }}?v={{ filemtime(public_path('js/riwaq.js')) }}"></script>
<script src="{{ asset('js/riwaq-ui.js') }}?v={{ filemtime(public_path('js/riwaq-ui.js')) }}"></script>
<script src="{{ asset('js/riwaq-reading.js') }}?v={{ filemtime(public_path('js/riwaq-reading.js')) }}"></script>
<script src="{{ asset('js/riwaq-hero.js') }}?v={{ filemtime(public_path('js/riwaq-hero.js')) }}"></script>
<script src="{{ asset('js/riwaq-bookpicker.js') }}?v={{ filemtime(public_path('js/riwaq-bookpicker.js')) }}"></script>
@stack('scripts')
</body>
</html>
