@extends('layouts.app')

@section('title', $book->title . ' - ' . $book->author . ' | رِواق المعرفة')
@section('crumb', $book->title)

@section('content')
@php
    $coverUrl = $book->cover_image ? (str_starts_with($book->cover_image, 'http') ? $book->cover_image : asset($book->cover_image)) : '';
    $availableCopiesCount = $book->copies->where('status', 'available')->count();
    $loanUrl = ($availableCopy && !$hasActiveLoan) ? route('libraries.requestLoan', $availableCopy->id) : '';
@endphp

<a href="{{ route('explore') }}" class="bp-back"><i data-lucide="arrow-right"></i><span>العودة إلى تصفح الكتب</span></a>

<section class="bp-hero">
    <button type="button" class="bp-cover explore-editorial-tome-stage" onclick="openBookFromCard(this)" title="انقر لعرض التفاصيل"
        data-id="{{ $book->id }}"
        data-title="{{ $book->title }}"
        data-author="{{ $book->author }}"
        data-category="{{ $book->category }}"
        data-pages="{{ $book->total_pages }}"
        data-color="{{ $book->cover_color }}"
        data-cover="{{ $coverUrl }}"
        data-desc="{{ $book->description }}"
        data-copies="{{ $book->copies->count() }}"
        data-loan-url="{{ $loanUrl }}"
        data-is-borrowed="{{ $hasActiveLoan ? '1' : '0' }}"
        data-in-library="{{ ($inPersonalLibrary ?? false) ? '1' : '0' }}">
        <div class="bk-cover" style="--c: {{ $book->cover_color ?: '#315749' }};">
            <span class="bk-frame"></span>
            <span class="bk-knot">✦</span>
            <strong>{{ $book->title }}</strong>
            <small>{{ $book->author }}</small>
            <span class="bk-brand">رِواق المعرفة</span>
            @if($coverUrl)<img src="{{ $coverUrl }}" alt="{{ $book->title }}">@endif
        </div>
    </button>

    <div class="bp-info">
        <div class="rq-chips">
            <span class="rq-chip">{{ $book->category }}</span>
            @if($bookLendingEnabled)
                @if($availableCopiesCount > 0)
                    <span class="rq-chip green">{{ $availableCopiesCount }} نسخة ورقية متاحة للإعارة</span>
                @else
                    <span class="rq-chip">كافة النسخ مستعارة حالياً</span>
                @endif
            @endif
        </div>

        <h1>{{ $book->title }}</h1>
        <p class="bp-author">تأليف: <b>{{ $book->author }}</b></p>

        <div class="bp-facts">
            <div><b>★ {{ $avgRating }}</b><span>{{ $ratingsCount }} تقييم</span></div>
            <div><b>{{ $book->total_pages }}</b><span>صفحة</span></div>
            <div><b>{{ $book->copies->count() }}</b><span>نسخة بالخزائن</span></div>
            @if($book->publisher)<div><b>{{ $book->publisher }}</b><span>الناشر</span></div>@endif
            @if($book->language)<div><b>{{ $book->language }}</b><span>اللغة</span></div>@endif
            @if($book->isbn)<div><b>{{ $book->isbn }}</b><span>ISBN</span></div>@endif
        </div>

        @if($book->description)
            <p class="bp-desc">{{ $book->description }}</p>
        @endif

        <div class="bp-actions">
            @auth
                @if($inPersonalLibrary ?? false)
                    <span class="bp-state"><i data-lucide="check-circle"></i>موجود في مكتبتك الشخصية ✦</span>
                @else
                    <form method="POST" action="{{ route('libraries.addToPersonal') }}">
                        @csrf
                        <input type="hidden" name="book_id" value="{{ $book->id }}">
                        <button type="submit" class="rq-btn rq-btn-primary"><i data-lucide="library-big"></i><span>أضف لمكتبتي ✦</span></button>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}" class="rq-btn rq-btn-primary"><i data-lucide="library-big"></i><span>أضف لمكتبتي (تسجيل الدخول)</span></a>
            @endauth

            @auth
                @if($isCurrentBook)
                    <span class="bp-state"><i data-lucide="bookmark-check"></i>كتابك الحالي في سجل القراءة</span>
                @else
                    <form method="POST" action="{{ route('reading.switchBook') }}">
                        @csrf
                        <input type="hidden" name="book_id" value="{{ $book->id }}">
                        <button type="submit" class="rq-btn rq-btn-ghost"><i data-lucide="bookmark"></i><span>أقرأ هذا الكتاب الآن</span></button>
                    </form>
                @endif
            @endauth

            <button type="button" class="rq-report-link" data-report-type="book" data-report-id="{{ $book->id }}" data-report-title="{{ $book->title }}"><i data-lucide="flag"></i><span>إبلاغ عن مخالفة</span></button>

            @if($bookLendingEnabled)
                @if($hasActiveLoan)
                    <span class="bp-state"><i data-lucide="book-marked"></i>لديك بالفعل إعارة مسجلة لهذا الكتاب ({{ $activeLoan->status === 'active' ? 'نشطة بحوزتك' : 'قيد المراجعة' }})</span>
                @elseif($availableCopy)
                    @auth
                        <form method="POST" action="{{ route('libraries.requestLoan', $availableCopy->id) }}">
                            @csrf
                            <button type="submit" class="rq-btn rq-btn-gold"><i data-lucide="book-copy"></i><span>طلب استعارة النسخة الورقية</span></button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rq-btn rq-btn-gold"><i data-lucide="log-in"></i><span>تسجيل الدخول للاستعارة</span></a>
                    @endauth
                @else
                    @auth
                        @php($firstLibraryId = $book->copies->first()->library_id ?? 1)
                        <form method="POST" action="{{ route('libraries.joinWaitlist', ['library' => $firstLibraryId, 'book' => $book->id]) }}">
                            @csrf
                            <button type="submit" class="rq-btn rq-btn-ghost"><i data-lucide="clock"></i><span>الانضمام لقائمة الانتظار</span></button>
                        </form>
                    @else
                        <span class="bp-state">النسخ مستعارة حالياً. سجّل الدخول لحجز دورك.</span>
                    @endauth
                @endif
            @endif
        </div>
    </div>
