<?php

namespace App\Providers;

use App\Models\Community;
use App\Models\Library;
use App\Models\Meeting;
use App\Models\SystemSetting;
use App\Models\User;
use App\Policies\CommunityPolicy;
use App\Policies\LibraryPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Library::class, LibraryPolicy::class);
        Gate::policy(Community::class, CommunityPolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // مشاركة حالة تفعيل نظام إعارة الكتب عالمياً مع كافة قوالب Blade
        View::composer('*', function ($view) {
            try {
                $view->with('bookLendingEnabled', SystemSetting::isLendingEnabled());
            } catch (\Throwable $e) {
                $view->with('bookLendingEnabled', true);
            }
        });
    }
}
