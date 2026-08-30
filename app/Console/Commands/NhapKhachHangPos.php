<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\PosImportService;
use Illuminate\Console\Command;

/**
 * Nhap the khach hang xuat tu POS (Sapo) vao bang pos_customers.
 *
 * Tep nay bo sung nhung gi hoa don khong noi: hang the, diem tich luy, sinh
 * nhat, ngay tro thanh khach quen. Khop voi hoa don va don dat ban qua so
 * dien thoai da chuan hoa.
 *
 *   php artisan pos:nhap-khach-hang danh_sach_khach_hang.xlsx
 *   php artisan pos:nhap-khach-hang danh_sach_khach_hang.xlsx --ghi
 *
 * Khong khai --quan thi coi la danh sach chung ca chuoi (brand_id de trong).
 */
class NhapKhachHangPos extends Command
{
    protected $signature = 'pos:nhap-khach-hang
        {tep? : Duong dan toi tep .xlsx xuat tu POS}
        {--quan= : Slug cua quan neu danh sach chi thuoc mot quan}
        {--ghi : That su ghi vao co so du lieu}';

    protected $description = 'Nhap the khach hang tu tep xlsx cua POS';

    public function handle(PosImportService $nhap): int
    {
        $tep = (string) $this->argument('tep');

        if (! is_file($tep)) {
            $this->error('Khong thay tep: '.$tep);

            return self::FAILURE;
        }

        $brandId = null;

        if ($quan = $this->option('quan')) {
            $brand = Brand::where('slug', $quan)->first();

            if (! $brand) {
                $this->error('Khong thay quan: '.$quan);

                return self::FAILURE;
            }

            $brandId = $brand->id;
        }

        $ghi = (bool) $this->option('ghi');

        $this->line($ghi ? 'Che do: GHI THAT' : 'Che do: chi xem truoc, khong ghi gi');
        $this->line($brandId ? 'Gan vao quan: '.$quan : 'Danh sach chung ca chuoi');

        try {
            $ketQua = $nhap->khachHang($tep, $brandId, $ghi);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Doc duoc '.$ketQua['tong'].' dong.');
        $this->newLine();
        $this->line('Khach moi         : '.$ketQua['moi']);
        $this->line('Cap nhat lai      : '.$ketQua['capNhat']);
        $this->line('Bo qua (khong sdt): '.$ketQua['khongSdt']);
        $this->line('Bo qua (trung so) : '.$ketQua['trung']);

        if (! $ghi) {
            $this->newLine();
            $this->comment('Chua ghi gi ca. Chay lai kem --ghi de luu that.');
        }

        return self::SUCCESS;
    }
}