</section>

<!-- 2. Physical Copies & Shelf Inventory -->
<section class="panel animate-fade-in" style="margin-bottom:28px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid var(--line);padding-bottom:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="boxes" style="width:20px;height:20px;color:var(--gold);"></i>
            <h2 style="margin:0;font-size:18px;font-family:'Noto Naskh Arabic', serif;">النسخ الورقية المادية وأماكنها على الرفوف</h2>
        </div>
        <span class="tag">{{ $book->copies->count() }} نسخ موثقة</span>
    </div>

    @if($book->copies->isNotEmpty())
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13.5px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--line);color:var(--sub);">
                        <th style="padding:10px 14px;">رقم النسخة والباركود</th>
                        <th style="padding:10px 14px;">الخزانة / المكتبة</th>
                        <th style="padding:10px 14px;">الموضع والرف</th>
                        <th style="padding:10px 14px;">حالة النسخة الورقية</th>
                        @if($bookLendingEnabled)
                        <th style="padding:10px 14px;">التوفر</th>
                        <th style="padding:10px 14px;">الإجراء</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($book->copies as $copy)
                        <tr style="border-bottom:1px solid var(--line);">
                            <td style="padding:12px 14px;font-weight:600;">
                                <span>نسخة #{{ $copy->copy_number }}</span>
                                <small class="muted" style="display:block;font-size:11px;font-family:monospace;">{{ $copy->barcode ?: 'RWQ-' . $copy->id }}</small>
                            </td>
                            <td style="padding:12px 14px;">
                                <b style="color:var(--green);">{{ $copy->library->name ?? 'المكتبة الشخصية' }}</b>
                                <small class="muted" style="display:block;font-size:11px;">{{ $copy->library->type ?? 'عامة' }}</small>
                            </td>
                            <td style="padding:12px 14px;">
                                <span class="tag" style="background:#FAF8F2;color:var(--ink);font-size:11px;">
                                    {{ $copy->shelf_location ?: 'الرف الرئيسي' }}
                                </span>
                            </td>
                            <td style="padding:12px 14px;">
                                <span class="tag" style="background:#E2ECDE;color:#214F34;font-size:11px;">
                                    {{ match($copy->condition) {
                                        'excellent' => 'ممتازة ✦',
                                        'good' => 'جيدة جداً',
                                        'fair' => 'مقبولة',
                                        default => 'بحالة حسنة'
                                    } }}
                                </span>
                            </td>
                            @if($bookLendingEnabled)
                            <td style="padding:12px 14px;">
                                @if($copy->status === 'available')
                                    <span class="tag" style="background:#E2ECDE;color:#214F34;">متاح للاستعارة</span>
                                @elseif($copy->status === 'borrowed')
                                    <span class="tag" style="background:#FAF4E6;color:#785419;">مستعار حالياً</span>
                                @else
                                    <span class="tag" style="background:#FBEAE5;color:#8F3829;">قيد المراجعة</span>
                                @endif
                            </td>
                            <td style="padding:12px 14px;">
                                @if($copy->status === 'available')
                                    @auth
                                        <form method="POST" action="{{ route('libraries.requestLoan', $copy->id) }}" style="margin:0;">
                                            @csrf
                                            <button type="submit" class="primary btn-sm" style="font-size:11.5px;padding:3px 12px;min-height:30px;">
                                                استعارة النسخة
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}" class="secondary btn-sm" style="font-size:11.5px;text-decoration:none;">تسجيل</a>
                                    @endauth
                                @else
                                    <small class="muted">غير متوفرة الآن</small>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted" style="text-align:center;padding:24px 0;">لا توجد نسخ ورقية مسجلة لهذا الكتاب حالياً.</p>
    @endif
