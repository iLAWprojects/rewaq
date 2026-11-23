<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Friendships Table (Pending -> Accepted / Rejected, Unfriend)
        if (! Schema::hasTable('friendships')) {
            Schema::create('friendships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->timestamps();

                $table->unique(['sender_id', 'recipient_id']);
                $table->index(['recipient_id', 'status']);
                $table->index(['sender_id', 'status']);
            });
        }

        // 2. User Blocks Table (Block / Unblock)
        if (! Schema::hasTable('user_blocks')) {
            Schema::create('user_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->unique(['blocker_id', 'blocked_id']);
            });
        }

        // 3. User Privacy Preferences for Social Activities
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'hidden_activity_types')) {
                $table->json('hidden_activity_types')->nullable()->after('bio');
            }
        });

        // 4. Point Transactions Enhancements (Status, is_valid, references, anti-gaming metadata)
        Schema::table('point_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('point_transactions', 'is_valid')) {
                $table->boolean('is_valid')->default(true)->after('points');
            }
            if (! Schema::hasColumn('point_transactions', 'status')) {
                $table->string('status')->default('verified')->after('is_valid'); // verified, flagged_for_review, rejected
            }
            if (! Schema::hasColumn('point_transactions', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('description');
            }
            if (! Schema::hasColumn('point_transactions', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }
            if (! Schema::hasColumn('point_transactions', 'metadata')) {
                $table->json('metadata')->nullable()->after('reference_id');
            }
        });

        // 5. Social Activities Privacy Support
        Schema::table('social_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('social_activities', 'is_private')) {
                $table->boolean('is_private')->default(false)->after('cheers_count');
            }
        });

        // 6. Platform Badges Rule-Based Enhancements
        Schema::table('badges', function (Blueprint $table) {
            if (! Schema::hasColumn('badges', 'rule_type')) {
                $table->string('rule_type')->nullable()->after('points_required'); // points_total, books_count, pages_count, streak_days, challenges_won, meetings_attended, diversity_score
            }
            if (! Schema::hasColumn('badges', 'rule_threshold')) {
                $table->integer('rule_threshold')->default(0)->after('rule_type');
            }
        });

        // 7. Community Badges Automatic vs Manual Enhancements
        Schema::table('community_badges', function (Blueprint $table) {
            if (! Schema::hasColumn('community_badges', 'award_type')) {
                $table->string('award_type')->default('manual')->after('color'); // manual, automatic
            }
            if (! Schema::hasColumn('community_badges', 'auto_rule_type')) {
                $table->string('auto_rule_type')->nullable()->after('award_type'); // discussions_count, comments_count, meetings_attended, challenges_won
            }
            if (! Schema::hasColumn('community_badges', 'auto_rule_threshold')) {
                $table->integer('auto_rule_threshold')->nullable()->default(0)->after('auto_rule_type');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('friendships');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hidden_activity_types');
        });

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropColumn(['is_valid', 'status', 'reference_type', 'reference_id', 'metadata']);
        });

        Schema::table('social_activities', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn(['rule_type', 'rule_threshold']);
        });

        Schema::table('community_badges', function (Blueprint $table) {
            $table->dropColumn(['award_type', 'auto_rule_type', 'auto_rule_threshold']);
        });
    }
};
