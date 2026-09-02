<?php

namespace App\Support;

use App\Models\GuestNote;
use App\Models\PosCustomer;

/**
 * Ten hien thi cua mot khach, chon tu nhieu nguon theo thu tu tin cay.
 *
 * Cung mot so dien thoai co the mang bon cai ten khac nhau: nhan vien tu ghi
 * chu, the khach hang ben POS, ten tren hoa don, va ten khach tu go luc dat
 * ban. Truoc day moi cho tu chon mot kieu nen bao cao dem mot nguoi thanh
 * nhieu nguoi. Gio moi noi deu goi ve day.
 *
 * Thu tu:
 *   1. Ghi chu cua nhan vien - nguoi that sua tay, dang tin nhat.
 *   2. The khach hang POS - ho so chinh thuc cua chuoi.
 *   3. Cac nguon du phong truyen vao (hoa don, dat ban) theo dung thu tu goi.
 *
 * DUNG tao ban thu hai cua quy tac nay o cho khac. Bai hoc tu loi gio ca khuya
 * hoi 30/8: hai ban sao lech nhau la du de sinh mot loi song rat lau.
 */
class TenKhach
{
    /**
     * @param  ?string  ...$duPhong  Ten lay tu hoa don, dat ban... theo thu tu uu tien giam dan
     */
    public static function chon(?GuestNote $ghiChu, ?PosCustomer $the, ?string ...$duPhong): ?string
    {
        $ungVien = array_merge([$ghiChu?->name, $the?->name], $duPhong);

        foreach ($ungVien as $ten) {
            $ten = trim((string) $ten);

            if ($ten !== '') {
                return $ten;
            }
        }

        return null;
    }

    /**
     * Nguon cua cai ten dang duoc dung, de man hinh noi ro cho nguoi dung biet
     * ten nay tu dau ra thay vi hien mot cai ten khong ro goc.
     */
    public static function nguon(?GuestNote $ghiChu, ?PosCustomer $the): ?string
    {
        if (filled($ghiChu?->name)) {
            return 'ghi chú của quán';
        }

        if (filled($the?->name)) {
            return 'danh sách khách hàng';
        }

        return null;
    }
}
