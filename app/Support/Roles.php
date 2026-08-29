<?php

namespace App\Support;

/**
 * Ba vai tro, co tinh de don gian.
 *
 * admin   - Ban Giam doc / IT: toan quyen, thay moi quan, sua duoc cau hinh he thong.
 * manager - Quan ly quan: xu ly dat ban, xep ban, khai bao ban va gio mo cua quan minh.
 * viewer  - Chi xem: nhin duoc lich dat ban, khong sua duoc gi.
 */
class Roles
{
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const VIEWER = 'viewer';

    public const ALL = [self::ADMIN, self::MANAGER, self::VIEWER];

    /** Cac vai duoc phep thay doi du lieu (xac nhan, huy, xep ban, khai bao ban). */
    public const CAN_WRITE = [self::ADMIN, self::MANAGER];

    public static function label(string $role): string
    {
        return match ($role) {
            self::ADMIN => 'Quản trị',
            self::MANAGER => 'Quản lý',
            self::VIEWER => 'Chỉ xem',
            default => $role,
        };
    }

    public static function description(string $role): string
    {
        return match ($role) {
            self::ADMIN => 'Toàn quyền, thấy mọi quán, sửa được cấu hình hệ thống.',
            self::MANAGER => 'Xử lý đặt bàn và khai báo bàn, giờ mở của quán mình.',
            self::VIEWER => 'Chỉ xem lịch đặt bàn, không sửa được gì.',
            default => '',
        };
    }

    public static function canWrite(string $role): bool
    {
        return in_array($role, self::CAN_WRITE, true);
    }

    /** Vai duoc phep khai bao ban, khu vuc, gio mo cua. */
    public static function canManageSetup(string $role): bool
    {
        return in_array($role, [self::ADMIN, self::MANAGER], true);
    }

    /** Chi quan tri duoc tao quan va tai khoan. */
    public static function canManageCompany(string $role): bool
    {
        return $role === self::ADMIN;
    }
}
