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
        // 1. Audit Logs Table (سجل الرقابة وتتبع العمليات الحساسة)
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 80); // transfer_ownership, suspend_user, change_role, delete_resource, etc.
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->text('description');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['auditable_type', 'auditable_id']);
                $table->index(['action', 'created_at']);
            });
        }

        // 2. Soft Deletes for core entities (حذف لين قابل للاسترجاع)
        $tables = ['users', 'communities', 'libraries', 'meetings', 'discussions'];
        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (! Schema::hasColumn($tbl, 'deleted_at')) {
                        $table->softDeletes();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['users', 'communities', 'libraries', 'meetings', 'discussions'];
        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'deleted_at')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }

        Schema::dropIfExists('audit_logs');
    }
};
