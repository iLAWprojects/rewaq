@forelse($books as $b)
    @php
        $hasActiveLoan = in_array($b->id, $userActiveLoanedBookIds ?? []);
        $loanUrl = '';
        if ($bookLendingEnabled && $b->available_copies_count > 0 && ! $hasActiveLoan) {
            $firstAvailableCopy = $b->copies()->where('status', 'available')
                ->whereHas('library', fn ($q) => $q->where('user_id', '!=', Auth::id() ?? 0))
                ->first();
            $loanUrl = $firstAvailableCopy ? route('libraries.requestLoan', $firstAvailableCopy->id) : '';
        }
        $coverUrl = $b->cover_image
            ? (str_starts_with($b->cover_image, 'http') || str_starts_with($b->cover_image, 'data:') ? $b->cover_image : asset($b->cover_image))
            : '';
        $bInLibrary = Auth::check() && (
            in_array($b->id, $userPersonalBookIds ?? []) ||
            in_array(\App\Models\Book::normalizeTitle($b->title), $userPersonalNormalizedTitles ?? [])
        );
    @endphp
    <article class="ex-card">
        <button type="button" class="ex-cover-btn open-book-btn" onclick="openBookFromCard(this)" aria-label="تفاصيل «{{ $b->title }}»"
            data-id="{{ $b->id }}"
            data-title="{{ $b->title }}"
            data-author="{{ $b->author }}"
            data-category="{{ $b->category }}"
            data-pages="{{ $b->total_pages }}"
            data-color="{{ $b->cover_color }}"
            data-cover="{{ $coverUrl }}"
            data-desc="{{ $b->description }}"
            data-copies="{{ $b->copies_count }}"
            data-loan-url="{{ $loanUrl }}"
            data-is-borrowed="{{ $hasActiveLoan ? '1' : '0' }}"
            data-in-library="{{ $bInLibrary ? '1' : '0' }}">
            <div class="bk-cover" style="--c: {{ $b->cover_color ?: '#315749' }};">
                <span class="bk-frame"></span>
                <span class="bk-knot">✦</span>
                <strong>{{ $b->title }}</strong>
                <small>{{ $b->author }}</small>
                <span class="bk-brand">رِواق المعرفة</span>
                @if($coverUrl)<img src="{{ $coverUrl }}" alt="غلاف {{ $b->title }}" loading="lazy">@endif
            </div>
        </button>

        <div class="ex-card-body">
            <h3>{{ $b->title }}</h3>
            <p>{{ $b->author }}</p>
            <div class="ex-tags">
                <span class="rq-chip">{{ $b->category }}</span>
                @if($bInLibrary)
                    <span class="rq-chip green"><i data-lucide="check"></i>في مكتبتك</span>
                @endif
                @if($bookLendingEnabled)
                    @if($hasActiveLoan)
                        <span class="rq-chip">مستعار لك</span>
                    @elseif($b->available_copies_count > 0)
                        <span class="rq-chip green">متاح للإعارة</span>
                    @elseif($b->copies_count > 0)
                        <span class="rq-chip">معار حاليًا</span>
                    @endif
                @endif
            </div>
        </div>
    </article>
@empty
    <div class="ex-empty">
        <i data-lucide="book-x"></i>
        <h3>لا توجد كتب مطابقة</h3>
        <p>جرّب كلمة بحث مختلفة أو اختر تصنيفًا آخر.</p>
        <a href="{{ route('explore') }}" class="rq-btn rq-btn-ghost rq-btn-sm"><i data-lucide="rotate-ccw"></i><span>إعادة الضبط</span></a>
    </div>
@endforelse
