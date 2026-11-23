<?php

namespace App\Services\BookLookup;

class Isbn
{
    /**
     * يزيل الشرطات والمسافات ويعيد الرقم بحروف كبيرة، أو null إن لم يكن ISBN-10 أو ISBN-13 صحيحًا.
     */
    public static function normalize(?string $raw): ?string
    {
        $isbn = strtoupper(preg_replace('/[^0-9Xx]/', '', (string) $raw));

        if (strlen($isbn) === 10 && self::isValid10($isbn)) {
            return $isbn;
        }

        if (strlen($isbn) === 13 && self::isValid13($isbn)) {
            return $isbn;
        }

        return null;
    }

    public static function to13(string $isbn): string
    {
        if (strlen($isbn) === 13) {
            return $isbn;
        }

        $base = '978'.substr($isbn, 0, 9);

        return $base.self::check13($base);
    }

    /**
     * ISBN-10 المقابل (يصلح كـ ASIN في أمازون). null إن لم يكن الرقم بمقدمة 978.
     */
    public static function to10(string $isbn): ?string
    {
        if (strlen($isbn) === 10) {
            return $isbn;
        }

        if (! str_starts_with($isbn, '978')) {
            return null;
        }

        $base = substr($isbn, 3, 9);
        $sum = 0;
        foreach (str_split($base) as $i => $digit) {
            $sum += (10 - $i) * (int) $digit;
        }
        $check = (11 - ($sum % 11)) % 11;

        return $base.($check === 10 ? 'X' : (string) $check);
    }

    /**
     * كل الصيغ الممكنة للرقم نفسه، للبحث في قاعدة البيانات.
     *
     * @return array<int, string>
     */
    public static function variants(string $isbn): array
    {
        return array_values(array_unique(array_filter([$isbn, self::to13($isbn), self::to10($isbn)])));
    }

    private static function isValid10(string $isbn): bool
    {
        if (! preg_match('/^[0-9]{9}[0-9X]$/', $isbn)) {
            return false;
        }

        $sum = 0;
        foreach (str_split($isbn) as $i => $char) {
            $sum += (10 - $i) * ($char === 'X' ? 10 : (int) $char);
        }

        return $sum % 11 === 0;
    }

    private static function isValid13(string $isbn): bool
    {
        return ctype_digit($isbn) && self::check13(substr($isbn, 0, 12)) === (int) $isbn[12];
    }

    private static function check13(string $first12): int
    {
        $sum = 0;
        foreach (str_split($first12) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
