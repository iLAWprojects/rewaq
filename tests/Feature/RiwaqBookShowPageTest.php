<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqBookShowPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_book_show_page_renders_html_for_web_browser(): void
    {
        $book = Book::find(39) ?? Book::first();
        $this->assertNotNull($book, 'Book should exist in database.');

        $response = $this->get("/books/{$book->id}");
        $response->assertStatus(200);
        $response->assertSee($book->title);
        $response->assertSee($book->author);
        $response->assertSee('النسخ الورقية المادية وأماكنها على الرفوف');
        $response->assertSee('المراجعات النقدية وآراء القراء');
    }

    public function test_book_show_endpoint_returns_json_for_ajax_or_api_clients(): void
    {
        $book = Book::find(39) ?? Book::first();
        $this->assertNotNull($book);

        $response = $this->getJson("/books/{$book->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'title',
            'author',
            'category',
            'total_pages',
            'cover_color',
            'copies',
        ]);
        $this->assertEquals($book->id, $response->json('id'));
    }

    public function test_authenticated_reader_can_switch_current_book_from_book_page(): void
    {
        $user = User::factory()->create();
        $book = Book::find(39) ?? Book::first();

        $response = $this->actingAs($user)->post(route('reading.switchBook'), [
            'book_id' => $book->id,
        ]);

        $response->assertRedirect();

        // Check active book
        $active = $user->activeBook();
        $this->assertNotNull($active);
        $this->assertEquals($book->id, $active->book_id);
    }
}
