<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add language to books table
        if (Schema::hasTable('books')) {
            Schema::table('books', function (Blueprint $table) {
                if (! Schema::hasColumn('books', 'language')) {
                    $table->string('language', 30)->default('العربية')->after('category');
                }
            });
        }

        // 2. Enhance user_current_books (User Book Reading Status & Progress)
        if (Schema::hasTable('user_current_books')) {
            Schema::table('user_current_books', function (Blueprint $table) {
                if (! Schema::hasColumn('user_current_books', 'status')) {
                    $table->string('status', 32)->default('currently_reading')->after('book_id');
                }
                if (! Schema::hasColumn('user_current_books', 'progress_percentage')) {
                    $table->integer('progress_percentage')->default(0)->after('current_page');
                }
                if (! Schema::hasColumn('user_current_books', 'start_date')) {
                    $table->date('start_date')->nullable()->after('progress_percentage');
                }
                if (! Schema::hasColumn('user_current_books', 'finish_date')) {
                    $table->date('finish_date')->nullable()->after('start_date');
                }
                if (! Schema::hasColumn('user_current_books', 'rating')) {
                    $table->tinyInteger('rating')->nullable()->after('finish_date');
                }
                if (! Schema::hasColumn('user_current_books', 'notes')) {
                    $table->text('notes')->nullable()->after('rating');
                }
            });
        }

        // 3. Enhance reading_sessions (Session Details: Started, Ended, Device, Start/End Page)
        if (Schema::hasTable('reading_sessions')) {
            Schema::table('reading_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('reading_sessions', 'started_at')) {
                    $table->dateTime('started_at')->nullable()->after('book_id');
                }
                if (! Schema::hasColumn('reading_sessions', 'ended_at')) {
                    $table->dateTime('ended_at')->nullable()->after('started_at');
                }
                if (! Schema::hasColumn('reading_sessions', 'start_page')) {
                    $table->integer('start_page')->nullable()->after('ended_at');
                }
                if (! Schema::hasColumn('reading_sessions', 'end_page')) {
                    $table->integer('end_page')->nullable()->after('start_page');
                }
                if (! Schema::hasColumn('reading_sessions', 'device')) {
                    $table->string('device', 60)->default('كتاب ورقي')->after('minutes');
                }
            });
        }

        // 4. Enhance reflections (Privacy: private vs public)
        if (Schema::hasTable('reflections')) {
            Schema::table('reflections', function (Blueprint $table) {
                if (! Schema::hasColumn('reflections', 'privacy')) {
                    $table->string('privacy', 20)->default('public')->after('is_public');
                }
            });
        }

        // 5. Enhance users (Annual goal metrics: pages, hours)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'annual_goal_pages')) {
                    $table->integer('annual_goal_pages')->nullable()->after('annual_goal');
                }
                if (! Schema::hasColumn('users', 'annual_goal_hours')) {
                    $table->integer('annual_goal_hours')->nullable()->after('annual_goal_pages');
                }
            });
        }

        // 6. Create yearly_reading_goals table (Preserving Annual Reading Log & Historical Achievements)
        if (! Schema::hasTable('yearly_reading_goals')) {
            Schema::create('yearly_reading_goals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->integer('year');
                $table->integer('target_books')->default(24);
                $table->integer('target_pages')->nullable();
                $table->integer('target_hours')->nullable();
                $table->integer('achieved_books')->default(0);
                $table->integer('achieved_pages')->default(0);
                $table->integer('achieved_minutes')->default(0);
                $table->float('knowledge_diversity_score')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('yearly_reading_goals');

        if (Schema::hasTable('books') && Schema::hasColumn('books', 'language')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }

        if (Schema::hasTable('user_current_books')) {
            Schema::table('user_current_books', function (Blueprint $table) {
                $table->dropColumn(['status', 'progress_percentage', 'start_date', 'finish_date', 'rating', 'notes']);
            });
        }

        if (Schema::hasTable('reading_sessions')) {
            Schema::table('reading_sessions', function (Blueprint $table) {
                $table->dropColumn(['started_at', 'ended_at', 'start_page', 'end_page', 'device']);
            });
        }

        if (Schema::hasTable('reflections') && Schema::hasColumn('reflections', 'privacy')) {
            Schema::table('reflections', function (Blueprint $table) {
                $table->dropColumn('privacy');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['annual_goal_pages', 'annual_goal_hours']);
            });
        }
    }
};
