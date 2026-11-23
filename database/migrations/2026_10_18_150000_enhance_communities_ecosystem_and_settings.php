<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance communities table
        if (Schema::hasTable('communities')) {
            Schema::table('communities', function (Blueprint $table) {
                if (! Schema::hasColumn('communities', 'logo')) {
                    $table->string('logo')->nullable()->after('name');
                }
                if (! Schema::hasColumn('communities', 'cover_image')) {
                    $table->string('cover_image')->nullable()->after('cover_color');
                }
                if (! Schema::hasColumn('communities', 'language')) {
                    $table->string('language', 30)->default('العربية')->after('type');
                }
                if (! Schema::hasColumn('communities', 'city')) {
                    $table->string('city', 60)->nullable()->after('language');
                }
                if (! Schema::hasColumn('communities', 'interests')) {
                    $table->json('interests')->nullable()->after('city');
                }
                if (! Schema::hasColumn('communities', 'max_members')) {
                    $table->integer('max_members')->nullable()->after('interests');
                }
                if (! Schema::hasColumn('communities', 'welcome_message')) {
                    $table->text('welcome_message')->nullable()->after('rules');
                }
                if (! Schema::hasColumn('communities', 'is_visible_in_search')) {
                    $table->boolean('is_visible_in_search')->default(true)->after('max_members');
                }
                if (! Schema::hasColumn('communities', 'requires_approval')) {
                    $table->boolean('requires_approval')->default(false)->after('is_visible_in_search');
                }
                if (! Schema::hasColumn('communities', 'allow_member_invites')) {
                    $table->boolean('allow_member_invites')->default(true)->after('requires_approval');
                }
                if (! Schema::hasColumn('communities', 'allow_member_challenges')) {
                    $table->boolean('allow_member_challenges')->default(true)->after('allow_member_invites');
                }
                if (! Schema::hasColumn('communities', 'allow_member_meetings')) {
                    $table->boolean('allow_member_meetings')->default(false)->after('allow_member_challenges');
                }
                if (! Schema::hasColumn('communities', 'linked_library_id')) {
                    $table->foreignId('linked_library_id')->nullable()->constrained('libraries')->nullOnDelete()->after('allow_member_meetings');
                }
                if (! Schema::hasColumn('communities', 'institutional_domain')) {
                    $table->string('institutional_domain', 100)->nullable()->after('linked_library_id');
                }
            });

            // Set initial defaults for existing communities based on type
            DB::table('communities')->where('type', 'private')->update([
                'is_visible_in_search' => false,
                'requires_approval' => true,
            ]);
            DB::table('communities')->where('type', 'public')->update([
                'is_visible_in_search' => true,
                'requires_approval' => true,
            ]);
            DB::table('communities')->where('type', 'open')->update([
                'is_visible_in_search' => true,
                'requires_approval' => false,
            ]);
        }

        // 2. Add community_id to challenges table
        if (Schema::hasTable('challenges')) {
            Schema::table('challenges', function (Blueprint $table) {
                if (! Schema::hasColumn('challenges', 'community_id')) {
                    $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete()->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('challenges')) {
            Schema::table('challenges', function (Blueprint $table) {
                if (Schema::hasColumn('challenges', 'community_id')) {
                    $table->dropForeign(['community_id']);
                    $table->dropColumn('community_id');
                }
            });
        }

        if (Schema::hasTable('communities')) {
            Schema::table('communities', function (Blueprint $table) {
                $columns = [
                    'logo', 'cover_image', 'language', 'city', 'interests',
                    'max_members', 'welcome_message', 'is_visible_in_search',
                    'requires_approval', 'allow_member_invites', 'allow_member_challenges',
                    'allow_member_meetings', 'linked_library_id', 'institutional_domain',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('communities', $col)) {
                        if ($col === 'linked_library_id') {
                            $table->dropForeign(['linked_library_id']);
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
