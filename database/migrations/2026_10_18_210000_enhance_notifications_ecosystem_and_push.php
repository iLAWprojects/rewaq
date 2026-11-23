<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance user_notifications table
        Schema::table('user_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('user_notifications', 'category')) {
                $table->string('category')->default('system')->after('type'); // communities, challenges, meetings, loans, comments, badges, friendships, system
            }
            if (! Schema::hasColumn('user_notifications', 'priority')) {
                $table->string('priority')->default('normal')->after('category'); // low, normal, high, urgent
            }
            if (! Schema::hasColumn('user_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }
            if (! Schema::hasColumn('user_notifications', 'metadata')) {
                $table->json('metadata')->nullable()->after('read_at');
            }
        });

        // 2. Enhance users table with timezone and notification preferences
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->default('Asia/Riyadh')->after('level');
            }
            if (! Schema::hasColumn('users', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('users', 'push_opt_in')) {
                $table->boolean('push_opt_in')->default(false)->after('notification_preferences');
            }
        });

        // 3. User Push / FCM Subscriptions Table with explicit opt-in
        if (! Schema::hasTable('user_push_subscriptions')) {
            Schema::create('user_push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->text('endpoint');
                $table->string('p256dh')->nullable();
                $table->string('auth_token')->nullable();
                $table->string('device_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
            });
        }

        // 4. Notification Delivery Log (privacy-preserving: logs channel & outcome without sensitive payload)
        if (! Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('notification_id')->nullable()->constrained('user_notifications')->onDelete('set null');
                $table->string('channel', 50); // in_app, web_push, fcm, email
                $table->string('category', 50)->default('system');
                $table->string('priority', 20)->default('normal');
                $table->string('status', 50); // delivered, suppressed_quiet_hours, suppressed_preference, skipped_low_priority, failed
                $table->string('response_code', 50)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('notification_id');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('user_push_subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'notification_preferences', 'push_opt_in']);
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropColumn(['category', 'priority', 'read_at', 'metadata']);
        });
    }
};
