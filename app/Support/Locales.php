<?php

namespace App\Support;

/**
 * Ngon ngu cua trang dat ban. Khu quan tri chi co tieng Viet.
 */
class Locales
{
    public const DEFAULT = 'vi';

    /** Ma ngon ngu => [ten hien tren nut, nhan ngan]. */
    public const ALL = [
        'vi' => ['Tiếng Việt', 'VI'],
        'en' => ['English', 'EN'],
    ];

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function supported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::ALL);
    }

    public static function label(string $locale): string
    {
        return self::ALL[$locale][0] ?? $locale;
    }

    public static function short(string $locale): string
    {
        return self::ALL[$locale][1] ?? strtoupper($locale);
    }
}
