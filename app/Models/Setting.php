<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cau hinh sua duoc tren web, ghi de gia tri trong .env luc chay.
 *
 * Ly do ton tai: hosting cua chuoi khong cho sua .env, ma token Zalo OA hay
 * API key SMS thi doi kha thuong xuyen. Gia tri duoc cache de khong phai
 * truy van CSDL o moi request.
 */
class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    protected $fillable = ['key', 'value', 'is_secret'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    /**
     * Toan bo cau hinh dang mang key => value.
     * Tra ve mang rong neu bang chua ton tai (luc chua migrate).
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::values()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * Luu mot nhom cau hinh. Gia tri null bi bo qua de o mat khau de trong
     * dong nghia voi "giu nguyen gia tri cu".
     *
     * @param  array<string, string|null>  $values
     * @param  array<int, string>  $secrets
     */
    public static function putMany(array $values, array $secrets = []): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            static::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'is_secret' => in_array($key, $secrets, true)]
            );
        }

        static::flush();
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Cache chua san sang thi thoi, lan doc sau se tu dung lai.
        }
    }
}
