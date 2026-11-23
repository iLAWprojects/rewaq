<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookNomination;
use App\Models\BookPoll;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\CommunityBookHistory;
use App\Models\Discussion;
use App\Models\Library;
use App\Models\LibraryMember;
use App\Models\Loan;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\PointTransaction;
use App\Models\ReadingSession;
use App\Models\Reflection;
use App\Models\User;
use App\Models\UserCurrentBook;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Super Admin User
        $superAdmin = User::firstOrCreate(['email' => 'admin@riwaq.test'], [
            'name' => 'المشرف العام',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'avatar' => 'م',
            'bio' => 'إدارة منصة رِواق المعرفة والإشراف على المحتوى والمجتمعات.',
            'annual_goal' => 30,
            'books_finished' => 12,
            'points' => 950,
            'level' => 5,
        ]);

        // 2. Demo User (القارئ التجريبي)
        $user = User::firstOrCreate(['email' => 'demo@riwaq.test'], [
            'name' => 'القارئ التجريبي',
            'password' => Hash::make('password'),
            'role' => 'user',
            'avatar' => 'ق',
            'bio' => 'شغوف بالأدب العربي، والتاريخ، وتدوين الأفكار الملهمة.',
            'annual_goal' => 24,
            'books_finished' => 8,
            'points' => 340,
            'level' => 3,
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ]);

        // 3. Books
        $bookWahyu = Book::firstOrCreate(['title' => 'وحي القلم'], [
            'author' => 'مصطفى صادق الرافعي',
            'category' => 'أدب',
            'total_pages' => 280,
            'cover_color' => '#315749',
            'isbn' => '978-977-02-1234-5',
            'description' => 'مجموعة مقالات ورسائل أدبية ونقدية وفلسفية تعتبر درة الأدب العربي المعاصر، تفيض بالبلاغة والبيان الإيماني والوجداني.',
        ]);

        $bookAyyam = Book::firstOrCreate(['title' => 'الأيام'], [
            'author' => 'طه حسين',
            'category' => 'سيرة',
            'total_pages' => 220,
            'cover_color' => '#a5654d',
            'isbn' => '978-977-02-5678-9',
            'description' => 'السيرة الذاتية الرائدة لعميد الأدب العربي طه حسين، يسرد فيها رحلة طفولته وشبابه وتحديه للصعاب حتى نال المعرفة والنور.',
        ]);

        $bookMuqaddimah = Book::firstOrCreate(['title' => 'مقدمة ابن خلدون'], [
            'author' => 'ابن خلدون',
            'category' => 'تاريخ',
            'total_pages' => 650,
            'cover_color' => '#4b6068',
            'isbn' => '978-977-02-9988-1',
            'description' => 'أعظم كتاب في فلسفة التاريخ وعلم العمران البشري وعلم الاجتماع، يحلل فيه نشأة الدول وأطوار الحضارات وقوانين المجتمع.',
        ]);

        $bookFusul = Book::firstOrCreate(['title' => 'فصول في الأدب والنقد'], [
            'author' => 'طه حسين',
            'category' => 'أدب',
            'total_pages' => 310,
            'cover_color' => '#214336',
            'isbn' => '978-977-02-4411-2',
            'description' => 'قراءات نقدية رصينة تتناول عيون الأدب العربي القديم وتوازن بين الاتجاهات الفنية والجمالية.',
        ]);

        $bookSayd = Book::firstOrCreate(['title' => 'صيد الخاطر'], [
            'author' => 'ابن الجوزي',
            'category' => 'فكر',
            'total_pages' => 400,
            'cover_color' => '#655c45',
            'isbn' => '978-977-02-3322-7',
            'description' => 'تأملات وخواطر في السلوك والنفس والمجتمع صاغها ابن الجوزي في ومضات فكرية متوهجة تعين على تزكية الروح.',
        ]);

        // 4. Libraries
        $personalLib = Library::firstOrCreate(['code' => 'LIB-PRSN-01'], [
            'user_id' => $user->id,
            'name' => 'المكتبة الشخصية',
            'description' => 'مساحتك الخاصة · أنت مالك المكتبة',
            'is_personal' => true,
            'type' => 'personal',
            'cover_color' => '#173D36',
        ]);

        LibraryMember::firstOrCreate([
            'library_id' => $personalLib->id,
            'user_id' => $user->id,
        ], [
            'role' => 'owner',
        ]);

        $familyLib = Library::firstOrCreate(['code' => 'LIB-FAML-02'], [
            'user_id' => $user->id,
            'name' => 'مكتبة العائلة والرفاق',
            'description' => 'مكتبة مشتركة بين أفراد الأسرة لتبادل الكتب والاستعارة المتبادلة',
            'is_personal' => false,
            'type' => 'family',
            'cover_color' => '#795B44',
        ]);

        LibraryMember::firstOrCreate([
            'library_id' => $familyLib->id,
            'user_id' => $user->id,
        ], [
            'role' => 'owner',
        ]);

        LibraryMember::firstOrCreate([
            'library_id' => $familyLib->id,
            'user_id' => $superAdmin->id,
        ], [
            'role' => 'librarian',
        ]);

        // Physical Copies in Personal Library
        $copyWahyu = BookCopy::firstOrCreate(['barcode' => 'RWQ-1001'], [
            'book_id' => $bookWahyu->id,
            'library_id' => $personalLib->id,
            'copy_number' => '1',
            'status' => 'available',
            'condition' => 'mint',
            'shelf_location' => 'الرف الأدبي - أ3',
        ]);

        $copyAyyam = BookCopy::firstOrCreate(['barcode' => 'RWQ-1002'], [
            'book_id' => $bookAyyam->id,
            'library_id' => $personalLib->id,
            'copy_number' => '1',
            'status' => 'available',
            'shelf_location' => 'رف التراجم والسير - س1',
        ]);

        $copyMuqaddimah = BookCopy::firstOrCreate(['barcode' => 'RWQ-1003'], [
            'book_id' => $bookMuqaddimah->id,
            'library_id' => $personalLib->id,
            'copy_number' => '1',
            'status' => 'available',
            'shelf_location' => 'رف التاريخ والحضارات - ت5',
        ]);

        $copyFusul = BookCopy::firstOrCreate(['barcode' => 'RWQ-1004'], [
            'book_id' => $bookFusul->id,
            'library_id' => $personalLib->id,
            'copy_number' => '1',
            'status' => 'available',
            'shelf_location' => 'الرف الأدبي - أ4',
        ]);

        $copySayd = BookCopy::firstOrCreate(['barcode' => 'RWQ-1005'], [
            'book_id' => $bookSayd->id,
            'library_id' => $personalLib->id,
            'copy_number' => '1',
            'status' => 'available',
            'shelf_location' => 'رف الفكر والتزكية - ف2',
        ]);

        // Active Loan on a family copy of "الأيام"
        $copyFamilyAyyam = BookCopy::firstOrCreate(['barcode' => 'RWQ-2001'], [
            'book_id' => $bookAyyam->id,
            'library_id' => $familyLib->id,
            'copy_number' => '1',
            'status' => 'borrowed',
            'shelf_location' => 'الممر الشرقي',
        ]);

        Loan::firstOrCreate([
            'book_copy_id' => $copyFamilyAyyam->id,
            'user_id' => $user->id,
        ], [
            'borrower_name' => 'القارئ التجريبي',
            'status' => 'active',
            'requested_at' => Carbon::now()->subDays(10),
            'borrowed_at' => Carbon::now()->subDays(9),
            'due_date' => Carbon::create(2026, 9, 20),
            'notes' => 'إعارة نشطة من مكتبة العائلة',
        ]);

        // 5. Current Active Reading
        UserCurrentBook::create([
            'user_id' => $user->id,
            'book_id' => $bookWahyu->id,
            'current_page' => 124,
            'is_active' => true,
        ]);

        // 6. Reading Sessions
        ReadingSession::create([
            'user_id' => $user->id,
            'book_id' => $bookWahyu->id,
            'current_page' => 124,
            'pages_read' => 24,
            'minutes' => 35,
            'note' => 'تأملات عميقة في باب أسرار البلاغة والتناسق بين المعنى واللفظ.',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        ReadingSession::create([
            'user_id' => $user->id,
            'book_id' => $bookWahyu->id,
            'current_page' => 100,
            'pages_read' => 18,
            'minutes' => 25,
            'note' => 'لحظة هادئة وملاحظات حول بلاغة الرافعي في رسم الصور البيانية.',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        // 8. Community: مجلس الأدب
        $community = Community::firstOrCreate(['code' => 'ADAB-2026'], [
            'name' => 'مجلس الأدب',
            'category' => 'الأدب العربي',
            'description' => 'نقرأ النصّ، ونصغي لما يتركه فينا. مساحة لقراءة متأنّية وحوار يُنضج المعنى وتبادل ما تركته الكتب فينا.',
            'cover_color' => '#173D36',
            'creator_id' => $user->id,
            'current_book_id' => $bookWahyu->id,
            'featured_question' => 'أيّ صورة بلاغية بقيت في ذهنك بعد القراءة؟',
        ]);

        $community->members()->syncWithoutDetaching([$user->id => [
            'role' => 'owner',
            'joined_at' => Carbon::now()->subMonths(2),
        ]]);

        // 7. Community Reflections & Highlights
        Reflection::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'book_id' => $bookWahyu->id,
            'content' => '«إذا لم تزد شيئاً على الدنيا كنت أنت زائداً عليها» — كلمة الرافعي الخالدة التي تضع القارئ أمام مسؤوليته المعرفية.',
            'page_number' => 84,
            'is_public' => true,
            'is_highlight' => true,
        ]);

        Reflection::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'book_id' => $bookWahyu->id,
            'content' => '«إن في روح الإنسان سرائر لا يكشفها إلا الأدب الرفيع حين يلامس أوتار الصدق والوجدان.»',
            'page_number' => 122,
            'is_public' => true,
            'is_highlight' => true,
        ]);

        // Additional Communities for Riwaq Councils Showcase
        $commPhil = Community::firstOrCreate(['name' => 'مجلس الرواية الفلسفية الخاص'], [
            'code' => 'PHIL-NOVEL',
            'category' => 'فلسفة ورواية',
            'type' => 'private',
            'description' => 'قراءات في الأدب الفلسفي والروايات الوجودية وتأمل الأسئلة الكبرى.',
            'cover_color' => '#1C3A35',
            'creator_id' => $admin->id,
            'current_book_id' => $bookAyyam->id,
            'featured_question' => 'كيف يعيد الأدب تشكيل المعنى في تجربة الإنسان؟',
        ]);

        $commThink = Community::firstOrCreate(['name' => 'مجلس الفكر المعاصر'], [
            'code' => 'MOD-THINK',
            'category' => 'فكر',
            'type' => 'public',
            'description' => 'مدارسة التيارات الفكرية الحديثة وفلسفة العمران والنهضة الحضارية.',
            'cover_color' => '#2A4D43',
            'creator_id' => $admin->id,
            'current_book_id' => $bookMuqaddimah->id,
            'featured_question' => 'ما شروط البناء الحضاري في فكر ابن خلدون؟',
        ]);

        $commNovel = Community::firstOrCreate(['name' => 'مجلس الرواية العربية'], [
            'code' => 'ARB-NOVEL',
            'category' => 'رواية',
            'type' => 'open',
            'description' => 'احتفاء بعيون الرواية العربية وسرديات الأدب من الخليج إلى المحيط.',
            'cover_color' => '#214D3D',
            'creator_id' => $user->id,
            'current_book_id' => $bookFusul->id,
            'featured_question' => 'ما الرواية التي غيّرت نظرتك للواقع؟',
        ]);
        $commNovel->members()->syncWithoutDetaching([$user->id => ['role' => 'member', 'joined_at' => Carbon::now()->subMonths(1)]]);

        $commPioneers = Community::firstOrCreate(['name' => 'مجلس رواد القراءة'], [
            'code' => 'READ-PIONEER',
            'category' => 'قراءة عامة',
            'type' => 'open',
            'description' => 'مساحة جامعة للقرّاء الجدد ومحبي مدارسة شتى فنون المعرفة والتزكية.',
            'cover_color' => '#1A433A',
            'creator_id' => $admin->id,
            'current_book_id' => $bookSayd->id,
            'featured_question' => 'ما الخاطرة التي تركت أثراً في نفسك اليوم؟',
        ]);

        $commPhilLit = Community::firstOrCreate(['name' => 'مجلس الفلسفة والأدب'], [
            'code' => 'PHIL-LIT',
            'category' => 'فلسفة وأدب',
            'type' => 'specialized',
            'description' => 'تلاقي الفلسفة مع البيان الأدبي واستكشاف مكامن الحكمة والجمال.',
            'cover_color' => '#173D36',
            'creator_id' => $admin->id,
            'current_book_id' => $bookWahyu->id,
            'featured_question' => 'كيف يلهمنا الأدب في صياغة الرؤية الفلسفية؟',
        ]);

        // Discussion in Community
        Discussion::firstOrCreate([
            'community_id' => $community->id,
            'title' => 'جماليات المجاز عند الرافعي',
        ], [
            'user_id' => $user->id,
            'book_id' => $bookWahyu->id,
            'content' => 'استوقفني وصف الرافعي للنفس الإنسانية حين تجد سكينة الإيمان والمعرفة كأنها روضة غناء تفتحت أزهارها بعد الغيث.',
            'is_spoiler' => false,
        ]);

        // Book Poll for Next Read
        $poll = BookPoll::firstOrCreate([
            'community_id' => $community->id,
            'title' => 'أيّ كتاب نقرأ بعد ذلك؟',
        ], [
            'is_active' => true,
            'closes_at' => Carbon::now()->addDays(30),
        ]);

        $poll->options()->firstOrCreate([
            'book_id' => $bookAyyam->id,
        ], [
            'custom_title' => 'الأيام · طه حسين',
            'votes_count' => 14,
        ]);

        $poll->options()->firstOrCreate([
            'book_id' => $bookMuqaddimah->id,
        ], [
            'custom_title' => 'مقدمة ابن خلدون · ابن خلدون',
            'votes_count' => 9,
        ]);

        // 9. Challenges
        $challenge = Challenge::firstOrCreate(['title' => 'آفاق جديدة'], [
            'description' => 'اقرأ ثلاثة كتب من ثلاثة مجالات مختلفة خلال هذا الشهر. يُحتسب الكتاب عند إكمال قراءته ولا تُحتسب القراءة الواحدة مرتين.',
            'category' => 'تنوع معرفي',
            'target_count' => 3,
            'start_date' => Carbon::create(2026, 9, 1),
            'end_date' => Carbon::create(2026, 9, 30),
            'is_active' => true,
            'is_team' => false,
        ]);

        $challenge->participants()->syncWithoutDetaching([$user->id => [
            'progress_count' => 1,
            'completed' => false,
        ]]);

        // Team vs Team Challenge
        $teamChallenge = Challenge::firstOrCreate(['title' => 'تحدي المجالس الكبرى: مجلس الأدب ضد رواد الفكر'], [
            'description' => 'سباق جماعي بين أعضاء المجالس لقراءة 5,000 صفحة مشتركة هذا الشهر لتعزيز روح الالتزام القرائي.',
            'category' => 'تحدي فرق',
            'target_count' => 5000,
            'start_date' => Carbon::create(2026, 9, 1),
            'end_date' => Carbon::create(2026, 9, 30),
            'is_active' => true,
            'is_team' => true,
            'team_a_name' => 'مجلس الأدب',
            'team_b_name' => 'رواد الفكر',
            'team_a_pages' => 3120,
            'team_b_pages' => 2850,
        ]);

        // 10. Meetings
        $meeting = Meeting::firstOrCreate(['title' => 'نقاش «وحي القلم»'], [
            'community_id' => $community->id,
            'book_id' => $bookWahyu->id,
            'meeting_date' => Carbon::create(2026, 9, 17, 19, 0, 0),
            'meeting_type' => 'remote',
            'location' => 'عن بُعد · منصة رِواق',
            'link' => 'https://meet.riwaq.test/adab-wahyu',
            'description' => 'من الصورة الأدبية إلى المعنى: نناقش انطباعاتنا وأسئلتنا حول كتاب وحي القلم للرافعي.',
            'is_past' => false,
        ]);

        // Past Meeting for Audio Archive
        Meeting::firstOrCreate(['title' => 'جلسة استكشافية: مقدمة ابن خلدون ونشأة العمران'], [
            'community_id' => $community->id,
            'book_id' => $bookMuqaddimah->id,
            'meeting_date' => Carbon::create(2026, 9, 3, 19, 0, 0),
            'meeting_type' => 'remote',
            'location' => 'عن بُعد · منصة رِواق (تسجيل صوتي متاح)',
            'link' => 'https://meet.riwaq.test/archive-khaldun',
            'description' => 'محضر ونقاش حول الفصول الأولى في فلسفة التاريخ وقوانين صعود الحضارات.',
            'is_past' => true,
            'summary_notes' => 'ناقش المجلس كيف تتشكّل عصبية المجتمعات، وأهمية العمران البشري كضرورة حتمية، مع مقارنة نقدية بين البداوة والحضارة.',
            'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
        ]);

        MeetingRsvp::firstOrCreate([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
        ], [
            'status' => 'attending',
            'qr_code' => 'RWQ-MEET-20260917-001',
            'checked_in' => false,
        ]);

        // 11. Notifications
        UserNotification::firstOrCreate([
            'user_id' => $user->id,
            'title' => 'موعد مجلس الأدب يقترب',
        ], [
            'body' => '17 سبتمبر · نقاش وحي القلم الساعة 7:00 مساءً.',
            'icon' => 'calendar-days',
            'link' => '/calendar',
            'type' => 'event',
            'is_read' => false,
            'created_at' => Carbon::now()->subHours(4),
        ]);

        UserNotification::firstOrCreate([
            'user_id' => $user->id,
            'title' => 'تذكير بإعادة كتاب',
        ], [
            'body' => 'موعد إعادة كتاب «الأيام» إلى مكتبة العائلة: 20 سبتمبر.',
            'icon' => 'library-big',
            'link' => '/libraries?tab=loans',
            'type' => 'loan',
            'is_read' => false,
            'created_at' => Carbon::now()->subHours(12),
        ]);

        // 12. Global Badges
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
        foreach ($badgesData as $bData) {
            $allBadgeModels[] = Badge::firstOrCreate(['name' => $bData['name']], $bData);
        }

        // Attach initial earned badges for demo user
        $user->badges()->syncWithoutDetaching([$allBadgeModels[0]->id, $allBadgeModels[1]->id]);

        // 13. Community Badges for مجلس الأدب
        $cBadge1 = CommunityBadge::firstOrCreate([
            'community_id' => $community->id,
            'name' => 'وسام أديب المجلس',
        ], [
            'description' => 'يُمنح للقراء الأكثر إثراءً للنقاشات النقدية والتأملات البلاغية في مجلس الأدب.',
            'icon' => 'crown',
            'color' => '#C3A15D',
        ]);

        $cBadge2 = CommunityBadge::firstOrCreate([
            'community_id' => $community->id,
            'name' => 'رفيق القراءة الصامتة',
        ], [
            'description' => 'انضباط وحضور ملهم في جلسات بومودورو القراءة الصامتة الحية.',
            'icon' => 'coffee',
            'color' => '#235738',
        ]);

        $cBadge3 = CommunityBadge::firstOrCreate([
            'community_id' => $community->id,
            'name' => 'خطيب الحوار المعرفي',
        ], [
            'description' => 'تميز بلاغي في صياغة الأفكار ومحاورة أعضاء المجلس بأدب جم.',
            'icon' => 'sparkles',
            'color' => '#785419',
        ]);

        // Award badge to user
        if (! $cBadge1->users()->where('user_id', $user->id)->exists()) {
            $cBadge1->users()->attach($user->id, [
                'awarded_by' => $user->id,
                'reason' => 'تقديراً لمشاركاته الثرية في نقاشات كتاب الشهر وجمال تعليقاته.',
                'awarded_at' => Carbon::now()->subDays(3),
            ]);
        }

        // 14. Book of the Month History for مجلس الأدب
        CommunityBookHistory::firstOrCreate([
            'community_id' => $community->id,
            'book_id' => $bookAyyam->id,
            'month_name' => 'يوليو 2026',
        ], [
            'read_year' => 2026,
            'read_month' => 7,
            'notes' => 'قراءة متأنية في سيرة طه حسين الذاتية والوعي التنويري.',
        ]);

        CommunityBookHistory::firstOrCreate([
            'community_id' => $community->id,
            'book_id' => $bookMuqaddimah->id,
            'month_name' => 'أغسطس 2026',
        ], [
            'read_year' => 2026,
            'read_month' => 8,
            'notes' => 'مدارسة فصول العمران البشري وطبائع الدول والاجتماع الإنساني.',
        ]);

        // 15. Book Nominations for October 2026
        BookNomination::firstOrCreate([
            'community_id' => $community->id,
            'title' => 'البخلاء',
            'author' => 'الجاحظ',
        ], [
            'user_id' => $user->id,
            'reason' => 'تحفة أدبية ساخرة وتوثيق مذهل للطبائع الإنسانية وأسلوب الجاحظ الفذ في السرد والبيان.',
            'endorsements_count' => 8,
            'status' => 'approved',
        ]);

        BookNomination::firstOrCreate([
            'community_id' => $community->id,
            'title' => 'طوق الحمامة في الألفة والأُلاّف',
            'author' => 'ابن حزم الأندلسي',
        ], [
            'user_id' => $user->id,
            'reason' => 'أرقّ دراسة نفسية وأدبية أندلسية في فلسفة العواطف والوفاء الإنساني.',
            'endorsements_count' => 12,
            'status' => 'added_to_poll',
        ]);

        // 16. Initial Point Transactions for Demo User
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
