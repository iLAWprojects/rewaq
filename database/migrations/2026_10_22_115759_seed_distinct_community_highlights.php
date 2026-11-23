<?php

use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = User::first();
        if (! $user) {
            return;
        }

        $userId = $user->id;

        // Fix existing reflections 41 & 42
        DB::table('reflections')->where('id', 41)->update([
            'community_id' => 38,
            'is_highlight' => true,
        ]);

        DB::table('reflections')->where('id', 42)->update([
            'community_id' => 40,
            'is_highlight' => true,
        ]);

        // Remove duplicates 43 & 44 if they exist
        DB::table('reflections')->whereIn('id', [43, 44])->delete();

        // Distinct highlights tailored for each community
        $communityData = [
            // مجلس الأدب (ADAB-2026 - book: 101 وحي القلم)
            38 => [
                'book_id' => 101,
                'quotes' => [
                    [
                        'content' => '«إن في روح الإنسان سرائر لا يكشفها إلا الأدب الرفيع حين يلامس أوتار الصدق والوجدان.»',
                        'page_number' => 122,
                        'likes_count' => 3,
                    ],
                    [
                        'content' => '«ألا ليت الحكمة تولد مع الإنسان، فلا يحتاج إلى مرارة الأيام وتجارب السنين ليتعلم معنى الحياة.»',
                        'page_number' => 195,
                        'likes_count' => 2,
                    ],
                ],
            ],

            // مجلس الرواية الفلسفية الخاص (PHIL-NOVEL - book: 102 الأيام)
            39 => [
                'book_id' => 102,
                'quotes' => [
                    [
                        'content' => '«كان الصبي يرى في الكتاب والنور مخرجه الوحيد من عتمة البصر إلى فضاء البصيرة الشاسع وفجر المعرفة.»',
                        'page_number' => 54,
                        'likes_count' => 4,
                    ],
                    [
                        'content' => '«تذوق مرارة الحرمان في سبيل طلب العلم يصنع نفساً أبية لا تنكسر أمام عواصف الزمان وعقباته.»',
                        'page_number' => 110,
                        'likes_count' => 1,
                    ],
                    [
                        'content' => '«إنما المعرفة أجنحة يحلق بها العقل فوق قيود المكان وضيق الحاضر نحو أفق لا يحده زمن.»',
                        'page_number' => 168,
                        'likes_count' => 3,
                    ],
                ],
            ],

            // مجلس الفكر المعاصر (MOD-THINK - book: 103 مقدمة ابن خلدون)
            40 => [
                'book_id' => 103,
                'quotes' => [
                    [
                        'content' => '«الظلم مؤذن بخراب العمران، والعدل أساس استقرار المجتمعات وازدهار الصنائع وحفظ الدول.»',
                        'page_number' => 288,
                        'likes_count' => 5,
                    ],
                    [
                        'content' => '«إن التاريخ في ظاهره لا يزيد عن الإخبار، ولكن في باطنه نظر وتحقيق وتعليل للكائنات ومبادئها دقيق.»',
                        'page_number' => 12,
                        'likes_count' => 2,
                    ],
                ],
            ],

            // مجلس الرواية العربية (ARB-NOVEL - book: 104 فصول في الأدب والنقد)
            41 => [
                'book_id' => 104,
                'quotes' => [
                    [
                        'content' => '«النقد الأدبي ليس هجوماً على الكاتب، بل هو مرآة صافية تصقل موهبته وتكشف مواطن الجمال الأصيل.»',
                        'page_number' => 68,
                        'likes_count' => 2,
                    ],
                    [
                        'content' => '«الرواية العربية صوت المجتمع الحي، تجسد همومه وأحلامه وتفتح نوافذ الوعي على واقع جديد.»',
                        'page_number' => 142,
                        'likes_count' => 3,
                    ],
                    [
                        'content' => '«بين السطور الأدبية تكمن تجارب أجيال خلت، نقرؤها لنفهم حاضرنا ونرسم مستقبلنا بوعي أعمق.»',
                        'page_number' => 215,
                        'likes_count' => 1,
                    ],
                ],
            ],

            // مجلس رواد القراءة (READ-PIONEER - book: 105 صيد الخاطر)
            42 => [
                'book_id' => 105,
                'quotes' => [
                    [
                        'content' => '«الوقت أنفس ما عنيت بحفظه، وأراه أسهل ما عليك يضيع! فاغتنم دقائق العمر في نيل الفضائل.»',
                        'page_number' => 72,
                        'likes_count' => 6,
                    ],
                    [
                        'content' => '«العلم والعمل توأمان؛ أحدهما يزكو بالآخر، ولا ينفع حفظ النصوص دون فقه المقاصد وحسن الخلق.»',
                        'page_number' => 134,
                        'likes_count' => 4,
                    ],
                    [
                        'content' => '«من قارب الفتنة بَعُدَت عنه السلامة، وإنما الحازم من قيّد خواطره بحبال الورع واليقظة الدائمة.»',
                        'page_number' => 250,
                        'likes_count' => 2,
                    ],
                ],
            ],

            // مجلس الفلسفة والأدب (PHIL-LIT - book: 101 وحي القلم)
            43 => [
                'book_id' => 101,
                'quotes' => [
                    [
                        'content' => '«الفلسفة هي البحث عن سر المعنى في الوجود، والأدب هو النور الذي يجعل هذا المعنى ينبض بالحياة والجمال.»',
                        'page_number' => 91,
                        'likes_count' => 3,
                    ],
                    [
                        'content' => '«حين تتحد بلاغة اللسان مع عمق الفكرة، يولد فكر رصين يحرر العقول من قيود التقليد الأعمى.»',
                        'page_number' => 178,
                        'likes_count' => 2,
                    ],
                    [
                        'content' => '«إن الكلمة الصادقة كالشجرة الطيبة، تمتد جذورها في وجدان القارئ وتثمر حكمة وتأملاً لا ينفد.»',
                        'page_number' => 230,
                        'likes_count' => 5,
                    ],
                ],
            ],
        ];

        foreach ($communityData as $commId => $data) {
            // Verify community exists
            if (! DB::table('communities')->where('id', $commId)->exists()) {
                continue;
            }

            foreach ($data['quotes'] as $q) {
                // Avoid inserting duplicates
                $exists = DB::table('reflections')
                    ->where('community_id', $commId)
                    ->where('content', $q['content'])
                    ->exists();

                if (! $exists) {
                    DB::table('reflections')->insert([
                        'user_id' => $userId,
                        'community_id' => $commId,
                        'book_id' => $data['book_id'],
                        'content' => $q['content'],
                        'page_number' => $q['page_number'],
                        'is_public' => true,
                        'privacy' => 'public',
                        'is_highlight' => true,
                        'likes_count' => $q['likes_count'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert seeded records
    }
};
