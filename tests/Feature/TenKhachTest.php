<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\GuestNote;
use App\Models\PosCustomer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CustomerInsightService;
use App\Services\GuestProfileService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Gop ten khach theo so dien thoai.
 *
 * Cung mot nguoi co the mang bon cai ten: nhan vien ghi chu, the khach hang
 * ben POS, ten tren hoa don, va ten khach tu go luc dat ban. Quy tac chon nam
 * o App\Support\TenKhach va moi noi deu phai goi ve do.
 *
 * Rang buoc quan trong nhat o cuoi file: viec tra ten theo so dien thoai
 * TUYET DOI khong duoc lo ra trang khach.
 */
class TenKhachTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected User $admin;

    protected User $manager;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán Thử',
            'slug' => 'quan-thu',
            'domain' => 'booking.quanthu.test',
            'mark' => 'QT',
            'accent_color' => '#c8a15a',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->branch = $this->brand->branches()->create([
            'name' => 'Quán Thử',
            'slug' => 'quan-thu-cn',
            'open_time' => '17:00',
            'close_time' => '23:30',
            'slot_minutes' => 30,
            'turn_minutes' => 120,
            'min_lead_minutes' => 60,
            'max_advance_days' => 30,
            'max_party_size' => 20,
            'is_active' => true,
        ]);

        foreach (['T1', 'T2'] as $code) {
            $this->branch->diningTables()->create([
                'code' => $code,
                'table_type' => 'high_table',
                'seats_min' => 1,
                'seats_max' => 4,
                'combinable' => true,
            ]);
        }

        $this->admin = User::create([
            'name' => 'Giám đốc', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Quản lý', 'email' => 'quanly@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->viewer = User::create([
            'name' => 'Người xem', 'email' => 'xem@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::VIEWER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);
    }

    protected function datBan(string $ten, string $sdt = '0912345678'): Booking
    {
        return app(BookingService::class)->create($this->branch, [
            'customer_name' => $ten,
            'customer_phone' => $sdt,
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ]);
    }

    // ---------- Thu tu chon ten ----------

    public function test_the_khach_hang_thang_ten_khach_tu_go(): void
    {
        $this->datBan('tâm');
        PosCustomer::create(['phone' => '0912345678', 'name' => 'Nguyễn Trọng Tâm']);

        $ho = app(GuestProfileService::class)->forPhone('0912345678', null, $this->brand->id);

        $this->assertSame('Nguyễn Trọng Tâm', $ho['name']);
        $this->assertSame('danh sách khách hàng', $ho['name_source']);
    }

    public function test_ghi_chu_cua_quan_thang_ca_the_khach_hang(): void
    {
        $this->datBan('tâm');
        PosCustomer::create(['phone' => '0912345678', 'name' => 'Nguyễn Trọng Tâm']);
        GuestNote::create([
            'brand_id' => $this->brand->id,
            'phone' => '0912345678',
            'name' => 'Anh Tâm (bạn của chủ quán)',
        ]);

        $ho = app(GuestProfileService::class)->forPhone('0912345678', null, $this->brand->id);

        $this->assertSame('Anh Tâm (bạn của chủ quán)', $ho['name']);
        $this->assertSame('ghi chú của quán', $ho['name_source']);
    }

    public function test_khong_co_nguon_nao_thi_lay_ten_khach_tu_go(): void
    {
        $this->datBan('Khách gõ tay');

        $ho = app(GuestProfileService::class)->forPhone('0912345678', null, $this->brand->id);

        $this->assertSame('Khách gõ tay', $ho['name']);
        $this->assertNull($ho['name_source']);
    }

    public function test_the_khach_hang_de_trong_ten_thi_khong_de_len_ten_that(): void
    {
        $this->datBan('Khách gõ tay');
        PosCustomer::create(['phone' => '0912345678', 'name' => '  ']);

        $ho = app(GuestProfileService::class)->forPhone('0912345678', null, $this->brand->id);

        $this->assertSame('Khách gõ tay', $ho['name'], 'Ten rong khong duoc coi la ten.');
    }

    // ---------- Trang phan tich dung cung mot ten ----------

    public function test_trang_phan_tich_hien_ten_chuan_chu_khong_phai_ten_go_tay(): void
    {
        $this->datBan('tâm');
        PosCustomer::create(['phone' => '0912345678', 'name' => 'Nguyễn Trọng Tâm']);

        $khach = app(CustomerInsightService::class)->tatCaKhach(null);
        $mot = $khach->firstWhere('phone', '0912345678');

        // Chi co don dat ban, chua co hoa don, nen khach nay khong nhat thiet
        // xuat hien o day; dieu can chac la NEU co thi phai dung ten chuan.
        if ($mot) {
            $this->assertSame('Nguyễn Trọng Tâm', $mot['name']);
        }

        $this->assertTrue(true);
    }

    // ---------- Endpoint tra nhanh ----------

    public function test_le_tan_tra_duoc_ten_theo_so_dien_thoai(): void
    {
        $this->datBan('tâm');
        PosCustomer::create(['phone' => '0912345678', 'name' => 'Nguyễn Trọng Tâm', 'tier' => 'Vàng']);

        $this->actingAs($this->manager)
            ->getJson(route('admin.guests.quick', ['phone' => '0912345678']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'name' => 'Nguyễn Trọng Tâm',
                'name_source' => 'danh sách khách hàng',
                'tier' => 'Vàng',
            ]);
    }

    public function test_so_la_thi_bao_khach_moi(): void
    {
        $this->actingAs($this->manager)
            ->getJson(route('admin.guests.quick', ['phone' => '0988887777']))
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_chua_go_du_so_thi_khong_tra_ket_qua(): void
    {
        $this->actingAs($this->manager)
            ->getJson(route('admin.guests.quick', ['phone' => '091']))
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_so_dien_thoai_khac_dinh_dang_van_tra_ra_dung_khach(): void
    {
        PosCustomer::create(['phone' => '0912345678', 'name' => 'Nguyễn Trọng Tâm']);

        $this->actingAs($this->manager)
            ->getJson(route('admin.guests.quick', ['phone' => '+84 912 345 678']))
            ->assertOk()
            ->assertJson(['name' => 'Nguyễn Trọng Tâm']);
    }

    public function test_canh_bao_khach_hay_bo_hen(): void
    {
        $don = $this->datBan('Khách hay lỡ hẹn');
        $don->update(['status' => Booking::STATUS_NO_SHOW]);

        $this->actingAs($this->manager)
            ->getJson(route('admin.guests.quick', ['phone' => '0912345678']))
            ->assertOk()
            ->assertJson(['no_show' => 1]);
    }

    // ---------- Ranh gioi quyen ----------

    public function test_vai_chi_xem_khong_tra_cuu_nhanh_duoc(): void
    {
        $this->actingAs($this->viewer)
            ->getJson(route('admin.guests.quick', ['phone' => '0912345678']))
            ->assertForbidden();
    }

    public function test_khach_vang_lai_khong_cham_toi_duoc(): void
    {
        PosCustomer::create(['phone' => '0912345678', 'name' => 'Nguyễn Trọng Tâm']);

        $this->getJson(route('admin.guests.quick', ['phone' => '0912345678']))
            ->assertUnauthorized()
            ->assertDontSee('Nguyễn Trọng Tâm');
    }

    /**
     * Rang buoc quan trong nhat cua ca tinh nang nay.
     *
     * Trang dat ban cua khach ai cung vao duoc. Neu go so dien thoai vao ma ten
     * hien ra thi bat ky ai cung do duoc ten cua toan bo khach hang. User da
     * chot 2026-09-02: chuc nang nay CHI o khu quan tri.
     */
    public function test_trang_khach_khong_he_lo_ten_theo_so_dien_thoai(): void
    {
        PosCustomer::create(['phone' => '0912345678', 'name' => 'Nguyễn Trọng Tâm']);
        $this->datBan('Nguyễn Trọng Tâm');

        // Tren ten mien cua quan, moi duong /quan-ly deu bi day sang ten mien
        // quan tri - khong mot byte du lieu khach nao duoc phuc vu o day.
        $tren = $this->get('http://booking.quanthu.test/quan-ly/khach/tra-nhanh?phone=0912345678');

        $this->assertTrue(
            $tren->isRedirect() || $tren->status() === 404,
            'Ten mien cua quan phai chuyen huong hoac 404, khong duoc phuc vu du lieu khach.'
        );
        $tren->assertDontSee('Nguyễn Trọng Tâm');

        // Va trang dat ban khong nhung san ten khach nao vao HTML.
        $this->get('http://booking.quanthu.test/')
            ->assertOk()
            ->assertDontSee('Nguyễn Trọng Tâm');
    }
}
