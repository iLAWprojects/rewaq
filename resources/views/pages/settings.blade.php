@extends('layouts.app')

@section('title', 'الإعدادات والتفضيلات')
@section('crumb', 'تفضيلات المساحة والإشعارات')

@section('content')
@php
    $prefs = $user->notificationPreferences();
    $timezones = [
        'Asia/Riyadh' => 'الرياض (توقيت مكة المكرمة GMT+3)',
        'Asia/Dubai' => 'دبي وأبوظبي (GMT+4)',
        'Africa/Cairo' => 'القاهرة (GMT+2 / GMT+3)',
        'Asia/Kuwait' => 'الكويت (GMT+3)',
        'Asia/Qatar' => 'الدوحة (GMT+3)',
        'Asia/Bahrain' => 'المنامة (GMT+3)',
        'Asia/Muscat' => 'مسقط (GMT+4)',
        'Asia/Amman' => 'عمّان (GMT+3)',
        'Asia/Beirut' => 'بيروت (GMT+3)',
        'Asia/Baghdad' => 'بغداد (GMT+3)',
        'Africa/Casablanca' => 'الدار البيضاء (GMT+1)',
        'Europe/London' => 'لندن (GMT+0 / GMT+1)',
        'UTC' => 'التوقيت العالمي المنسق (UTC)',
    ];

    $categoriesList = [
        'communities' => ['label' => 'نوادي القراءة', 'desc' => 'دعوات نوادي القراءة، ترقيات العضوية، وإعلانات الإدارة'],
        'challenges' => ['label' => 'تحديات القراءة والفرق', 'desc' => 'تحقيق المراحل، اقتراب نهاية التحدي، وتغير ترتيب الفرق'],
        'meetings' => ['label' => 'الاجتماعات والتقويم', 'desc' => 'تأكيد التسجيل، توفر مقاعد قائمة الانتظار، وتذكيرات ما قبل اللقاء'],
        'loans' => ['label' => 'الإعارات والخزائن', 'desc' => 'طلبات الاستعارة، مواعيد الإرجاع، وتنبيهات الإيداع'],
        'comments' => ['label' => 'النقاشات والتعليقات', 'desc' => 'الردود على مداخلاتك وإشارات الرفقاء والإعجابات'],
        'badges' => ['label' => 'الأوسمة والتحفيز', 'desc' => 'نيل الأوسمة التقديرية، الترقية في المستويات، ورصيد النقاط'],
        'friendships' => ['label' => 'الصداقة والرفاق', 'desc' => 'طلبات الصداقة الجديدة والأنشطة القرائية لرفاقك المقربين'],
    ];

    if (! $bookLendingEnabled) {
        unset($categoriesList['loans']);
    }
@endphp

<div class="ch-head">
    <div>
        <h1>الإعدادات</h1>
        <p>تفضيلات مساحتك وإيقاع الإشعارات: هدفك السنوي، وساعات الهدوء، والمنطقة الزمنية.</p>
    </div>
</div>

