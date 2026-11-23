<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookNomination;
use App\Models\BookPoll;
use App\Models\BookPollOption;
use App\Models\BookReview;
use App\Models\BookWaitlist;
use App\Models\Challenge;
use App\Models\ChallengeProgressLog;
use App\Models\Community;
use App\Models\CommunityBookHistory;
use App\Models\Discussion;
use App\Models\Meeting;
use App\Models\ReadingSession;
use App\Models\Reflection;
use App\Models\UserCurrentBook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateBooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'riwaq:deduplicate-books {--dry-run : محاكاة الفحص دون تطبيق التغييرات}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'دمج الكتب المكررة في الفهرس العام وإعادة ربط جميع النسخ وسجلات القراءة بالمرجع الأساسي';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info($isDryRun ? '--- بدء فحص الكتب المكررة (وضع المعاينة التجريبية) ---' : '--- بدء دمج الكتب المكررة في الفهرس العام ---');

        $allBooks = Book::withTrashed()->orderBy('id')->get();
        $this->line("إجمالي سجلات الكتب في قاعدة البيانات: {$allBooks->count()}");

        // Group books by normalized title
        $grouped = $allBooks->groupBy(function (Book $book) {
            return Book::normalizeTitle($book->title);
        });

        $duplicatesFound = 0;
        $mergedCount = 0;

        foreach ($grouped as $normalizedTitle => $books) {
            if ($books->count() <= 1) {
                continue;
            }

            $duplicatesFound++;

            // Select canonical book: prefer non-deleted with lowest id and richest data
            $canonical = $books->first(fn ($b) => ! $b->trashed()) ?? $books->first();

            $this->warn("\nوجدت كتباً مكررة لعنوان: «{$canonical->title}» (المفتاح الموحد: '{$normalizedTitle}'):");

            $duplicates = $books->reject(fn ($b) => $b->id === $canonical->id);

            foreach ($duplicates as $duplicate) {
                $this->line("  - مكرر #{$duplicate->id}: «{$duplicate->title}» للمؤلف ({$duplicate->author}) [محذوف: ".($duplicate->trashed() ? 'نعم' : 'لا').']');

                if (! $isDryRun) {
                    DB::transaction(function () use ($canonical, $duplicate) {
                        // 1. Re-point Book Copies
                        $copies = BookCopy::where('book_id', $duplicate->id)->get();
                        foreach ($copies as $copy) {
                            // Ensure copy_number is unique per library for canonical book
                            $existingWithNum = BookCopy::where('book_id', $canonical->id)
                                ->where('library_id', $copy->library_id)
                                ->where('copy_number', $copy->copy_number)
                                ->exists();

                            if ($existingWithNum) {
                                $maxNum = (int) BookCopy::where('book_id', $canonical->id)
                                    ->where('library_id', $copy->library_id)
                                    ->max('copy_number');
                                $copy->copy_number = (string) ($maxNum + 1);
                            }

                            $copy->book_id = $canonical->id;
                            $copy->save();
                        }

                        // 2. Re-point UserCurrentBook (Reading log)
                        $userBooks = UserCurrentBook::where('book_id', $duplicate->id)->get();
                        foreach ($userBooks as $ub) {
                            $existingCanonicalRecord = UserCurrentBook::where('user_id', $ub->user_id)
                                ->where('book_id', $canonical->id)
                                ->first();

                            if ($existingCanonicalRecord) {
                                // If existing record has less progress, adopt higher progress
                                if ($ub->current_page > $existingCanonicalRecord->current_page) {
                                    $existingCanonicalRecord->current_page = $ub->current_page;
                                    $existingCanonicalRecord->status = $ub->status;
                                    $existingCanonicalRecord->is_active = $ub->is_active || $existingCanonicalRecord->is_active;
                                    $existingCanonicalRecord->save();
                                }
                                $ub->delete();
                            } else {
                                $ub->book_id = $canonical->id;
                                $ub->save();
                            }
                        }

                        // 3. Re-point Reading Sessions
                        ReadingSession::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);

                        // 4. Re-point Reviews
                        $reviews = BookReview::where('book_id', $duplicate->id)->get();
                        foreach ($reviews as $rev) {
                            $alreadyReviewed = BookReview::where('user_id', $rev->user_id)
                                ->where('book_id', $canonical->id)
                                ->exists();

                            if ($alreadyReviewed) {
                                $rev->delete();
                            } else {
                                $rev->book_id = $canonical->id;
                                $rev->save();
                            }
                        }

                        // 5. Re-point Reflections
                        Reflection::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);

                        // 6. Re-point Discussions
                        Discussion::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);

                        // 7. Re-point Communities
                        Community::where('current_book_id', $duplicate->id)->update(['current_book_id' => $canonical->id]);
                        CommunityBookHistory::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);

                        // 8. Re-point Meetings
                        Meeting::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);
                        Meeting::where('next_book_id', $duplicate->id)->update(['next_book_id' => $canonical->id]);

                        // 9. Re-point Waitlists
                        $waitlists = BookWaitlist::where('book_id', $duplicate->id)->get();
                        foreach ($waitlists as $wl) {
                            $alreadyWaiting = BookWaitlist::where('user_id', $wl->user_id)
                                ->where('book_id', $canonical->id)
                                ->exists();

                            if ($alreadyWaiting) {
                                $wl->delete();
                            } else {
                                $wl->book_id = $canonical->id;
                                $wl->save();
                            }
                        }

                        // 10. Re-point Nominations and Polls
                        BookNomination::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);
                        BookPollOption::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);
                        BookPoll::where('winner_book_id', $duplicate->id)->update(['winner_book_id' => $canonical->id]);

                        // 11. Re-point Challenges
                        Challenge::where('specific_book_id', $duplicate->id)->update(['specific_book_id' => $canonical->id]);
                        ChallengeProgressLog::where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);

                        // 12. Re-point Reading series pivot
                        DB::table('reading_series_books')->where('book_id', $duplicate->id)->update(['book_id' => $canonical->id]);

                        // 13. Inherit cover if canonical is missing one
                        if (empty($canonical->cover_image) && ! empty($duplicate->cover_image)) {
                            $canonical->cover_image = $duplicate->cover_image;
                            $canonical->save();
                        }

                        // 14. Force delete the duplicate record
                        $duplicate->forceDelete();
                    });

                    $mergedCount++;
                }
            }
        }

        if ($duplicatesFound === 0) {
            $this->info('لم يتم العثور على أي كتب مكررة في الفهرس. جميع العناوين متفردة ونظيفة تماماً.');
        } else {
            if ($isDryRun) {
                $this->warn("\nتم رصد {$duplicatesFound} مجموعة كتب بها تكرار في وضع المعاينة التجريبية.");
            } else {
                $this->info("\nاكتمل الدمج بنجاح: تم دمج وإزالة {$mergedCount} كتاب مكرر، وتحديث كافة النسخ وسجلات القراءة.");
            }
        }

        return Command::SUCCESS;
    }
}
