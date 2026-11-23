/**
 * رِواق المعرفة — JavaScript التفاعلي ومحرك الرسوم
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Lucide Icons
    if (window.lucide) {
        window.lucide.createIcons();
    }

    // 2. Mobile Menu Toggle
    const menuToggle = document.getElementById('menu-toggle');
    const aside = document.getElementById('riwaq-sidebar');
    if (menuToggle && aside) {
        menuToggle.addEventListener('click', () => {
            aside.classList.toggle('open');
            const isOpen = aside.classList.contains('open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
        });
    }

    // 3. Modal System
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
            const firstInput = modal.querySelector('input:not([type="checkbox"]):not([type="radio"]), textarea, select, button.primary');
            if (firstInput) setTimeout(() => firstInput.focus(), 50);
        }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Close on overlay click
    document.querySelectorAll('.overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                if (overlay.id === 'book-details-modal') {
                    window.closeBookModal();
                } else {
                    overlay.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const bookModal = document.getElementById('book-details-modal');
            if (bookModal && bookModal.style.display !== 'none') {
                window.closeBookModal();
            }
            document.querySelectorAll('.overlay:not(#book-details-modal)').forEach(overlay => {
                overlay.style.display = 'none';
            });
            document.body.style.overflow = '';
        }
    });

    // 4. Live Toast Announcement
    window.showToast = function (message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = `
            <i data-lucide="${type === 'success' ? 'check-circle' : 'info'}"></i>
            <span>${message}</span>
        `;
        container.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };

    // 4.1 Universal Reliable Copy to Clipboard (supports HTTP, HTTPS, mobile, desktop)
    window.copyToClipboard = function (text, successMessage = null) {
        if (!text) return Promise.reject(new Error('Empty text'));

        const fallback = (str) => {
            const el = document.createElement('textarea');
            el.value = str;
            el.setAttribute('readonly', '');
            el.style.position = 'fixed';
            el.style.top = '-9999px';
            el.style.left = '-9999px';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.focus();
            el.select();
            let ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (err) {
                console.warn('execCommand copy failed:', err);
            }
            document.body.removeChild(el);
            if (ok) {
                if (successMessage && window.showToast) window.showToast(successMessage);
                return Promise.resolve(true);
            }
            return Promise.reject(new Error('Fallback copy failed'));
        };

        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(() => {
                if (successMessage && window.showToast) window.showToast(successMessage);
                return true;
            }).catch(() => fallback(text));
        }

        return fallback(text);
    };

    // ==========================================================================
    // 5. Interactive Reading Session Timer & Stopwatch
    // ==========================================================================
    let timerInterval = null;
    let timerMode = 'countdown'; // 'countdown' or 'stopwatch'
    let isRunning = false;
    let timerSeconds = 25 * 60;
    let targetMinutes = 25;

    const digitsEl = document.getElementById('stopwatch-digits');
    const startBtn = document.getElementById('stopwatch-toggle');
    const resetBtn = document.getElementById('stopwatch-reset');
    const minutesInput = document.getElementById('session-minutes-input');
    const stopwatchBox = document.querySelector('.stopwatch-box');
    const modeCountdownBtn = document.getElementById('timer-mode-countdown');
    const modeStopwatchBtn = document.getElementById('timer-mode-stopwatch');
    const presetBtns = document.querySelectorAll('.timer-preset-btn');
    const hintMinutesSpan = document.getElementById('timer-hint-minutes');

    function playTimerBell() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            if (ctx.state === 'suspended') ctx.resume();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
            osc.frequency.exponentialRampToValueAtTime(1046.50, ctx.currentTime + 0.15); // C6
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.2);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 1.2);
        } catch (e) {}
    }

    function formatTime(totalSec) {
        const s = Math.max(0, Math.floor(totalSec));
        const m = Math.floor(s / 60);
        const remS = s % 60;
        return `${String(m).padStart(2, '0')}:${String(remS).padStart(2, '0')}`;
    }

    function syncDigits() {
        if (!digitsEl) return;
        digitsEl.textContent = formatTime(timerSeconds);
    }

    function syncActivePreset() {
        presetBtns.forEach(btn => {
            const m = parseInt(btn.dataset.minutes, 10);
            if (m === targetMinutes) {
                btn.classList.add('active');
                btn.style.borderColor = 'var(--green)';
                btn.style.background = 'rgba(23,61,54,0.09)';
                btn.style.color = 'var(--green)';
                btn.style.fontWeight = '700';
            } else {
                btn.classList.remove('active');
                btn.style.borderColor = '#D0C5B0';
                btn.style.background = '#FFFDF8';
                btn.style.color = 'var(--ink)';
                btn.style.fontWeight = 'normal';
            }
        });
        if (hintMinutesSpan) {
            hintMinutesSpan.textContent = targetMinutes;
        }
    }

    function setMode(mode) {
        if (isRunning) {
            pauseTimer();
        }
        timerMode = mode;
        if (mode === 'countdown') {
            timerSeconds = targetMinutes * 60;
            if (modeCountdownBtn) {
                modeCountdownBtn.classList.add('active');
                modeCountdownBtn.style.background = 'var(--green)';
                modeCountdownBtn.style.color = '#fff';
            }
            if (modeStopwatchBtn) {
                modeStopwatchBtn.classList.remove('active');
                modeStopwatchBtn.style.background = 'transparent';
                modeStopwatchBtn.style.color = 'var(--ink)';
            }
        } else {
            // Stopwatch
            timerSeconds = 0;
            if (modeStopwatchBtn) {
                modeStopwatchBtn.classList.add('active');
                modeStopwatchBtn.style.background = 'var(--green)';
                modeStopwatchBtn.style.color = '#fff';
            }
            if (modeCountdownBtn) {
                modeCountdownBtn.classList.remove('active');
                modeCountdownBtn.style.background = 'transparent';
                modeCountdownBtn.style.color = 'var(--ink)';
            }
        }
        syncDigits();
    }

    function pauseTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        isRunning = false;
        if (startBtn) {
            startBtn.classList.remove('active', 'running');
            startBtn.innerHTML = '<i data-lucide="play" style="width:14px;height:14px;"></i> <span>استئناف المؤقت</span>';
        }
        if (stopwatchBox) stopwatchBox.classList.remove('active-breathing');
        if (window.lucide) window.lucide.createIcons();
    }

    function resetTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        isRunning = false;
        if (timerMode === 'countdown') {
            timerSeconds = targetMinutes * 60;
        } else {
            timerSeconds = 0;
        }
        syncDigits();
        if (startBtn) {
            startBtn.classList.remove('active', 'running');
            startBtn.innerHTML = '<i data-lucide="play" style="width:14px;height:14px;"></i> <span>تشغيل المؤقت</span>';
        }
        if (stopwatchBox) stopwatchBox.classList.remove('active-breathing');
        if (window.lucide) window.lucide.createIcons();
    }

    function startTimer() {
        if (isRunning) return;
        isRunning = true;
        if (startBtn) {
            startBtn.classList.add('active', 'running');
            startBtn.innerHTML = '<i data-lucide="pause" style="width:14px;height:14px;"></i> <span>إيقاف مؤقت</span>';
        }
        if (stopwatchBox) stopwatchBox.classList.add('active-breathing');
        if (window.lucide) window.lucide.createIcons();

        // If countdown was at 0, restart from targetMinutes
        if (timerMode === 'countdown' && timerSeconds <= 0) {
            timerSeconds = targetMinutes * 60;
            syncDigits();
        }

        timerInterval = setInterval(() => {
            if (timerMode === 'countdown') {
                timerSeconds--;
                syncDigits();

                // Ensure minutesInput keeps targetMinutes (never overwritten down to 1!)
                if (minutesInput && parseInt(minutesInput.value, 10) !== targetMinutes) {
                    minutesInput.value = targetMinutes;
                }

                if (timerSeconds <= 0) {
                    // Countdown complete!
                    clearInterval(timerInterval);
                    timerInterval = null;
                    isRunning = false;
                    if (startBtn) {
                        startBtn.classList.remove('active', 'running');
                        startBtn.innerHTML = '<i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i> <span>بدء من جديد</span>';
                    }
                    if (stopwatchBox) stopwatchBox.classList.remove('active-breathing');
                    if (window.lucide) window.lucide.createIcons();

                    playTimerBell();
                    if (typeof window.showToast === 'function') {
                        window.showToast(`🎉 مبارك! أتممت جلسة القراءة المحددة (${targetMinutes} دقيقة) بنجاح.`);
                    }
                    if (typeof window.triggerPointsCelebration === 'function') {
                        window.triggerPointsCelebration(25, `إتمام قراءة ${targetMinutes} دقيقة`);
                    }
                }
            } else {
                // Stopwatch mode (count up)
                timerSeconds++;
                syncDigits();

                // Only update minutesInput if actual elapsed minutes exceed initial setting
                const elapsedMin = Math.ceil(timerSeconds / 60);
                if (minutesInput) {
                    if (elapsedMin > targetMinutes) {
                        minutesInput.value = elapsedMin;
                    } else if (parseInt(minutesInput.value, 10) !== targetMinutes) {
                        minutesInput.value = targetMinutes;
                    }
                }
            }
        }, 1000);
    }

    if (startBtn && digitsEl) {
        if (minutesInput) {
            targetMinutes = parseInt(minutesInput.value, 10) || 25;
            timerSeconds = targetMinutes * 60;

            minutesInput.addEventListener('input', () => {
                const val = parseInt(minutesInput.value, 10);
                if (!isNaN(val) && val > 0) {
                    targetMinutes = val;
                    if (!isRunning && timerMode === 'countdown') {
                        timerSeconds = targetMinutes * 60;
                        syncDigits();
                    }
                    syncActivePreset();
                }
            });
        }

        syncDigits();
        syncActivePreset();

        startBtn.addEventListener('click', () => {
            if (isRunning) {
                pauseTimer();
            } else {
                startTimer();
            }
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                resetTimer();
            });
        }

        if (modeCountdownBtn) {
            modeCountdownBtn.addEventListener('click', () => setMode('countdown'));
        }
        if (modeStopwatchBtn) {
            modeStopwatchBtn.addEventListener('click', () => setMode('stopwatch'));
        }

        presetBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const mins = parseInt(btn.dataset.minutes, 10);
                if (mins && minutesInput) {
                    targetMinutes = mins;
                    minutesInput.value = mins;
                    if (!isRunning && timerMode === 'countdown') {
                        timerSeconds = mins * 60;
                        syncDigits();
                    }
                    syncActivePreset();
                }
            });
        });
    }

    // ==========================================================================
    // 6. 3D Book Opening Animation Modal & Sound Engine
    // ==========================================================================
    function playBookSound(type = 'open') {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            if (ctx.state === 'suspended') {
                ctx.resume();
            }

            if (type === 'open') {
                // Realistic soft parchment paper rustle
                const duration = 0.35;
                const buffer = ctx.createBuffer(1, Math.floor(ctx.sampleRate * duration), ctx.sampleRate);
                const output = buffer.getChannelData(0);
                let last = 0;
                for (let i = 0; i < buffer.length; i++) {
                    const p = i / buffer.length;
                    const env = Math.sin(p * Math.PI) * Math.exp(-p * 2.2);
                    const white = Math.random() * 2 - 1;
                    last = last * 0.82 + white * 0.18;
                    output[i] = last * env * 0.14;
                }
                const src = ctx.createBufferSource();
                src.buffer = buffer;
                const filter = ctx.createBiquadFilter();
                filter.type = 'bandpass';
                filter.frequency.setValueAtTime(650, ctx.currentTime);
                filter.frequency.exponentialRampToValueAtTime(320, ctx.currentTime + duration);
                filter.Q.value = 1.0;
                const gain = ctx.createGain();
                gain.gain.setValueAtTime(0.28, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
                src.connect(filter);
                filter.connect(gain);
                gain.connect(ctx.destination);
                src.start();
            } else {
                // Subtle leather book shut thump
                const duration = 0.26;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(110, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(45, ctx.currentTime + duration);
                gain.gain.setValueAtTime(0.22, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + duration);
            }
        } catch (e) {
            // Silently continue if audio context is blocked
        }
    }

    window.openBookFromCard = function (el) {
        if (!el) return;
        
        // 1. Locate the button or container with data attributes
        let target = null;
        if (el.dataset && (el.dataset.title || el.dataset.id)) {
            target = el;
        } else {
            target = el.closest('[data-title]') || el.closest('[data-id]');
        }

        if (!target) {
            const card = el.closest('.physical-copy-card, .book-card, article');
            if (card) {
                target = card.querySelector('.open-book-btn, [data-title], [data-id]');
            }
        }

        if (!target) target = el;
        const d = target.dataset || {};

        // 2. Discover cover image from data attributes, or fallback to any image inside the card
        let coverImg = d.cover ? d.cover.trim() : null;
        if (!coverImg || coverImg === 'null' || coverImg === 'undefined' || coverImg === '') {
            const card = target.closest('.physical-copy-card, .book-card, article') || (el.closest ? el.closest('.physical-copy-card, .book-card, article') : null);
            if (card) {
                const realImg = card.querySelector('img.copy-cover-real-img, .copy-cover-3d img, .cover img, img');
                if (realImg && realImg.src && !realImg.src.includes('avatar') && !realImg.src.includes('icon')) {
                    coverImg = realImg.src;
                }
            }
        }

        const cardParent = target.closest ? target.closest('.book-card, .physical-copy-card, article, section') : null;
        const inLibrary = d.inLibrary === '1' || d.inLibrary === 'true' ||
            (cardParent && (cardParent.dataset.inLibrary === '1' || cardParent.dataset.inLibrary === 'true'));

        window.previewBook(
            d.id,
            d.title,
            d.author,
            d.category,
            d.pages,
            d.color,
            d.desc,
            d.copies,
            d.loanUrl,
            d.isBorrowed === '1' || d.isBorrowed === 'true',
            coverImg,
            inLibrary
        );
    };

    window.previewBook = function (bookId, title, author, cat, pages, color, desc, copiesCount, loanUrl, isAlreadyBorrowed = false, coverImage = null, inLibrary = false) {
        const modal = document.getElementById('book-details-modal');
        const bookObj = document.getElementById('riwaq-book-object');
        if (!modal) return;

        // Normalize coverImage
        if (coverImage && (coverImage === 'null' || coverImage === 'undefined' || coverImage.trim() === '')) {
            coverImage = null;
        }

        // Populate inner pages details
        const titleEl = document.getElementById('bmodal-title');
        if (titleEl) titleEl.textContent = title;

        const authorCatEl = document.getElementById('bmodal-author-cat');
        if (authorCatEl) authorCatEl.textContent = `${author} · ${cat} (${pages} صفحة)`;

        const descEl = document.getElementById('bmodal-desc');
        if (descEl) descEl.textContent = desc || 'كتاب قيم محفوظ ومفهرس في رواق المعرفة.';

        const copiesEl = document.getElementById('bmodal-copies');
        if (copiesEl) copiesEl.textContent = `${copiesCount} نسخة مادية متوفرة`;

        const catTag = document.getElementById('bmodal-cat-tag');
        if (catTag) catTag.textContent = cat;

        const pagesTag = document.getElementById('bmodal-pages-tag');
        if (pagesTag) pagesTag.textContent = `${pages} صفحة`;

        // Calculate Reading Time & Archival Stamp
        const pInt = parseInt(pages, 10) || 250;
        const estTimeEl = document.getElementById('bmodal-est-time');
        if (estTimeEl) {
            const hours = Math.max(0.5, Math.round((pInt * 1.1 / 60) * 10) / 10);
            estTimeEl.textContent = `~${hours} ساعات`;
        }

        const archiveCodeEl = document.getElementById('bmodal-archive-code');
        if (archiveCodeEl) {
            const numericId = parseInt(bookId, 10) || 1042;
            archiveCodeEl.textContent = `WQ-${String(numericId).padStart(4, '0')}-MS`;
        }

        // Thematic chips
        const t1 = document.getElementById('bmodal-theme-1');
        const t2 = document.getElementById('bmodal-theme-2');
        const t3 = document.getElementById('bmodal-theme-3');
        if (t1 || t2 || t3) {
            const cleanCat = (cat || 'معرفة').trim();
            if (/دين|إسلام|فقه|حديث|عقيدة/i.test(cleanCat)) {
                if (t1) t1.textContent = '#علوم_تراثية';
                if (t2) t2.textContent = '#أصول_وفكر';
                if (t3) t3.textContent = '#مخطوطات_معتمدة';
            } else if (/أدب|رواية|شعر|قصص|بلاغة/i.test(cleanCat)) {
                if (t1) t1.textContent = '#نصوص_أدبية';
                if (t2) t2.textContent = '#بلاغة_وبيَان';
                if (t3) t3.textContent = '#قراءة_وجدانية';
            } else if (/تاريخ|حضارة|سيرة|أندلس/i.test(cleanCat)) {
                if (t1) t1.textContent = '#شواهد_تاريخية';
                if (t2) t2.textContent = '#وثائق_الزمن';
                if (t3) t3.textContent = '#تراث_إنساني';
            } else if (/فلسفة|منطق|فكر/i.test(cleanCat)) {
                if (t1) t1.textContent = '#تأمل_وفلسفة';
                if (t2) t2.textContent = '#حوار_العقول';
                if (t3) t3.textContent = '#منطق_وبرهان';
            } else {
                if (t1) t1.textContent = `#${cleanCat.replace(/\s+/g, '_')}`;
                if (t2) t2.textContent = '#قراءة_عميقة';
                if (t3) t3.textContent = '#إرث_معرفي';
            }
        }

        // Populate Heritage Page Numbers
        const pageNumRight = document.getElementById('bmodal-page-num-right');
        const pageNumLeft = document.getElementById('bmodal-page-num-left');
        const halfP = Math.max(12, Math.floor(pInt * 0.48));
        if (pageNumRight) pageNumRight.textContent = `صـ ${halfP}`;
        if (pageNumLeft) pageNumLeft.textContent = `صـ ${halfP + 1}`;

        // 1. Populate Right Page Miniature Cover Showcase
        const coverEl = document.getElementById('bmodal-cover');
        const coverRealImg = document.getElementById('bmodal-cover-real-img');
        const coverTypo = document.getElementById('bmodal-cover-typography');
        const coverTitle = document.getElementById('bmodal-cover-title');
        const coverAuthor = document.getElementById('bmodal-cover-author');

        if (coverTitle) coverTitle.textContent = title;
        if (coverAuthor) coverAuthor.textContent = author;

        if (coverEl) {
            if (coverImage) {
                coverEl.classList.add('has-real-cover');
                coverEl.style.background = '#0E2B26';
                if (coverRealImg) {
                    coverRealImg.src = coverImage;
                    coverRealImg.style.display = 'block';
                    coverRealImg.onerror = function() {
                        this.style.display = 'none';
                        if (coverTypo) coverTypo.style.display = 'flex';
                        coverEl.classList.remove('has-real-cover');
                        coverEl.style.background = color || '#173D36';
                    };
                }
                if (coverTypo) coverTypo.style.display = 'none';
            } else {
                coverEl.classList.remove('has-real-cover');
                if (coverRealImg) {
                    coverRealImg.src = '';
                    coverRealImg.style.display = 'none';
                }
                if (coverTypo) coverTypo.style.display = 'flex';
                coverEl.style.background = color || '#173D36';
            }
        }

        // 2. Populate 3D Front Hardcover outside face (The exterior of the closed/opening tome)
        const coverFaceEl = document.getElementById('bmodal-cover-face');
        const faceRealImg = document.getElementById('bmodal-cover-face-img');
        const faceCrease = document.getElementById('bmodal-face-crease-overlay');
        const faceTypo = document.getElementById('bmodal-face-typography');
        const flapTitleEl = document.getElementById('bmodal-cover-flap-title');
        const flapAuthorEl = document.getElementById('bmodal-cover-flap-author');

        if (flapTitleEl) flapTitleEl.textContent = title;
        if (flapAuthorEl) flapAuthorEl.textContent = author;

        if (coverFaceEl) {
            if (coverImage) {
                coverFaceEl.classList.add('has-real-cover');
                coverFaceEl.style.background = '#0E2B26';
                if (faceRealImg) {
                    faceRealImg.src = coverImage;
                    faceRealImg.style.display = 'block';
                    faceRealImg.onerror = function() {
                        this.style.display = 'none';
                        if (faceTypo) faceTypo.style.display = 'flex';
                        if (faceCrease) faceCrease.style.display = 'none';
                        coverFaceEl.classList.remove('has-real-cover');
                        coverFaceEl.style.background = color || '#173D36';
                    };
                }
                if (faceCrease) faceCrease.style.display = 'block';
                if (faceTypo) faceTypo.style.display = 'none';
            } else {
                coverFaceEl.classList.remove('has-real-cover');
                if (faceRealImg) {
                    faceRealImg.src = '';
                    faceRealImg.style.display = 'none';
                }
                if (faceCrease) faceCrease.style.display = 'none';
                if (faceTypo) faceTypo.style.display = 'flex';
                coverFaceEl.style.background = color || '#173D36';
            }

            // Click to unfold if clicked while closed
            coverFaceEl.onclick = function() {
                if (bookObj && bookObj.classList.contains('book-closed')) {
                    bookObj.classList.remove('book-closed');
                    bookObj.classList.add('book-opened');
                    playBookSound('open');
                }
            };
        }

        // Add to Personal Library Form & Button handling
        const addLibForm = document.getElementById('bmodal-add-library-form');
        const addLibBookId = document.getElementById('bmodal-add-library-book-id');
        const addLibBtn = document.getElementById('bmodal-add-library-btn');
        const addLibText = document.getElementById('bmodal-add-library-text');

        if (addLibBookId) {
            addLibBookId.value = bookId || '';
        }

        if (addLibBtn) {
            if (inLibrary) {
                addLibBtn.classList.add('already-in-library');
                addLibBtn.disabled = true;
                addLibBtn.style.background = 'rgba(33, 79, 52, 0.15)';
                addLibBtn.style.color = '#173D36';
                addLibBtn.style.borderColor = 'rgba(74, 222, 128, 0.5)';
                addLibBtn.style.cursor = 'default';
                if (addLibText) {
                    addLibText.innerHTML = '<i data-lucide="check-circle" style="width:15px;height:15px;display:inline-block;vertical-align:middle;color:#1F5148;margin-inline-end:4px;"></i> موجود في مكتبتك الشخصية ✦';
                }
            } else {
                addLibBtn.classList.remove('already-in-library');
                addLibBtn.disabled = false;
                addLibBtn.style.background = 'linear-gradient(135deg, #FAF6EC 0%, #F3EBD8 100%)';
                addLibBtn.style.color = '#173D36';
                addLibBtn.style.borderColor = '#C3A15D';
                addLibBtn.style.cursor = 'pointer';
                if (addLibText) {
                    addLibText.innerHTML = '<i data-lucide="library-big" style="width:15px;height:15px;display:inline-block;vertical-align:middle;color:#C3A15D;margin-inline-end:4px;"></i> أضف لمكتبتي الشخصية ✦';
                }
            }
            if (window.lucide) {
                window.lucide.createIcons();
            }
        }

        if (addLibForm && !addLibForm.dataset.listenerAttached) {
            addLibForm.dataset.listenerAttached = 'true';
            addLibForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const curId = addLibBookId ? addLibBookId.value : null;
                if (!curId) return;

                if (addLibBtn) {
                    addLibBtn.disabled = true;
                    if (addLibText) addLibText.textContent = 'جارٍ الإضافة لمكتبتك...';
                }

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || addLibForm.querySelector('input[name="_token"]')?.value || '';
                    const res = await fetch(addLibForm.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ book_id: curId }),
                    });

                    if (res.status === 401) {
                        window.location.href = '/login';
                        return;
                    }

                    const data = await res.json();
                    if (data.success) {
                        if (addLibBtn) {
                            addLibBtn.classList.add('already-in-library');
                            addLibBtn.disabled = true;
                            addLibBtn.style.background = 'rgba(33, 79, 52, 0.15)';
                            addLibBtn.style.color = '#173D36';
                            addLibBtn.style.borderColor = 'rgba(74, 222, 128, 0.5)';
                            addLibBtn.style.cursor = 'default';
                            if (addLibText) {
                                addLibText.innerHTML = '<i data-lucide="check-circle" style="width:15px;height:15px;display:inline-block;vertical-align:middle;color:#1F5148;margin-inline-end:4px;"></i> موجود في مكتبتك الشخصية ✦';
                            }
                            if (window.lucide) window.lucide.createIcons();
                        }

                        // Also mark any card on the page for this book
                        document.querySelectorAll(`[data-id="${curId}"]`).forEach(card => {
                            card.dataset.inLibrary = '1';
                            card.querySelectorAll('[data-in-library]').forEach(child => {
                                child.dataset.inLibrary = '1';
                            });
                        });

                        const toastMsg = data.message || 'تمت إضافة الكتاب لمكتبتك وسجل قراءتك بنجاح ✦';
                        const note = document.createElement('div');
                        note.textContent = toastMsg;
                        note.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#173D36;color:#FFFDF8;border:1px solid #C3A15D;padding:12px 24px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.25);z-index:99999;font-weight:700;font-family:Noto Naskh Arabic,serif;';
                        document.body.appendChild(note);
                        setTimeout(() => note.remove(), 4000);
                    } else {
                        throw new Error(data.message || 'تعذرت الإضافة');
                    }
                } catch (err) {
                    console.error(err);
                    addLibForm.submit();
                }
            });
        }

        // Loan form action
        const loanForm = document.getElementById('bmodal-loan-form');
        if (loanForm) {
            loanForm.action = loanUrl || '';
            const loanBtn = loanForm.querySelector('button');
            const loanBtnText = loanBtn ? loanBtn.querySelector('.btn-royal-text, span') : null;
            if (loanBtn) {
                if (isAlreadyBorrowed) {
                    loanBtn.disabled = true;
                    loanBtn.style.opacity = '0.75';
                    loanBtn.style.filter = 'grayscale(0.4)';
                    loanBtn.title = 'لا يمكن استعارة نفس الكتاب أكثر من مرة — لديك إعارة نشطة لهذا الكتاب بالفعل';
                    if (loanBtnText) loanBtnText.textContent = 'مُستعار من قِبلك بالفعل (لا يمكن التكرار)';
                } else if (!loanUrl) {
                    loanBtn.disabled = true;
                    loanBtn.style.opacity = '0.6';
                    loanBtn.style.filter = 'grayscale(0.6)';
                    loanBtn.title = 'لا توجد نسخ متاحة للإعارة حالياً';
                    if (loanBtnText) loanBtnText.textContent = 'لا توجد نسخ متاحة للإعارة حالياً';
                } else {
                    loanBtn.disabled = false;
                    loanBtn.style.opacity = '1';
                    loanBtn.style.filter = 'none';
                    loanBtn.title = '';
                    if (loanBtnText) loanBtnText.textContent = 'طلب استعارة نسخة ورقية';
                }
            }
        }

        // Display modal
        modal.style.display = 'flex';
        // Trigger opacity fade-in
        requestAnimationFrame(() => {
            modal.classList.add('active');
        });
        document.body.style.overflow = 'hidden';

        if (bookObj) {
            // 1. Ensure closed tome state first
            bookObj.classList.remove('book-opened');
            bookObj.classList.add('book-closed');

            // 2. Force browser repaint
            void bookObj.offsetWidth;

            // 3. Play paper open rustle
            playBookSound('open');

            // 4. Begin opening sequence after deliberate tactile pause (140ms)
            setTimeout(() => {
                bookObj.classList.remove('book-closed');
                bookObj.classList.add('book-opened');
            }, 140);
        }

        if (window.lucide) window.lucide.createIcons();
    };

    window.closeBookModal = function () {
        const modal = document.getElementById('book-details-modal');
        const bookObj = document.getElementById('riwaq-book-object');
        if (!modal) return;

        if (bookObj && bookObj.classList.contains('book-opened')) {
            // Trigger 3D Book Closing animation first
            bookObj.classList.remove('book-opened');
            bookObj.classList.add('book-closed');
            playBookSound('close');

            setTimeout(() => {
                modal.classList.remove('active');
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 650);
        } else {
            modal.classList.remove('active');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('book-details-modal');
            if (modal && modal.style.display !== 'none') {
                window.closeBookModal();
            }
        }
    });

    // ==========================================================================
    // 7. 3D Card Hover Parallax Tilt Effect Engine
    // ==========================================================================
    function init3DCardTilt() {
        const cards = document.querySelectorAll('.book-card, .tilt-card');
        
        cards.forEach(card => {
            // Create dynamic light glare element if missing
            let glare = card.querySelector('.card-glare');
            if (!glare) {
                glare = document.createElement('div');
                glare.className = 'card-glare';
                card.appendChild(glare);
            }

            let isHovering = false;

            card.addEventListener('mouseenter', () => {
                isHovering = true;
                card.style.transition = 'transform 0.1s ease-out';
            });

            card.addEventListener('mousemove', (e) => {
                if (!isHovering) return;
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                // Max tilt 12 degrees for realistic depth
                const maxTilt = 12;
                const rotateX = ((y - centerY) / centerY) * -maxTilt;
                const rotateY = ((x - centerX) / centerX) * maxTilt;

                card.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) scale3d(1.03, 1.03, 1.03)`;

                // Update glare position
                const glareX = (x / rect.width) * 100;
                const glareY = (y / rect.height) * 100;
                glare.style.background = `radial-gradient(circle at ${glareX.toFixed(1)}% ${glareY.toFixed(1)}%, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0) 70%)`;
                glare.style.opacity = '1';
            });

            card.addEventListener('mouseleave', () => {
                isHovering = false;
                card.style.transition = 'transform 0.55s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.5s ease';
                card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
                glare.style.opacity = '0';
            });
        });
    }

    init3DCardTilt();

    // 8. Parallax Floating Books Engine
    function initParallaxFloatingBooks() {
        const layer = document.getElementById('parallaxBooksLayer');
        if (!layer) return;
        const books = layer.querySelectorAll('.floating-book');
        if (!books.length) return;

        window.addEventListener('scroll', () => {
            const scrollY = window.scrollY;
            books.forEach(b => {
                const speed = parseFloat(b.dataset.speed || 0.1);
                b.style.transform = `translateY(${scrollY * speed}px) rotate(var(--rot, 0deg))`;
            });
        }, { passive: true });

        const hero = layer.closest('.landing-hero');
        if (hero) {
            hero.addEventListener('mousemove', (e) => {
                const rect = hero.getBoundingClientRect();
                const x = (e.clientX - rect.left - rect.width / 2) / (rect.width / 2);
                const y = (e.clientY - rect.top - rect.height / 2) / (rect.height / 2);
                books.forEach(b => {
                    const speed = parseFloat(b.dataset.speed || 0.1);
                    b.style.transform = `translate(${x * speed * 25}px, ${y * speed * 25}px) rotate(var(--rot, 0deg))`;
                });
            });
        }
    }
    initParallaxFloatingBooks();

    // 9. Spoiler Blur Ink Reveal
    document.addEventListener('click', (e) => {
        const spoiler = e.target.closest('.spoiler-blur');
        if (spoiler) {
            spoiler.classList.toggle('revealed');
        }
    });

    // 10. Points & Badges Confetti Particle Celebration Burst
    window.triggerPointsCelebration = function (points = 25, label = 'أثر معرفي جديد') {
        const canvas = document.createElement('canvas');
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100vw';
        canvas.style.height = '100vh';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '999998';
        document.body.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const colors = ['#C3A15D', '#FFE2A3', '#173D36', '#235738', '#D4BD83', '#FAF6ED'];
        const particles = [];
        const particleCount = 75;

        for (let i = 0; i < particleCount; i++) {
            particles.push({
                x: canvas.width / 2,
                y: canvas.height / 2,
                vx: (Math.random() - 0.5) * 16,
                vy: (Math.random() - 0.7) * 18,
                radius: Math.random() * 5 + 3,
                color: colors[Math.floor(Math.random() * colors.length)],
                alpha: 1,
                decay: Math.random() * 0.015 + 0.01,
                rotation: Math.random() * 360,
                spin: (Math.random() - 0.5) * 10
            });
        }

        let animationFrame;
        function render() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let active = false;

            particles.forEach(p => {
                if (p.alpha > 0) {
                    active = true;
                    p.x += p.vx;
                    p.y += p.vy;
                    p.vy += 0.35;
                    p.alpha -= p.decay;
                    p.rotation += p.spin;

                    ctx.save();
                    ctx.globalAlpha = Math.max(0, p.alpha);
                    ctx.translate(p.x, p.y);
                    ctx.rotate((p.rotation * Math.PI) / 180);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.radius, -p.radius, p.radius * 2, p.radius * 1.5);
                    ctx.restore();
                }
            });

            if (active) {
                animationFrame = requestAnimationFrame(render);
            } else {
                cancelAnimationFrame(animationFrame);
                canvas.remove();
            }
        }
        render();

        const popup = document.createElement('div');
        popup.className = 'celebration-counter-popup';
        popup.innerHTML = `
            <div style="font-size:36px;margin-bottom:6px;">✨</div>
            <strong style="font-size:26px;font-family:'Noto Naskh Arabic', serif;display:block;color:#FFE2A3;">+${points} أثر</strong>
            <small style="color:#D5BF8C;font-size:13px;">${label}</small>
        `;
        document.body.appendChild(popup);
        setTimeout(() => popup.classList.add('show'), 30);
        setTimeout(() => {
            popup.classList.remove('show');
            setTimeout(() => popup.remove(), 400);
        }, 2600);
    };

    // 11. 3D Join Community Badge Flip Helper
    window.flipJoinBadge = function (el) {
        const card = el ? el.closest('.join-flipper-card') : document.querySelector('.join-flipper-card');
        if (card) {
            card.classList.toggle('flipped');
        }
    };
});