<form method="POST" action="{{ route('settings.update') }}" class="st-form">
    @csrf

    <section class="st-card">
        <header>
            <span class="st-ico"><i data-lucide="user-round"></i></span>
            <div><h2>الهوية القرائية</h2><p>كيف يظهر اسمك ونبذتك للآخرين.</p></div>
        </header>
        <div class="st-fields">
            <label>
                <span>اسم القارئ في رِواق</span>
                <input name="name" value="{{ old('name', $user->name) }}" required>
            </label>
            <label>
                <span>هدف القراءة السنوي (عدد الكتب)</span>
                <input name="annual_goal" type="number" min="1" max="1000" value="{{ old('annual_goal', $user->annual_goal) }}" required>
            </label>
            <label class="wide">
                <span>نبذة تعريفية قصيرة</span>
                <textarea name="bio" rows="3" placeholder="اكتب اهتماماتك أو مجالاتك المفضلة…">{{ old('bio', $user->bio) }}</textarea>
            </label>
        </div>
    </section>

    <section class="st-card">
        <header>
            <span class="st-ico"><i data-lucide="moon"></i></span>
            <div><h2>المنطقة الزمنية وساعات الهدوء والسكينة الليلية</h2><p>تُكتم التنبيهات الفورية في هذه الساعات (عدا العاجلة).</p></div>
        </header>
        <div class="st-fields">
            <label class="wide">
                <span>المنطقة الزمنية المحلية</span>
                <select name="timezone">
                    @foreach($timezones as $tzCode => $tzLabel)
                        <option value="{{ $tzCode }}" {{ ($user->timezone ?? 'Asia/Riyadh') === $tzCode ? 'selected' : '' }}>{{ $tzLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="st-switch wide">
                <input type="checkbox" name="quiet_hours_enabled" value="1" {{ $user->quiet_hours_enabled ? 'checked' : '' }}>
                <i></i>
                <span>تفعيل وضع ساعات الهدوء الليلية</span>
            </label>
            <label>
                <span>بداية الهدوء (مساءً)</span>
                <input type="time" name="quiet_hours_start" value="{{ $user->quiet_hours_start ?? '22:00' }}">
            </label>
            <label>
                <span>نهاية الهدوء (صباحًا)</span>
                <input type="time" name="quiet_hours_end" value="{{ $user->quiet_hours_end ?? '08:00' }}">
            </label>
        </div>
    </section>

    <section class="st-card">
        <header>
            <span class="st-ico"><i data-lucide="bell-ring"></i></span>
            <div><h2>إشعارات المتصفح الفورية (Web Push Opt-in)</h2><p>تصلك التنبيهات المهمة عبر متصفح جهازك فقط.</p></div>
        </header>
        <div class="st-fields">
            <label class="st-switch wide">
                <input type="checkbox" name="push_opt_in" value="1" {{ $user->push_opt_in ? 'checked' : '' }}>
                <i></i>
                <span>الموافقة على استلام تنبيهات الويب (Push)</span>
            </label>
        </div>
    </section>

    <section class="st-card">
        <header>
            <span class="st-ico"><i data-lucide="sliders-horizontal"></i></span>
            <div><h2>تفضيلات القنوات بحسب الفئة</h2><p>اختر القنوات التي تصلك منها تنبيهات كل فئة.</p></div>
        </header>
        <div class="st-table">
            <div class="st-row st-row-head">
                <span>الفئة</span><span>داخل التطبيق</span><span>Push</span>
            </div>
            @foreach($categoriesList as $catKey => $catInfo)
                @php
                    $inAppChecked = $prefs[$catKey]['in_app'] ?? true;
                    $pushChecked = $prefs[$catKey]['push'] ?? false;
                @endphp
                <div class="st-row">
                    <span class="st-cat"><b>{{ $catInfo['label'] }}</b><small>{{ $catInfo['desc'] }}</small></span>
                    <label class="st-switch st-mini" aria-label="داخل التطبيق: {{ $catInfo['label'] }}">
                        <input type="hidden" name="preferences[{{ $catKey }}][in_app]" value="0">
                        <input type="checkbox" name="preferences[{{ $catKey }}][in_app]" value="1" {{ $inAppChecked ? 'checked' : '' }}><i></i>
                    </label>
                    <label class="st-switch st-mini" aria-label="Push: {{ $catInfo['label'] }}">
                        <input type="hidden" name="preferences[{{ $catKey }}][push]" value="0">
                        <input type="checkbox" name="preferences[{{ $catKey }}][push]" value="1" {{ $pushChecked ? 'checked' : '' }}><i></i>
                    </label>
                </div>
            @endforeach
        </div>
    </section>

    <section class="st-card">
        <header>
            <span class="st-ico"><i data-lucide="mail-open"></i></span>
            <div><h2>ملخص التنبيهات اليومي</h2><p>تجميع كل إشعارات المنصة في ملخص واحد بدل الإشعارات المتفرقة.</p></div>
        </header>
        <div class="st-fields">
            <label class="st-switch wide">
                <input type="checkbox" name="digest_enabled" value="1" {{ $user->digest_enabled ? 'checked' : '' }}>
                <i></i>
                <span>تفعيل الملخص اليومي الموحد</span>
            </label>
            <label>
                <span>وقت استلام الملخص</span>
                <input type="time" name="digest_time" value="{{ $user->digest_time ?? '20:00' }}">
            </label>
        </div>
    </section>

    <div class="st-save">
        <button type="submit" class="rq-btn rq-btn-primary"><i data-lucide="check"></i><span>حفظ التعديلات</span></button>
    </div>
</form>
@endsection
