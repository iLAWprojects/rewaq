<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Community;
use App\Models\Reflection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqReflectionHighlightLikesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * اختبار التبديل التفاعلي للأثر (Like / Unlike) على خواطر وتأملات المجلس
     */
    public function test_authenticated_user_can_toggle_like_on_reflection_highlight(): void
    {
        $author = User::factory()->create();
        $liker = User::factory()->create();
        $book = Book::first() ?? Book::factory()->create();

        $reflection = Reflection::create([
            'user_id' => $author->id,
            'book_id' => $book->id,
            'content' => 'تأمل معرفي عميق حول فلسفة القراءة والحكمة.',
            'page_number' => 42,
            'is_public' => true,
            'privacy' => 'public',
            'is_highlight' => true,
            'likes_count' => 0,
        ]);

        // 1. First like (إعجاب / ترك أثر)
        $response1 = $this->actingAs($liker)->postJson(route('reflections.like', $reflection));
        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_liked' => true,
                'likes_count' => 1,
            ]);

        $reflection->refresh();
        $this->assertEquals(1, $reflection->likes_count);
        $this->assertTrue($reflection->isLikedBy($liker));
        $this->assertDatabaseHas('reflection_likes', [
            'reflection_id' => $reflection->id,
            'user_id' => $liker->id,
        ]);

        // Check author received notification
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'type' => 'reflection_like',
        ]);

        // 2. Second like (إلغاء الإعجاب / سحب الأثر - Dislike/Unlike)
        $response2 = $this->actingAs($liker)->postJson(route('reflections.like', $reflection));
        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_liked' => false,
                'likes_count' => 0,
            ]);

        $reflection->refresh();
        $this->assertEquals(0, $reflection->likes_count);
        $this->assertFalse($reflection->isLikedBy($liker));
        $this->assertDatabaseMissing('reflection_likes', [
            'reflection_id' => $reflection->id,
            'user_id' => $liker->id,
        ]);
    }

    /**
     * اختبار منع الزائر غير المسجل من تسجيل الأثر
     */
    public function test_guest_cannot_toggle_like_on_reflection(): void
    {
        $user = User::factory()->create();
        $book = Book::first() ?? Book::factory()->create();
        $reflection = Reflection::create([
            'user_id' => $user->id,
            'content' => 'اقتباس مميز',
            'book_id' => $book->id,
            'is_public' => true,
            'is_highlight' => true,
        ]);

        $response = $this->postJson(route('reflections.like', $reflection));
        $response->assertStatus(401);
    }

    /**
     * اختبار ظهور حالة الأثر (is-liked) والعدد الفعلي في صفحة المجالس
     */
    public function test_communities_page_renders_actual_likes_and_active_state(): void
    {
        $user = User::factory()->create();
        $book = Book::first() ?? Book::factory()->create();
        $community = Community::first() ?? Community::factory()->create();

        $reflection = Reflection::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'book_id' => $book->id,
            'content' => 'نص الخاطرة المعجمية البارزة بالمجلس',
            'is_public' => true,
            'is_highlight' => true,
            'likes_count' => 0,
        ]);

        // User likes the highlight
        $reflection->toggleLike($user);
        $reflection->refresh();

        $response = $this->actingAs($user)->get(route('communities.index', ['community_id' => $community->id]));
        $response->assertStatus(200);

        // Assert reflection content is displayed
        $response->assertSee('نص الخاطرة المعجمية البارزة بالمجلس');

        // Assert button has is-liked class and count is 1
        $response->assertSee('data-reflection-id="'.$reflection->id.'"', false);
        $response->assertSee('is-liked', false);
        $response->assertSee('<b class="react-cnt">1</b>', false);
    }

    /**
     * اختبار تخصيص معجم الأفكار لكل مجلس على حدة وعدم تكرار الخواطر بين المجالس
     */
    public function test_community_highlights_are_strictly_isolated_per_community(): void
    {
        $user = User::factory()->create();
        $book = Book::first() ?? Book::factory()->create();

        $commA = Community::create([
            'name' => 'مجلس ألف للاختبار',
            'category' => 'فكر',
            'description' => 'وصف مجلس أ',
            'code' => 'TEST-AAA1',
            'creator_id' => $user->id,
            'current_book_id' => $book->id,
        ]);

        $commB = Community::create([
            'name' => 'مجلس باء للاختبار',
            'category' => 'أدب',
            'description' => 'وصف مجلس ب',
            'code' => 'TEST-BBB2',
            'creator_id' => $user->id,
            'current_book_id' => $book->id,
        ]);

        $uniqueContentA = 'خاطرة مميزة وفريدة تخص مجلس ألف فقط ولا تظهر لغيره';
        $uniqueContentB = 'خاطرة مختلفة كلياً تخص مجلس باء حصراً';

        Reflection::create([
            'user_id' => $user->id,
            'community_id' => $commA->id,
            'book_id' => $book->id,
            'content' => $uniqueContentA,
            'is_public' => true,
            'is_highlight' => true,
        ]);

        Reflection::create([
            'user_id' => $user->id,
            'community_id' => $commB->id,
            'book_id' => $book->id,
            'content' => $uniqueContentB,
            'is_public' => true,
            'is_highlight' => true,
        ]);

        // When viewing Community A:
        $respA = $this->actingAs($user)->get(route('communities.index', ['community_id' => $commA->id]));
        $respA->assertStatus(200);
        $respA->assertSee($uniqueContentA);
        $respA->assertDontSee($uniqueContentB);

        // When viewing Community B:
        $respB = $this->actingAs($user)->get(route('communities.index', ['community_id' => $commB->id]));
        $respB->assertStatus(200);
        $respB->assertSee($uniqueContentB);
        $respB->assertDontSee($uniqueContentA);
    }

    /**
     * اختبار إضافة تأمل جديد من عضو المجلس وربطه مباشرة بالمجلس الحالي
     */
    public function test_member_can_store_community_highlight(): void
    {
        $user = User::factory()->create();
        $book = Book::first() ?? Book::factory()->create();

        $community = Community::create([
            'name' => 'مجلس الإشراق',
            'category' => 'فلسفة',
            'description' => 'وصف المجلس',
            'code' => 'TEST-SHRQ',
            'creator_id' => $user->id,
            'current_book_id' => $book->id,
        ]);

        $community->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

        $newHighlightText = 'هذا الاقتباس الجديد الذي أضفته بنفسي لمجلس الإشراق';

        $response = $this->actingAs($user)->post(route('communities.highlights.store', $community->id), [
            'content' => $newHighlightText,
            'book_id' => $book->id,
            'page_number' => 88,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reflections', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'content' => $newHighlightText,
            'is_highlight' => true,
        ]);

        // Verify it appears in this community's page
        $viewResp = $this->actingAs($user)->get(route('communities.index', ['community_id' => $community->id]));
        $viewResp->assertSee($newHighlightText);
    }

    /**
     * اختبار إضافة اقتباس يحمل علامة حرق الأحداث وظهوره معتّماً مع خيار الكشف
     */
    public function test_user_can_add_quote_with_spoiler_and_view_renders_spoiler_blur(): void
    {
        $user = User::factory()->create();
        $book = Book::first() ?? Book::factory()->create();

        $community = Community::create([
            'name' => 'مجلس الروايات العميقة',
            'category' => 'رواية',
            'description' => 'وصف المجلس',
            'code' => 'TEST-SPOIL',
            'creator_id' => $user->id,
            'current_book_id' => $book->id,
        ]);

        $community->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

        $spoilerQuote = 'مشهد النهاية الصادم وموت البطل في الفصل الأخير';

        // 1. Post quote with is_spoiler = 1
        $response = $this->actingAs($user)->post(route('communities.highlights.store', $community->id), [
            'content' => $spoilerQuote,
            'book_id' => $book->id,
            'page_number' => 312,
            'is_spoiler' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reflections', [
            'community_id' => $community->id,
            'content' => $spoilerQuote,
            'is_spoiler' => true,
        ]);

        // 2. Assert page renders spoiler warnings and blur
        $viewResp = $this->actingAs($user)->get(route('communities.index', ['community_id' => $community->id]));
        $viewResp->assertStatus(200);
        $viewResp->assertSee('حرق أحداث');
        $viewResp->assertSee('spoiler-blur');
        $viewResp->assertSee('انقر لكشف الاقتباس');
        $viewResp->assertSee($spoilerQuote);
    }
}
