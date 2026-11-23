@extends('layouts.app')

@section('title', 'التجمعات')
@section('crumb', 'التقويم والاجتماعات')

@push('styles')
<style>
.calendar-type-nav {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 16px;
}
.cal-type-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    color: #4A564A;
    background: #FAF8F3;
    border: 1px solid #DFD7C5;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.cal-type-pill:hover {
    background: #EDE6D6;
    color: var(--green);
}
.cal-type-pill.active {
    background: var(--green);
    color: #FFFDF8;
    border-color: var(--green);
    box-shadow: 0 3px 10px rgba(23,61,54,0.18);
}
.capacity-gauge {
    background: #EDE6D6;
    border-radius: 6px;
    height: 6px;
    overflow: hidden;
    margin: 6px 0;
}
.capacity-gauge-fill {
    height: 100%;
    background: var(--green);
    transition: width 0.3s;
}
.qr-badge-card {
    background: #FFFDF8;
    border: 2px dashed var(--gold);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    margin-top: 14px;
}
.meeting-review-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #FFF3D6;
    color: #785419;
    padding: 3px 10px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 700;
}
.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #F4EFE6;
    color: #4B5548;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.weekly-day-card {
    background: #FAF8F3;
    border: 1px solid #E5DFCF;
    border-radius: 10px;
    padding: 12px;
    min-height: 140px;
    display: flex;
    flex-direction: column;
}
.weekly-day-header {
    border-bottom: 1px solid #ECE4D4;
    padding-bottom: 6px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.daily-timeline-item {
    display: flex;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid #ECE4D4;
}
.daily-timeline-time {
    min-width: 90px;
    font-weight: 700;
    color: var(--green);
    font-size: 14px;
}
.table-meeting-row:hover {
    background: #FAF8F3;
}
</style>
@endpush

@section('content')
<div class="ch-head">
    <div>
        <h1>التجمعات</h1>
        <p>التقويم الموحد والاجتماعات: لقاءات حضورية وعن بُعد وهجينة، موثقة بمحاضر وتوصيات.</p>
    </div>
    @auth
        @if(Auth::user()->isSuperAdmin() || Auth::user()->isAdmin() || Auth::user()->communities()->wherePivotIn('role', ['manager', 'creator'])->exists())
            <button type="button" class="rq-btn rq-btn-primary" onclick="openModal('create-meeting-modal')">
                <i data-lucide="calendar-plus"></i><span>جدولة لقاء جديد</span>
            </button>
        @endif
    @endauth
</div>

<div class="ch-toolbar">
    <div class="rq-tabs" role="tablist">
        @foreach($supportedViews as $vKey => $vLabel)
            <a href="{{ route('calendar.index', ['view' => $vKey, 'type' => $typeFilter ?? 'all']) }}"
               class="cal-view-btn {{ ($view ?? 'monthly') === $vKey ? 'on active' : '' }}">{{ trim(preg_replace('/[^\p{L}\s]/u', '', $vLabel)) }}</a>
        @endforeach
    </div>
    <label class="cm-type">
        <span>نوع اللقاء</span>
        <select onchange="window.location.href = this.value">
            @foreach(($supportedTypes ?? $meetingTypes) as $tKey => $tLabel)
                <option value="{{ route('calendar.index', ['type' => $tKey, 'view' => $view ?? 'monthly']) }}" {{ ($typeFilter ?? 'all') === $tKey ? 'selected' : '' }}>{{ $tLabel }}</option>
            @endforeach
        </select>
    </label>
</div>


@if(($view ?? 'monthly') === 'monthly')
    <!-- ==================== VIEW 1: MONTHLY CALENDAR GRID ==================== -->
    <div class="grid2" style="margin-top:0;">
        <!-- Calendar Grid Panel -->
        <section class="panel animate-fade-in">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h2 style="margin:0;">سبتمبر 2026</h2>
                <span class="tag">{{ count($eventDays) }} فعاليات هذا الشهر</span>
            </div>

            <div class="calendar">
                @foreach(['ن','ث','ر','خ','ج','س','ح'] as $dayName)
                    <small style="font-weight:700;color:var(--sub);">{{ $dayName }}</small>
                @endforeach

                <!-- Empty slots before day 1 (Tuesday in Sept 2026) -->
                <span></span>

                @for($i = 1; $i <= 30; $i++)
                    @php $hasEvent = in_array($i, $eventDays); @endphp
                    <span class="{{ $hasEvent ? 'event-day calendar-day-expandable' : '' }}"
                          @if($hasEvent)
                              onclick="expandCalendarDay({{ $i }})"
                              role="button"
                              tabindex="0"
                          @endif
                          title="{{ $hasEvent ? 'يوجد موعد مجدول — انقر للتفاصيل' : '' }}">
                        {{ $i }}
                    </span>
                @endfor
            </div>

            <!-- Animated Calendar Day Event Expansion Drawer -->
            <div id="calendar-day-drawer" class="calendar-event-drawer" style="display:none;margin-top:14px;background:#FAF8F3;border:1px solid #E2D8C3;border-radius:10px;padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <span class="eyebrow" id="drawer-event-date" style="color:var(--gold);">17 سبتمبر 2026</span>
                        <h3 id="drawer-event-title" style="margin:2px 0 4px;font-size:16px;">لقاء نقاش «وحي القلم»</h3>
                        <p class="muted" id="drawer-event-desc" style="font-size:13px;margin:0 0 10px;">مجلس الأدب · مناقشة مفتوحة بين الأعضاء حول بلاغة الرافعي والعمق الوجداني.</p>
                    </div>
                    <button type="button" class="close-btn" onclick="document.getElementById('calendar-day-drawer').style.display='none'">&times;</button>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px;">
                    <span class="tag" id="drawer-event-type" style="background:#E1ECE2;color:#235738;">لقاء مجتمعي حي</span>
                    <span class="tag" style="background:#FFF3D6;color:#785419;">7:00 مساءً</span>
                    @if($meeting)
                        <form method="POST" action="{{ route('calendar.toggleRsvp', $meeting->id) }}" style="display:inline;margin:0;">
                            @csrf
                            <button type="submit" class="primary btn-sm" style="min-height:30px;padding:2px 10px;font-size:11.5px;">
                                <i data-lucide="{{ $isRsvp ? 'check' : 'user-check' }}" style="width:12px;height:12px;"></i>
                                <span>{{ $isRsvp ? 'تأكيد الحضور مسجّل ✓' : 'تأكيد الحضور الآن (+20 أثر)' }}</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <!-- Featured Meeting Panel (Included in Monthly Grid) -->
        @if($meeting)
            @include('pages.partials.featured-meeting-card', ['meeting' => $meeting])
        @else
            <section class="panel animate-fade-in" style="display:flex;align-items:center;justify-content:center;text-align:center;padding:40px 20px;">
                <div>
                    <i data-lucide="calendar" style="width:48px;height:48px;color:#C4B69B;margin-bottom:12px;"></i>
                    <h3 style="margin:0 0 8px;">لا توجد لقاءات مجدولة ضمن هذا التصنيف</h3>
                    <p class="muted" style="margin:0 0 16px;font-size:13px;">اختر تصنيفاً آخر أو قم بجدولة لقاء جديد لناديك.</p>
                    <a href="{{ route('calendar.index') }}" class="secondary btn-sm">عرض جميع اللقاءات</a>
                </div>
            </section>
        @endif
    </div>

@elseif(($view ?? 'monthly') === 'weekly')
    <!-- ==================== VIEW 2: WEEKLY VIEW ==================== -->
    <section class="panel animate-fade-in" style="margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <div>
                <h2 style="margin:0;font-size:18px;">الجدول الأسبوعي للمجالس</h2>
                <span class="muted" style="font-size:13px;">الأسبوع الحالي (15 - 21 سبتمبر 2026) بتوقيت مكة المكرمة</span>
            </div>
            <span class="tag" style="background:#E1ECE2;color:#1B5E20;font-weight:700;">{{ $upcomingMeetings->count() }} لقاء مجدول</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;">
            @php
                $weekDaysArabic = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
            @endphp
            @foreach($weekDaysArabic as $wIdx => $wName)
                @php
                    $dayNum = 15 + $wIdx;
                    $dayMeetings = $upcomingMeetings->filter(function($m) use ($dayNum) {
                        return $m->meeting_date && $m->meeting_date->day === $dayNum;
                    });
                @endphp
                <div class="weekly-day-card" style="{{ $dayMeetings->count() > 0 ? 'border-color:var(--gold);background:#FFFDF8;' : '' }}">
                    <div class="weekly-day-header">
                        <strong style="color:var(--green);font-size:13px;">{{ $wName }}</strong>
                        <span style="font-size:12px;font-weight:700;color:var(--sub);">{{ $dayNum }} سبت</span>
                    </div>

                    @if($dayMeetings->count() > 0)
                        <div style="display:grid;gap:8px;">
                            @foreach($dayMeetings as $dm)
                                <div style="background:#FAF8F3;border:1px solid #E5DFCF;border-radius:6px;padding:8px;font-size:12px;">
                                    <div style="font-weight:700;color:var(--ink);margin-bottom:2px;">{{ Str::limit($dm->title, 28) }}</div>
                                    <div style="color:var(--sub);font-size:11px;margin-bottom:4px;">
                                        🕒 {{ $dm->meeting_date->format('g:i A') }}
                                    </div>
                                    <span class="tag" style="font-size:10px;padding:2px 6px;">{{ $dm->meetingTypeLabel() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="margin:auto 0;text-align:center;color:#B2A692;font-size:12px;">
                            لا لقاءات مجدولة
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    @if($meeting)
        <div style="margin-top:20px;">
            @include('pages.partials.featured-meeting-card', ['meeting' => $meeting])
        </div>
    @endif

@elseif(($view ?? 'monthly') === 'daily')
    <!-- ==================== VIEW 3: DAILY AGENDA VIEW ==================== -->
    <section class="panel animate-fade-in" style="margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <div>
                <h2 style="margin:0;font-size:18px;">أجندة اليوم التفصيلية</h2>
                <span class="muted" style="font-size:13px;">الخميس، 17 سبتمبر 2026 · توقيت مكة المكرمة (GMT+3)</span>
            </div>
            <div style="display:flex;gap:6px;">
                <button type="button" class="secondary btn-sm" disabled>« اليوم السابق</button>
                <button type="button" class="secondary btn-sm" style="font-weight:700;">اليوم</button>
                <button type="button" class="secondary btn-sm" disabled>اليوم التالي »</button>
            </div>
        </div>

        <div class="daily-timeline">
            @forelse($upcomingMeetings as $dm)
                <div class="daily-timeline-item">
                    <div class="daily-timeline-time">
                        {{ $dm->meeting_date ? $dm->meeting_date->translatedFormat('g:i A') : '7:00 م' }}
                        <small style="display:block;font-size:11px;color:var(--sub);font-weight:normal;">
                            {{ $dm->timezone ?? 'Asia/Riyadh' }}
                        </small>
                    </div>
                    <div style="flex:1;background:#FAF8F3;border:1px solid #E5DFCF;border-radius:10px;padding:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                            <div>
                                <span class="tag" style="background:#E1ECE2;color:#1B5E20;margin-bottom:6px;">{{ $dm->meetingTypeLabel() }}</span>
                                <h3 style="margin:2px 0 4px;font-size:16px;color:var(--green);">{{ $dm->title }}</h3>
                                <div style="font-size:13px;color:var(--sub);">
                                    {{ $dm->community->name ?? 'مجلس الأدب' }} · 
                                    @if($dm->book) كتاب اللقاء: <b>{{ $dm->book->title }}</b> @endif
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <a href="{{ route('calendar.exportIcs', $dm->id) }}" class="secondary btn-sm" title="ICS"><i data-lucide="download" style="width:12px;height:12px;"></i> ICS</a>
                                <a href="{{ $dm->googleCalendarUrl() }}" target="_blank" class="secondary btn-sm" title="Google"><i data-lucide="calendar-plus" style="width:12px;height:12px;"></i> Google</a>
                            </div>
                        </div>

                        @if($dm->description)
                            <p class="muted" style="margin:8px 0 10px;font-size:13px;">{{ $dm->description }}</p>
                        @endif

                        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:12px;color:var(--sub);margin-top:8px;">
                            <span>👥 المقاعد: <b>{{ $dm->attendingCount() }}</b> / {{ $dm->capacity ?: 25 }}</span>
                            @if($dm->location)
                                <span>📍 {{ $dm->location }}</span>
                            @elseif($dm->link)
                                <span>🔗 <a href="{{ $dm->link }}" target="_blank" style="color:var(--green);text-decoration:underline;">رابط الجلسة</a></span>
                            @endif
                            @if($dm->coordinator)
                                <span>👤 المنسق: {{ $dm->coordinator->name }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div style="text-align:center;padding:30px 0;color:var(--sub);">
                    لا توجد لقاءات مجدولة لهذا اليوم.
                </div>
            @endforelse
        </div>
    </section>

@elseif(($view ?? 'monthly') === 'list')
    <!-- ==================== VIEW 4: TABULAR / LIST VIEW ==================== -->
    <section class="panel animate-fade-in" style="margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
            <div>
                <h2 style="margin:0;font-size:18px;">قائمة اللقاءات المجدولة</h2>
                <span class="muted" style="font-size:13px;">استعراض شامل لكافة الفعاليات وجدول النقاشات الثقافية</span>
            </div>
            <span class="tag">{{ $upcomingMeetings->count() }} لقاء</span>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13.5px;text-align:right;">
                <thead>
                    <tr style="border-bottom:2px solid #E8DFCB;color:var(--sub);">
                        <th style="padding:10px 12px;">التاريخ والوقت</th>
                        <th style="padding:10px 12px;">عنوان اللقاء</th>
                        <th style="padding:10px 12px;">النادي والكتاب</th>
                        <th style="padding:10px 12px;">النوع والمكان</th>
                        <th style="padding:10px 12px;">السعة والمقاعد</th>
                        <th style="padding:10px 12px;">المنسق</th>
                        <th style="padding:10px 12px;text-align:center;">الإجراءات والتقويم</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($upcomingMeetings as $lm)
                        @php
                            $lmFull = $lm->isFull();
                            $lmAttending = $lm->attendingCount();
                            $lmCap = $lm->capacity ?: 25;
                            $lmPct = min(100, round(($lmAttending / $lmCap) * 100));
                        @endphp
                        <tr class="table-meeting-row" style="border-bottom:1px solid #ECE4D4;">
                            <td style="padding:12px;white-space:nowrap;">
                                <strong style="display:block;color:var(--ink);">{{ $lm->meeting_date ? $lm->meeting_date->translatedFormat('d M Y') : '17 سبتمبر 2026' }}</strong>
                                <small class="muted">{{ $lm->meeting_date ? $lm->meeting_date->format('g:i A') : '7:00 م' }} ({{ $lm->timezone ?? 'مكة' }})</small>
                            </td>
                            <td style="padding:12px;">
                                <strong style="color:var(--green);display:block;">{{ $lm->title }}</strong>
                                @if($lm->discussion_questions)
                                    <small class="muted" style="font-size:11.5px;">يتضمن محاور نقاش معتمدة 💡</small>
                                @endif
                            </td>
                            <td style="padding:12px;">
                                <div>{{ $lm->community->name ?? 'مجلس الأدب' }}</div>
                                @if($lm->book)
                                    <small class="muted">📖 {{ $lm->book->title }}</small>
                                @endif
                            </td>
                            <td style="padding:12px;">
                                <span class="tag" style="display:inline-block;margin-bottom:3px;font-size:11.5px;">{{ $lm->meetingTypeLabel() }}</span>
                                <div style="font-size:11.5px;color:var(--sub);">
                                    {{ $lm->location ? Str::limit($lm->location, 20) : ($lm->link ? 'عبر رابط مرئي' : 'قاعة المجلس') }}
                                </div>
                            </td>
                            <td style="padding:12px;min-width:130px;">
                                <div style="display:flex;justify-content:space-between;font-size:11.5px;margin-bottom:3px;">
                                    <span>{{ $lmAttending }} / {{ $lmCap }}</span>
                                    <span style="font-weight:700;color:{{ $lmFull ? '#A32A2A' : 'var(--green)' }};">
                                        {{ $lmFull ? 'مكتمل' : 'متاح' }}
                                    </span>
                                </div>
                                <div class="capacity-gauge">
                                    <div class="capacity-gauge-fill" style="width:{{ $lmPct }}%;background:{{ $lmFull ? '#C85A32' : 'var(--green)' }};"></div>
                                </div>
                                @if($lm->waitlistCount() > 0)
                                    <small style="font-size:10.5px;color:#927233;">⏳ انتظار: {{ $lm->waitlistCount() }} (FIFO)</small>
                                @endif
                            </td>
                            <td style="padding:12px;white-space:nowrap;font-size:12.5px;color:var(--sub);">
                                {{ $lm->coordinator->name ?? 'إدارة المجلس' }}
                            </td>
                            <td style="padding:12px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    <a href="{{ route('calendar.exportIcs', $lm->id) }}" class="secondary btn-sm" title="تحميل ملف ICS (Apple/Outlook)" style="padding:4px 8px;">
                                        <i data-lucide="download" style="width:12px;height:12px;"></i>
                                        <span>ICS</span>
                                    </a>
                                    <a href="{{ $lm->googleCalendarUrl() }}" target="_blank" class="secondary btn-sm" title="إضافة لـ Google Calendar" style="padding:4px 8px;color:#3367D6;">
                                        <i data-lucide="calendar-plus" style="width:12px;height:12px;"></i>
                                        <span>Google</span>
                                    </a>
                                    @auth
                                        <form method="POST" action="{{ route('calendar.toggleRsvp', $lm->id) }}" style="display:inline;margin:0;">
                                            @csrf
                                            <button type="submit" class="primary btn-sm" style="padding:4px 8px;font-size:11.5px;">
                                                <span>{{ $lmFull ? 'قائمة الانتظار' : 'RSVP' }}</span>
                                            </button>
                                        </form>
                                    @endauth
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:30px;text-align:center;color:var(--sub);">
                                لا توجد لقاءات متاحة حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

<!-- Upcoming Commitments Section -->
<section class="panel animate-fade-in delay-2" style="margin-top:24px;">
    <h2>الالتزامات والمواعيد القادمة</h2>

    @forelse($commitments as $c)
        <div style="display:flex;gap:14px;align-items:center;padding:12px 0;border-top:1px solid var(--line);">
            <div style="background:#EEE9DD;color:#64705C;padding:6px 12px;border-radius:8px;min-width:48px;text-align:center;">
                <b style="font-size:20px;font-weight:700;display:block;line-height:1.2;">{{ $c['date']->format('d') }}</b>
                <small style="font-size:11px;">{{ $c['date']->translatedFormat('F') }}</small>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:14px;">{{ $c['title'] }}</div>
                <small class="muted" style="display:block;">{{ $c['sub'] }}</small>
            </div>
            <span class="tag" style="background:{{ $c['color'] }};color:{{ $c['text'] }};">{{ $c['tag'] }}</span>
        </div>
    @empty
        <div class="muted" style="padding:18px 0;border-top:1px solid var(--line);font-size:13px;">لا توجد التزامات قادمة. تظهر هنا مواعيد إعادة الكتب واللقاءات التي سجلت حضورها وتحدياتك الجارية.</div>
    @endforelse
</section>

<!-- أرشيف اللقاءات السابقة والتسجيلات الصوتية ومحاضر الجلسات (Past Sessions, Audio & Minutes) -->
<section class="panel animate-fade-in delay-2" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <div>
            <span class="eyebrow" style="color:var(--gold);">أرشيف الجلسات والتوثيق الصوتي</span>
            <h2 style="margin:2px 0 0;font-size:20px;">تسجيلات ومحاضر وتوصيات اللقاءات السابقة</h2>
        </div>
        <span class="tag" style="background:#EBF3ED;color:#1B4D3E;font-weight:600;">
            <i data-lucide="headphones" style="width:12px;height:12px;"></i> تسجيلات ومحاضر معتمدة
        </span>
    </div>

    @if(isset($pastMeetings) && $pastMeetings->count() > 0)
        <div class="past-meetings-list" style="display:grid;gap:16px;">
            @foreach($pastMeetings as $pm)
                <div class="past-meeting-card" style="background:#FAF8F3;border:1px solid #E2D9C5;border-radius:12px;padding:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <h3 style="margin:0;font-size:17px;color:var(--green);">{{ $pm->title }}</h3>
                                <span class="tag" style="font-size:11px;">{{ $pm->meetingTypeLabel() }}</span>
                                @if($pm->rating > 0)
                                    <span class="meeting-review-pill">
                                        ★ {{ number_format($pm->rating, 1) }}
                                        <small style="font-size:10.5px;">({{ $pm->ratings_count }} تقييم)</small>
                                    </span>
                                @endif
                            </div>
                            <small class="muted">{{ $pm->community->name ?? 'لقاء عام' }}@if($pm->meeting_date) · {{ $pm->meeting_date->translatedFormat('d F Y') }}@endif</small>
                        </div>
                        <span class="tag" style="background:#EEE9DD;color:#64705C;">محضر معتمد ✓</span>
                    </div>

                    <!-- Audio Player -->
                    @if($pm->audio_url)
                        <div class="audio-player-wrapper" style="background:#FFFDF8;border:1px solid #E8DFCB;padding:12px;border-radius:10px;margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:12.5px;font-weight:600;color:var(--ink);">
                                <i data-lucide="mic" style="width:15px;height:15px;color:var(--gold);"></i>
                                <span>التسجيل الصوتي الكامل للنقاش (جلسة موثقة)</span>
                            </div>
                            <audio controls style="width:100%;height:38px;border-radius:6px;outline:none;">
                                <source src="{{ $pm->audio_url }}" type="audio/mpeg">
                                متصفحك لا يدعم مشغل الصوت.
                            </audio>
                        </div>
                    @endif

                    <!-- Summary Notes / Official Minutes -->
                    @if($pm->meeting_minutes || $pm->summary_notes)
                    <div class="meeting-summary-box" style="font-size:13px;line-height:1.75;color:#3A443A;background:rgba(255,255,255,0.7);padding:14px 16px;border-radius:8px;border-right:3px solid var(--gold);margin-bottom:10px;">
                        <strong style="color:var(--green);display:block;margin-bottom:4px;font-size:13.5px;">محضر اللقاء وخلاصة الحوار:</strong>
                        {!! nl2br(e($pm->meeting_minutes ?: $pm->summary_notes)) !!}
                    </div>
                    @endif

                    <!-- Key Ideas & Recommendations Grid -->
                    @if($pm->key_ideas || $pm->recommendations)
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:12px;margin-bottom:10px;">
                            @if($pm->key_ideas)
                                <div style="background:#F7F4EC;border:1px solid #E5DFCF;border-radius:8px;padding:12px;">
                                    <strong style="color:#785419;display:block;font-size:12.5px;margin-bottom:4px;">💡 الأفكار والمحاور الجوهرية:</strong>
                                    <div style="font-size:12.5px;line-height:1.6;color:#4B5548;">{!! nl2br(e($pm->key_ideas)) !!}</div>
                                </div>
                            @endif
                            @if($pm->recommendations)
                                <div style="background:#F0F5F1;border:1px solid #D6E4D9;border-radius:8px;padding:12px;">
                                    <strong style="color:var(--green);display:block;font-size:12.5px;margin-bottom:4px;">🎯 توصيات القراءة والعمل:</strong>
                                    <div style="font-size:12.5px;line-height:1.6;color:#24583A;">{!! nl2br(e($pm->recommendations)) !!}</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Next Book for Following Session -->
                    @if($pm->nextBook)
                        <div style="display:inline-flex;align-items:center;gap:8px;background:#FFFDF8;border:1px dashed #C3A15D;border-radius:8px;padding:8px 12px;font-size:12.5px;color:var(--ink);">
                            <i data-lucide="bookmark" style="width:14px;height:14px;color:var(--gold);"></i>
                            <span>الكتاب المتفق عليه للجلسة التالية: <strong>{{ $pm->nextBook->title }}</strong> ({{ $pm->nextBook->author }})</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div style="text-align:center;padding:26px 12px;background:#FAF8F3;border-radius:10px;border:1px dashed #E5DFCF;" class="muted">
            <i data-lucide="headphones" style="width:32px;height:32px;margin:0 auto 6px;display:block;color:#cbd5e1;"></i>
            <p style="margin:0;font-size:13px;">لا توجد لقاءات سابقة موثّقة بعد. تظهر هنا تسجيلات اللقاءات ومحاضرها بعد انتهائها.</p>
        </div>
    @endif
</section>
@endsection

@section('modals')
<!-- Create Meeting Modal -->
@auth
<div id="create-meeting-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:620px;max-height:90vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <h3 style="margin:0;font-size:18px;color:var(--green);">جدولة لقاء وموعد نقاش جديد ✦</h3>
            <button class="close-btn" onclick="closeModal('create-meeting-modal')">&times;</button>
        </div>
        <form action="{{ route('calendar.meetings.store') }}" method="POST">
            @csrf
            <div style="display:grid;gap:12px;">
                <label style="display:grid;gap:4px;">
                    <span style="font-weight:600;font-size:13px;">عنوان اللقاء *</span>
                    <input type="text" name="title" required placeholder="مثال: نقاش كتاب «وحي القلم» للرافعي" style="font-size:14px;">
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">النادي الثقافي</span>
                        <select name="community_id" style="font-size:13.5px;">
                            <option value="">بدون نادٍ محدد (عام)</option>
                            @foreach($availableCommunities as $comm)
                                <option value="{{ $comm->id }}">{{ $comm->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">كتاب النقاش</span>
                        <select name="book_id" data-book-picker style="font-size:13.5px;">
                            <option value="">بدون كتاب محدد</option>
                        </select>
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">نوع اللقاء *</span>
                        <select name="meeting_type" required style="font-size:13.5px;">
                            <option value="in_person">📍 حضوري في المجلس</option>
                            <option value="remote" selected>💻 عن بُعد (جلسة مرئية)</option>
                            <option value="hybrid">🌐 هجين (حضوري وعن بُعد)</option>
                            <option value="open_discussion">💬 نقاش مفتوح</option>
                            <option value="author_meetup">✍️ لقاء مع مؤلف</option>
                            <option value="book_review">📖 مراجعة كتاب</option>
                            <option value="reading_workshop">🛠️ ورشة قراءة</option>
                            <option value="managers_meeting">🏛️ اجتماع مديري المجلس</option>
                        </select>
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">المنطقة الزمنية (Timezone)</span>
                        <input type="text" name="timezone" value="Asia/Riyadh" placeholder="Asia/Riyadh" style="font-size:13.5px;">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">تاريخ ووقت اللقاء *</span>
                        <input type="datetime-local" name="meeting_date" required style="font-size:13.5px;">
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">موعد الانتهاء</span>
                        <input type="datetime-local" name="ends_at" style="font-size:13.5px;">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">السعة القصوى للمقاعد</span>
                        <input type="number" name="capacity" value="25" min="1" max="5000" style="font-size:13.5px;">
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">آخر موعد للتسجيل (Deadline)</span>
                        <input type="datetime-local" name="registration_deadline" style="font-size:13.5px;">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">المكان (للحضوري والهجين)</span>
                        <input type="text" name="location" placeholder="مثال: قاعة رِواق، الرياض" style="font-size:13.5px;">
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">رابط الجلسة المرئية (للعن بُعد)</span>
                        <input type="url" name="link" placeholder="https://zoom.us/j/..." style="font-size:13.5px;">
                    </label>
                </div>

                <label style="display:grid;gap:4px;">
                    <span style="font-weight:600;font-size:13px;">وصف وأهداف اللقاء</span>
                    <textarea name="description" rows="2" placeholder="اكتب نبذة عن الجلسة والهدف من مناقشة الكتاب..." style="font-size:13.5px;"></textarea>
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">محاور وأسئلة النقاش</span>
                        <textarea name="discussion_questions" rows="2" placeholder="سؤال 1: ...&#10;سؤال 2: ..." style="font-size:13px;"></textarea>
                    </label>

                    <label style="display:grid;gap:4px;">
                        <span style="font-weight:600;font-size:13px;">تعليمات الحضور والتحضير</span>
                        <textarea name="instructions" rows="2" placeholder="يُرجى قراءة أول 50 صفحة والالتزام بالحضور قبل 10 دقائق..." style="font-size:13px;"></textarea>
                    </label>
                </div>

                <label style="display:grid;gap:4px;">
                    <span style="font-weight:600;font-size:13px;">الكتاب المقترح للجلسة التالية</span>
                    <select name="next_book_id" data-book-picker style="font-size:13.5px;">
                        <option value="">بدون تحديد</option>
                    </select>
                </label>

                <button type="submit" class="primary" style="width:100%;margin-top:6px;">
                    <i data-lucide="check-circle"></i>
                    <span>حفظ وجدولة اللقاء ونشره في التقويم</span>
                </button>
            </div>
        </form>
    </section>
</div>
@endauth

<!-- QR Check-in Modal for Attendee -->
@if($meeting && $isRsvp)
<div id="qr-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:440px;text-align:center;">
        <div class="heading" style="margin-bottom:12px;">
            <h3 style="margin:0;font-size:18px;color:var(--green);">بطاقة الحضور ورمز التحقق (QR)</h3>
            <button class="close-btn" onclick="closeModal('qr-modal')">&times;</button>
        </div>
        <p class="muted" style="font-size:13px;margin:0 0 14px;">
            أبرز هذا الرمز عند مدخل القاعة أو في بداية اللقاء الهجين لتسجيل حضورك الفعلي.
        </p>

        <div class="qr-badge-card">
            @if($rsvp && $rsvp->qr_svg)
                <div style="display:flex;justify-content:center;margin:10px auto;">
                    {!! $rsvp->qr_svg !!}
                </div>
            @else
                <!-- Fallback Graphical QR Pattern SVG -->
                <svg width="180" height="180" viewBox="0 0 100 100" style="margin:0 auto;display:block;">
                    <rect width="100" height="100" fill="#FFFDF8"/>
                    <rect x="5" y="5" width="26" height="26" fill="#173D36"/>
                    <rect x="9" y="9" width="18" height="18" fill="#FFFDF8"/>
                    <rect x="13" y="13" width="10" height="10" fill="#C3A15D"/>
                    <rect x="69" y="5" width="26" height="26" fill="#173D36"/>
                    <rect x="73" y="9" width="18" height="18" fill="#FFFDF8"/>
                    <rect x="77" y="13" width="10" height="10" fill="#C3A15D"/>
                    <rect x="5" y="69" width="26" height="26" fill="#173D36"/>
                    <rect x="9" y="73" width="18" height="18" fill="#FFFDF8"/>
                    <rect x="13" y="77" width="10" height="10" fill="#C3A15D"/>
                    <rect x="36" y="36" width="28" height="28" fill="#173D36"/>
                    <rect x="42" y="42" width="16" height="16" fill="#C3A15D"/>
                    <circle cx="50" cy="50" r="4" fill="#FFFDF8"/>
                    <rect x="36" y="10" width="8" height="8" fill="#173D36"/>
                    <rect x="52" y="18" width="8" height="8" fill="#173D36"/>
                    <rect x="12" y="42" width="8" height="8" fill="#173D36"/>
                    <rect x="72" y="42" width="8" height="8" fill="#173D36"/>
                    <rect x="42" y="72" width="8" height="8" fill="#173D36"/>
                    <rect x="72" y="72" width="8" height="8" fill="#173D36"/>
                </svg>
            @endif

            <div style="margin-top:12px;">
                <span class="muted" style="font-size:11.5px;display:block;">رمز التحقق السريع المعتمد:</span>
                <strong style="font-size:16px;letter-spacing:1px;color:var(--ink);">{{ $rsvp->qr_code ?? 'RWQ-MEET-001' }}</strong>
            </div>

            <div style="margin-top:6px;background:#F0F5F1;padding:6px 10px;border-radius:6px;font-size:11px;color:#24583A;">
                🔒 رمز مشفّر ومحدود بالجلسة (Scoped Token) — لا يُكشف معرّف حسابك لحماية الخصوصية.
            </div>

            <div style="margin-top:10px;">
                <span class="tag" style="background:#E1ECE2;color:#1B5E20;font-weight:700;">
                    {{ $rsvp && ($rsvp->checked_in || $rsvp->status === 'attended') ? 'تم تأكيد الحضور الفعلي ✓ (+15 أثر)' : 'في انتظار مسح الحضور' }}
                </span>
            </div>
        </div>

        <button type="button" class="secondary" onclick="window.print()" style="width:100%;margin-top:14px;">
            <i data-lucide="printer"></i>
            <span>طباعة بطاقة الحضور</span>
        </button>
    </section>
</div>
@endif

<!-- Meeting Rating Modal -->
@if($meeting)
<div id="rating-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:440px;">
        <div class="heading" style="margin-bottom:14px;">
            <h3 style="margin:0;font-size:18px;">تقييم لقاء «{{ $meeting->title }}»</h3>
            <button class="close-btn" onclick="closeModal('rating-modal')">&times;</button>
        </div>
        <form action="{{ route('calendar.storeRating', $meeting->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>ما هو تقييمك لجلسة النقاش؟</span>
                    <select name="rating" required style="font-size:15px;">
                        <option value="5">★★★★★ (5 من 5 - جلسة استثنائية وملهمة)</option>
                        <option value="4" selected>★★★★☆ (4 من 5 - نقاش ثري ومثمر)</option>
                        <option value="3">★★★☆☆ (3 من 5 - جيد ومفيد)</option>
                        <option value="2">★★☆☆☆ (2 من 5 - يحتاج تنظيماً أفضل)</option>
                        <option value="1">★☆☆☆☆ (1 من 5 - لم يناسبني)</option>
                    </select>
                </label>

                <label style="display:grid;gap:6px;">
                    <span>ملاحظاتك ومقترحاتك (اختياري)</span>
                    <textarea name="review" rows="3" placeholder="شاركنا انطباعك عن إدارة الحوار والنقاط الأكثر إثراءً..."></textarea>
                </label>

                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="star"></i>
                    <span>إرسال التقييم (+10 نقاط)</span>
                </button>
            </div>
        </form>
    </section>
</div>
@endif

<!-- Post-Meeting Details & Minutes Drafting Modal for Coordinator / Manager -->
@if($meeting)
<div id="post-meeting-details-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:580px;max-height:90vh;overflow-y:auto;">
        <div class="heading" style="margin-bottom:14px;">
            <h3 style="margin:0;font-size:18px;color:var(--green);">توثيق محضر وتوصيات لقاء «{{ $meeting->title }}»</h3>
            <button class="close-btn" onclick="closeModal('post-meeting-details-modal')">&times;</button>
        </div>
        <form action="{{ route('calendar.storePostDetails', $meeting->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:12px;">
                <p class="muted" style="font-size:13px;line-height:1.7;margin:0;">
                    يُرسل هذا المحضر لجميع الحاضرين تلقائياً ويُوثق في الأرشيف المعرفي والصوتي للمجلس.
                </p>

                <label style="display:grid;gap:4px;">
                    <span style="font-weight:600;font-size:13px;">المحضر الرسمي وخلاصة الحوار *</span>
                    <textarea name="meeting_minutes" rows="5" placeholder="اكتب خلاصة محاور النقاش والنقاط الجوهرية المتفق عليها..." required>{{ $meeting->meeting_minutes ?: $meeting->summary_notes }}</textarea>
                </label>

                <label style="display:grid;gap:4px;">
                    <span style="font-weight:600;font-size:13px;">💡 الأفكار والمحاور الرئيسية المستفادة</span>
                    <textarea name="key_ideas" rows="3" placeholder="1. فكرة محورية...&#10;2. استنتاج نقدي...">{{ $meeting->key_ideas }}</textarea>
                </label>

                <label style="display:grid;gap:4px;">
                    <span style="font-weight:600;font-size:13px;">🎯 التوصيات الختامية</span>
                    <textarea name="recommendations" rows="3" placeholder="1. توصية قراءة كتاب...&#10;2. بحث مسألة...">{{ $meeting->recommendations }}</textarea>
                </label>

                <label style="display:grid;gap:4px;">
                    <span style="font-weight:600;font-size:13px;">📖 الكتاب المتفق عليه للجلسة التالية</span>
                    <select name="next_book_id" data-book-picker style="font-size:13.5px;">
                        <option value="">بدون تحديد</option>
                        @if($meeting->nextBook)
                            <option value="{{ $meeting->next_book_id }}" selected>{{ $meeting->nextBook->title }} ({{ $meeting->nextBook->author }})</option>
                        @endif
                    </select>
                </label>

                <button type="submit" class="primary" style="width:100%;margin-top:6px;">
                    <i data-lucide="file-check"></i>
                    <span>اعتماد ونشر المحضر وإشعار المشاركين</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Backwards Compatible Minutes Modal Alias -->
<div id="minutes-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:540px;">
        <div class="heading" style="margin-bottom:14px;">
            <h3 style="margin:0;font-size:18px;">تدوين محضر وتوصيات اللقاء</h3>
            <button class="close-btn" onclick="closeModal('minutes-modal')">&times;</button>
        </div>
        <form action="{{ route('calendar.storePostDetails', $meeting->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>نص المحضر وأبرز التوصيات</span>
                    <textarea name="meeting_minutes" rows="6" placeholder="اكتب خلاصة محاور النقاش، الأفكار الجوهرية المتفق عليها، وتوصيات القراءة الختامية..." required>{{ $meeting->meeting_minutes ?: $meeting->summary_notes }}</textarea>
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="file-check"></i>
                    <span>اعتماد ونشر المحضر وإشعار الحاضرين</span>
                </button>
            </div>
        </form>
    </section>
</div>

<!-- Manager Attendance Check-in Modal -->
<div id="checkin-scanner-modal" class="overlay" style="display:none;">
    <section class="dialog" role="dialog" aria-modal="true" style="max-width:440px;">
        <div class="heading" style="margin-bottom:14px;">
            <h3 style="margin:0;font-size:18px;">تسجيل حضور القارئ عبر الرمز</h3>
            <button class="close-btn" onclick="closeModal('checkin-scanner-modal')">&times;</button>
        </div>
        <form action="{{ route('calendar.checkIn', $meeting->id) }}" method="POST">
            @csrf
            <div style="display:grid;gap:14px;">
                <label style="display:grid;gap:6px;">
                    <span>أدخل رمز التحقق (QR Token أو الكود السريع)</span>
                    <input name="qr_code" placeholder="مثال: RWQ-MEET-1-4" required style="font-size:15px;text-transform:uppercase;">
                </label>
                <button type="submit" class="primary" style="width:100%;">
                    <i data-lucide="check-circle"></i>
                    <span>تأكيد تسجيل الحضور الفعلي (+15 نقطة)</span>
                </button>
            </div>
        </form>
    </section>
</div>
@endif
@endsection

@push('scripts')
<script>
const eventsData = {
    17: {
        date: '17 سبتمبر 2026 · 7:00 م',
        title: 'لقاء نقاش «وحي القلم»',
        desc: 'مجلس الأدب · لقاء عن بُعد لمناقشة صور البيان والمعنى الوجداني لكتاب الشهر.',
        type: 'لقاء مجتمعي حي',
        color: '#E1ECE2',
        textColor: '#235738'
    },
    20: {
        date: '20 سبتمبر 2026',
        title: 'موعد إعادة كتاب «الأيام»',
        desc: 'مكتبة العائلة · إعارة نشطة تستحق الإرجاع أو التمديد لأسبوع إضافي.',
        type: 'استحقاق إعارة مادية',
        color: '#FFF3D6',
        textColor: '#785419'
    },
    30: {
        date: '30 سبتمبر 2026',
        title: 'ختام تحدّي «آفاق جديدة»',
        desc: 'تسليم مراجعات الكتب الثلاثة وحصد وسام الإنجاز الذهبي وتوثيق الشهادة.',
        type: 'تحدي قراءة سنوي',
        color: '#EDE6D7',
        textColor: '#655431'
    }
};

function expandCalendarDay(day) {
    const drawer = document.getElementById('calendar-day-drawer');
    if (!drawer) return;
    const evt = eventsData[day];
    if (evt) {
        document.getElementById('drawer-event-date').textContent = evt.date;
        document.getElementById('drawer-event-title').textContent = evt.title;
        document.getElementById('drawer-event-desc').textContent = evt.desc;
        const typeEl = document.getElementById('drawer-event-type');
        typeEl.textContent = evt.type;
        typeEl.style.background = evt.color;
        typeEl.style.color = evt.textColor;
        drawer.style.display = 'block';
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
@endpush
