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
        // 1. Book of the Month History (أرشيف كتب الأشهر السابقة)
        if (! Schema::hasTable('community_books_history')) {
            Schema::create('community_books_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->string('month_name'); // e.g. أغسطس 2026
                $table->integer('read_year')->default(2026);
                $table->integer('read_month')->default(8);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Book Nominations for Next Month (ترشيحات كتب الشهر القادم)
        if (! Schema::hasTable('book_nominations')) {
            Schema::create('book_nominations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->string('author');
                $table->text('reason'); // لماذا ترشح هذا الكتاب؟
                $table->integer('endorsements_count')->default(0);
                $table->string('status', 30)->default('pending'); // pending, approved, added_to_poll, selected, rejected
                $table->timestamps();

                $table->index(['community_id', 'status']);
            });
        }

        // 3. Nomination Endorsements (تأييد الترشيح مع منع التكرار)
        if (! Schema::hasTable('book_nomination_endorsements')) {
            Schema::create('book_nomination_endorsements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nomination_id')->constrained('book_nominations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['nomination_id', 'user_id']);
            });
        }

        // 4. Update Book Polls table: winner tracking & month
        Schema::table('book_polls', function (Blueprint $table) {
            if (! Schema::hasColumn('book_polls', 'winner_book_id')) {
                $table->foreignId('winner_book_id')->nullable()->after('is_active')->constrained('books')->nullOnDelete();
            }
            if (! Schema::hasColumn('book_polls', 'month_name')) {
                $table->string('month_name')->nullable()->after('winner_book_id'); // e.g. أكتوبر 2026
            }
        });

        // 5. Community-specific Badges (أوسمة المجتمع الخاصة)
        if (! Schema::hasTable('community_badges')) {
            Schema::create('community_badges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description');
                $table->string('icon')->default('award');
                $table->string('color', 20)->default('#C3A15D');
                $table->timestamps();
            });
        }

        // 6. Community Badges Awarded to Users (تقليد الأعضاء بأوسمة المجلس)
        if (! Schema::hasTable('community_badge_user')) {
            Schema::create('community_badge_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_badge_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason')->nullable();
                $table->timestamp('awarded_at')->useCurrent();
                $table->timestamps();

                $table->unique(['community_badge_id', 'user_id']);
            });
        }

        // 7. Gamification Points Transactions Ledger (سجل حركات ونقاط الأثر)
        if (! Schema::hasTable('point_transactions')) {
            Schema::create('point_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->integer('points');
                $table->string('activity_type', 40); // reading_session, book_finished, review, discussion, poll_vote, meeting_checkin, challenge_complete, badge_earned, bonus
                $table->string('description');
                $table->timestamps();

                $table->index(['user_id', 'activity_type']);
            });
        }

        // 8. Update Badges Table with Category & Level Tier
        Schema::table('badges', function (Blueprint $table) {
            if (! Schema::hasColumn('badges', 'category')) {
                $table->string('category', 30)->default('general')->after('icon'); // general, streak, discussion, challenge, reading
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            if (Schema::hasColumn('badges', 'category')) {
                $table->dropColumn('category');
            }
        });
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('community_badge_user');
        Schema::dropIfExists('community_badges');
        Schema::table('book_polls', function (Blueprint $table) {
            if (Schema::hasColumn('book_polls', 'winner_book_id')) {
                $table->dropConstrainedForeignId('winner_book_id');
            }
            if (Schema::hasColumn('book_polls', 'month_name')) {
                $table->dropColumn('month_name');
            }
        });
        Schema::dropIfExists('book_nomination_endorsements');
        Schema::dropIfExists('book_nominations');
        Schema::dropIfExists('community_books_history');
    }
};
