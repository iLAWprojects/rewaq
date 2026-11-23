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
        // 1. Loans: QR Handshake and Book Swap
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'qr_token')) {
                $table->string('qr_token', 64)->nullable()->after('status');
            }
            if (! Schema::hasColumn('loans', 'swap_book_title')) {
                $table->string('swap_book_title', 255)->nullable();
            }
            if (! Schema::hasColumn('loans', 'handshake_at')) {
                $table->timestamp('handshake_at')->nullable();
            }
        });

        // 2. Book Copies: Physical Copy Passport
        Schema::table('book_copies', function (Blueprint $table) {
            if (! Schema::hasColumn('book_copies', 'readers_count')) {
                $table->integer('readers_count')->default(1);
            }
            if (! Schema::hasColumn('book_copies', 'cities_traveled')) {
                $table->string('cities_traveled', 255)->nullable()->default('الرياض، الشارقة');
            }
            if (! Schema::hasColumn('book_copies', 'margin_notes')) {
                $table->text('margin_notes')->nullable();
            }
        });

        // 3. Challenges: Team vs Team Battles
        Schema::table('challenges', function (Blueprint $table) {
            if (! Schema::hasColumn('challenges', 'is_team')) {
                $table->boolean('is_team')->default(false);
            }
            if (! Schema::hasColumn('challenges', 'team_a_name')) {
                $table->string('team_a_name', 100)->nullable();
            }
            if (! Schema::hasColumn('challenges', 'team_b_name')) {
                $table->string('team_b_name', 100)->nullable();
            }
            if (! Schema::hasColumn('challenges', 'team_a_pages')) {
                $table->integer('team_a_pages')->default(0);
            }
            if (! Schema::hasColumn('challenges', 'team_b_pages')) {
                $table->integer('team_b_pages')->default(0);
            }
        });

        // 4. Meetings: Past Sessions Archive & Audio Summaries
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'is_past')) {
                $table->boolean('is_past')->default(false);
            }
            if (! Schema::hasColumn('meetings', 'summary_notes')) {
                $table->text('summary_notes')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'audio_url')) {
                $table->string('audio_url', 255)->nullable();
            }
        });

        // 5. Users: Notification Digest
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'digest_enabled')) {
                $table->boolean('digest_enabled')->default(false);
            }
            if (! Schema::hasColumn('users', 'digest_time')) {
                $table->string('digest_time', 10)->default('20:00');
            }
        });

        // 6. Reflections: Community Highlights
        Schema::table('reflections', function (Blueprint $table) {
            if (! Schema::hasColumn('reflections', 'is_highlight')) {
                $table->boolean('is_highlight')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'swap_book_title', 'handshake_at']);
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropColumn(['readers_count', 'cities_traveled', 'margin_notes']);
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['is_team', 'team_a_name', 'team_b_name', 'team_a_pages', 'team_b_pages']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['is_past', 'summary_notes', 'audio_url']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_enabled', 'digest_time']);
        });

        Schema::table('reflections', function (Blueprint $table) {
            $table->dropColumn(['is_highlight']);
        });
    }
};
