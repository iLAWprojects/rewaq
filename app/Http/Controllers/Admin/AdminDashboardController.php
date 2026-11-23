<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Community;
use App\Models\ContentReport;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\Loan;
use App\Models\ReadingSession;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AdminDashboardController extends Controller
{
    /**
     * العرض الرئيسي للوحة المشرف العام (Super Admin)
     */
    public function index(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->canManagePlatform()) {
            abort(403, 'غير مصرح بالدخول: هذه اللوحة مخصصة للإدارة والمشرفين.');
        }

        $tab = $request->input('tab', 'overview');

        // Sensitive tabs restricted to Super Admin
        if (in_array($tab, ['settings', 'audit_logs']) && ! $admin->isSuperAdmin()) {
            abort(403, 'هذا القسم مخصص للمشرف العام (Super Admin) فقط.');
        }

        // إحصائيات النظام الشاملة
        $stats = [
            'users_count' => User::count(),
            'suspended_users_count' => User::where('is_suspended', true)->count(),
            'books_count' => Book::count(),
            'copies_count' => BookCopy::count(),
            'active_loans_count' => Loan::where('status', 'active')->count(),
            'pending_loans_count' => Loan::where('status', 'pending')->count(),
            'overdue_loans_count' => Loan::where('status', 'active')->where('due_date', '<', now())->count(),
            'communities_count' => Community::count(),
            'discussions_count' => Discussion::count(),
            'sessions_count' => ReadingSession::count(),
            'total_minutes' => ReadingSession::sum('minutes'),
            'pending_reports_count' => ContentReport::where('status', 'pending')->count(),
        ];

        // إدارة المستخدمين مع إمكانية البحث والتصفية
        $userQuery = User::query()->latest();
        if ($search = $request->input('user_search')) {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($roleFilter = $request->input('role_filter')) {
            $userQuery->where('role', $roleFilter);
        }
        $users = $userQuery->take(50)->get();

        // أحدث الإعارات
        $recentLoans = Loan::with('copy.book', 'user')->latest()->take(10)->get();

        // المحتوى والمناقشات
        $discussions = Discussion::with(['user', 'community', 'book'])->withCount('comments')->latest()->take(20)->get();
        $recentComments = DiscussionComment::with(['user', 'discussion'])->latest()->take(20)->get();

        // البلاغات والرقابة المركزية
        $reports = ContentReport::with(['user', 'moderator'])->latest()->take(30)->get();

        // فهرس الكتب
        $books = Book::withCount('copies')->latest()->take(20)->get();

        // سجل التدقيق والرقابة (Audit Log) للمشرف العام
        $auditLogs = $admin->isSuperAdmin()
            ? AuditLog::with('user')->latest()->take(60)->get()
            : collect();

        // الإعدادات العامة للمنصة
        $this->ensureDefaultSettingsExist();
        $settings = SystemSetting::all()->keyBy('key');

        return view('pages.admin.dashboard', compact(
            'tab',
            'stats',
            'users',
            'recentLoans',
            'discussions',
            'recentComments',
            'reports',
            'books',
            'settings',
            'auditLogs'
        ));
    }

    /**
     * تحديث دور المستخدم وصلاحياته مع الحماية الصارمة للمدير الأخير والتأكيد الأمني
     */
    public function updateUserRole(Request $request, User $user)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'role' => 'required|in:super_admin,admin,user',
            'confirmed' => 'required|accepted',
        ], [
            'confirmed.required' => 'يتطلب تعديل الدور المنصي تأكيداً صريحاً لحساسية الإجراء الأمني.',
            'confirmed.accepted' => 'يتطلب تعديل الدور المنصي تأكيداً صريحاً لحساسية الإجراء الأمني.',
        ]);

        // 1. لا يمكن للمدير إلغاء صلاحية نفسه أو أي مدير إذا كان آخر Super Admin نشط
        if ($user->role === 'super_admin' && $validated['role'] !== 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->where('is_suspended', false)->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'إجراء أمني محظور: لا يمكن تجريد أو إلغاء صلاحية آخر مدير عام (Super Admin) في المنصة للحفاظ على استمرارية الإدارة واستقرار النظام.');
            }
        }

        // 2. لا يمكن للمدير إلغاء صلاحية نفسه إذا كان آخر مدير عام
        if ($user->id === Auth::id() && $user->role === 'super_admin' && $validated['role'] !== 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->where('is_suspended', false)->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'إجراء أمني محظور: لا يمكنك إلغاء صلاحية نفسك بصفتك آخر مدير عام (Super Admin) في المنصة.');
            }
        }

        $oldRole = $user->role;
        $user->update(['role' => $validated['role']]);

        AuditLog::record(
            Auth::user(),
            'change_platform_role',
            $user,
            "تم تعديل الدور المنصي للمستخدم «{$user->name}» من ({$oldRole}) إلى ({$validated['role']}) بعد التأكيد الصريح.",
            ['role' => $oldRole],
            ['role' => $validated['role'], 'security_confirmation' => true]
        );

        return back()->with('success', "تم تحديث دور المستخدم «{$user->name}» إلى «{$validated['role']}» بنجاح ✦");
    }

    /**
     * تعليق أو فك تعليق حساب المستخدم مع التأكيد الصريح وحماية المدير الأخير
     */
    public function toggleSuspension(Request $request, User $user)
    {
        $admin = Auth::user();
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'confirmed' => 'required|accepted',
            'suspension_reason' => 'nullable|string|max:500',
        ], [
            'confirmed.required' => 'يتطلب تعليق أو فك تعليق الحساب تأكيداً صريحاً لحماية حقوق المستخدمين.',
            'confirmed.accepted' => 'يتطلب تعليق أو فك تعليق الحساب تأكيداً صريحاً لحماية حقوق المستخدمين.',
        ]);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'إجراء أمني محظور: لا يمكنك تعليق حسابك الخاص.');
        }

        // منع تعليق آخر مدير عام نشط
        if ($user->role === 'super_admin' && ! $user->is_suspended) {
            $activeSuperAdmins = User::where('role', 'super_admin')->where('is_suspended', false)->count();
            if ($activeSuperAdmins <= 1) {
                return back()->with('error', 'إجراء أمني محظور: لا يمكن تعليق آخر مدير عام (Super Admin) نشط في المنصة.');
            }
        }

        if ($user->is_suspended) {
            $user->update([
                'is_suspended' => false,
                'suspension_reason' => null,
            ]);

            AuditLog::record(
                $admin,
                'unsuspend_user',
                $user,
                "تم إلغاء تعليق حساب القارئ «{$user->name}» واستعادة صلاحياته بعد التأكيد الصريح.",
                ['is_suspended' => true],
                ['is_suspended' => false, 'security_confirmation' => true]
            );

            return back()->with('success', "تم إلغاء تعليق حساب القارئ «{$user->name}» بنجاح واستعادة صلاحياته.");
        } else {
            $reason = $validated['suspension_reason'] ?? 'مخالفة معايير رِواق المعرفة والسياسات المجتمعية.';
            $user->update([
                'is_suspended' => true,
                'suspension_reason' => $reason,
            ]);

            AuditLog::record(
                $admin,
                'suspend_user',
                $user,
                "تم تعليق حساب القارئ «{$user->name}» بعد التأكيد الصريح. السبب: {$reason}",
                ['is_suspended' => false],
                ['is_suspended' => true, 'suspension_reason' => $reason, 'security_confirmation' => true]
            );

            return back()->with('info', "تم تعليق حساب القارئ «{$user->name}» وتجميد أنشطته.");
        }
    }

    /**
     * حذف أو تعطيل حساب المستخدم بنظام الحذف المرن (Soft Delete) مع حماية المدير الأخير
     */
    public function destroyUser(User $user)
    {
        $admin = Auth::user();
        Gate::authorize('delete', $user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'إجراء أمني محظور: لا يمكنك حذف حسابك الخاص من هذه اللوحة.');
        }

        if ($user->role === 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'إجراء أمني محظور: لا يمكن حذف أو أرشفة آخر مدير عام (Super Admin) في المنصة.');
            }
        }

        $name = $user->name;
        $user->delete(); // Soft Delete

        AuditLog::record(
            $admin,
            'delete_user',
            $user,
            "تمت أرشفة وتعطيل حساب المستخدم «{$name}» بأمان وفق سياسة الحذف المرن (Soft Delete)."
        );

        return back()->with('success', "تم حذف وتعطيل حساب المستخدم «{$name}» بأمان (Soft Delete).");
    }

    /**
     * حذف وأرشفة محتوى مخالف باستخدام الحذف المرن (Soft Delete)
     */
    public function deleteContent(Request $request, string $type, int $id)
    {
        $admin = Auth::user();
        $this->authorizeSuperAdmin();

        if ($type === 'discussion') {
            $item = Discussion::findOrFail($id);
            $title = $item->title ?? ('مناقشة #'.$item->id);
            $item->comments()->delete(); // Soft deletes related comments
            $item->delete(); // Soft deletes discussion

            AuditLog::record(
                $admin,
                'delete_discussion',
                $item,
                "تمت أرشفة وحذف المناقشة «{$title}» وكافة الردود المرتبطة بها (Soft Delete)."
            );

            return back()->with('success', "تم حذف وأرشفة المناقشة «{$title}» بنجاح (Soft Delete).");
        } elseif ($type === 'comment') {
            $item = DiscussionComment::findOrFail($id);
            $item->delete(); // Soft Delete

            AuditLog::record(
                $admin,
                'delete_comment',
                $item,
                "تم حذف وأرشفة تعليق مخالف للكاتب «{$item->user->name}» (Soft Delete)."
            );

            return back()->with('success', 'تم حذف وأرشفة التعليق المخالف بنجاح (Soft Delete).');
        } elseif ($type === 'book') {
            $item = Book::findOrFail($id);
            $title = $item->title;
            $item->delete(); // Soft Delete

            AuditLog::record(
                $admin,
                'delete_book',
                $item,
                "تمت أرشفة وحذف الكتاب «{$title}» بأمان (Soft Delete)."
            );

            return back()->with('success', "تم حذف وأرشفة الكتاب «{$title}» بنجاح (Soft Delete).");
        }

        return back()->with('error', 'نوع المحتوى المطلوب غير صالح.');
    }

    /**
     * معالجة البلاغ المركزي (قبول البلاغ وحذف المحتوى أو رفضه)
     */
    public function handleReportAction(Request $request, ContentReport $report)
    {
        $admin = Auth::user();
        $this->authorizeSuperAdmin();

        $action = $request->input('action'); // 'resolve_and_delete', 'suspend_author', 'dismiss'
        $notes = $request->input('moderator_notes', '');

        if ($action === 'resolve_and_delete') {
            $target = $report->target();
            if ($target && $report->reportable_type !== 'user') {
                $target->delete();
            }
            $report->update([
                'status' => 'action_taken',
                'moderator_notes' => $notes ?: 'تم حذف المحتوى المخالف بواسطة المشرف العام.',
                'moderated_by' => Auth::id(),
            ]);

            AuditLog::record(
                $admin,
                'resolve_report_delete',
                $report,
                "تم اعتماد البلاغ #{$report->id} وحذف المحتوى المخالف."
            );

            $this->notifyReporter($report, 'تم اعتماد بلاغك واتخاذ الإجراء اللازم بحق المحتوى المخالف. شكرًا لمساهمتك.');

            return back()->with('success', 'تم اعتماد البلاغ وحذف المحتوى المخالف.');
        } elseif ($action === 'suspend_author') {
            $target = $report->target();
            $authorId = $report->reportable_type === 'user' ? ($target->id ?? null) : ($target->user_id ?? null);
            if ($target && $authorId) {
                $author = User::find($authorId);
                if ($author && $author->id !== Auth::id()) {
                    $author->update([
                        'is_suspended' => true,
                        'suspension_reason' => 'تعليق إثر بلاغات متكررة عن محتوى مسيء.',
                    ]);

                    AuditLog::record(
                        $admin,
                        'suspend_user_via_report',
                        $author,
                        "تم تعليق حساب كاتب المحتوى «{$author->name}» إثر البلاغ #{$report->id}."
                    );
                }
            }
            $report->update([
                'status' => 'action_taken',
                'moderator_notes' => $notes ?: 'تم تعليق حساب كاتب المحتوى المخالف.',
                'moderated_by' => Auth::id(),
            ]);

            $this->notifyReporter($report, 'تم اعتماد بلاغك واتخاذ إجراء بحق صاحب المحتوى. شكرًا لمساهمتك.');

            return back()->with('success', 'تم تعليق حساب كاتب المحتوى واعتماد الإجراء.');
        } else {
            // Dismiss
            $report->update([
                'status' => 'dismissed',
                'moderator_notes' => $notes ?: 'تمت المراجعة وتبين عدم وجود مخالفة تستوجب الإجراء.',
                'moderated_by' => Auth::id(),
            ]);

            AuditLog::record(
                $admin,
                'dismiss_report',
                $report,
                "تم حفظ البلاغ #{$report->id} كغير مخالف."
            );

            $this->notifyReporter($report, 'راجعت الإدارة بلاغك ولم تجد مخالفة تستوجب الإجراء. شكرًا لحرصك.');

            return back()->with('info', 'تم حفظ البلاغ وتصنيفه كغير مخالف.');
        }
    }

    private function notifyReporter(ContentReport $report, string $body): void
    {
        UserNotification::create([
            'user_id' => $report->user_id,
            'title' => 'نتيجة مراجعة بلاغك',
            'body' => $body,
            'icon' => 'flag',
            'link' => route('notifications.index'),
            'type' => 'moderation',
        ]);
    }

    /**
     * حفظ وتحديث الإعدادات العامة للمنصة
     */
    public function updateSettings(Request $request)
    {
        $admin = Auth::user();
        $this->authorizeSuperAdmin();

        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            $existing = SystemSetting::where('key', $key)->first();
            $type = $existing ? $existing->type : (is_numeric($value) ? 'integer' : 'string');
            $group = $existing ? $existing->group : 'general';
            $description = $existing ? $existing->description : null;

            SystemSetting::set($key, $value, $type, $group, $description);
        }

        // معالجة الخيارات المنطقية (checkboxes) التي قد لا ترسل عند عدم تحديدها
        if (! $request->has('settings.enable_book_lending')) {
            SystemSetting::set('enable_book_lending', '0', 'boolean', 'lending', 'تفعيل نظام إعارة واستعارة الكتب الورقية في المنصة');
        }
        if (! $request->has('settings.allow_registrations')) {
            SystemSetting::set('allow_registrations', '0', 'boolean', 'general', 'السماح بتسجيل مستخدمين جدد');
        }
        if (! $request->has('settings.maintenance_mode')) {
            SystemSetting::set('maintenance_mode', '0', 'boolean', 'general', 'تفعيل وضع الصيانة العام');
        }
        if (! $request->has('settings.admin_mfa_enforced')) {
            SystemSetting::set('admin_mfa_enforced', '0', 'boolean', 'security', 'إلزامية التحقق الثنائي (MFA) للمشرفين');
        }

        AuditLog::record(
            $admin,
            'update_system_settings',
            null,
            'تم تحديث الإعدادات العامة للمنصة وإعدادات الإعارة والنقاط.',
            null,
            $settings
        );

        return back()->with('success', 'تم حفظ وتحديث الإعدادات العامة للمنصة بنجاح ✦');
    }

    /**
     * التأكد من وجود الإعدادات الافتراضية
     */
    protected function ensureDefaultSettingsExist(): void
    {
        $defaults = [
            'site_name' => ['value' => 'رِواق المعرفة', 'type' => 'string', 'group' => 'general', 'desc' => 'اسم المنصة الرسمي'],
            'site_tagline' => ['value' => 'منصة القراءة المجتمعية وتبادل الكتب الورقية', 'type' => 'string', 'group' => 'general', 'desc' => 'الشعار اللفظي والوصف المختصر'],
            'enable_book_lending' => ['value' => '1', 'type' => 'boolean', 'group' => 'lending', 'desc' => 'تفعيل نظام إعارة واستعارة الكتب الورقية في المنصة'],
            'max_active_loans' => ['value' => '3', 'type' => 'integer', 'group' => 'lending', 'desc' => 'الحد الأقصى للإعارات المتزامنة لكل قارئ'],
            'loan_duration_days' => ['value' => '14', 'type' => 'integer', 'group' => 'lending', 'desc' => 'المدة الافتراضية للإعارة بالأيام'],
            'points_per_session' => ['value' => '10', 'type' => 'integer', 'group' => 'gamification', 'desc' => 'نقاط الأثر الممنوحة لكل جلسة قراءة موثقة'],
            'points_per_finished_book' => ['value' => '50', 'type' => 'integer', 'group' => 'gamification', 'desc' => 'نقاط الأثر الممنوحة عند إتمام قراءة كتاب كامل'],
            'allow_registrations' => ['value' => '1', 'type' => 'boolean', 'group' => 'general', 'desc' => 'السماح للأعضاء الجدد بإنشاء حسابات'],
            'maintenance_mode' => ['value' => '0', 'type' => 'boolean', 'group' => 'general', 'desc' => 'تفعيل وضع الصيانة العام'],
            'admin_mfa_enforced' => ['value' => '0', 'type' => 'boolean', 'group' => 'security', 'desc' => 'إلزامية التحقق الثنائي (MFA) للمشرفين'],
            'admin_session_timeout_minutes' => ['value' => '15', 'type' => 'integer', 'group' => 'security', 'desc' => 'مهلة انتهاء جلسة الإدارة بالدقائق عند عدم النشاط'],
        ];

        foreach ($defaults as $k => $data) {
            if (! SystemSetting::where('key', $k)->exists()) {
                SystemSetting::create([
                    'key' => $k,
                    'value' => $data['value'],
                    'type' => $data['type'],
                    'group' => $data['group'],
                    'description' => $data['desc'],
                ]);
            }
        }
    }

    /**
     * التحقق الصارم من صلاحية المشرف العام
     */
    protected function authorizeSuperAdmin(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'هذا الإجراء مخصص للمشرف العام فقط.');
        }
    }
}
