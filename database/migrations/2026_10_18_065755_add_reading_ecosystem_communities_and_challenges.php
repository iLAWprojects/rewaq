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
        // 1. Reading Reviews & Ratings (المراجعات والتقييمات)
        if (! Schema::hasTable('book_reviews')) {
            Schema::create('book_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->integer('rating')->default(5); // 1-5 stars
                $table->string('title')->nullable();
                $table->text('content');
                $table->boolean('is_spoiler')->default(false);
                $table->integer('likes_count')->default(0);
                $table->timestamps();
            });
        }

        // 2. Reading Series & Themed Tracks (سلاسل الكتب والمسارات المعرفية)
        if (! Schema::hasTable('reading_series')) {
            Schema::create('reading_series', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('category')->default('أدب');
                $table->string('badge_name')->nullable();
                $table->string('cover_color')->default('#173D36');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reading_series_books')) {
            Schema::create('reading_series_books', function (Blueprint $table) {
                $table->id();
                $table->foreignId('series_id')->constrained('reading_series')->cascadeOnDelete();
                $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
                $table->integer('order_num')->default(1);
                $table->timestamps();
            });
        }

        // 3. User Reading Streaks (سلاسل الأيام المتتالية)
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'reading_streak')) {
                $table->integer('reading_streak')->default(3)->after('points');
            }
            if (! Schema::hasColumn('users', 'last_read_date')) {
                $table->date('last_read_date')->nullable()->after('reading_streak');
            }
        });

        // 4. Communities: Types, Announcements, and Join Requests
        Schema::table('communities', function (Blueprint $table) {
            if (! Schema::hasColumn('communities', 'type')) {
                $table->string('type', 30)->default('public')->after('category'); // public, private, specialized, book_of_month
            }
            if (! Schema::hasColumn('communities', 'announcement')) {
                $table->text('announcement')->nullable()->after('description');
            }
            if (! Schema::hasColumn('communities', 'rules')) {
                $table->text('rules')->nullable()->after('announcement');
            }
        });

        if (! Schema::hasTable('community_join_requests')) {
            Schema::create('community_join_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20)->default('pending'); // pending, approved, rejected
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['community_id', 'status']);
            });
        }

        if (! Schema::hasTable('community_announcements')) {
            Schema::create('community_announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('content');
                $table->boolean('is_pinned')->default(true);
                $table->timestamps();
            });
        }

        // 5. Challenges: Types, Rewards, Certificates, and Archive
        Schema::table('challenges', function (Blueprint $table) {
            if (! Schema::hasColumn('challenges', 'type')) {
                $table->string('type', 30)->default('individual')->after('category'); // individual, team, speed
            }
            if (! Schema::hasColumn('challenges', 'reward_badge')) {
                $table->string('reward_badge', 100)->nullable()->default('وسام القراءة المعتمد')->after('type');
            }
            if (! Schema::hasColumn('challenges', 'reward_points')) {
                $table->integer('reward_points')->default(150)->after('reward_badge');
            }
            if (! Schema::hasColumn('challenges', 'certificate_enabled')) {
                $table->boolean('certificate_enabled')->default(true)->after('reward_points');
            }
            if (! Schema::hasColumn('challenges', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('challenges', 'winner_team')) {
                $table->string('winner_team', 100)->nullable()->after('team_b_pages');
            }
        });

        Schema::table('challenge_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('challenge_participants', 'team')) {
                $table->string('team', 30)->nullable()->after('progress_count'); // team_a, team_b
            }
            if (! Schema::hasColumn('challenge_participants', 'certificate_code')) {
                $table->string('certificate_code', 64)->nullable()->after('completed');
            }
            if (! Schema::hasColumn('challenge_participants', 'rank')) {
                $table->integer('rank')->nullable()->after('certificate_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenge_participants', function (Blueprint $table) {
            $table->dropColumn(['team', 'certificate_code', 'rank']);
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['type', 'reward_badge', 'reward_points', 'certificate_enabled', 'is_archived', 'winner_team']);
        });

        Schema::dropIfExists('community_announcements');
        Schema::dropIfExists('community_join_requests');

        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['type', 'announcement', 'rules']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reading_streak', 'last_read_date']);
        });

        Schema::dropIfExists('reading_series_books');
        Schema::dropIfExists('reading_series');
        Schema::dropIfExists('book_reviews');
    }
};