</section>

<!-- 3. Community Reviews & Critiques -->
<div class="grid2" style="margin-top:0;">
    <!-- Reviews List -->
    <section class="panel animate-fade-in">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="message-square" style="width:18px;height:18px;color:var(--gold);"></i>
                <h2 style="margin:0;font-size:17px;font-family:'Noto Naskh Arabic', serif;">المراجعات النقدية وآراء القراء</h2>
            </div>
            <span class="tag">{{ $book->reviews->count() }} مراجعة</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px;">
            @forelse($book->reviews as $rev)
                <div class="panel" style="padding:16px;background:#FFFDF8;border:1px solid var(--line);">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="avatar" style="width:32px;height:32px;font-size:13px;">
                                {{ $rev->user->avatar ?? mb_substr($rev->user->name ?? 'ق', 0, 1) }}
                            </span>
                            <div>
                                <b style="font-size:13.5px;color:var(--green);display:block;">{{ $rev->user->name ?? 'قارئ' }}</b>
                                <small class="muted" style="font-size:11px;">{{ $rev->created_at->locale('ar')->diffForHumans() }}</small>
                            </div>
                        </div>

                        <div style="color:var(--gold);font-size:13px;letter-spacing:1px;">
                            {{ str_repeat('★', $rev->rating) }}{{ str_repeat('☆', 5 - $rev->rating) }}
                        </div>
                    </div>

                    @if($rev->title)
                        <h4 style="margin:0 0 6px;font-size:14px;font-family:'Noto Naskh Arabic', serif;">{{ $rev->title }}</h4>
                    @endif

                    @if($rev->is_spoiler)
                        <div style="background:#FAF4E6;padding:8px 12px;border-radius:6px;font-size:12px;color:#785419;margin-bottom:8px;">
                            ⚠️ تحتوي هذه المراجعة على حرق لأحداث الكتاب (Spoiler).
                        </div>
                    @endif

                    <p style="font-size:13px;line-height:1.6;color:var(--ink);margin:0 0 10px;">
                        {{ $rev->content }}
                    </p>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        @if(Auth::id() !== $rev->user_id)
                            <button type="button" class="rq-report-link" data-report-type="review" data-report-id="{{ $rev->id }}" data-report-title="{{ \Illuminate\Support\Str::limit($rev->content, 60) }}"><i data-lucide="flag"></i><span>إبلاغ</span></button>
                        @else
                            <span></span>
                        @endif
                        <form method="POST" action="{{ route('reading.likeReview', $rev->id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="secondary btn-sm" style="font-size:11px;padding:2px 10px;display:inline-flex;align-items:center;gap:4px;">
                                <i data-lucide="thumbs-up" style="width:12px;height:12px;color:var(--gold);"></i>
                                <span>أعجبني ({{ $rev->likes_count ?: 0 }})</span>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="muted" style="text-align:center;padding:24px 0;font-size:13px;">كن أول من يكتب مراجعة نقدية لهذا الكتاب!</p>
            @endforelse
        </div>
    </section>

    <!-- Write a Review Form -->
    <section class="panel animate-fade-in">
        <div style="margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="edit-3" style="width:18px;height:18px;color:var(--gold);"></i>
                <h2 style="margin:0;font-size:17px;font-family:'Noto Naskh Arabic', serif;">شارك مراجعتك ورؤيتك النقدية</h2>
            </div>
        </div>

        @auth
            <form method="POST" action="{{ route('reading.storeReview') }}">
                @csrf
                <input type="hidden" name="book_id" value="{{ $book->id }}">

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">تقييمك للكتاب:</label>
                    <select name="rating" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:13px;outline:none;">
                        <option value="5">★★★★★ - عمل استثنائي ورفيع (5/5)</option>
                        <option value="4">★★★★☆ - كتاب ممتاز وقيّم (4/5)</option>
                        <option value="3">★★★☆☆ - كتاب جيد ويستحق القراءة (3/5)</option>
                        <option value="2">★★☆☆☆ - عمل متواضع (2/5)</option>
                        <option value="1">★☆☆☆☆ - لم يرق للتوقعات (1/5)</option>
                    </select>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">عنوان المراجعة (اختياري):</label>
                    <input type="text" name="title" placeholder="خلاصة انطباعك بعبارة بليغة..." style="width:100%;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:13px;outline:none;">
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">نص المراجعة والتأملات:</label>
                    <textarea name="content" rows="5" required placeholder="ما الذي أضافه لك هذا الكتاب؟ كيف كانت لغة الكاتب وأفكاره؟" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:10px;font-size:13px;font-family:inherit;outline:none;"></textarea>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;cursor:pointer;">
                        <input type="checkbox" name="is_spoiler" value="1">
                        <span>تنبيه: تحتوي المراجعة على حرق لأحداث الكتاب (Spoiler)</span>
                    </label>
                </div>

                <button type="submit" class="primary" style="padding:9px 20px;font-size:13px;font-weight:600;width:100%;">
                    نشر المراجعة وكسب 25 نقطة أثر ✨
                </button>
            </form>
        @else
            <div style="text-align:center;padding:30px 16px;">
                <p class="muted" style="margin-bottom:14px;font-size:13px;">يرجى تسجيل الدخول لمشاركة مراجعتك وتقييمك للكتاب.</p>
                <a href="{{ route('login') }}" class="primary btn-sm" style="text-decoration:none;padding:6px 16px;">تسجيل الدخول</a>
            </div>
        @endauth
    </section>
