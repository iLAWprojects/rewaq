<?php

namespace App\Services\BookLookup;

interface IsbnProvider
{
    /** المعرّف المختصر المستخدم في الإعدادات (openlibrary, googlebooks, worldcat, amazon). */
    public function key(): string;

    /** الاسم المعروض للمستخدم. */
    public function label(): string;

    /** هل بيانات الاعتماد المطلوبة متوفرة؟ المزوّد غير المهيّأ يُتجاوز بصمت. */
    public function isConfigured(): bool;

    /**
     * @return array{title?: string, author?: string, publisher?: string, pages?: int, category?: string, language?: string, description?: string, cover?: string}|null
     */
    public function find(string $isbn): ?array;
}
