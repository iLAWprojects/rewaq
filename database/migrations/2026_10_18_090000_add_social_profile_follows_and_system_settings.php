<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. User Follows / Friends System (متابعة الأصدقاء ورفقاء القراءة)
        if (! Schema::hasTable('user_follows')) {
            Schema::create('user_follows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['follower_id', 'following_id']);
                $table->index('following_id');
            });
        }

        // 2. Platform System Settings (الإعدادات العامة للمنصة)
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->string('type', 30)->default('string'); // string, boolean, integer, json
                $table->string('group', 40)->default('general'); // general, lending, gamification, maintenance
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        }

        // 3. User Suspension / Ban status for Super Admin management
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_suspended')) {
                $table->boolean('is_suspended')->default(false)->after('role');
            }
            if (! Schema::hasColumn('users', 'suspension_reason')) {
                $table->string('suspension_reason')->nullable()->after('is_suspended');
            }
        });

        // 4. Social Activities Feed (خلاصة النشاط المعرفي والاجتماعي)
        if (! Schema::hasTable('social_activities')) {
            Schema::create('social_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('activity_type', 50); // reading_session, book_finished, review_posted, reflection_posted, badge_earned, community_joined, meeting_attended
                $table->string('title');
                $table->text('content')->nullable();
                $table->string('link')->nullable();
                $table->integer('cheers_count')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_activities');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'suspension_reason')) {
                $table->dropColumn('suspension_reason');
            }
            if (Schema::hasColumn('users', 'is_suspended')) {
                $table->dropColumn('is_suspended');
            }
        });
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('user_follows');
    }
};
