<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\CustomerInsightService;
use App\Services\PosImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Nhap tep "danh sach mat hang" - moi dong mot mon trong mot hoa don.
 *
 * Cai de sai nhat: tep lap lai toan bo cot cua hoa don o MOI dong, nen co hai
 * cap ten de lan nhau - "Tiền hàng" (cua mon) voi "Tổng tiền hàng (1)" (cua ca
 * hoa don), va "Tổng giảm giá" hai cap tuong tu. Tep mau trong tests/fixtures
 * co du ca bon cot do, va so tien cua mon co y khac so tien cua hoa don de bat
 * duoc loi anh xa nham cot.
 */
class NhapMatHangTest extends TestCase
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

    protected function tepMau(): string
    {
        return base_path('tests/fixtures/mat-hang-mau.xlsx');
    }

    protected function hoaDon(string $ma): Invoice
    {
        return Invoice::create([
            'branch_id' => $this->branch->id,
            'code' => $ma,
            'status' => 'Đã thanh toán',
            'ordered_at' => '2026-09-01 20:00:00',
            'paid_at' => '2026-09-01 22:00:00',
            'total' => 390_052,
            'party_size' => 2,
            'customer_phone' => '0900000001',
            'customer_name' => 'Khách thử',
        ]);
    }

    protected function nhap(bool $ghi = true): array
    {
        return app(PosImportService::class)->matHang($this->tepMau(), $this->branch, $ghi);
    }

    public function test_lay_dung_cot_cua_mon_chu_khong_phai_cua_hoa_don(): void
    {
        $this->hoaDon('HD001');
        $this->nhap();

        $mon = InvoiceItem::where('name', 'Hương Lộ')->firstOrFail();

        $this->assertSame('HL', $mon->sku);
        $this->assertSame('Signature Cocktail', $mon->category);
        $this->assertSame('Ly', $mon->unit);
        $this->assertEqualsWithDelta(1, (float) $mon->quantity, 0.01);

        // 289.000 la tien cua MON. 338.000 la tong tien hang cua ca hoa don -
        // lay nham con so nay nghia la anh xa dinh cot "Tổng tiền hàng (1)".
        $this->assertEqualsWithDelta(289_000, (float) $mon->unit_price, 0.01);
        $this->assertEqualsWithDelta(289_000, (float) $mon->amount, 0.01);
    }

    public function test_nhieu_mon_trong_mot_hoa_don(): void
    {
        $this->hoaDon('HD001');
        $k = $this->nhap();

        $this->assertSame(2, $k['mon']);
        $this->assertSame(1, $k['hoaDon']);
        $this->assertSame(2, InvoiceItem::count());

        $tonic = InvoiceItem::where('name', 'Tonic')->firstOrFail();
        $this->assertEqualsWithDelta(2, (float) $tonic->quantity, 0.01);
        $this->assertEqualsWithDelta(98_000, (float) $tonic->amount, 0.01);
    }

    public function test_dong_khong_co_hoa_don_tuong_ung_bi_bo_qua_va_dem_lai(): void
    {
        $this->hoaDon('HD001');

        $k = $this->nhap();

        // Tep co 4 dong: 2 cua HD001, 1 cua HD002, 1 cua HD999.
        $this->assertSame(4, $k['tong']);
        $this->assertSame(2, $k['mon']);
        $this->assertSame(2, $k['khongKhop']);
    }

    public function test_xem_truoc_khong_ghi_gi(): void
    {
        $this->hoaDon('HD001');

        $k = $this->nhap(false);

        $this->assertSame(2, $k['mon']);
        $this->assertSame(0, InvoiceItem::count());
    }

    public function test_nhap_lai_khong_sinh_ban_trung(): void
    {
        $this->hoaDon('HD001');
        $this->hoaDon('HD002');

        $this->nhap();
        $lanDau = InvoiceItem::count();

        $this->nhap();

        $this->assertSame($lanDau, InvoiceItem::count());
        $this->assertSame(3, $lanDau);
    }

    public function test_mon_cua_quan_khac_khong_bi_dung_toi(): void
    {
        $this->hoaDon('HD001');

        // Cung ma hoa don nhung o quan khac - POS danh so rieng tung quan.
        $quanKhac = $this->branch->brand->branches()->create([
            'name' => 'Quán hai', 'slug' => 'quan-hai-cn', 'open_time' => '17:00', 'close_time' => '23:30',
        ]);
        $hdKhac = Invoice::create([
            'branch_id' => $quanKhac->id, 'code' => 'HD001', 'status' => 'Đã thanh toán',
            'paid_at' => '2026-09-01 22:00:00', 'total' => 100,
        ]);
        InvoiceItem::create([
            'invoice_id' => $hdKhac->id, 'name' => 'Món của quán khác', 'quantity' => 1, 'amount' => 100,
        ]);

        $this->nhap();

        $this->assertSame(1, InvoiceItem::where('invoice_id', $hdKhac->id)->count());
    }

    public function test_tep_hoa_don_thuong_bi_tu_choi_ro_rang(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tên mặt hàng, combo');

        // Tep danh sach hoa don thuong: khong co cot mat hang nao.
        app(PosImportService::class)->matHang(
            base_path('tests/fixtures/hoa-don-mau.xlsx'),
            $this->branch,
            false
        );
    }

    public function test_xoa_hoa_don_thi_mon_di_theo(): void
    {
        $hd = $this->hoaDon('HD001');
        $this->nhap();

        $this->assertSame(2, InvoiceItem::count());

        $hd->delete();

        $this->assertSame(0, InvoiceItem::count());
    }

    public function test_chan_dung_khach_xep_mon_theo_so_ly(): void
    {
        $this->hoaDon('HD001');
        $this->hoaDon('HD002');
        $this->nhap();

        $ho = app(CustomerInsightService::class)->profile('0900000001', null);

        // Old Fashioned 4 ly dung tren Tonic 2 ly va Hương Lộ 1 ly.
        $this->assertSame('Old Fashioned', $ho['habits']['mon'][0]['label']);
        $this->assertSame(4, $ho['habits']['mon'][0]['count']);
        $this->assertSame('Classic Cocktail', $ho['habits']['danh_muc'][0]['label']);
    }

    public function test_chua_nhap_mat_hang_thi_khong_hien_dong_mon(): void
    {
        $this->hoaDon('HD001');

        $ho = app(CustomerInsightService::class)->profile('0900000001', null);

        $this->assertArrayNotHasKey('mon', $ho['habits']);
        $this->assertArrayNotHasKey('danh_muc', $ho['habits']);
    }
}
