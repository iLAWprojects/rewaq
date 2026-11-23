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
        // 1. Enhance meetings table
        if (Schema::hasTable('meetings')) {
            // Modify meeting_type enum to varchar to support all 8 types
            try {
                DB::statement("ALTER TABLE meetings MODIFY COLUMN meeting_type VARCHAR(50) DEFAULT 'remote'");
            } catch (Throwable $e) {
                // Fallback for non-MySQL or already varchar
            }

            Schema::table('meetings', function (Blueprint $table) {
                if (! Schema::hasColumn('meetings', 'timezone')) {
                    $table->string('timezone', 50)->default('Asia/Riyadh')->after('meeting_date');
                }
                if (! Schema::hasColumn('meetings', 'starts_at')) {
                    $table->dateTime('starts_at')->nullable()->after('timezone');
                }
                if (! Schema::hasColumn('meetings', 'ends_at')) {
                    $table->dateTime('ends_at')->nullable()->after('starts_at');
                }
                if (! Schema::hasColumn('meetings', 'registration_deadline')) {
                    $table->dateTime('registration_deadline')->nullable()->after('ends_at');
                }
                if (! Schema::hasColumn('meetings', 'coordinator_id')) {
                    $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete()->after('book_id');
                }
                if (! Schema::hasColumn('meetings', 'cover_image')) {
                    $table->string('cover_image')->nullable()->after('description');
                }
                if (! Schema::hasColumn('meetings', 'discussion_questions')) {
                    $table->json('discussion_questions')->nullable()->after('cover_image');
                }
                if (! Schema::hasColumn('meetings', 'instructions')) {
                    $table->text('instructions')->nullable()->after('discussion_questions');
                }
                if (! Schema::hasColumn('meetings', 'photos')) {
                    $table->json('photos')->nullable()->after('meeting_minutes');
                }
                if (! Schema::hasColumn('meetings', 'key_ideas')) {
                    $table->text('key_ideas')->nullable()->after('photos');
                }
                if (! Schema::hasColumn('meetings', 'recommendations')) {
                    $table->text('recommendations')->nullable()->after('key_ideas');
                }
                if (! Schema::hasColumn('meetings', 'next_book_id')) {
                    $table->foreignId('next_book_id')->nullable()->constrained('books')->nullOnDelete()->after('recommendations');
                }
            });

            // Backfill starts_at and ends_at from meeting_date and end_time
            DB::statement('UPDATE meetings SET starts_at = meeting_date WHERE starts_at IS NULL');
            DB::statement('UPDATE meetings SET ends_at = end_time WHERE ends_at IS NULL AND end_time IS NOT NULL');
        }

        // 2. Enhance meeting_rsvps table
        if (Schema::hasTable('meeting_rsvps')) {
            // Modify status enum to varchar to support all 6 statuses: going, maybe, not_going, waitlist, attended, no_show
            try {
                DB::statement("ALTER TABLE meeting_rsvps MODIFY COLUMN status VARCHAR(30) DEFAULT 'going'");
            } catch (Throwable $e) {
                // Fallback
            }

            // Normalize existing attending/declined to going/not_going
            DB::table('meeting_rsvps')->where('status', 'attending')->update(['status' => 'going']);
            DB::table('meeting_rsvps')->where('status', 'declined')->update(['status' => 'not_going']);

            Schema::table('meeting_rsvps', function (Blueprint $table) {
                if (! Schema::hasColumn('meeting_rsvps', 'qr_token')) {
                    $table->string('qr_token', 64)->nullable()->unique()->after('status');
                }
                if (! Schema::hasColumn('meeting_rsvps', 'qr_token_expires_at')) {
                    $table->dateTime('qr_token_expires_at')->nullable()->after('qr_token');
                }
                if (! Schema::hasColumn('meeting_rsvps', 'notes')) {
                    $table->text('notes')->nullable()->after('review');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('meeting_rsvps')) {
            Schema::table('meeting_rsvps', function (Blueprint $table) {
                if (Schema::hasColumn('meeting_rsvps', 'qr_token')) {
                    $table->dropColumn(['qr_token', 'qr_token_expires_at', 'notes']);
                }
            });
        }

        if (Schema::hasTable('meetings')) {
            Schema::table('meetings', function (Blueprint $table) {
                if (Schema::hasColumn('meetings', 'next_book_id')) {
                    $table->dropConstrainedForeignId('next_book_id');
                }
                if (Schema::hasColumn('meetings', 'coordinator_id')) {
                    $table->dropConstrainedForeignId('coordinator_id');
                }
                $table->dropColumn([
                    'timezone',
                    'starts_at',
                    'ends_at',
                    'registration_deadline',
                    'cover_image',
                    'discussion_questions',
                    'instructions',
                    'photos',
                    'key_ideas',
                    'recommendations',
                ]);
            });
        }
    }
};
