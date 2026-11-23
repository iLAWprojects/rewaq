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
        if (! Schema::hasColumn('reflections', 'community_id')) {
            Schema::table('reflections', function (Blueprint $table) {
                $table->foreignId('community_id')->nullable()->after('book_id')->constrained('communities')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('reflections', 'community_id')) {
            Schema::table('reflections', function (Blueprint $table) {
                $table->dropForeign(['community_id']);
                $table->dropColumn('community_id');
            });
        }
    }
};