</div>

<!-- 4. Reader Reflections & Quotes -->
@if($book->reflections->isNotEmpty())
<section class="panel animate-fade-in" style="margin-top:28px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="quote" style="width:18px;height:18px;color:var(--gold);"></i>
            <h2 style="margin:0;font-size:17px;font-family:'Noto Naskh Arabic', serif;">اقتباسات وتأملات من صفحات هذا الكتاب</h2>
        </div>
        <span class="tag">{{ $book->reflections->count() }} اقتباس</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:16px;">
        @foreach($book->reflections as $ref)
            <div class="panel" style="padding:16px;background:#FFFDF8;border:1px solid var(--line);">
                <blockquote style="margin:0 0 10px;font-family:'Noto Naskh Arabic', serif;font-size:14px;color:var(--green);border-inline-start:3px solid var(--gold);padding-inline-start:10px;line-height:1.6;">
                    «{{ $ref->quote }}»
                </blockquote>
                @if($ref->thought)
                    <p style="font-size:12.5px;color:var(--ink);margin:0 0 8px;">{{ $ref->thought }}</p>
                @endif
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;" class="muted">
                    <span>دوّنه: {{ $ref->user->name ?? 'قارئ' }}</span>
                    @if($ref->page_number)
                        <span>صفحة {{ $ref->page_number }}</span>
                    @endif
                    @if(Auth::id() !== $ref->user_id)
                        <button type="button" class="rq-report-link" data-report-type="reflection" data-report-id="{{ $ref->id }}" data-report-title="{{ \Illuminate\Support\Str::limit($ref->quote, 60) }}"><i data-lucide="flag"></i><span>إبلاغ</span></button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

<!-- 5. Related Books in Same Category -->
@if($relatedBooks->isNotEmpty())
<section class="panel animate-fade-in" style="margin-top:28px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="sparkles" style="width:18px;height:18px;color:var(--gold);"></i>
            <h2 style="margin:0;font-size:17px;font-family:'Noto Naskh Arabic', serif;">كتب مقترحة من نفس المجال ({{ $book->category }})</h2>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:16px;">
        @foreach($relatedBooks as $rb)
            <div class="panel card-tilt" style="padding:14px;background:#FFFDF8;border:1px solid var(--line);display:flex;gap:12px;align-items:center;">
                <div style="width:48px;height:70px;background:{{ $rb->cover_color ?? '#173D36' }};border-radius:4px;display:flex;align-items:center;justify-content:center;color:#FFFDF8;font-size:16px;flex-shrink:0;">
                    📖
                </div>
                <div style="flex:1;min-width:0;">
                    <a href="{{ route('books.show', $rb->id) }}" style="text-decoration:none;color:inherit;">
                        <h4 style="font-size:14px;margin:0 0 4px;font-family:'Noto Naskh Arabic', serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $rb->title }}
                        </h4>
                    </a>
                    <small class="muted" style="display:block;margin-bottom:6px;">{{ $rb->author }}</small>
                    <a href="{{ route('books.show', $rb->id) }}" class="secondary btn-sm" style="font-size:11px;padding:2px 8px;text-decoration:none;">
                        عرض البطاقة
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
@endsection
