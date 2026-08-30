<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Services\PosImportService;
use Illuminate\Console\Command;

/**
 * Nhap danh sach hoa don xuat tu POS (Sapo) vao bang invoices.
 *
 * Mac dinh chi xem truoc. Them --ghi moi that su luu.
 *
 *   php artisan pos:nhap-hoa-don danh_sach_hoa_don.xlsx --quan=drinking-healing
 *   php artisan pos:nhap-hoa-don danh_sach_hoa_don.xlsx --quan=drinking-healing --ghi
 *
 * Phan doc tep nam o PosImportService, dung chung voi nut tai tep trong khu
 * quan ly - hai loi vao khong bao gio hieu tep khac nhau.
 */
class NhapHoaDonPos extends Command
{
    protected $signature = 'pos:nhap-hoa-don
        {tep? : Duong dan toi tep .xlsx xuat tu POS}
        {--quan= : Slug cua quan, vi du drinking-healing}
        {--dia-diem= : Slug dia diem, mac dinh lay dia diem dau tien cua quan}
        {--ghi : That su ghi vao co so du lieu}
        {--xoa : Xoa toan bo hoa don cua dia diem roi dung}';

    protected $description = 'Nhap danh sach hoa don tu tep xlsx cua POS';

    public function handle(PosImportService $nhap): int
    {
        $branch = $this->diaDiem();

        if (! $branch) {
            return self::FAILURE;
        }

        if ($this->option('xoa')) {
            return $this->xoaHoaDon($branch);
        }

        $tep = (string) $this->argument('tep');

        if (! is_file($tep)) {
            $this->error('Khong thay tep: '.$tep);

            return self::FAILURE;
        }

        $ghi = (bool) $this->option('ghi');

        $this->info('Dia diem: '.$branch->name.' ('.$branch->brand?->name.')');
        $this->line($ghi ? 'Che do: GHI THAT' : 'Che do: chi xem truoc, khong ghi gi');

        try {
            $ketQua = $nhap->hoaDon($tep, $branch, $ghi);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Doc duoc '.$ketQua['tong'].' dong.');
        $this->newLine();
        $this->line('Hoa don moi     : '.$ketQua['moi']);
        $this->line('Cap nhat lai    : '.$ketQua['capNhat']);
        $this->line('Bo qua (loi)    : '.$ketQua['boQua']);
        $this->line('Co so dien thoai: '.$ketQua['coSdt'].' / '.($ketQua['moi'] + $ketQua['capNhat']));

        if (! $ghi) {
            $this->newLine();
            $this->comment('Chua ghi gi ca. Chay lai kem --ghi de luu that.');
        }

        return self::SUCCESS;
    }

    protected function diaDiem(): ?Branch
    {
        if ($slug = $this->option('dia-diem')) {
            $branch = Branch::where('slug', $slug)->first();

            if (! $branch) {
                $this->error('Khong thay dia diem: '.$slug);
            }

            return $branch;
        }

        $quan = $this->option('quan');

        if (! $quan) {
            $this->error('Phai chi ro --quan=slug hoac --dia-diem=slug.');

            return null;
        }

        $brand = Brand::where('slug', $quan)->first();

        if (! $brand) {
            $this->error('Khong thay quan: '.$quan);

            return null;
        }

        $branch = $brand->branches()->orderBy('id')->first();

        if (! $branch) {
            $this->error('Quan '.$brand->name.' chua co dia diem nao.');
        }

        return $branch;
    }

    protected function xoaHoaDon(Branch $branch): int
    {
        $so = Invoice::where('branch_id', $branch->id)->count();

        if ($so === 0) {
            $this->info('Dia diem nay chua co hoa don nao.');

            return self::SUCCESS;
        }

        if (! $this->option('ghi') && ! $this->confirm('Xóa '.$so.' hóa đơn của '.$branch->name.'?', false)) {
            return self::SUCCESS;
        }

        Invoice::where('branch_id', $branch->id)->delete();
        $this->info('Đã xóa '.$so.' hóa đơn.');

        return self::SUCCESS;
    }
}
