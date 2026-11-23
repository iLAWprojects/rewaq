<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('pages.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // حسابات الديمو تُنشأ تلقائيًا في البيئة المحلية والاختبار فقط، وأبدًا في الإنتاج
        if ($this->demoAccessAllowed() && ! User::where('email', $credentials['email'])->exists()) {
            if ($credentials['email'] === 'demo@riwaq.test' && $credentials['password'] === 'password') {
                User::create([
                    'name' => 'القارئ التجريبي',
                    'email' => 'demo@riwaq.test',
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'avatar' => 'ق',
                    'bio' => 'شغوف بالأدب العربي، والتاريخ، وتدوين الأفكار الملهمة.',
                    'annual_goal' => 24,
                    'books_finished' => 8,
                    'points' => 340,
                    'level' => 3,
                ]);
            } elseif ($credentials['email'] === 'admin@riwaq.test' && $credentials['password'] === 'password') {
                User::create([
                    'name' => 'المشرف العام',
                    'email' => 'admin@riwaq.test',
                    'password' => Hash::make('password'),
                    'role' => 'super_admin',
                    'avatar' => 'م',
                    'bio' => 'إدارة منصة رِواق المعرفة والإشراف على المحتوى ونوادي القراءة.',
                    'annual_goal' => 30,
                    'books_finished' => 12,
                    'points' => 950,
                    'level' => 5,
                ]);
            }
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            if ($user->is_suspended) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'تم تعليق هذا الحساب: '.($user->suspension_reason ?: 'يرجى مراجعة إدارة الرواق.'),
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('libraries.index'))->with('success', 'أهلاً بك مجدداً في رِواق المعرفة.');
        }

        return back()->withErrors([
            'email' => 'البيانات المدخلة غير صحيحة.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        return view('pages.auth.register');
    }

    public function register(Request $request)
    {
        if (! SystemSetting::get('allow_registrations', true)) {
            return back()->with('error', 'تم تعليق استقبال عضويات جديدة مؤقتاً بقرار من إدارة المنصة.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'annual_goal' => 'nullable|integer|min:1|max:365',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'avatar' => mb_substr($validated['name'], 0, 1),
            'annual_goal' => $validated['annual_goal'] ?? 24,
            'role' => 'user',
        ]);

        // Create default personal library
        $user->libraries()->create([
            'name' => 'المكتبة الشخصية',
            'description' => 'مساحتك الخاصة لحفظ كتبك ونسخك',
            'is_personal' => true,
        ]);

        Auth::login($user);

        return redirect()->route('libraries.index')->with('success', 'مرحباً بك! تم إنشاء حسابك ومكتبتك الشخصية بنجاح.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing')->with('info', 'تم تسجيل خروجك بأمان.');
    }

    /**
     * الدخول السريع وحسابات الديمو للتطوير فقط: لا تعمل في الإنتاج مهما كان الرابط.
     */
    private function demoAccessAllowed(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    // Quick login / switch between Demo User and Admin for review purposes (local/testing only)
    public function switchUser(string $role)
    {
        abort_unless($this->demoAccessAllowed(), 404);

        if ($role === 'admin') {
            $user = User::where('email', 'admin@riwaq.test')->first()
                ?? User::whereIn('role', ['super_admin', 'admin'])->first();

            if (! $user) {
                $user = User::create([
                    'name' => 'المشرف العام',
                    'email' => 'admin@riwaq.test',
                    'password' => Hash::make('password'),
                    'role' => 'super_admin',
                    'avatar' => 'م',
                    'bio' => 'إدارة منصة رِواق المعرفة والإشراف على المحتوى ونوادي القراءة.',
                    'annual_goal' => 30,
                    'books_finished' => 12,
                    'points' => 950,
                    'level' => 5,
                ]);
            }
        } else {
            $user = User::where('email', 'demo@riwaq.test')->first()
                ?? User::where('role', 'user')->first();

            if (! $user) {
                $user = User::create([
                    'name' => 'القارئ التجريبي',
                    'email' => 'demo@riwaq.test',
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'avatar' => 'ق',
                    'bio' => 'شغوف بالأدب العربي، والتاريخ، وتدوين الأفكار الملهمة.',
                    'annual_goal' => 24,
                    'books_finished' => 8,
                    'points' => 340,
                    'level' => 3,
                    'quiet_hours_enabled' => true,
                    'quiet_hours_start' => '22:00',
                    'quiet_hours_end' => '08:00',
                ]);
            }
        }

        if ($user) {
            Auth::login($user, true);
            request()->session()->regenerate();

            return redirect()->intended(route('libraries.index'))->with('success', "تم تسجيل الدخول بحساب: {$user->name}");
        }

        return redirect()->route('login')->with('error', 'المستخدم غير متوفر.');
    }
}
