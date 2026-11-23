@extends('layouts.app')

@section('content')
<div style="max-width:960px;margin:24px auto;padding:0 16px;">
    <!-- Breadcrumb & Actions Bar (Hidden on Print) -->
    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <a href="{{ route('libraries.index', ['library_id' => $loan->copy->library_id, 'tab' => 'loans']) }}" class="btn-royal" style="padding:7px 14px;background:#EDE6D6;color:var(--ink);border:1px solid #D8CDBA;border-radius:8px;font-size:13px;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
                <span>العودة لسجل الإعارات</span>
            </a>
            <span class="muted">/</span>
            <span style="font-weight:600;color:var(--green);font-size:14px;">تقرير فحص حالة النسخة</span>
        </div>
        <div style="display:flex;gap:10px;">
            <button onclick="window.print()" class="btn-royal" style="padding:9px 18px;background:var(--green);color:#FFE2A3;border:none;border-radius:8px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 2px 8px rgba(23,61,54,0.15);">
                <i data-lucide="printer" style="width:16px;height:16px;"></i>
                <span>طباعة التقرير (Print)</span>
            </button>
        </div>
    </div>

    <!-- Official Certificate / Inspection Document -->
    <div id="printable-certificate" style="background:#FFFDF9;border:2px solid #D8CDBA;border-radius:16px;padding:36px;box-shadow:0 8px 30px rgba(23,61,54,0.06);position:relative;overflow:hidden;">
        <!-- Islamic Arch Watermark Pattern Background -->
        <div style="position:absolute;top:0;right:0;width:100%;height:8px;background:linear-gradient(90deg, #173D36, #C3A15D, #173D36);"></div>

        <!-- Document Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #EFE8DC;padding-bottom:20px;margin-bottom:28px;flex-wrap:wrap;gap:16px;">
            <div style="display:flex;align-items:center;gap:14px;">
                <img src="{{ asset('images/logo.png') }}" alt="شعار رِواق المعرفة" style="width:52px;height:52px;object-fit:contain;border-radius:10px;background:#173D36;padding:4px;" onerror="this.style.display='none'">
                <div>
                    <h1 style="margin:0 0 4px;font-size:22px;color:var(--green);font-family:'Noto Naskh Arabic',serif;">وثيقة فحص وتقرير حالة النسخة المادية</h1>
                    <div style="font-size:13px;color:#8C6D2C;font-weight:600;">رِواق المعرفة · وحدة الأمانة والإعارة التبادلية</div>
                </div>
            </div>
            <div style="text-align:left;font-size:12px;color:var(--ink);">
                <div><strong>رقم الوثيقة:</strong> <span style="font-family:monospace;font-size:14px;color:var(--green);">CERT-{{ str_pad($loan->id, 6, '0', STR_PAD_LEFT) }}</span></div>
                <div><strong>تاريخ الإصدار:</strong> {{ now()->format('Y-m-d H:i') }}</div>
                <div style="margin-top:4px;">
                    <span class="tag" style="background:rgba(195,161,93,0.15);color:#8C6D2C;font-weight:700;">
                        {{ $loan->status_label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 1. Metadata Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:28px;">
            <!-- Book & Copy Data -->
            <div style="background:#FAF7F0;border:1px solid #E8DFCC;border-radius:12px;padding:16px;">
                <div style="display:flex;align-items:center;gap:8px;color:var(--green);margin-bottom:10px;font-weight:700;font-size:14px;">
                    <i data-lucide="book" style="width:16px;height:16px;"></i>
                    <span>بيانات المصنف والنسخة</span>
                </div>
                <div style="font-size:13px;line-height:1.7;">
                    <div><strong>عنوان الكتاب:</strong> {{ $loan->copy->book->title ?? 'كتاب' }}</div>
                    <div><strong>المؤلف:</strong> {{ $loan->copy->book->author ?? 'مؤلف' }}</div>
                    <div><strong>التصنيف:</strong> {{ $loan->copy->book->category ?? 'عام' }}</div>
                    <div><strong>رمز النسخة (Barcode):</strong> <code style="background:#FFF;padding:2px 6px;border-radius:4px;border:1px solid #DDD;">{{ $loan->copy->barcode }}</code></div>
                    <div><strong>موقع الرف:</strong> {{ $loan->copy->shelf_location ?? 'الخزانة الرئيسية' }}</div>
                </div>
            </div>

            <!-- Library & Borrower Data -->
            <div style="background:#FAF7F0;border:1px solid #E8DFCC;border-radius:12px;padding:16px;">
                <div style="display:flex;align-items:center;gap:8px;color:var(--green);margin-bottom:10px;font-weight:700;font-size:14px;">
                    <i data-lucide="building" style="width:16px;height:16px;"></i>
                    <span>أطراف الإعارة</span>
                </div>
                <div style="font-size:13px;line-height:1.7;">
                    <div><strong>المكتبة الحاضنة:</strong> {{ $loan->copy->library->name }}</div>
                    <div><strong>كود المكتبة:</strong> {{ $loan->copy->library->code }}</div>
                    <div><strong>المستعير:</strong> {{ $loan->borrower_name ?? $loan->user->name }}</div>
                    <div><strong>معرف الحساب:</strong> #{{ $loan->user_id }}</div>
                    <div><strong>تاريخ الطلب:</strong> {{ $loan->requested_at ? $loan->requested_at->format('Y-m-d') : '-' }}</div>
                </div>
            </div>
        </div>

        <!-- 2. Inspection Comparison: Handover vs Return -->
        <h2 style="font-size:16px;color:var(--green);margin:0 0 14px;display:flex;align-items:center;gap:8px;">
            <i data-lucide="clipboard-check" style="width:18px;height:18px;"></i>
            <span>محضر الفحص الدوري المقارن (التسليم والاسترجاع)</span>
        </h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;">
            <!-- Handover Inspection Box -->
            <div style="background:#FFF;border:1.5px solid #C4D3CB;border-radius:12px;padding:18px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid #E5EDE8;padding-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:6px;font-weight:700;color:var(--green);font-size:14px;">
                        <i data-lucide="log-out" style="width:16px;height:16px;"></i>
                        <span>1. تقرير الفحص عند التسليم</span>
                    </div>
                    <span class="tag" style="background:rgba(35,87,56,0.12);color:var(--green);font-weight:700;">
                        {{ $loan->handover_condition_label }}
                    </span>
                </div>
                <div style="font-size:13px;line-height:1.7;color:var(--ink);">
                    <div><strong>تاريخ التسليم:</strong> {{ $loan->handover_at ? $loan->handover_at->format('Y-m-d H:i') : ($loan->borrowed_at ? $loan->borrowed_at->format('Y-m-d H:i') : 'قيد المعالجة') }}</div>
                    <div><strong>القائم بالفحص:</strong> {{ $loan->handoverInspector->name ?? ($loan->copy->library->user->name ?? 'أمين المكتبة') }}</div>
                    <div><strong>موعد الاستحقاق المعتمد:</strong> {{ $loan->due_date ? $loan->due_date->format('Y-m-d') : '-' }}</div>
                    <div style="margin-top:10px;padding:10px;background:#F9FAF9;border-radius:8px;border:1px dashed #C4D3CB;">
                        <small class="muted" style="display:block;margin-bottom:3px;font-weight:600;">ملاحظات الفحص المبدئي:</small>
                        <p style="margin:0;font-size:12px;">{{ $loan->handover_notes ?? 'النسخة خضعت للفحص قبل التسليم وتبيّن أنها بحالة جيدة وكاملة الصفحات.' }}</p>
                    </div>
                </div>
            </div>

            <!-- Return Inspection Box -->
            <div style="background:#FFF;border:1.5px solid {{ $loan->returned_at ? '#C4D3CB' : '#E8DFCC' }};border-radius:12px;padding:18px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid #E5EDE8;padding-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:6px;font-weight:700;color:{{ $loan->returned_at ? 'var(--green)' : '#8C6D2C' }};font-size:14px;">
                        <i data-lucide="log-in" style="width:16px;height:16px;"></i>
                        <span>2. تقرير الفحص عند الاسترجاع</span>
                    </div>
                    @if($loan->returned_at || $loan->status === 'returned' || $loan->status === 'closed')
                        <span class="tag" style="background:rgba(35,87,56,0.12);color:var(--green);font-weight:700;">
                            {{ $loan->return_condition_label }}
                        </span>
                    @else
                        <span class="tag" style="background:#FAF0D9;color:#8C6D2C;font-weight:700;">
                            الإعارة جارية ⏳
                        </span>
                    @endif
                </div>
                <div style="font-size:13px;line-height:1.7;color:var(--ink);">
                    <div><strong>تاريخ الاسترجاع:</strong> {{ $loan->returned_at ? $loan->returned_at->format('Y-m-d H:i') : 'لم تُسترجع بعد' }}</div>
                    <div><strong>القائم بفحص الاسترجاع:</strong> {{ $loan->returnInspector->name ?? ($loan->returned_at ? ($loan->copy->library->user->name ?? 'أمين المكتبة') : '-') }}</div>
                    <div><strong>عدد مرات التمديد:</strong> {{ $loan->renewal_count }} من أصل {{ $loan->max_renewals ?: 2 }}</div>
                    <div style="margin-top:10px;padding:10px;background:#F9FAF9;border-radius:8px;border:1px dashed #C4D3CB;">
                        <small class="muted" style="display:block;margin-bottom:3px;font-weight:600;">ملاحظات الفحص النهائي:</small>
                        <p style="margin:0;font-size:12px;">{{ $loan->return_notes ?? ($loan->returned_at ? 'تم استرجاع النسخة وفحص سلامتها.' : 'الكتاب حالياً بحوزة المستعير بانتظار استرجاعه للمكتبة.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Integrity Verdict / Assessment -->
        <div style="background:#FAF7F0;border:1px solid #E8DFCC;border-radius:12px;padding:18px;margin-bottom:28px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <i data-lucide="shield-check" style="width:20px;height:20px;color:var(--green);"></i>
                <h3 style="margin:0;font-size:15px;color:var(--green);">النتيجة وخلاصة تقرير السلامة (Condition Integrity Assessment)</h3>
            </div>
            @if($loan->returned_at || $loan->status === 'returned' || $loan->status === 'closed')
                @if($loan->isConditionDegraded())
                    <div style="padding:10px 14px;background:#FDE8E4;border:1px solid #F7B8B0;border-radius:8px;color:#B3261E;font-size:13px;">
                        <strong>تنبيه:</strong> لوحظ تراجع في حالة النسخة (من حالة <strong>«{{ $loan->handover_condition_label }}»</strong> إلى <strong>«{{ $loan->return_condition_label }}»</strong>). تم توجيه النسخة للصيانة وإشعار إدارة المكتبة.
                    </div>
                @else
                    <div style="padding:10px 14px;background:#E7F4EE;border:1px solid #B8E0CC;border-radius:8px;color:var(--green);font-size:13px;">
                        <strong>سليمة ومطابقة:</strong> تم استرجاع النسخة المادية بحالة ممتازة ومطابقة لمعايير التسليم، مع الحفاظ الكامل على سلامة الورق والتجليد.
                    </div>
                @endif
            @else
                <div style="padding:10px 14px;background:#FFF9EB;border:1px solid #EEDBB2;border-radius:8px;color:#8C6D2C;font-size:13px;">
                    <strong>قيد الإعارة:</strong> الكتاب بحوزة المستعير حالياً. سيتم تحديث خلاصة السلامة فور تسليم النسخة وفحصها من قبل أمين المكتبة.
                </div>
            @endif
        </div>

        <!-- 4. Signatures & Verification Seal -->
        <div style="display:flex;justify-content:space-between;align-items:flex-end;border-top:1px dashed #D8CDBA;padding-top:20px;flex-wrap:wrap;gap:20px;">
            <div style="text-align:center;min-width:180px;">
                <small class="muted" style="display:block;margin-bottom:30px;">توقيع واعتماد أمين المكتبة</small>
                <div style="font-weight:700;color:var(--green);border-top:1px solid #D8CDBA;padding-top:6px;font-size:13px;">
                    {{ $loan->handoverInspector->name ?? ($loan->copy->library->user->name ?? 'إدارة المكتبة') }}
                </div>
            </div>

            <div style="text-align:center;min-width:180px;">
                <small class="muted" style="display:block;margin-bottom:30px;">إقرار وتوقيع المستعير بالاستلام</small>
                <div style="font-weight:700;color:var(--ink);border-top:1px solid #D8CDBA;padding-top:6px;font-size:13px;">
                    {{ $loan->borrower_name ?? $loan->user->name }}
                </div>
            </div>

            <div style="text-align:left;">
                <div style="display:inline-block;padding:8px 14px;background:#FAF7F0;border:1px solid #E8DFCC;border-radius:8px;text-align:center;">
                    <div style="font-size:10px;color:#8C6D2C;font-weight:700;">رمز التحقق الرقمي</div>
                    <div style="font-family:monospace;font-size:13px;font-weight:700;color:var(--green);">{{ $loan->qr_token ?? 'RWQ-DOC-' . $loan->id }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printable-certificate, #printable-certificate * {
        visibility: visible;
    }
    #printable-certificate {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        margin: 0 !important;
        padding: 20px !important;
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    .no-print, header, nav, footer {
        display: none !important;
    }
}
</style>
@endsection
