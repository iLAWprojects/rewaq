<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function ($table) {
            $table->integer('reading_streak')->default(0)->change();
        });

        // مستخدمون لم يسجلوا أي جلسة قراءة لا يجوز أن يظهر لهم تتابع وهمي
        DB::table('users')
            ->where('reading_streak', '>', 0)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('reading_sessions')->whereColumn('reading_sessions.user_id', 'users.id'))
            ->whereNotIn('role', ['super_admin'])
            ->update(['reading_streak' => 0]);
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->integer('reading_streak')->default(3)->change();
        });
    }
};
