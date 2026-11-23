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
        // 1. Enrich meetings table
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'capacity')) {
                $table->integer('capacity')->nullable()->default(25);
            }
            if (! Schema::hasColumn('meetings', 'meeting_minutes')) {
                $table->text('meeting_minutes')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'rating')) {
                $table->decimal('rating', 3, 2)->default(5.00);
            }
            if (! Schema::hasColumn('meetings', 'ratings_count')) {
                $table->integer('ratings_count')->default(0);
            }
            if (! Schema::hasColumn('meetings', 'end_time')) {
                $table->dateTime('end_time')->nullable();
            }
        });

        // 2. Enrich meeting_rsvps table
        Schema::table('meeting_rsvps', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_rsvps', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_rsvps', 'rating')) {
                $table->tinyInteger('rating')->nullable();
            }
            if (! Schema::hasColumn('meeting_rsvps', 'review')) {
                $table->text('review')->nullable();
            }
        });

        // 3. Discussion Comments & Nested Replies
        if (! Schema::hasTable('discussion_comments')) {
            Schema::create('discussion_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('discussion_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('discussion_comments')->cascadeOnDelete();
                $table->text('content');
                $table->boolean('is_spoiler')->default(false);
                $table->integer('likes_count')->default(0);
                $table->timestamps();
            });
        }

        // 4. Content Reports for Moderation
        if (! Schema::hasTable('content_reports')) {
            Schema::create('content_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('reportable_type'); // discussion, comment
                $table->unsignedBigInteger('reportable_id');
                $table->string('reason'); // spoiler, inappropriate, spam, disrespect, other
                $table->text('details')->nullable();
                $table->enum('status', ['pending', 'reviewed', 'dismissed', 'action_taken'])->default('pending');
                $table->text('moderator_notes')->nullable();
                $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['reportable_type', 'reportable_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('discussion_comments');

        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'rating', 'review']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'meeting_minutes', 'rating', 'ratings_count', 'end_time']);
        });
    }
};
