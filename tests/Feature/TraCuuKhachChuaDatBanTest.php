<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\PosCustomer;
use App\Services\GuestProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O tim khach phai ra ca khach chua tung dat ban.
 *
 * Loi nguoi dung bao 17/09/2026: khach co trong danh sach khach hang nhung go
 * vao Tra cuu khach thi "khong tim thay" - vi ham tim chi quet bang bookings.
 */
class TraCuuKhachChuaDatBanTest extends TestCase
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

    protected function hoaDon(string $ma, string $sdt, string $ten = 'Anh Hiếu', string $luc = '2026-09-01 22:00:00', ?int $branchId = null): Invoice
    {
        return Invoice::create([
            'branch_id' => $branchId ?? $this->branch->id,
            'code' => $ma,
            'status' => 'Đã thanh toán',
            'ordered_at' => $luc,
            'paid_at' => $luc,
            'total' => 500000,
            'party_size' => 2,
            'customer_phone' => $sdt,
            'customer_name' => $ten,
        ]);
    }

    protected function tim(string $tu, ?array $branchIds = null)
    {
        return app(GuestProfileService::class)->search($tu, $branchIds);
    }

    public function test_khach_chi_co_hoa_don_tim_theo_so_va_ten_deu_ra(): void
    {
        $this->hoaDon('HD1', '0937936666');
        $this->hoaDon('HD2', '0937936666', 'Anh Hiếu', '2026-09-10 23:00:00');

        $theoSo = $this->tim('0937 936 666');
        $this->assertCount(1, $theoSo);
        $this->assertSame('0937936666', $theoSo->first()['phone']);
        $this->assertSame(2, $theoSo->first()['visits']);
        $this->assertSame(0, $theoSo->first()['bookings']);
        $this->assertSame('10/09/2026', $theoSo->first()['last']->format('d/m/Y'));

        $this->assertCount(1, $this->tim('Hiếu'));
    }

    public function test_khach_chi_co_trong_danh_sach_khach_hang_cung_ra(): void
    {
        PosCustomer::create(['phone' => '0919834444', 'name' => 'Anh Thịnh - My Wine', 'tier' => 'Vàng']);

        $ketQua = $this->tim('My Wine');

        $this->assertCount(1, $ketQua);
        $this->assertSame('Anh Thịnh - My Wine', $ketQua->first()['name']);
        $this->assertSame('Vàng', $ketQua->first()['card']->tier);
        $this->assertNull($ketQua->first()['last']);
    }

    public function test_gop_ba_nguon_ve_mot_dong_theo_so(): void
    {
        PosCustomer::create(['phone' => '0865683649', 'name' => 'Nguyễn Trọng Tâm']);
        $this->hoaDon('HD3', '0865683649', 'Tâm');
        Booking::create([
            'branch_id' => $this->branch->id, 'code' => 'DB0001',
            'customer_name' => 'Tam', 'customer_phone' => '086 568 3649',
            'booking_date' => '2026-09-05', 'start_time' => '19:00', 'end_time' => '21:00',
            'party_size' => 2, 'status' => Booking::STATUS_COMPLETED,
        ]);

        $ketQua = $this->tim('0865683649');

        $this->assertCount(1, $ketQua);
        $this->assertSame(1, $ketQua->first()['visits']);
        $this->assertSame(1, $ketQua->first()['bookings']);
        // Ten theo quy tac chung: the POS dung truoc ten tren hoa don / dat ban.
        $this->assertSame('Nguyễn Trọng Tâm', $ketQua->first()['name']);
    }

    public function test_nguoi_chi_xem_mot_quan_khong_thay_khach_quan_khac(): void
    {
        $khac = $this->branch->brand->branches()->create([
            'name' => 'Quán khác', 'slug' => 'quan-khac', 'open_time' => '17:00', 'close_time' => '23:30',
        ]);
        $this->hoaDon('HD4', '0900000009', 'Khách quán khác', '2026-09-01 22:00:00', $khac->id);
        PosCustomer::create(['phone' => '0900000008', 'name' => 'Khách quán khác POS']);

        $this->assertCount(0, $this->tim('quán khác', [$this->branch->id]));
        $this->assertCount(2, $this->tim('quán khác', null));
    }

    public function test_trang_tra_cuu_mot_ket_qua_chuyen_thang_ho_so(): void
    {
        $this->hoaDon('HD5', '0937936666');

        $this->assertSame('0937936666', $this->tim('0937936666')->first()['phone']);
    }
}
