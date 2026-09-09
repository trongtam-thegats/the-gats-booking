<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Services\CustomerInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Thoi gian khach ngoi lai va so khach hay di cung.
 *
 * Hai cho de sai: don bi quen chua chot keo dai thoi gian ngoi len vai tieng,
 * va "hay di may nguoi" phai lay so gap NHIEU LAN NHAT chu khong phai trung binh.
 */
class ThoiQuenNgoiTest extends TestCase
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

    /** @param  array<string, mixed>  $them */
    protected function hoaDon(string $ma, string $mo, string $chot, int $soNguoi, array $them = []): Invoice
    {
        return Invoice::create(array_merge([
            'branch_id' => $this->branch->id,
            'code' => $ma,
            'status' => 'Đã thanh toán',
            'ordered_at' => $mo,
            'paid_at' => $chot,
            'total' => 500_000,
            'party_size' => $soNguoi,
            'customer_phone' => '0900000001',
            'customer_name' => 'Khách thử',
        ], $them));
    }

    protected function hoSo(): array
    {
        return app(CustomerInsightService::class)->profile('0900000001', null);
    }

    public function test_trung_vi_thoi_gian_ngoi_lai(): void
    {
        // 60, 90, 120 phút -> trung vị 90.
        $this->hoaDon('A1', '2026-09-01 20:00:00', '2026-09-01 21:00:00', 2);
        $this->hoaDon('A2', '2026-09-02 20:00:00', '2026-09-02 21:30:00', 2);
        $this->hoaDon('A3', '2026-09-03 20:00:00', '2026-09-03 22:00:00', 2);

        $this->assertSame(90, $this->hoSo()['stats']['dwell_median']);
    }

    public function test_don_bi_quen_chua_chot_khong_keo_lech_thoi_gian_ngoi(): void
    {
        $this->hoaDon('B1', '2026-09-01 20:00:00', '2026-09-01 21:00:00', 2);
        $this->hoaDon('B2', '2026-09-02 20:00:00', '2026-09-02 21:00:00', 2);

        // 7 tiếng — vượt ngưỡng 360 phút, phải bị loại.
        $this->hoaDon('B3', '2026-09-03 19:00:00', '2026-09-04 02:00:00', 2);

        $this->assertSame(60, $this->hoSo()['stats']['dwell_median']);
    }

    public function test_du_lieu_gio_hong_bi_bo_qua(): void
    {
        $this->hoaDon('C1', '2026-09-01 20:00:00', '2026-09-01 21:00:00', 2);

        // Chốt trước cả lúc mở đơn.
        $this->hoaDon('C2', '2026-09-02 22:00:00', '2026-09-02 20:00:00', 2);
        // Thiếu mốc mở đơn.
        $this->hoaDon('C3', '2026-09-03 20:00:00', '2026-09-03 23:00:00', 2, ['ordered_at' => null]);

        $this->assertSame(60, $this->hoSo()['stats']['dwell_median']);
    }

    public function test_khong_co_hoa_don_dung_duoc_thi_tra_ve_null(): void
    {
        $this->hoaDon('D1', '2026-09-01 20:00:00', '2026-09-01 20:00:00', 2);

        $this->assertNull($this->hoSo()['stats']['dwell_median']);
    }

    public function test_so_khach_hay_di_lay_theo_so_lan_gap_nhieu_nhat(): void
    {
        // Ba lần đi 2 người, một lần dẫn nhóm 10 người.
        // Trung bình sẽ ra 4, nhưng con số đúng phải là 2.
        foreach (['E1', 'E2', 'E3'] as $ma) {
            $this->hoaDon($ma, '2026-09-01 20:00:00', '2026-09-01 21:00:00', 2);
        }
        $this->hoaDon('E4', '2026-09-05 20:00:00', '2026-09-05 21:00:00', 10);

        $stats = $this->hoSo()['stats'];

        $this->assertSame(2, $stats['party_mode']);
        $this->assertSame(16, $stats['guests']);   // tổng lượt khách vẫn giữ nguyên
    }

    public function test_hoa_quyet_thi_lay_nhom_nho_hon(): void
    {
        $this->hoaDon('F1', '2026-09-01 20:00:00', '2026-09-01 21:00:00', 2);
        $this->hoaDon('F2', '2026-09-02 20:00:00', '2026-09-02 21:00:00', 4);

        $this->assertSame(2, $this->hoSo()['stats']['party_mode']);
    }

    public function test_thoi_quen_co_dong_khoang_ngoi_va_so_nguoi(): void
    {
        $this->hoaDon('G1', '2026-09-01 20:00:00', '2026-09-01 20:30:00', 2);
        $this->hoaDon('G2', '2026-09-02 20:00:00', '2026-09-02 21:30:00', 2);
        $this->hoaDon('G3', '2026-09-03 20:00:00', '2026-09-03 21:45:00', 4);

        $thoiQuen = $this->hoSo()['habits'];

        $this->assertSame('1 – 2 giờ', $thoiQuen['dwell'][0]['label']);
        $this->assertSame(2, $thoiQuen['dwell'][0]['count']);

        $this->assertSame('2 người', $thoiQuen['party'][0]['label']);
        $this->assertSame(2, $thoiQuen['party'][0]['count']);
    }

    public function test_hoa_don_da_huy_khong_tinh_vao_thoi_quen(): void
    {
        $this->hoaDon('H1', '2026-09-01 20:00:00', '2026-09-01 21:00:00', 2);
        $huy = $this->hoaDon('H2', '2026-09-02 20:00:00', '2026-09-02 23:00:00', 8);
        $huy->update(['status' => Invoice::HUY]);

        $stats = $this->hoSo()['stats'];

        $this->assertSame(60, $stats['dwell_median']);
        $this->assertSame(2, $stats['party_mode']);
    }
}
