<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\PosCustomer;
use App\Models\User;
use App\Services\CustomerInsightService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Section hoa don va phan tich khach hang: ghep ba nguon du lieu theo so
 * dien thoai va xep loai tinh trang khach.
 */
class PhanTichKhachHangTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'Quán A', 'slug' => 'quan-a', 'domain' => 'booking.quan-a.test',
            'mark' => 'QA', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Quán A', 'slug' => 'quan-a', 'open_time' => '17:00', 'close_time' => '00:00',
            'slot_minutes' => 30, 'turn_minutes' => 120, 'min_lead_minutes' => 60,
            'max_advance_days' => 30, 'max_party_size' => 20, 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Giám đốc', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $them */
    protected function hoaDon(string $phone, string $ngay, float $tien, array $them = []): Invoice
    {
        static $dem = 0;
        $dem++;

        return Invoice::create($them + [
            'branch_id' => $this->branch->id,
            'code' => 'HD'.str_pad((string) $dem, 6, '0', STR_PAD_LEFT),
            'status' => 'Đã thanh toán',
            'paid_at' => $ngay,
            'total' => $tien,
            'customer_phone' => $phone,
            'customer_name' => $phone === '' ? null : 'Khách '.substr($phone, -3),
            'area' => 'Quầy Bar',
            'table_code' => 'Bar 1',
            'payment_method' => 'Tiền mặt',
            'party_size' => 2,
        ]);
    }

    public function test_hoa_don_khong_co_so_dien_thoai_khong_vao_bang_xep_hang(): void
    {
        $this->hoaDon('0900000001', now()->subDays(3)->toDateTimeString(), 500000);
        $this->hoaDon('', now()->subDays(2)->toDateTimeString(), 900000);

        $tongQuan = app(CustomerInsightService::class)->overview(null);

        $this->assertSame(2, $tongQuan['invoices']);
        $this->assertSame(1, $tongQuan['invoices_with_phone']);
        $this->assertSame(1, $tongQuan['customers']);
        $this->assertSame(50.0, $tongQuan['phone_rate']);
    }

    public function test_hoa_don_da_huy_khong_tinh_vao_doanh_thu(): void
    {
        $this->hoaDon('0900000001', now()->subDays(3)->toDateTimeString(), 500000);
        $this->hoaDon('0900000001', now()->subDays(2)->toDateTimeString(), 900000, ['status' => Invoice::HUY]);

        $tongQuan = app(CustomerInsightService::class)->overview(null);

        $this->assertSame(500000.0, $tongQuan['revenue']);
    }

    public function test_khach_vang_qua_lau_so_voi_nhip_quen_thi_bi_danh_dau_nguy_co(): void
    {
        // Ghe deu moi 10 ngay, roi bien mat 90 ngay.
        foreach ([130, 120, 110, 100, 90] as $truoc) {
            $this->hoaDon('0900000002', now()->subDays($truoc)->toDateTimeString(), 400000);
        }

        $k = app(CustomerInsightService::class)->ranking(null)->firstWhere('phone', '0900000002');

        $this->assertSame(5, $k['visits']);
        $this->assertSame(10, $k['cadence']);
        $this->assertSame('nguy_co', $k['segment']);
    }

    public function test_khach_van_ghe_dung_nhip_thi_la_deu_dan(): void
    {
        foreach ([40, 30, 20, 10, 2] as $truoc) {
            $this->hoaDon('0900000003', now()->subDays($truoc)->toDateTimeString(), 400000);
        }

        $k = app(CustomerInsightService::class)->ranking(null)->firstWhere('phone', '0900000003');

        $this->assertSame('deu_dan', $k['segment']);
    }

    public function test_ho_so_khach_ghep_ca_hoa_don_lan_don_dat_ban(): void
    {
        $this->hoaDon('0900000004', now()->subDays(5)->toDateTimeString(), 1200000);
        $this->hoaDon('0900000004', now()->subDays(1)->toDateTimeString(), 800000);

        Booking::create([
            'code' => 'ABC123', 'branch_id' => $this->branch->id,
            'customer_name' => 'Khách 004', 'customer_phone' => '0900000004',
            'party_size' => 2, 'booking_date' => now()->subDays(5)->toDateString(),
            'start_time' => '19:00', 'end_time' => '21:00',
            'status' => Booking::STATUS_NO_SHOW, 'source' => 'phone',
        ]);

        $ho = app(CustomerInsightService::class)->profile('0900000004', null);

        $this->assertSame(2, $ho['stats']['visits']);
        $this->assertSame(2000000.0, $ho['stats']['spend']);
        $this->assertSame(1, $ho['booking_stats']['total']);
        $this->assertSame(1, $ho['booking_stats']['no_show']);
        $this->assertSame(0, $ho['booking_stats']['show_rate']);
        $this->assertSame('Quầy Bar', $ho['habits']['area'][0]['label']);
    }

    public function test_the_khach_hang_bo_sung_hang_the(): void
    {
        $this->hoaDon('0900000005', now()->subDays(2)->toDateTimeString(), 600000);

        PosCustomer::create([
            'phone' => '0900000005', 'name' => 'Khách VIP', 'tier' => 'VIP',
            'points' => 1200, 'birthday' => '1990-05-20', 'invoice_count' => 40,
            'total_spent' => 90000000,
        ]);

        $k = app(CustomerInsightService::class)->ranking(null)->firstWhere('phone', '0900000005');

        $this->assertSame('VIP', $k['card']->tier);
    }

    public function test_cac_trang_mo_duoc(): void
    {
        $this->hoaDon('0900000006', now()->subDays(2)->toDateTimeString(), 600000);

        $this->actingAs($this->admin)->get('/quan-ly/hoa-don')->assertOk()->assertSee('Hóa đơn');
        $this->actingAs($this->admin)->get('/quan-ly/khach-hang')->assertOk()->assertSee('Phân tích khách hàng');
        $this->actingAs($this->admin)->get('/quan-ly/khach-hang/0900000006')->assertOk();
        $this->actingAs($this->admin)->get('/quan-ly/khach-hang/0900000999')->assertNotFound();
    }

    public function test_chi_quan_tri_duoc_tai_tep_pos_len(): void
    {
        $quanLy = User::create([
            'name' => 'Quản lý', 'email' => 'ql@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'is_active' => true,
        ]);

        $this->actingAs($quanLy)
            ->post('/quan-ly/hoa-don/nhap', ['loai' => 'hoa-don', 'branch_id' => $this->branch->id])
            ->assertForbidden();
    }

    public function test_tep_khong_phai_xlsx_thi_bi_tu_choi(): void
    {
        $this->actingAs($this->admin)
            ->post('/quan-ly/hoa-don/nhap', [
                'loai' => 'hoa-don',
                'branch_id' => $this->branch->id,
                'tep' => UploadedFile::fake()->create('bao-cao.csv', 10, 'text/csv'),
            ])
            ->assertSessionHasErrors('tep');
    }

    public function test_quan_ly_chi_thay_hoa_don_cua_quan_minh(): void
    {
        $brandB = Brand::create([
            'name' => 'Quán B', 'slug' => 'quan-b', 'domain' => 'booking.quan-b.test',
            'mark' => 'QB', 'accent_color' => '#c8a15a', 'is_active' => true,
        ]);

        $branchB = $brandB->branches()->create([
            'name' => 'Quán B', 'slug' => 'quan-b', 'open_time' => '17:00', 'close_time' => '00:00',
            'slot_minutes' => 30, 'turn_minutes' => 120, 'min_lead_minutes' => 60,
            'max_advance_days' => 30, 'max_party_size' => 20, 'is_active' => true,
        ]);

        $this->hoaDon('0900000007', now()->subDays(2)->toDateTimeString(), 600000);

        Invoice::create([
            'branch_id' => $branchB->id, 'code' => 'HDB1', 'status' => 'Đã thanh toán',
            'paid_at' => now()->subDay(), 'total' => 5000000, 'customer_phone' => '0900000008',
        ]);

        $quanLyB = User::create([
            'name' => 'Quản lý B', 'email' => 'qlb@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $brandB->id, 'is_active' => true,
        ]);

        $tongQuan = app(CustomerInsightService::class)->overview($quanLyB->visibleBranchIds());

        $this->assertSame(1, $tongQuan['invoices']);
        $this->assertSame(5000000.0, $tongQuan['revenue']);

        $this->actingAs($quanLyB)->get('/quan-ly/khach-hang/0900000007')->assertNotFound();
    }
}
