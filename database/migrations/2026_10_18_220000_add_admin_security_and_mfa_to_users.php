<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add MFA fields to users table
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'mfa_enabled')) {
                $table->boolean('mfa_enabled')->default(false)->after('role');
            }
            if (! Schema::hasColumn('users', 'mfa_secret')) {
                $table->string('mfa_secret')->nullable()->after('mfa_enabled');
            }
            if (! Schema::hasColumn('users', 'mfa_confirmed_at')) {
                $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_secret');
            }
            if (! Schema::hasColumn('users', 'recovery_codes')) {
                $table->json('recovery_codes')->nullable()->after('mfa_confirmed_at');
            }
        });

        // 2. Add SoftDeletes to books table if not exists
        if (Schema::hasTable('books') && ! Schema::hasColumn('books', 'deleted_at')) {
            Schema::table('books', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 3. Add SoftDeletes to discussion_comments table if not exists
        if (Schema::hasTable('discussion_comments') && ! Schema::hasColumn('discussion_comments', 'deleted_at')) {
            Schema::table('discussion_comments', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_enabled', 'mfa_secret', 'mfa_confirmed_at', 'recovery_codes']);
        });

        if (Schema::hasColumn('books', 'deleted_at')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('discussion_comments', 'deleted_at')) {
            Schema::table('discussion_comments', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
