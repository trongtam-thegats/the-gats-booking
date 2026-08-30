<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\GuestNote;
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

    public function test_danh_dau_da_xem_xet_roi_thi_khach_ra_khoi_nhom_chua_xem_xet(): void
    {
        $this->hoaDon('0900000010', now()->subDays(90)->toDateTimeString(), 500000);
        $this->hoaDon('0900000010', now()->subDays(60)->toDateTimeString(), 500000);

        $s = app(CustomerInsightService::class);

        $this->assertSame('chua_xem_xet', $s->tatCaKhach(null)->firstWhere('phone', '0900000010')['review']);

        $this->actingAs($this->admin)
            ->post('/quan-ly/khach-hang/0900000010/danh-dau', ['review_outcome' => 'da_lien_he'])
            ->assertRedirect();

        $this->assertSame('da_xem_xet', $s->tatCaKhach(null)->firstWhere('phone', '0900000010')['review']);

        $ghiChu = GuestNote::where('phone', '0900000010')->firstOrFail();
        $this->assertSame('da_lien_he', $ghiChu->review_outcome);
        $this->assertSame($this->admin->id, $ghiChu->reviewed_by);
    }

    public function test_khach_ghe_lai_sau_khi_danh_dau_thi_he_thong_tu_chuyen_nhan(): void
    {
        $this->hoaDon('0900000011', now()->subDays(90)->toDateTimeString(), 500000);

        $this->actingAs($this->admin)
            ->post('/quan-ly/khach-hang/0900000011/danh-dau')
            ->assertRedirect();

        $s = app(CustomerInsightService::class);
        $this->assertSame('da_xem_xet', $s->tatCaKhach(null)->firstWhere('phone', '0900000011')['review']);

        // Vai ngay sau khach quay lai - khong ai bam gi ca, nhan phai tu doi.
        $this->travel(3)->days();
        $this->hoaDon('0900000011', now()->toDateTimeString(), 700000);

        $this->assertSame('da_ghe_lai', $s->tatCaKhach(null)->firstWhere('phone', '0900000011')['review']);
    }

    public function test_dat_ban_lai_cung_duoc_tinh_la_da_ghe_lai(): void
    {
        $this->hoaDon('0900000012', now()->subDays(90)->toDateTimeString(), 500000);

        $this->actingAs($this->admin)->post('/quan-ly/khach-hang/0900000012/danh-dau')->assertRedirect();

        $this->travel(3)->days();

        Booking::create([
            'code' => 'GHE001', 'branch_id' => $this->branch->id,
            'customer_name' => 'Khách 012', 'customer_phone' => '0900000012',
            'party_size' => 2, 'booking_date' => now()->toDateString(),
            'start_time' => '19:00', 'end_time' => '21:00',
            'status' => Booking::STATUS_CONFIRMED, 'source' => 'phone',
        ]);

        $this->assertSame(
            'da_ghe_lai',
            app(CustomerInsightService::class)->tatCaKhach(null)->firstWhere('phone', '0900000012')['review']
        );
    }

    public function test_bo_danh_dau_dua_khach_ve_lai_nhom_chua_xem_xet(): void
    {
        $this->hoaDon('0900000013', now()->subDays(90)->toDateTimeString(), 500000);

        $this->actingAs($this->admin)->post('/quan-ly/khach-hang/0900000013/danh-dau')->assertRedirect();
        $this->actingAs($this->admin)
            ->post('/quan-ly/khach-hang/0900000013/danh-dau', ['bo_danh_dau' => 1])
            ->assertRedirect();

        $this->assertSame(
            'chua_xem_xet',
            app(CustomerInsightService::class)->tatCaKhach(null)->firstWhere('phone', '0900000013')['review']
        );
    }

    public function test_vai_chi_xem_khong_duoc_danh_dau(): void
    {
        $this->hoaDon('0900000014', now()->subDays(10)->toDateTimeString(), 500000);

        $viewer = User::create([
            'name' => 'Người xem', 'email' => 'xem@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::VIEWER, 'brand_id' => $this->branch->brand_id, 'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->post('/quan-ly/khach-hang/0900000014/danh-dau')
            ->assertForbidden();
    }

    public function test_bo_loc_thu_hep_dung_nhom_khach(): void
    {
        // Khach ghe nhieu, chi nhieu.
        foreach ([120, 110, 100, 90, 80] as $truoc) {
            $this->hoaDon('0900000015', now()->subDays($truoc)->toDateTimeString(), 2000000);
        }

        // Khach ghe mot lan, chi it.
        $this->hoaDon('0900000016', now()->subDays(5)->toDateTimeString(), 200000);

        $s = app(CustomerInsightService::class);
        $tatCa = $s->tatCaKhach(null);

        $this->assertCount(2, $tatCa);

        $nhieuLan = $s->locVaXep($tatCa, 'spend', ['visits_min' => 5]);
        $this->assertCount(1, $nhieuLan);
        $this->assertSame('0900000015', $nhieuLan->first()['phone']);

        $chiNhieu = $s->locVaXep($tatCa, 'spend', ['spend_min' => 1000000]);
        $this->assertSame('0900000015', $chiNhieu->first()['phone']);

        $vangLau = $s->locVaXep($tatCa, 'spend', ['vang_min' => 30]);
        $this->assertCount(1, $vangLau);
        $this->assertSame('0900000015', $vangLau->first()['phone']);

        $timTheoSo = $s->locVaXep($tatCa, 'spend', ['tim' => '0016']);
        $this->assertCount(1, $timTheoSo);
        $this->assertSame('0900000016', $timTheoSo->first()['phone']);

        $this->assertCount(0, $s->locVaXep($tatCa, 'spend', ['segment' => ['deu_dan'], 'visits_min' => 99]));
    }

    public function test_so_lieu_theo_thang_tach_khach_moi_va_khach_quay_lai(): void
    {
        $this->hoaDon('0900000017', now()->subMonths(2)->startOfMonth()->addDays(2)->toDateTimeString(), 500000);
        $this->hoaDon('0900000017', now()->startOfMonth()->addDays(2)->toDateTimeString(), 600000);
        $this->hoaDon('0900000018', now()->startOfMonth()->addDays(3)->toDateTimeString(), 400000);

        $thang = collect(app(CustomerInsightService::class)->theoThang(null, 6))->keyBy('month');

        $nay = $thang[now()->format('Y-m')];

        $this->assertSame(1, $nay['new_customers']);   // 0900000018
        $this->assertSame(1, $nay['returning']);       // 0900000017
    }

    public function test_ket_qua_xac_nhan_de_len_tinh_trang_may_suy_ra(): void
    {
        // Ghe deu moi 10 ngay roi bien mat 90 ngay -> may doan "nguy co roi bo".
        foreach ([130, 120, 110, 100, 90] as $truoc) {
            $this->hoaDon('0900000020', now()->subDays($truoc)->toDateTimeString(), 400000);
        }

        $s = app(CustomerInsightService::class);
        $this->assertSame('nguy_co', $s->tatCaKhach(null)->firstWhere('phone', '0900000020')['trang_thai']);

        // Nhan vien goi va biet chac khach da chuyen di xa.
        $this->actingAs($this->admin)
            ->post('/quan-ly/khach-hang/0900000020/danh-dau', ['review_outcome' => 'da_chuyen_di'])
            ->assertRedirect();

        $k = $s->tatCaKhach(null)->firstWhere('phone', '0900000020');

        $this->assertSame('xn_da_chuyen_di', $k['trang_thai']);
        $this->assertSame('Đã chuyển đi xa', CustomerInsightService::nhanTinhTrang($k['trang_thai']));
        $this->assertTrue(CustomerInsightService::laXacNhan($k['trang_thai']));

        // Va roi khoi danh sach can cham soc gap - do la muc dich cua viec danh dau.
        $canGoi = $s->locVaXep($s->tatCaKhach(null), 'spend', ['segment' => ['nguy_co']]);
        $this->assertFalse($canGoi->contains('phone', '0900000020'));
    }

    public function test_ket_qua_da_lien_he_khong_de_len_tinh_trang(): void
    {
        foreach ([130, 120, 110, 100, 90] as $truoc) {
            $this->hoaDon('0900000021', now()->subDays($truoc)->toDateTimeString(), 400000);
        }

        // "Da lien he" chi ghi nhan da goi, chua noi gi ve quan he cua khach.
        $this->actingAs($this->admin)
            ->post('/quan-ly/khach-hang/0900000021/danh-dau', ['review_outcome' => 'da_lien_he'])
            ->assertRedirect();

        $k = app(CustomerInsightService::class)->tatCaKhach(null)->firstWhere('phone', '0900000021');

        $this->assertSame('nguy_co', $k['trang_thai']);
        $this->assertSame('da_xem_xet', $k['review']);
    }

    public function test_khach_quay_lai_thi_xac_nhan_cu_het_hieu_luc(): void
    {
        $this->hoaDon('0900000022', now()->subDays(90)->toDateTimeString(), 400000);

        $this->actingAs($this->admin)
            ->post('/quan-ly/khach-hang/0900000022/danh-dau', ['review_outcome' => 'da_roi_bo'])
            ->assertRedirect();

        $s = app(CustomerInsightService::class);
        $this->assertSame('xn_da_roi_bo', $s->tatCaKhach(null)->firstWhere('phone', '0900000022')['trang_thai']);

        // Khach quay lai that -> loi xac nhan "da roi bo" khong con dung nua.
        $this->travel(3)->days();
        $this->hoaDon('0900000022', now()->toDateTimeString(), 500000);

        $k = $s->tatCaKhach(null)->firstWhere('phone', '0900000022');

        $this->assertSame('da_ghe_lai', $k['review']);
        $this->assertFalse(CustomerInsightService::laXacNhan($k['trang_thai']));
    }

    public function test_khoang_thoi_gian_tinh_lai_moi_chi_so(): void
    {
        // Khach ghe day dac tu lau, gan day chi ghe mot lan.
        foreach ([300, 290, 280, 270] as $truoc) {
            $this->hoaDon('0900000023', now()->subDays($truoc)->toDateTimeString(), 1000000);
        }
        $this->hoaDon('0900000023', now()->subDays(10)->toDateTimeString(), 500000);

        $s = app(CustomerInsightService::class);

        $toanBo = $s->tatCaKhach(null)->firstWhere('phone', '0900000023');
        $this->assertSame(5, $toanBo['visits']);
        $this->assertSame(4500000.0, $toanBo['spend']);

        // Nhin trong 1 thang thi chi con dung mot lan ghe.
        $motThang = $s->tatCaKhach(null, now()->subMonth())->firstWhere('phone', '0900000023');
        $this->assertSame(1, $motThang['visits']);
        $this->assertSame(500000.0, $motThang['spend']);
    }

    public function test_khach_lau_nam_khong_bi_goi_nham_la_khach_moi_khi_xem_khoang_ngan(): void
    {
        // Ghe lan dau tu rat lau roi, va van con ghe gan day.
        $this->hoaDon('0900000024', now()->subDays(400)->toDateTimeString(), 800000);
        $this->hoaDon('0900000024', now()->subDays(5)->toDateTimeString(), 900000);

        $motThang = app(CustomerInsightService::class)
            ->tatCaKhach(null, now()->subMonth())
            ->firstWhere('phone', '0900000024');

        // Trong khoang 1 thang khach nay chi co dung mot hoa don, nhung lan ghe
        // dau tien that su la 400 ngay truoc nen khong phai khach moi.
        $this->assertSame(1, $motThang['visits']);
        $this->assertNotSame('khach_moi', $motThang['segment']);
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
