<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create challenge_teams table first (so challenges & participants can reference it)
        if (! Schema::hasTable('challenge_teams')) {
            Schema::create('challenge_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
                $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete();
                $table->string('name', 100);
                $table->string('color', 30)->default('#173D36');
                $table->string('badge_icon', 50)->default('shield');
                $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('members_count')->default(0);
                $table->unsignedBigInteger('total_progress')->default(0);
                $table->unsignedBigInteger('total_points')->default(0);
                $table->unsignedInteger('rank')->nullable();
                $table->timestamps();

                $table->index(['challenge_id', 'rank']);
            });
        }

        // 2. Enhance challenges table with statuses, 13 types, and team strategies
        if (Schema::hasTable('challenges')) {
            Schema::table('challenges', function (Blueprint $table) {
                if (! Schema::hasColumn('challenges', 'status')) {
                    $table->enum('status', ['draft', 'upcoming', 'active', 'completed', 'cancelled', 'archived'])
                        ->default('active')
                        ->after('description')
                        ->index();
                }
                if (! Schema::hasColumn('challenges', 'challenge_type')) {
                    $table->string('challenge_type', 50)->default('books_count')->after('status');
                }
                if (! Schema::hasColumn('challenges', 'team_assignment_method')) {
                    $table->enum('team_assignment_method', ['manual', 'random', 'by_level', 'user_choice'])
                        ->default('user_choice')
                        ->after('challenge_type');
                }
                if (! Schema::hasColumn('challenges', 'target_unit')) {
                    $table->string('target_unit', 30)->default('كتب')->after('target_count');
                }
                if (! Schema::hasColumn('challenges', 'specific_book_id')) {
                    $table->foreignId('specific_book_id')->nullable()->constrained('books')->nullOnDelete()->after('target_unit');
                }
                if (! Schema::hasColumn('challenges', 'series_name')) {
                    $table->string('series_name', 120)->nullable()->after('specific_book_id');
                }
                if (! Schema::hasColumn('challenges', 'season_name')) {
                    $table->string('season_name', 80)->nullable()->after('series_name');
                }
                if (! Schema::hasColumn('challenges', 'creator_id')) {
                    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete()->after('community_id');
                }
                if (! Schema::hasColumn('challenges', 'winner_user_id')) {
                    $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete()->after('winner_team');
                }
                if (! Schema::hasColumn('challenges', 'winner_team_id')) {
                    $table->foreignId('winner_team_id')->nullable()->constrained('challenge_teams')->nullOnDelete()->after('winner_user_id');
                }
                if (! Schema::hasColumn('challenges', 'milestones')) {
                    $table->json('milestones')->nullable()->after('reward_points');
                }
            });

            // Backfill existing challenge statuses based on is_archived and is_active
            DB::table('challenges')->where('is_archived', true)->update(['status' => 'archived']);
            DB::table('challenges')->where('is_archived', false)->where('is_active', true)->update(['status' => 'active']);
            DB::table('challenges')->where('is_archived', false)->where('is_active', false)->update(['status' => 'draft']);
        }

        // 3. Enhance challenge_participants table with team_id, points, and milestone tracking
        if (Schema::hasTable('challenge_participants')) {
            Schema::table('challenge_participants', function (Blueprint $table) {
                if (! Schema::hasColumn('challenge_participants', 'team_id')) {
                    $table->foreignId('team_id')->nullable()->constrained('challenge_teams')->nullOnDelete()->after('user_id');
                }
                if (! Schema::hasColumn('challenge_participants', 'points_earned')) {
                    $table->unsignedInteger('points_earned')->default(0)->after('progress_count');
                }
                if (! Schema::hasColumn('challenge_participants', 'joined_at')) {
                    $table->timestamp('joined_at')->useCurrent()->after('rank');
                }
                if (! Schema::hasColumn('challenge_participants', 'last_milestone')) {
                    $table->unsignedTinyInteger('last_milestone')->default(0)->after('points_earned'); // 0, 25, 50, 75, 100
                }
            });
        }

        // 4. Create challenge_progress_logs table (Event / Transaction-based audit log)
        if (! Schema::hasTable('challenge_progress_logs')) {
            Schema::create('challenge_progress_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('challenge_teams')->nullOnDelete();
                $table->foreignId('reading_session_id')->nullable()->constrained('reading_sessions')->nullOnDelete();
                $table->foreignId('book_id')->nullable()->constrained('books')->nullOnDelete();
                $table->string('event_type', 50)->default('pages_read');
                $table->integer('increment_value');
                $table->integer('progress_before')->default(0);
                $table->integer('progress_after')->default(0);
                $table->integer('points_awarded')->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('logged_at')->useCurrent();
                $table->timestamps();

                $table->index(['challenge_id', 'user_id']);
                $table->index(['challenge_id', 'team_id']);
                $table->index(['challenge_id', 'logged_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_progress_logs');

        if (Schema::hasTable('challenge_participants')) {
            Schema::table('challenge_participants', function (Blueprint $table) {
                if (Schema::hasColumn('challenge_participants', 'team_id')) {
                    $table->dropConstrainedForeignId('team_id');
                }
                $table->dropColumn(['points_earned', 'joined_at', 'last_milestone']);
            });
        }

        if (Schema::hasTable('challenges')) {
            Schema::table('challenges', function (Blueprint $table) {
                if (Schema::hasColumn('challenges', 'winner_team_id')) {
                    $table->dropConstrainedForeignId('winner_team_id');
                }
                if (Schema::hasColumn('challenges', 'winner_user_id')) {
                    $table->dropConstrainedForeignId('winner_user_id');
                }
                if (Schema::hasColumn('challenges', 'specific_book_id')) {
                    $table->dropConstrainedForeignId('specific_book_id');
                }
                if (Schema::hasColumn('challenges', 'creator_id')) {
                    $table->dropConstrainedForeignId('creator_id');
                }
                $table->dropColumn([
                    'status',
                    'challenge_type',
                    'team_assignment_method',
                    'target_unit',
                    'series_name',
                    'season_name',
                    'milestones',
                ]);
            });
        }

        Schema::dropIfExists('challenge_teams');
    }
};
