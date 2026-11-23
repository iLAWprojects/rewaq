<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiwaqLibrarySettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeLibrary(User $owner, array $overrides = []): Library
    {
        return Library::create($overrides + [
            'user_id' => $owner->id, 'name' => 'مكتبة الأصدقاء', 'type' => 'personal',
            'is_personal' => false, 'code' => 'LIB-'.strtoupper(substr(uniqid(), -6)),
        ]);
    }

    public function test_owner_sees_settings_button_and_can_rename_and_make_public(): void
    {
        $owner = User::factory()->create();
        $library = $this->makeLibrary($owner);

        $this->actingAs($owner)->get(route('libraries.index', ['library_id' => $library->id]))
            ->assertOk()->assertSee('إعدادات المكتبة');

        $this->actingAs($owner)->put(route('libraries.updateSettings', $library->id), [
            'name' => 'اسم جديد', 'description' => 'وصف', 'type' => 'public', 'cover_color' => '#795B44',
        ])->assertSessionHas('success');

        $library->refresh();
        $this->assertSame('اسم جديد', $library->name);
        $this->assertSame('public', $library->type);
    }

    public function test_non_owner_cannot_update_or_delete_library(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $library = $this->makeLibrary($owner);

        $this->actingAs($other)->put(route('libraries.updateSettings', $library->id), [
            'name' => 'اختراق', 'type' => 'public',
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('libraries.forceDestroy', $library->id), ['confirm_name' => $library->name])
            ->assertForbidden();
    }

    public function test_personal_library_stays_private_and_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $personal = $owner->getPersonalLibrary();

        $this->actingAs($owner)->put(route('libraries.updateSettings', $personal->id), [
            'name' => 'مكتبتي', 'type' => 'public',
        ]);
        $this->assertSame('personal', $personal->fresh()->type);

        $this->actingAs($owner)->delete(route('libraries.forceDestroy', $personal->id), ['confirm_name' => $personal->fresh()->name])
            ->assertSessionHas('error');
        $this->assertNotNull(Library::find($personal->id));
    }

    public function test_force_delete_requires_matching_name_and_removes_library(): void
    {
        $owner = User::factory()->create();
        $library = $this->makeLibrary($owner);

        $this->actingAs($owner)->delete(route('libraries.forceDestroy', $library->id), ['confirm_name' => 'خطأ'])
            ->assertSessionHas('error');
        $this->assertNotNull(Library::find($library->id));

        $this->actingAs($owner)->delete(route('libraries.forceDestroy', $library->id), ['confirm_name' => $library->name])
            ->assertSessionHas('success');
        $this->assertNull(Library::withTrashed()->find($library->id));
    }

    public function test_personal_library_rejects_second_copy_of_same_book_but_shared_library_allows_it(): void
    {
        $owner = User::factory()->create();
        $book = Book::create(['title' => 'كتاب مكرر', 'author' => 'م', 'category' => 'أدب', 'total_pages' => 100]);
        $personal = $owner->getPersonalLibrary();
        $payload = fn ($libraryId) => ['title' => 'كتاب مكرر', 'author' => 'م', 'category' => 'أدب', 'existing_book_id' => $book->id, 'library_id' => $libraryId];

        $this->actingAs($owner)->post(route('libraries.storeBook'), $payload($personal->id))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('libraries.storeBook'), $payload($personal->id))->assertSessionHas('warning');
        $this->assertSame(1, BookCopy::where('library_id', $personal->id)->where('book_id', $book->id)->count());

        $shared = $this->makeLibrary($owner);
        $this->actingAs($owner)->post(route('libraries.storeBook'), $payload($shared->id))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('libraries.storeBook'), $payload($shared->id))->assertSessionHas('success');
        $this->assertSame(2, BookCopy::where('library_id', $shared->id)->where('book_id', $book->id)->count());
    }
}
