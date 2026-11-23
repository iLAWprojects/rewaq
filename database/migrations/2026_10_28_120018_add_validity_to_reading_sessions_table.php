<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->boolean('is_valid')->default(true)->after('minutes');
            $table->string('invalid_reason')->nullable()->after('is_valid');
        });
    }

    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_valid', 'invalid_reason']);
        });
    }
};
