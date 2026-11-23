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
        if (! Schema::hasColumn('reflections', 'likes_count')) {
            Schema::table('reflections', function (Blueprint $table) {
                $table->unsignedInteger('likes_count')->default(0)->after('is_highlight');
            });
        }

        if (! Schema::hasTable('reflection_likes')) {
            Schema::create('reflection_likes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reflection_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['reflection_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reflection_likes');

        if (Schema::hasColumn('reflections', 'likes_count')) {
            Schema::table('reflections', function (Blueprint $table) {
                $table->dropColumn('likes_count');
            });
        }
    }
};
