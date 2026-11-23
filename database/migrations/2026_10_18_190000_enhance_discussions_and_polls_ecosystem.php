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
        // 1. Enrich discussions table
        Schema::table('discussions', function (Blueprint $table) {
            if (! Schema::hasColumn('discussions', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('is_spoiler');
            }
            if (! Schema::hasColumn('discussions', 'is_coordinator_question')) {
                $table->boolean('is_coordinator_question')->default(false)->after('is_pinned');
            }
            if (! Schema::hasColumn('discussions', 'likes_count')) {
                $table->unsignedInteger('likes_count')->default(0)->after('is_coordinator_question');
            }
        });

        // 2. Create discussion_likes table
        if (! Schema::hasTable('discussion_likes')) {
            Schema::create('discussion_likes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discussion_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['discussion_id', 'user_id']);
            });
        }

        // 3. Create discussion_comment_likes table
        if (! Schema::hasTable('discussion_comment_likes')) {
            Schema::create('discussion_comment_likes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discussion_comment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['discussion_comment_id', 'user_id']);
            });
        }

        // 4. Enrich book_polls table
        Schema::table('book_polls', function (Blueprint $table) {
            if (! Schema::hasColumn('book_polls', 'show_results')) {
                $table->string('show_results', 30)->default('after_vote')->after('closes_at'); // always, after_vote, after_close
            }
            if (! Schema::hasColumn('book_polls', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('show_results');
            }
            if (! Schema::hasColumn('book_polls', 'allow_nominations')) {
                $table->boolean('allow_nominations')->default(true)->after('starts_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_polls', function (Blueprint $table) {
            $table->dropColumn(['show_results', 'starts_at', 'allow_nominations']);
        });

        Schema::dropIfExists('discussion_comment_likes');
        Schema::dropIfExists('discussion_likes');

        Schema::table('discussions', function (Blueprint $table) {
            $table->dropColumn(['is_pinned', 'is_coordinator_question', 'likes_count']);
        });
    }
};
