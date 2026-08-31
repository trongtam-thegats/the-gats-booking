<?php

namespace App\Support;

/**
 * Ba vai tro, co tinh de don gian (user chot lai 2026-08-31).
 *
 * viewer  - Chi xem lich dat ban. Khong sua gi, va khong vao phan phan tich.
 * manager - Xu ly dat ban, dat ban ho khach, va xem phan tich (bao cao, hoa
 *           don, phan tich khach hang). Khong dung toi cau hinh.
 * admin   - Nhu quan ly, cong toan bo cau hinh: dia diem, gio mo, khu vuc, ban,
 *           noi dung trang khach, quan, tai khoan, cai dat gui tin.
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
            self::ADMIN => 'Toàn quyền: xử lý đặt bàn, xem phân tích, và sửa mọi cấu hình.',
            self::MANAGER => 'Xử lý đặt bàn, đặt bàn hộ khách, và xem phân tích. Không sửa cấu hình.',
            self::VIEWER => 'Chỉ xem lịch đặt bàn, không sửa gì và không vào phần phân tích.',
            default => '',
        };
    }

    public static function canWrite(string $role): bool
    {
        return in_array($role, self::CAN_WRITE, true);
    }

    /** Cac vai duoc xem bao cao, hoa don va phan tich khach hang. */
    public const CAN_SEE_ANALYTICS = [self::ADMIN, self::MANAGER];

    /**
     * Vai duoc phep khai bao ban, khu vuc, gio mo cua.
     *
     * Tu 2026-08-31 chi con quan tri: quan ly lo viec dat ban va phan tich,
     * khong dung toi cau hinh cua quan nua.
     */
    public static function canManageSetup(string $role): bool
    {
        return $role === self::ADMIN;
    }

    /** Vai duoc xem bao cao, hoa don va phan tich khach hang. */
    public static function canSeeAnalytics(string $role): bool
    {
        return in_array($role, self::CAN_SEE_ANALYTICS, true);
    }

    /** Chi quan tri duoc tao quan va tai khoan. */
    public static function canManageCompany(string $role): bool
    {
        return $role === self::ADMIN;
    }
}
