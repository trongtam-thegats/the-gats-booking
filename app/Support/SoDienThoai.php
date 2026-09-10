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

    /**
     * Cac chuoi CHI SO co the gap trong du lieu, ung voi mot so da chuan hoa.
     *
     * Bang bookings luu nguyen van khach go nen cung mot nguoi nam duoi nhieu
     * dang: "0865683649", "+84 865 683 649", "84865683649". Khi so sanh, cot
     * duoc boc het dau cong va dau cach - nen ve con lai cung phai la chi so,
     * va phai thu ca dang +84.
     *
     * Da tung hong vi cho nay: so nuoc ngoai chuan hoa thanh "+66864161420"
     * dem so voi cot da boc dau cong ("66864161420") thi khong bao gio khop,
     * 133 don dat ban cua khach nuoc ngoai tra khong ra.
     *
     * @return array<int, string>
     */
    public static function bienTheChiSo(string $chuan): array
    {
        $chiSo = (string) preg_replace('/[^0-9]/', '', $chuan);

        if ($chiSo === '') {
            return [];
        }

        $bienThe = [$chiSo];

        // So Viet dang 0xxx co the duoc go vao dang +84xxx.
        if (str_starts_with($chiSo, '0') && strlen($chiSo) >= 9) {
            $bienThe[] = '84'.substr($chiSo, 1);
        }

        return array_values(array_unique($bienThe));
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
