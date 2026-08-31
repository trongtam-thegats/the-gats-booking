<?php

namespace App\Support;

/**
 * Nguon don dat ban: khach den voi quan tu dau.
 *
 * Khach tu dat online thi nguon lay tu tham so tren duong dan (moi kenh mot
 * duong dan rieng). Nhan vien dat ho thi tu chon.
 *
 * Co tinh KHONG doan nguon tu Referer: trinh duyet trong ung dung cua Facebook
 * va Instagram che hoac bo han Referer, iOS cung thuong bo. Doan sai con te
 * hon la khong doan.
 */
class NguonDatBan
{
    public const FACEBOOK = 'facebook';

    public const INSTAGRAM = 'instagram';

    public const GOOGLE = 'google';

    public const WEBSITE = 'website';

    public const PHONE = 'phone';

    public const WALK_IN = 'walk_in';

    /** Nguon mac dinh khi khach vao thang trang dat ban, khong qua kenh nao. */
    public const MAC_DINH = self::WEBSITE;

    /** Tham so tren duong dan de danh dau kenh. */
    public const THAM_SO = 'nguon';

    /** Khoa phien luu nguon cua khach trong suot lan ghe tham. */
    public const KHOA_PHIEN = 'nguon_dat_ban';

    /** @var array<string, string> */
    public const NHAN = [
        self::FACEBOOK => 'Facebook',
        self::INSTAGRAM => 'Instagram',
        self::GOOGLE => 'Google',
        self::WEBSITE => 'Website',
        self::PHONE => 'Điện thoại',
        self::WALK_IN => 'Walking',
    ];

    /** Cac nguon nhan vien duoc chon khi dat ho khach. */
    public const NHAN_VIEN_CHON = [
        self::PHONE, self::WALK_IN, self::FACEBOOK, self::INSTAGRAM, self::GOOGLE, self::WEBSITE,
    ];

    /**
     * Cac cach viet tat chap nhan tren duong dan.
     *
     * De quan dan duoc duong dan ngan gon o bio Instagram hay bai dang Facebook,
     * va de tep xuat tu he thong cu van doc duoc.
     *
     * @var array<string, string>
     */
    public const VIET_TAT = [
        'fb' => self::FACEBOOK,
        'facebook' => self::FACEBOOK,
        'ig' => self::INSTAGRAM,
        'insta' => self::INSTAGRAM,
        'instagram' => self::INSTAGRAM,
        'gg' => self::GOOGLE,
        'google' => self::GOOGLE,
        'web' => self::WEBSITE,
        'website' => self::WEBSITE,
        'direct_from_nightify' => self::WEBSITE,
        'venue_initiated_booking' => self::PHONE,
        'phone' => self::PHONE,
        'dien_thoai' => self::PHONE,
        'walk_in' => self::WALK_IN,
        'walkin' => self::WALK_IN,
        'walking' => self::WALK_IN,
        'online' => self::WEBSITE,
    ];

    public static function chuan(?string $nguon): ?string
    {
        $nguon = mb_strtolower(trim((string) $nguon));

        if ($nguon === '') {
            return null;
        }

        return self::VIET_TAT[$nguon] ?? (isset(self::NHAN[$nguon]) ? $nguon : null);
    }

    public static function nhan(?string $nguon): string
    {
        return self::NHAN[(string) $nguon] ?? (string) $nguon;
    }

    /** @return array<int, string> */
    public static function tatCa(): array
    {
        return array_keys(self::NHAN);
    }

    /**
     * Cac kenh co duong dan rieng de dan ra ngoai, kem viet tat goi y.
     *
     * @return array<string, string>
     */
    public static function kenhCoDuongDan(): array
    {
        return [
            self::FACEBOOK => 'fb',
            self::INSTAGRAM => 'ig',
            self::GOOGLE => 'google',
        ];
    }
}
