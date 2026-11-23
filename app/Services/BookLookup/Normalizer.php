<?php

namespace App\Services\BookLookup;

class Normalizer
{
    private const LANGUAGES = [
        'ar' => 'العربية', 'ara' => 'العربية', 'arabic' => 'العربية',
        'en' => 'الإنجليزية', 'eng' => 'الإنجليزية', 'english' => 'الإنجليزية',
        'fr' => 'الفرنسية', 'fre' => 'الفرنسية', 'fra' => 'الفرنسية', 'french' => 'الفرنسية',
        'de' => 'الألمانية', 'ger' => 'الألمانية', 'deu' => 'الألمانية',
        'es' => 'الإسبانية', 'spa' => 'الإسبانية',
        'tr' => 'التركية', 'tur' => 'التركية',
        'fa' => 'الفارسية', 'per' => 'الفارسية', 'fas' => 'الفارسية',
    ];

    /** كلمات مفتاحية إنجليزية/عربية تُرجَّح إلى تصنيفات رِواق. */
    private const CATEGORIES = [
        'فلسفة' => ['philosoph', 'فلسف'],
        'شعر' => ['poetry', 'poems', 'شعر', 'ديوان'],
        'سيرة' => ['biograph', 'autobiograph', 'memoir', 'سيرة', 'مذكرات'],
        'تاريخ' => ['history', 'histor', 'تاريخ'],
        'فكر' => ['religion', 'islam', 'politic', 'sociolog', 'psycholog', 'thought', 'فكر', 'اجتماع', 'دين'],
        'أدب' => ['fiction', 'novel', 'literature', 'stories', 'drama', 'أدب', 'رواية', 'قصص'],
    ];

    public static function language(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return self::LANGUAGES[strtolower(trim($code))] ?? null;
    }

    /**
     * @param  array<int, string>  $subjects
     */
    public static function category(array $subjects): ?string
    {
        $haystack = mb_strtolower(implode(' | ', $subjects));

        foreach (self::CATEGORIES as $category => $needles) {
            foreach ($needles as $needle) {
                if ($haystack !== '' && str_contains($haystack, $needle)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * يرفع الصور إلى https ويحذف المعاملات التي تقصّ الغلاف في جوجل.
     */
    public static function cover(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = preg_replace('#^http://#i', 'https://', $url);
        $url = str_replace('&edge=curl', '', $url);

        return $url;
    }

    /**
     * يحذف الحقول الفارغة ويُنظّف النصوص.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value));
            }
            if ($value === null || $value === '' || $value === 0) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
