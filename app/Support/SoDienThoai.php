<?php

namespace App\Support;

/**
 * Chuan hoa so dien thoai de ghep du lieu tu nhieu nguon ve cung mot khach.
 *
 * POS, Nightify va form dat ban moi noi ghi mot kieu: "+84 354374027",
 * "0354374027", hoac 354374027 (o Excel dinh dang so nen mat so 0 dau).
 * Tat ca deu phai ve mot dang thi moi noi duoc lich su cua cung mot nguoi.
 */
class SoDienThoai
{
    /**
     * Dang dung de luu va so sanh: so Viet Nam ve 0xxxxxxxxx,
     * so nuoc ngoai giu ma quoc gia kem dau cong.
     */
    public static function chuan(string|float|int|null $so): string
    {
        if ($so === null) {
            return '';
        }

        // O Excel dinh dang so: 769689017.0 von la 0769689017.
        if (is_float($so) || is_int($so)) {
            $so = number_format((float) $so, 0, '', '');
            $so = strlen($so) === 9 ? '0'.$so : $so;
        }

        $so = trim((string) $so);

        if ($so === '') {
            return '';
        }

        $quocTe = str_starts_with($so, '+');
        $chiSo = (string) preg_replace('/[^0-9]/', '', $so);

        if ($chiSo === '') {
            return '';
        }

        // 84xxxxxxxxx -> 0xxxxxxxxx. Chi doi khi do dai dung voi so Viet Nam,
        // tranh cat nham so nuoc ngoai tinh co bat dau bang 84.
        if (str_starts_with($chiSo, '84') && strlen($chiSo) >= 11 && strlen($chiSo) <= 12) {
            return '0'.substr($chiSo, 2);
        }

        if (! $quocTe && strlen($chiSo) === 9 && ! str_starts_with($chiSo, '0')) {
            return '0'.$chiSo;
        }

        return $quocTe ? '+'.$chiSo : $chiSo;
    }

    /** Che bot so giua khi khong duoc phep xem day du. */
    public static function che(string $so): string
    {
        $so = trim($so);

        return strlen($so) > 6
            ? substr($so, 0, 4).str_repeat('•', max(0, strlen($so) - 6)).substr($so, -2)
            : $so;
    }
}
