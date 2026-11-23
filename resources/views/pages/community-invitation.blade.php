@extends('layouts.app')

@section('title', 'دعوة انضمام - ' . ($community->name ?? 'مجلس قراءة'))
@section('crumb', 'دعوة انضمام لمجلس قراءة')

@section('content')
@php
    $stateKey = match (true) {
        $invitation->isRevoked() => 'revoked',
        $invitation->isExpired() => 'expired',
        $invitation->status === 'rejected' => 'rejected',
        $invitation->status === 'accepted' => 'accepted',
        default => 'active',
    };
    $stateLabels = [
        'revoked' => ['دعوة ملغاة', 'ban', 'bad'],
        'expired' => ['منتهية الصلاحية', 'clock', 'muted'],
        'rejected' => ['تم رفض الدعوة', 'x-circle', 'bad'],
        'accepted' => ['دعوة مقبولة', 'check-circle', 'ok'],
        'active' => ['دعوة نشطة وصالحة', 'mail-check', 'ok'],
    ];
    [$stateText, $stateIcon, $stateTone] = $stateLabels[$stateKey];
@endphp

<div class="iv-wrap">
    <div class="iv-intro">
        <span class="lp-kicker">دعوة خاصة ورابط انضمام معتمد</span>
        <h1>دعوة للانضمام إلى مجلس «{{ $community->name }}»</h1>
        <p>وجّه إليك هذه الدعوة القارئ <b>{{ $invitation->inviter->name ?? 'رفيق في رِواق' }}</b> للمشاركة في الحوارات والأنشطة القرائية.</p>
    </div>

    <article class="iv-card">
        <header class="iv-head" style="--accent: {{ $community->cover_color ?? '#173D36' }};">
            <div class="iv-logo">
                @if($community->logo)
                    <img src="{{ $community->logo }}" alt="{{ $community->name }}">
                @else
                    <i data-lucide="book-open"></i>
                @endif
            </div>
            <div class="iv-head-text">
                <div class="iv-tags">
                    <span>{{ $community->category }}</span>
                    <span>مجلس {{ $community->type_label ?? 'قرائي' }}</span>
                </div>
                <h2>{{ $community->name }}</h2>
            </div>
            <span class="iv-state {{ $stateTone }}"><i data-lucide="{{ $stateIcon }}"></i>{{ $stateText }}</span>
        </header>

        <div class="iv-body">
            @if($invitation->custom_message)
                <blockquote class="iv-quote">
                    <small>رسالة من {{ $invitation->inviter->name ?? 'الداعي' }}</small>
                    «{{ $invitation->custom_message }}»
                </blockquote>
            @endif

            @if($community->description)
                <section><h3>عن هذا المجلس</h3><p>{{ $community->description }}</p></section>
            @endif

            @if($community->rules)
                <section class="iv-rules"><h3><i data-lucide="shield"></i>ميثاق وقواعد المجلس</h3><p>{{ Str::limit($community->rules, 180) }}</p></section>
            @endif

            <div class="iv-metrics">
                <div><b>{{ $community->members()->count() }}@if($community->max_members) <small>/ {{ $community->max_members }}</small>@endif</b><span>عدد الأعضاء</span></div>
                <div><b>{{ $community->currentBook->title ?? 'لم يحدد بعد' }}</b><span>كتاب الشهر</span></div>
                <div><b>{{ $community->city ?? 'عالمي / افتراضي' }}</b><span>المدينة / النطاق</span></div>
                <div><b>{{ $invitation->max_uses ? "{$invitation->uses_count} / {$invitation->max_uses}" : 'غير محدود' }}</b><span>حد الاستخدامات</span></div>
            </div>

            <section class="iv-share">
                <div class="iv-qr">
                    <div>{!! $qrSvg !!}</div>
                    <small><i data-lucide="qr-code"></i>امسح الرمز بالجوال للانضمام فوراً</small>
                </div>
                <div class="iv-link">
                    <h3>رابط الدعوة المعتمد</h3>
                    <p>ينقلك هذا الرابط مباشرة إلى صفحة الانضمام لهذا المجلس، ويمكنك مشاركته مع رفقاء القراءة.</p>
                    <div class="iv-copy">
                        <input type="text" id="inviteUrlField" readonly value="{{ $inviteUrl }}">
                        <button type="button" class="rq-btn rq-btn-ghost rq-btn-sm" onclick="copyInviteLink()"><i data-lucide="copy"></i><span id="copyBtnText">نسخ</span></button>
                    </div>
                    <small>تاريخ الانتهاء: {{ $invitation->expires_at ? $invitation->expires_at->format('Y-m-d H:i') : 'صلاحية مفتوحة' }} · رمز التحقق: <code>{{ substr($invitation->token, 0, 16) }}…</code></small>
                </div>
            </section>

            <div class="iv-action">
                @if($isMember)
                    <div class="iv-note ok">
                        <i data-lucide="check-circle"></i>
                        <h4>أنت بالفعل عضو في مجلس «{{ $community->name }}»!</h4>
                        <p>لا حاجة لقبول الدعوة مرة أخرى، يمكنك التوجه مباشرة إلى أنشطة المجلس.</p>
                        <a href="{{ route('communities.index', ['community_id' => $community->id]) }}" class="rq-btn rq-btn-primary"><span>دخول ساحة المجلس</span><i data-lucide="arrow-left"></i></a>
                    </div>
                @elseif($invitation->isRevoked())
                    <div class="iv-note bad">
                        <i data-lucide="alert-octagon"></i>
                        <h4>رابط الدعوة ملغى</h4>
                        <p>تم إلغاء هذا الرابط بواسطة إدارة المجلس أو منشئ الدعوة.</p>
                        <a href="{{ route('communities.index') }}" class="rq-btn rq-btn-ghost rq-btn-sm">استكشاف مجالس أخرى</a>
                    </div>
                @elseif($invitation->isExpired())
                    <div class="iv-note warn">
                        <i data-lucide="hourglass"></i>
                        <h4>انتهت صلاحية رابط الدعوة</h4>
                        <p>تجاوز هذا الرابط المدة الزمنية المحددة أو استنفد العدد الأقصى للانضمام.</p>
                        <a href="{{ route('communities.index') }}" class="rq-btn rq-btn-ghost rq-btn-sm">استكشاف مجالس أخرى</a>
                    </div>
                @elseif($invitation->status === 'rejected')
                    <div class="iv-note">
                        <h4>تم تسجيل رفض هذه الدعوة سابقاً</h4>
                        <a href="{{ route('communities.index') }}" class="rq-btn rq-btn-ghost rq-btn-sm">استكشاف المجالس العامة</a>
                    </div>
                @else
                    <div class="iv-buttons">
                        <form method="POST" action="{{ route('communities.invitations.accept', $invitation->token) }}">
                            @csrf
                            <button type="submit" class="rq-btn rq-btn-primary" style="min-height:50px;padding:0 30px;font-size:15px;"><i data-lucide="check"></i><span>قبول الدعوة والانضمام للمجلس ✦</span></button>
                        </form>
                        <form method="POST" action="{{ route('communities.invitations.reject', $invitation->token) }}" onsubmit="return confirm('هل أنت متأكد من رغبتك في رفض هذه الدعوة؟');">
                            @csrf
                            <button type="submit" class="rq-btn rq-btn-ghost" style="min-height:50px;"><i data-lucide="x"></i><span>اعتذار عن الانضمام</span></button>
                        </form>
                    </div>
                    @guest
                        <small class="iv-guest">سيُطلب منك تسجيل الدخول أو إنشاء حساب جديد مجاناً لتثبيت عضويتك في المجلس.</small>
                    @endguest
                @endif
            </div>
        </div>
    </article>
</div>

<script>
function copyInviteLink() {
    const input = document.getElementById('inviteUrlField');
    if (!input) return;
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btnText = document.getElementById('copyBtnText');
        if (btnText) {
            btnText.textContent = 'تم النسخ!';
            setTimeout(() => { btnText.textContent = 'نسخ'; }, 2000);
        }
    }).catch(() => {
        document.execCommand('copy');
        alert('تم نسخ الرابط بنجاح');
    });
}
</script>
@endsection
