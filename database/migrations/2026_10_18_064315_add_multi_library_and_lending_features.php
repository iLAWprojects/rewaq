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
        // 1. Update Libraries: Code, Type, Cover Color
        Schema::table('libraries', function (Blueprint $table) {
            if (! Schema::hasColumn('libraries', 'code')) {
                $table->string('code', 32)->nullable()->unique()->after('description');
            }
            if (! Schema::hasColumn('libraries', 'type')) {
                $table->string('type', 30)->default('personal')->after('is_personal'); // personal, public, club, family
            }
            if (! Schema::hasColumn('libraries', 'cover_color')) {
                $table->string('cover_color', 20)->default('#173D36')->after('type');
            }
        });

        // 2. Library Members & Roles (أعضاء المكتبات وأدوارهم)
        if (! Schema::hasTable('library_members')) {
            Schema::create('library_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('library_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 20)->default('member'); // owner, librarian, member, viewer
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamps();

                $table->unique(['library_id', 'user_id']);
            });
        }

        // 3. Update Book Copies: Condition & Notes (حالة النسخة المادية)
        Schema::table('book_copies', function (Blueprint $table) {
            if (! Schema::hasColumn('book_copies', 'condition')) {
                $table->string('condition', 20)->default('good')->after('status'); // mint, good, fair, damaged
            }
            if (! Schema::hasColumn('book_copies', 'condition_notes')) {
                $table->text('condition_notes')->nullable()->after('condition');
            }
        });

        // 4. Update Loans: Return Condition, Return Notes, Max Renewals
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'return_condition')) {
                $table->string('return_condition', 20)->nullable()->after('returned_at');
            }
            if (! Schema::hasColumn('loans', 'return_notes')) {
                $table->text('return_notes')->nullable()->after('return_condition');
            }
            if (! Schema::hasColumn('loans', 'max_renewals')) {
                $table->integer('max_renewals')->default(2)->after('renewal_count');
            }
        });

        // 5. Book Waitlists (قوائم الانتظار وحجز النسخ المعارة)
        if (! Schema::hasTable('book_waitlists')) {
            Schema::create('book_waitlists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('library_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20)->default('waiting'); // waiting, notified, fulfilled, cancelled
                $table->integer('priority')->default(1);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['library_id', 'book_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_waitlists');
        Schema::dropIfExists('library_members');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['return_condition', 'return_notes', 'max_renewals']);
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropColumn(['condition', 'condition_notes']);
        });

        Schema::table('libraries', function (Blueprint $table) {
            $table->dropColumn(['code', 'type', 'cover_color']);
        });
    }
};
