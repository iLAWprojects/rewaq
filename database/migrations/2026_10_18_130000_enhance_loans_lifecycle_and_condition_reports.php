<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update status enum in loans to support 'approved', 'closed', etc.
        if (Schema::hasTable('loans')) {
            DB::statement("ALTER TABLE `loans` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'pending'");

            Schema::table('loans', function (Blueprint $table) {
                if (! Schema::hasColumn('loans', 'handover_condition')) {
                    $table->string('handover_condition', 32)->nullable()->after('borrowed_at');
                }
                if (! Schema::hasColumn('loans', 'handover_notes')) {
                    $table->text('handover_notes')->nullable()->after('handover_condition');
                }
                if (! Schema::hasColumn('loans', 'handover_inspector_id')) {
                    $table->foreignId('handover_inspector_id')->nullable()->after('handover_notes')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('loans', 'handover_at')) {
                    $table->dateTime('handover_at')->nullable()->after('handover_inspector_id');
                }
                if (! Schema::hasColumn('loans', 'return_inspector_id')) {
                    $table->foreignId('return_inspector_id')->nullable()->after('return_notes')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('loans', 'closed_at')) {
                    $table->dateTime('closed_at')->nullable()->after('return_inspector_id');
                }
                if (! Schema::hasColumn('loans', 'closed_by')) {
                    $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('loans', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('closed_by');
                }
                if (! Schema::hasColumn('loans', 'notified_due_soon_at')) {
                    $table->dateTime('notified_due_soon_at')->nullable()->after('rejection_reason');
                }
                if (! Schema::hasColumn('loans', 'notified_overdue_at')) {
                    $table->dateTime('notified_overdue_at')->nullable()->after('notified_due_soon_at');
                }
            });
        }

        // 2. Add lending settings to libraries table
        if (Schema::hasTable('libraries')) {
            Schema::table('libraries', function (Blueprint $table) {
                if (! Schema::hasColumn('libraries', 'loan_duration_days')) {
                    $table->integer('loan_duration_days')->default(14)->after('cover_color');
                }
                if (! Schema::hasColumn('libraries', 'max_renewals')) {
                    $table->integer('max_renewals')->default(2)->after('loan_duration_days');
                }
                if (! Schema::hasColumn('libraries', 'grace_period_days')) {
                    $table->integer('grace_period_days')->default(2)->after('max_renewals');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loans')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('handover_inspector_id');
                $table->dropConstrainedForeignId('return_inspector_id');
                $table->dropConstrainedForeignId('closed_by');
                $table->dropColumn([
                    'handover_condition',
                    'handover_notes',
                    'handover_at',
                    'closed_at',
                    'rejection_reason',
                    'notified_due_soon_at',
                    'notified_overdue_at',
                ]);
            });
        }

        if (Schema::hasTable('libraries')) {
            Schema::table('libraries', function (Blueprint $table) {
                $table->dropColumn(['loan_duration_days', 'max_renewals', 'grace_period_days']);
            });
        }
    }
};
