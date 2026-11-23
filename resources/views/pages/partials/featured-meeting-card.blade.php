@php
    $attendingCount = $meeting->attendingCount();
    $capacity = $meeting->capacity ?: 25;
    $capPct = min(100, round(($attendingCount / $capacity) * 100));
    $isFull = $meeting->isFull();
    $waitlistCount = $meeting->waitlistCount();
    $meetingShareText = rawurlencode("تذكير موعد لقاء «{$meeting->title}» في رِواق المعرفة 📖\nالتاريخ: " . ($meeting->meeting_date ? $meeting->meeting_date->translatedFormat('l d F Y - g:i A') : '') . "\nالرابط: " . route('calendar.index'));
@endphp

<section class="panel animate-fade-in delay-1" style="border:1px solid #E8DFCB;box-shadow:0 6px 18px rgba(0,0,0,0.04);">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
        <span class="eyebrow" style="color:var(--gold);margin:0;">
            {{ $meeting->meeting_date ? $meeting->meeting_date->translatedFormat('d F Y · g:i A') : '17 سبتمبر 2026' }}
        </span>
        <span class="tag" style="background:#EBF3ED;color:var(--green);font-weight:700;">
            {{ $meeting->meetingTypeLabel() }}
        </span>
    </div>

    <h2 style="margin:8px 0 6px;font-size:22px;color:var(--green);">{{ $meeting->title }}</h2>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
        <span class="meta-chip">
            <i data-lucide="users" style="width:13px;height:13px;"></i>
            <span>{{ $meeting->community->name ?? 'مجلس الأدب' }}</span>
        </span>
        @if($meeting->book)
            <span class="meta-chip" style="background:#FBF5E8;color:#785419;">
                <i data-lucide="book" style="width:13px;height:13px;"></i>
                <span>كتاب اللقاء: {{ $meeting->book->title }} ({{ $meeting->book->author }})</span>
            </span>
        @endif
        @if($meeting->coordinator)
            <span class="meta-chip">
                <i data-lucide="user" style="width:13px;height:13px;"></i>
                <span>منسق الجلسة: {{ $meeting->coordinator->name }}</span>
            </span>
        @endif
        <span class="meta-chip">
            <i data-lucide="globe" style="width:13px;height:13px;"></i>
            <span>{{ $meeting->timezone ?? 'Asia/Riyadh' }} (توقيت مكة)</span>
        </span>
    </div>

    <!-- Location or Link -->
    <div style="font-weight:600;font-size:13.5px;margin-bottom:12px;color:var(--ink);">
        @if($meeting->meeting_type === 'in_person')
            <span style="color:#24583A;">📍 مقر المجلس: {{ $meeting->location ?? 'قاعة رواق، الرياض' }}</span>
        @elseif($meeting->meeting_type === 'hybrid')
            <span style="color:#24583A;">🌐 مقر اللقاء الحضوري ({{ $meeting->location ?? 'قاعة رواق' }}) + بث مباشر</span>
            @if($meeting->link)
                <a href="{{ $meeting->link }}" target="_blank" style="margin-right:8px;color:var(--green);text-decoration:underline;">[رابط البث]</a>
            @endif
        @else
            <span>💻 لقاء مرئي عبر المنصة: </span>
            @if($meeting->link)
                <a href="{{ $meeting->link }}" target="_blank" style="color:var(--green);text-decoration:underline;">{{ $meeting->link }}</a>
            @else
                <span class="muted">(سيُتاح الرابط للمسجلين)</span>
            @endif
        @endif
    </div>

    @if($meeting->description)
        <p class="muted" style="line-height:1.75;font-size:13.5px;margin-bottom:14px;">
            {{ $meeting->description }}
        </p>
    @endif

    <!-- Discussion Questions & Instructions Accordion/Box -->
    @if($meeting->discussion_questions || $meeting->instructions)
        <div style="background:#FAF8F3;border:1px solid #E5DFCF;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
            @if($meeting->discussion_questions)
                <div style="margin-bottom:8px;">
                    <strong style="color:var(--green);font-size:12.5px;display:block;margin-bottom:3px;">💡 محاور وأسئلة النقاش المعتمدة:</strong>
                    <div style="font-size:12.5px;line-height:1.6;color:#4B5548;">{!! nl2br(e($meeting->discussion_questions)) !!}</div>
                </div>
            @endif
            @if($meeting->instructions)
                <div>
                    <strong style="color:#785419;font-size:12.5px;display:block;margin-bottom:3px;">📋 تعليمات وإرشادات الحضور:</strong>
                    <div style="font-size:12.5px;line-height:1.6;color:#524B3D;">{!! nl2br(e($meeting->instructions)) !!}</div>
                </div>
            @endif
        </div>
    @endif

    <!-- Capacity Gauge & Seat Availability -->
    <div style="background:#FAF8F3;border:1px solid #E5DFCF;border-radius:10px;padding:12px 14px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12.5px;margin-bottom:4px;">
            <span style="color:#524B3D;font-weight:600;">حالة المقاعد والسعة المتاحة:</span>
            <span style="font-weight:700;color:{{ $isFull ? '#A32A2A' : 'var(--green)' }};">
                {{ $attendingCount }} من {{ $capacity }} مقعداً
                @if($isFull) (اكتملت المقاعد — متاح في قائمة الانتظار) @endif
            </span>
        </div>
        <div class="capacity-gauge">
            <div class="capacity-gauge-fill" style="width: {{ $capPct }}%;background:{{ $isFull ? '#C85A32' : 'var(--green)' }};"></div>
        </div>
        @if($waitlistCount > 0)
            <small class="muted" style="font-size:11.5px;display:block;margin-top:2px;">
                ⏳ يوجد حالياً <b>{{ $waitlistCount }}</b> في قائمة الانتظار (يُصعدون تلقائياً فور توفر مقعد وفق سياسة الأسبقية FIFO).
            </small>
        @endif
        @if($meeting->registration_deadline)
            <small class="muted" style="font-size:11.5px;display:block;margin-top:2px;">
                ⏰ آخر موعد لتأكيد التسجيل: {{ $meeting->registration_deadline->translatedFormat('d F Y · g:i A') }}
            </small>
        @endif
    </div>

    <!-- RSVP Lifecycle & Status Section -->
    <div style="margin-bottom:14px;">
        @auth
            <!-- Current Status Feedback Pill -->
            @if($isRsvp)
                <div style="background:#E9EFE7;color:#284731;padding:10px 14px;border-radius:8px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                        <i data-lucide="check-circle" style="color:var(--forest);"></i>
                        <span>أنت مسجل كـ <b>مؤكد الحضور (Going)</b>. رمز الدخول السريع: <b>{{ $rsvp->qr_code ?? 'RWQ-MEET-001' }}</b></span>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="secondary btn-sm" onclick="openModal('qr-modal')" style="font-size:11.5px;padding:3px 8px;">
                            <i data-lucide="qr-code" style="width:11px;height:11px;"></i>
                            <span>عرض QR</span>
                        </button>
                    </div>
                </div>
            @elseif($isWaitlist)
                <div style="background:#FFF8E7;color:#785419;padding:10px 14px;border-radius:8px;margin-bottom:10px;display:flex;align-items:center;gap:8px;font-size:13px;">
                    <i data-lucide="clock" style="color:var(--gold);"></i>
                    <span>أنت مسجل في <b>قائمة الانتظار (Waitlist)</b>. ستُصعّد تلقائياً ويصلك إشعار فوري فور اعتذار أي مشارك وفق سياسة FIFO.</span>
                </div>
            @elseif($isMaybe)
                <div style="background:#F2EFF8;color:#533F78;padding:10px 14px;border-radius:8px;margin-bottom:10px;display:flex;align-items:center;gap:8px;font-size:13px;">
                    <i data-lucide="help-circle"></i>
                    <span>سجلت اهتمامك كـ <b>ربما أحضر (Maybe)</b>. لتأكيد حجز مقعد رسمي، يُرجى تأكيد الحضور قبل اكتمال السعة.</span>
                </div>
            @elseif($isAttended)
                <div style="background:#E1ECE2;color:#1B5E20;padding:10px 14px;border-radius:8px;margin-bottom:10px;display:flex;align-items:center;gap:8px;font-size:13px;">
                    <i data-lucide="award"></i>
                    <span>تم توثيق حضورك الفعلي لهذا اللقاء بنجاح (+15 أثر)!</span>
                </div>
            @endif

            <!-- RSVP Action Buttons Group -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:8px;">
                <!-- Going / Waitlist Button -->
                <form method="POST" action="{{ route('calendar.toggleRsvp', $meeting->id) }}" style="margin:0;">
                    @csrf
                    <input type="hidden" name="status" value="going">
                    @if($isRsvp)
                        <button type="button" class="primary" style="width:100%;background:#235738;opacity:0.9;" disabled>
                            <i data-lucide="check"></i>
                            <span>حاضر (Going) ✓</span>
                        </button>
                    @else
                        <button type="submit" class="primary" style="width:100%;">
                            <i data-lucide="{{ $isFull ? 'clock' : 'check-circle' }}"></i>
                            <span>{{ $isFull ? 'انتظار ⏳ (FIFO)' : 'أؤكد الحضور (Going)' }}</span>
                        </button>
                    @endif
                </form>

                <!-- Maybe Button -->
                <form method="POST" action="{{ route('calendar.toggleRsvp', $meeting->id) }}" style="margin:0;">
                    @csrf
                    <input type="hidden" name="status" value="maybe">
                    <button type="submit" class="secondary" style="width:100%;{{ $isMaybe ? 'background:#EDE6D6;border-color:#C3A15D;' : '' }}">
                        <i data-lucide="help-circle"></i>
                        <span>ربما (Maybe)</span>
                    </button>
                </form>

                <!-- Not Going / Cancel Button -->
                <form method="POST" action="{{ route('calendar.toggleRsvp', $meeting->id) }}" style="margin:0;">
                    @csrf
                    <input type="hidden" name="status" value="not_going">
                    <button type="submit" class="secondary" style="width:100%;color:#A13030;border-color:#E2BDBD;">
                        <i data-lucide="x-circle"></i>
                        <span>{{ ($isRsvp || $isWaitlist || $isMaybe) ? 'إلغاء الحجز' : 'معتذر (Not Going)' }}</span>
                    </button>
                </form>
            </div>
        @else
            <a href="{{ route('login') }}" class="primary" style="display:flex;justify-content:center;align-items:center;gap:6px;width:100%;text-decoration:none;padding:10px 14px;">
                <i data-lucide="log-in"></i>
                <span>سجّل دخولك لتأكيد الحضور (RSVP)</span>
            </a>
        @endauth
    </div>

    <!-- Reminders & Calendar Exports Row -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #ECE4D4;padding-top:14px;">
        <a href="https://api.whatsapp.com/send?text={{ $meetingShareText }}" target="_blank" class="secondary btn-sm" title="إرسال تذكير عبر واتساب" style="color:#1b5e20;">
            <i data-lucide="message-circle" style="color:#25D366;width:13px;height:13px;"></i>
            <span>واتساب</span>
        </a>

        <a href="https://t.me/share/url?url={{ urlencode(route('calendar.index')) }}&text={{ $meetingShareText }}" target="_blank" class="secondary btn-sm" title="إرسال تذكير عبر تيليجرام" style="color:#0077b5;">
            <i data-lucide="send" style="color:#0088cc;width:13px;height:13px;"></i>
            <span>تيليجرام</span>
        </a>

        <a href="{{ route('calendar.exportIcs', $meeting->id) }}" class="secondary btn-sm" title="تحميل ملف ICS المتوافق مع Apple Calendar و Outlook">
            <i data-lucide="download" style="width:13px;height:13px;"></i>
            <span>ملف ICS (Apple/Outlook)</span>
        </a>

        <a href="{{ $meeting->googleCalendarUrl() }}" target="_blank" class="secondary btn-sm" title="إضافة الموعد إلى Google Calendar" style="color:#3367D6;">
            <i data-lucide="calendar-plus" style="width:13px;height:13px;"></i>
            <span>Google Calendar</span>
        </a>

        @auth
            @if(Auth::user()->isSuperAdmin() || ($meeting->coordinator_id && $meeting->coordinator_id === Auth::id()) || (isset($meeting->community) && $meeting->community->canManage(Auth::user())))
                <button type="button" class="secondary btn-sm" onclick="openModal('post-meeting-details-modal')" style="margin-inline-start:auto;color:var(--green);border-color:var(--green);" title="توثيق المحضر والتوصيات والكتاب التالي">
                    <i data-lucide="file-text" style="width:13px;height:13px;"></i>
                    <span>المحضر والتوصيات</span>
                </button>
                <button type="button" class="secondary btn-sm" onclick="openModal('checkin-scanner-modal')" title="تسجيل حضور القارئ عبر مسح رمز QR">
                    <i data-lucide="scan-line" style="width:13px;height:13px;"></i>
                    <span>تسجيل حضور QR</span>
                </button>
            @endif
        @endauth
    </div>
</section>
