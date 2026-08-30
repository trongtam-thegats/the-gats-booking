<?php

namespace App\Support;

/**
 * Duong dan tep tinh kem dau thoi gian sua doi.
 *
 * Nho dau nay ma may chu co the bao trinh duyet giu tep trong bo nho that lau:
 * doi noi dung thi dia chi doi theo, khach khong bao gio phai xoa bo nho dem.
 */
class Assets
{
    /** @var array<string, string> */
    protected static array $cache = [];

    public static function url(string $path): string
    {
        $path = ltrim($path, '/');

        return static::$cache[$path] ??= static::build($path);
    }

    protected static function build(string $path): string
    {
        $file = public_path($path);
        $stamp = is_file($file) ? filemtime($file) : false;

        return asset($path).($stamp ? '?v='.$stamp : '');
    }
}
