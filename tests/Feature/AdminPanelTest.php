<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Khu quan ly: dang nhap, phan quyen ba vai, va pham vi du lieu theo quan.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brandA;

    protected Brand $brandB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected User $admin;

    protected User $manager;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->brandA, $this->branchA] = $this->makeVenue('Quán A', 'quan-a', true);
        [$this->brandB, $this->branchB] = $this->makeVenue('Quán B', 'quan-b', false);

        $this->admin = User::create([
            'name' => 'Giám đốc',
            'email' => 'admin@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::ADMIN,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Quản lý A',
            'email' => 'quanlya@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::MANAGER,
            'brand_id' => $this->brandA->id,
            'is_active' => true,
        ]);

        $this->viewer = User::create([
            'name' => 'Người xem A',
            'email' => 'xema@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::VIEWER,
            'brand_id' => $this->brandA->id,
            'is_active' => true,
        ]);
    }

    /** @return array{0: Brand, 1: Branch} */
    protected function makeVenue(string $name, string $slug, bool $default): array
    {
        $brand = Brand::create([
            'name' => $name,
            'slug' => $slug,
            'domain' => 'booking.'.$slug.'.test',
            'mark' => 'QX',
            'accent_color' => '#c8a15a',
            'is_active' => true,
            'is_default' => $default,
        ]);

        $branch = $brand->branches()->create([
            'name' => $name,
            'slug' => $slug.'-cn',
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
            $branch->diningTables()->create([
                'code' => $code,
                'table_type' => 'high_table',
                'seats_min' => 1,
                'seats_max' => 4,
                'combinable' => true,
            ]);
        }

        return [$brand, $branch];
    }

    /**
     * Tao booking lam du lieu mau.
     *
     * Goi thang service thay vi POST sang ten mien cua quan: test client giu
     * lai host cua request truoc do, nen mot request sang booking.quan-a.test
     * se lam lech tat ca request quan tri ngay sau no.
     */
    protected function makeBooking(Brand $brand, Branch $branch, array $overrides = []): Booking
    {
        return app(BookingService::class)->create($branch, array_merge([
            'customer_name' => 'Khách thử',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ], $overrides));
    }

    // ---------- Dang nhap ----------

    public function test_khach_vang_lai_bi_day_ve_trang_dang_nhap(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_dang_nhap_sai_mat_khau_bao_loi(): void
    {
        $this->post(route('admin.login.submit'), [
            'email' => 'admin@thegats.vn',
            'password' => 'sai-mat-khau',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tai_khoan_bi_khoa_khong_dang_nhap_duoc(): void
    {
        $this->manager->update(['is_active' => false]);

        $this->post(route('admin.login.submit'), [
            'email' => 'quanlya@thegats.vn',
            'password' => 'matkhau123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Khach go them /quan-ly tren ten mien cua quan thi phai duoc dua sang khu
     * quan ly, khong duoc dung lai o trang dang nhap tren chinh ten mien do.
     *
     * Kiem tra ca khi CHUA dang nhap - day la truong hop tung hong vi middleware
     * kiem tra dang nhap chay truoc middleware kiem tra ten mien.
     */
    public function test_ten_mien_cua_quan_khong_mo_duoc_khu_quan_ly(): void
    {
        Setting::putMany(['admin_domain' => 'booking.thegats.test']);

        foreach (['/quan-ly', '/quan-ly/dang-nhap', '/quan-ly/dat-ban'] as $path) {
            $this->get('http://booking.quan-a.test'.$path)
                ->assertRedirect('https://booking.thegats.test'.$path);
        }

        // Da dang nhap cung khong duoc phep o lai ten mien cua quan.
        $this->actingAs($this->admin)
            ->get('http://booking.quan-a.test/quan-ly')
            ->assertRedirect('https://booking.thegats.test/quan-ly');
    }

    public function test_ten_mien_quan_tri_mo_duoc_khu_quan_ly(): void
    {
        Setting::putMany(['admin_domain' => 'booking.thegats.test']);

        $this->actingAs($this->admin)
            ->get('http://booking.thegats.test/quan-ly')
            ->assertOk();
    }

    // ---------- Cac trang hien thi duoc ----------

    public function test_quan_tri_mo_duoc_moi_trang(): void
    {
        $this->makeBooking($this->brandA, $this->branchA);

        $this->actingAs($this->admin);

        foreach ([
            route('admin.dashboard'),
            route('admin.floor', ['branch' => $this->branchA->id]),
            route('admin.bookings.index'),
            route('admin.bookings.create'),
            route('admin.branches.index'),
            route('admin.branches.create'),
            route('admin.branches.edit', $this->branchA),
            route('admin.tables.index', ['branch' => $this->branchA->id]),
            route('admin.brands.index'),
            route('admin.users.index'),
            route('admin.settings.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_quan_ly_xu_ly_dat_ban_va_xem_phan_tich_nhung_khong_cham_cau_hinh(): void
    {
        $this->actingAs($this->manager);

        // Xu ly dat ban va dat ho khach.
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.bookings.index'))->assertOk();
        $this->get(route('admin.bookings.create'))->assertOk();

        // Xem phan tich.
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.customers.index'))->assertOk();
        $this->get(route('admin.invoices.index'))->assertOk();

        // Cau hinh thi khong.
        $this->get(route('admin.tables.index', ['branch' => $this->branchA->id]))->assertForbidden();
        $this->get(route('admin.branches.index'))->assertForbidden();
        $this->get(route('admin.content.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.brands.index'))->assertForbidden();
        $this->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_vai_chi_xem_chi_xem_duoc_lich_dat_ban(): void
    {
        $this->makeBooking($this->brandA, $this->branchA);

        $this->actingAs($this->viewer);

        // Xem lich dat ban thi duoc.
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.bookings.index'))->assertOk();
        $this->get(route('admin.floor', ['branch' => $this->branchA->id]))->assertOk();
        $this->get(route('admin.guests.index'))->assertOk();

        // Dat ho khach va phan tich thi khong.
        $this->get(route('admin.bookings.create'))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.customers.index'))->assertForbidden();
        $this->get(route('admin.invoices.index'))->assertForbidden();
        $this->get(route('admin.tables.index', ['branch' => $this->branchA->id]))->assertForbidden();
    }

    // ---------- Vai chi xem ----------

    public function test_vai_chi_xem_doc_duoc_nhung_khong_sua_duoc(): void
    {
        $booking = $this->makeBooking($this->brandA, $this->branchA);

        $this->actingAs($this->viewer)
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('Khách thử');

        $this->actingAs($this->viewer)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk();

        // Moi thao tac ghi deu bi chan.
        $this->actingAs($this->viewer)
            ->post(route('admin.bookings.confirm', $booking))
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->post(route('admin.bookings.cancel', $booking), ['reason' => 'thử'])
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->post(route('admin.tables.store', $this->branchA), ['code' => 'X9'])
            ->assertForbidden();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_vai_chi_xem_thay_day_du_so_dien_thoai_khach(): void
    {
        $this->makeBooking($this->brandA, $this->branchA, ['customer_phone' => '0987654321']);

        $this->actingAs($this->viewer)
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('0987654321');
    }

    // ---------- Pham vi du lieu theo quan ----------

    public function test_quan_ly_chi_thay_dat_ban_cua_quan_minh(): void
    {
        $own = $this->makeBooking($this->brandA, $this->branchA, ['customer_name' => 'Khách của A']);
        $other = $this->makeBooking($this->brandB, $this->branchB, ['customer_name' => 'Khách của B']);

        $this->actingAs($this->manager)
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('Khách của A')
            ->assertDontSee('Khách của B');

        $this->actingAs($this->manager)
            ->get(route('admin.bookings.show', $other))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('admin.bookings.show', $own))
            ->assertOk();
    }

    public function test_quan_ly_khong_sua_duoc_dia_diem_cua_quan_khac(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.branches.edit', $this->branchB))
            ->assertForbidden();
    }

    public function test_quan_tri_thay_dat_ban_cua_ca_hai_quan(): void
    {
        $this->makeBooking($this->brandA, $this->branchA, ['customer_name' => 'Khách của A']);
        $this->makeBooking($this->brandB, $this->branchB, ['customer_name' => 'Khách của B']);

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('Khách của A')
            ->assertSee('Khách của B');
    }

    // ---------- Xu ly dat ban ----------

    public function test_xac_nhan_ghi_nhan_nguoi_duyet_va_gui_thong_bao(): void
    {
        $booking = $this->makeBooking($this->brandA, $this->branchA);

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.confirm', $booking))
            ->assertRedirect();

        $booking->refresh();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertSame($this->manager->id, $booking->confirmed_by);
        $this->assertNotNull($booking->confirmed_at);

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'event' => 'confirmed',
        ]);
    }

    public function test_nhan_vien_huy_booking_thi_nha_ban(): void
    {
        $booking = $this->makeBooking($this->brandA, $this->branchA);

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.cancel', $booking), ['reason' => 'Khách báo bận']);

        $booking->refresh();

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertSame('Khách báo bận', $booking->cancel_reason);
        $this->assertCount(0, $booking->diningTables);
    }

    public function test_khong_gan_duoc_ban_da_co_khach_khac_giu(): void
    {
        $first = $this->makeBooking($this->brandA, $this->branchA, ['customer_phone' => '0911111111']);
        $second = $this->makeBooking($this->brandA, $this->branchA, ['customer_phone' => '0922222222']);

        $taken = $first->diningTables->first();

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.tables', $second), ['table_ids' => [$taken->id]])
            ->assertSessionHasErrors('table_ids');

        $this->assertFalse($second->fresh()->diningTables->contains('id', $taken->id));
    }

    public function test_le_tan_dat_ho_khach_duoc_bo_qua_gioi_han_sat_gio(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(18, 45));

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.store'), [
                'branch_id' => $this->branchA->id,
                'customer_name' => 'Khách gọi điện',
                'customer_phone' => '0912345678',
                'party_size' => 2,
                'booking_date' => Carbon::today()->toDateString(),
                'start_time' => '19:00',
                'source' => 'phone',
            ]);

        $booking = Booking::firstOrFail();

        $this->assertSame('phone', $booking->source);
        $this->assertSame($this->manager->id, $booking->created_by);

        Carbon::setTestNow();
    }

    // ---------- Khai bao ban ----------

    public function test_them_ban_hang_loat(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.tables.bulk', $this->branchA), [
                'prefix' => 'B',
                'from' => 1,
                'to' => 5,
                'seats_max' => 1,
            ])->assertRedirect();

        $this->assertSame(7, $this->branchA->diningTables()->count()); // 2 ban cu + 5 moi
        $this->assertNotNull($this->branchA->diningTables()->where('code', 'B03')->first());
    }

    public function test_xoa_ban_da_co_khach_thi_chuyen_sang_an(): void
    {
        $booking = $this->makeBooking($this->brandA, $this->branchA);
        $table = $booking->diningTables->first();

        $this->actingAs($this->admin)
            ->delete(route('admin.tables.destroy', [$this->branchA, $table]));

        $this->assertDatabaseHas('dining_tables', ['id' => $table->id, 'is_active' => false]);
    }

    // ---------- Quan va tai khoan ----------

    public function test_khong_xoa_duoc_quan_dang_con_dia_diem(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.brands.destroy', $this->brandA))
            ->assertSessionHasErrors('brand');

        $this->assertDatabaseHas('brands', ['id' => $this->brandA->id]);
    }

    public function test_ten_mien_khong_duoc_trung_giua_hai_quan(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.brands.update', $this->brandB), [
                'name' => 'Quán B',
                'domain' => $this->brandA->domain,
                'mark' => 'QB',
                'accent_color' => '#7fb59b',
            ])->assertSessionHasErrors('domain');
    }

    public function test_admin_khong_tu_khoa_tai_khoan_cua_chinh_minh(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHasErrors('user');

        $this->assertTrue($this->admin->fresh()->is_active);
    }
}
