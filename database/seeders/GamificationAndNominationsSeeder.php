<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Book;
use App\Models\BookNomination;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\CommunityBookHistory;
use App\Models\PointTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class GamificationAndNominationsSeeder extends Seeder
{
    public function run(): void
    {
        $badgesData = [
            ['name' => 'فاتحة الرواق', 'description' => 'أتم أول قراءة وسجل خطواته الأولى في منصة رِواق المعرفة.', 'icon' => 'book-open', 'category' => 'general', 'points_required' => 50],
            ['name' => 'القارئ الدؤوب', 'description' => 'حقق سلسلة قراءة مستمرة والتزاماً معرفياً يومياً.', 'icon' => 'flame', 'category' => 'streak', 'points_required' => 100],
            ['name' => 'قناص الأفكار', 'description' => 'دوّن خلاصة تأملاته واقتباساته الملهمة من بطون الكتب.', 'icon' => 'feather', 'category' => 'reading', 'points_required' => 150],
            ['name' => 'فارس المجالس', 'description' => 'أثرى نقاشات الكتب وتفاعل مع القراء في مجالس الرواق.', 'icon' => 'message-square', 'category' => 'discussion', 'points_required' => 200],
            ['name' => 'حاضر الفكر', 'description' => 'شارك في لقاءات المجالس الحوارية وثبت حضوره برمز QR.', 'icon' => 'calendar-check', 'category' => 'meeting', 'points_required' => 250],
            ['name' => 'جامع المجلدات', 'description' => 'أتم قراءة 5 كتب في مسيرته المعرفية داخل المنصة.', 'icon' => 'library', 'category' => 'reading', 'points_required' => 400],
            ['name' => 'سفير المعرفة', 'description' => 'دعا رفاقه للمجالس وساهم في نشر الشغف بالقراءة.', 'icon' => 'share-2', 'category' => 'community', 'points_required' => 500],
            ['name' => 'حكيم الرواق', 'description' => 'بلغ قمة مراتب الأثر القرائي والريادة المعرفية في رِواق.', 'icon' => 'crown', 'category' => 'elite', 'points_required' => 1000],
        ];

        $allBadgeModels = [];
        foreach ($badgesData as $b) {
            $allBadgeModels[] = Badge::updateOrCreate(['name' => $b['name']], $b);
        }

        $user = User::where('email', 'demo@riwaq.test')->first();
        if ($user) {
            $user->badges()->syncWithoutDetaching([$allBadgeModels[0]->id, $allBadgeModels[1]->id]);
        }

        $community = Community::first();
        $bookAyyam = Book::where('title', 'like', '%الأيام%')->first() ?? Book::first();
        $bookMuqaddimah = Book::where('title', 'like', '%المقدمة%')->first() ?? Book::skip(1)->first();

        if ($community) {
            $cb1 = CommunityBadge::firstOrCreate(['community_id' => $community->id, 'name' => 'وسام أديب المجلس'], [
                'description' => 'يُمنح للقراء الأكثر إثراءً للنقاشات النقدية والتأملات البلاغية في مجلس الأدب.',
                'icon' => 'crown',
                'color' => '#C3A15D',
            ]);
            $cb2 = CommunityBadge::firstOrCreate(['community_id' => $community->id, 'name' => 'رفيق القراءة الصامتة'], [
                'description' => 'انضباط وحضور ملهم في جلسات بومودورو القراءة الصامتة الحية.',
                'icon' => 'coffee',
                'color' => '#235738',
            ]);
            $cb3 = CommunityBadge::firstOrCreate(['community_id' => $community->id, 'name' => 'خطيب الحوار المعرفي'], [
                'description' => 'تميز بلاغي في صياغة الأفكار ومحاورة أعضاء المجلس بأدب جم.',
                'icon' => 'sparkles',
                'color' => '#785419',
            ]);

            if ($user && ! $cb1->users()->where('user_id', $user->id)->exists()) {
                $cb1->users()->attach($user->id, [
                    'awarded_by' => $user->id,
                    'reason' => 'تقديراً لمشاركاته الثرية في نقاشات كتاب الشهر وجمال تعليقاته.',
                    'awarded_at' => Carbon::now()->subDays(3),
                ]);
            }

            if ($bookAyyam) {
                CommunityBookHistory::firstOrCreate(['community_id' => $community->id, 'book_id' => $bookAyyam->id, 'month_name' => 'يوليو 2026'], [
                    'read_year' => 2026, 'read_month' => 7, 'notes' => 'قراءة متأنية في سيرة طه حسين الذاتية والوعي التنويري.',
                ]);
            }
            if ($bookMuqaddimah) {
                CommunityBookHistory::firstOrCreate(['community_id' => $community->id, 'book_id' => $bookMuqaddimah->id, 'month_name' => 'أغسطس 2026'], [
                    'read_year' => 2026, 'read_month' => 8, 'notes' => 'مدارسة فصول العمران البشري وطبائع الدول والاجتماع الإنساني.',
                ]);
            }

            if ($user) {
                BookNomination::firstOrCreate(['community_id' => $community->id, 'title' => 'البخلاء'], [
                    'author' => 'الجاحظ',
                    'user_id' => $user->id,
                    'reason' => 'تحفة أدبية ساخرة وتوثيق مذهل للطبائع الإنسانية وأسلوب الجاحظ الفذ في السرد والبيان.',
                    'endorsements_count' => 8,
                    'status' => 'approved',
                ]);

                BookNomination::firstOrCreate(['community_id' => $community->id, 'title' => 'طوق الحمامة في الألفة والأُلاّف'], [
                    'author' => 'ابن حزم الأندلسي',
                    'user_id' => $user->id,
                    'reason' => 'أرقّ دراسة نفسية وأدبية أندلسية في فلسفة العواطف والوفاء الإنساني.',
                    'endorsements_count' => 12,
                    'status' => 'added_to_poll',
                ]);

                // Points transactions
                PointTransaction::firstOrCreate([
                    'user_id' => $user->id,
                    'activity_type' => 'reading_session',
                    'description' => 'جلسة قراءة متأملة في «وحي القلم» (24 صفحة)',
                ], [
                    'points' => 15,
                    'created_at' => Carbon::now()->subDays(2),
                ]);

                PointTransaction::firstOrCreate([
                    'user_id' => $user->id,
                    'activity_type' => 'discussion',
                    'description' => 'مشاركة تأمل «جماليات المجاز عند الرافعي» في مجلس الأدب',
                ], [
                    'points' => 15,
                    'created_at' => Carbon::now()->subDays(3),
                ]);

                PointTransaction::firstOrCreate([
                    'user_id' => $user->id,
                    'activity_type' => 'poll_vote',
                    'description' => 'تصويت لاختيار كتاب الشهر القادم في مجلس الأدب',
                ], [
                    'points' => 10,
                    'created_at' => Carbon::now()->subDays(4),
                ]);

                PointTransaction::firstOrCreate([
                    'user_id' => $user->id,
                    'activity_type' => 'badge_earned',
                    'description' => 'استحقاق وسام «فاتحة الرواق» المعرفي',
                ], [
                    'points' => 50,
                    'created_at' => Carbon::now()->subDays(10),
                ]);
            }
        }
    }
}
