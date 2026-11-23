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
        // Libraries
        Schema::create('libraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_personal')->default(true);
            $table->timestamps();
        });

        // Books Catalog
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('category')->default('أدب'); // أدب، سيرة، تاريخ، فكر، إلخ
            $table->integer('total_pages')->default(200);
            $table->string('cover_color')->default('#315749');
            $table->string('isbn')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Physical Book Copies (النسخ المادية)
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->string('barcode')->nullable();
            $table->string('copy_number')->default('1');
            $table->enum('status', ['available', 'borrowed', 'reserved', 'maintenance'])->default('available');
            $table->string('shelf_location')->nullable();
            $table->timestamps();
        });

        // Loans (الإعارات وطلباتها)
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_copy_id')->constrained('book_copies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('borrower_name')->nullable();
            $table->enum('status', ['pending', 'active', 'returned', 'overdue', 'rejected'])->default('pending');
            $table->dateTime('requested_at')->useCurrent();
            $table->dateTime('borrowed_at')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->integer('renewal_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Current Book being read by user
        Schema::create('user_current_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->integer('current_page')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Reading Sessions (جلسات القراءة)
        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->integer('current_page')->default(0);
            $table->integer('pages_read')->default(0);
            $table->integer('minutes')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Reflections / Quotes (التأملات والخواطر والاقتباسات)
        Schema::create('reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->integer('page_number')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        // Communities (مجتمعات القراءة)
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('أدب');
            $table->text('description')->nullable();
            $table->string('cover_color')->default('#173D36');
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('current_book_id')->nullable()->constrained('books')->nullOnDelete();
            $table->text('featured_question')->nullable();
            $table->timestamps();
        });

        // Community Members
        Schema::create('community_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'coordinator', 'member', 'suspended'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['community_id', 'user_id']);
        });

        // Community Discussions
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('content');
            $table->boolean('is_spoiler')->default(false);
            $table->timestamps();
        });

        // Book of the Month Polls (كتاب الشهر)
        Schema::create('book_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->date('closes_at')->nullable();
            $table->timestamps();
        });

        // Poll Options
        Schema::create('book_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_title');
            $table->integer('votes_count')->default(0);
            $table->timestamps();
        });

        // Poll Votes (One vote per user)
        Schema::create('book_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['book_poll_id', 'user_id']);
        });

        // Challenges (التحديات)
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('category')->default('تنوع معرفي');
            $table->integer('target_count')->default(3);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Challenge Participants
        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('progress_count')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
        });

        // Meetings (الاجتماعات واللقاءات)
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->dateTime('meeting_date');
            $table->enum('meeting_type', ['remote', 'in_person', 'hybrid'])->default('remote');
            $table->string('location')->nullable();
            $table->string('link')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Meeting RSVPs
        Schema::create('meeting_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['attending', 'declined', 'waitlist'])->default('attending');
            $table->string('qr_code')->nullable();
            $table->boolean('checked_in')->default(false);
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });

        // In-app Notifications
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('icon')->default('bell');
            $table->string('link')->nullable();
            $table->string('type')->default('info');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // Gamification: Badges
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('icon')->default('award');
            $table->integer('points_required')->default(100);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('meeting_rsvps');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenges');
        Schema::dropIfExists('book_poll_votes');
        Schema::dropIfExists('book_poll_options');
        Schema::dropIfExists('book_polls');
        Schema::dropIfExists('discussions');
        Schema::dropIfExists('community_members');
        Schema::dropIfExists('communities');
        Schema::dropIfExists('reflections');
        Schema::dropIfExists('reading_sessions');
        Schema::dropIfExists('user_current_books');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('books');
        Schema::dropIfExists('libraries');
    }
};
