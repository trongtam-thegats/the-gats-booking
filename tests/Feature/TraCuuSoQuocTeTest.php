<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Services\GuestProfileService;
use App\Support\SoDienThoai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tra cuu khach theo so dien thoai, ke ca so nuoc ngoai.
 *
 * Loi that da gap tren may chu: bang bookings luu nguyen van khach go, cau
 * truy van boc dau cong o COT nhung khong boc o VE SO SANH. So "+66864161420"
 * chuan hoa van giu dau cong nen khong bao gio khop voi "66864161420" - 133
 * don dat ban cua khach nuoc ngoai tra khong ra.
 */
class TraCuuSoQuocTeTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'Quán thử', 'slug' => 'quan-thu', 'domain' => 'booking.quan-thu.test',
            'mark' => 'QT', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Quán thử', 'slug' => 'quan-thu-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
        ]);
    }

    protected function datBan(string $ma, string $sdt): Booking
    {
        return Booking::create([
            'branch_id' => $this->branch->id,
            'code' => $ma,
            'customer_name' => 'Khách thử',
            'customer_phone' => $sdt,
            'booking_date' => '2026-09-01',
            'start_time' => '19:00',
            'end_time' => '21:00',
            'party_size' => 2,
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }

    protected function hoSo(string $sdt): array
    {
        return app(GuestProfileService::class)->forPhone($sdt, null, null);
    }

    public function test_bien_the_chi_so_cua_so_viet_va_so_ngoai(): void
    {
        // So Viet: phai thu ca dang khach go +84.
        $this->assertSame(['0865683649', '84865683649'], SoDienThoai::bienTheChiSo('0865683649'));

        // So ngoai: bo dau cong di, vi cot da bi boc dau cong khi so sanh.
        $this->assertSame(['66864161420'], SoDienThoai::bienTheChiSo('+66864161420'));

        $this->assertSame([], SoDienThoai::bienTheChiSo(''));
    }

    public function test_so_nuoc_ngoai_tra_ra_dat_ban(): void
    {
        $this->datBan('DB0001', '+66864161420');
        $this->datBan('DB0002', '+66864161420');

        $this->assertSame(2, $this->hoSo('+66864161420')['total']);
    }

    public function test_so_viet_go_kieu_quoc_te_van_ra_cung_mot_khach(): void
    {
        // Cung mot nguoi, hai lan dat ban go hai kieu khac nhau.
        $this->datBan('DB0003', '0865683649');
        $this->datBan('DB0004', '+84 865 683 649');

        $this->assertSame(2, $this->hoSo('0865683649')['total']);
        $this->assertSame(2, $this->hoSo('+84865683649')['total']);
    }

    public function test_so_viet_thuong_van_hoat_dong_nhu_cu(): void
    {
        $this->datBan('DB0005', '0865683649');
        $this->datBan('DB0006', '090 000 0002');

        $this->assertSame(1, $this->hoSo('0865683649')['total']);
        $this->assertSame(1, $this->hoSo('0900000002')['total']);
    }

    public function test_khong_bat_nham_khach_khac(): void
    {
        $this->datBan('DB0007', '+66864161420');
        $this->datBan('DB0008', '+66864161421');

        $this->assertSame(1, $this->hoSo('+66864161420')['total']);
    }

    public function test_o_tim_kiem_cung_ra_so_nuoc_ngoai(): void
    {
        $this->datBan('DB0009', '+66864161420');

        $ketQua = app(GuestProfileService::class)->search('+66864161420', null);

        $this->assertCount(1, $ketQua);
        $this->assertSame('+66864161420', $ketQua->first()['phone']);
    }
}
