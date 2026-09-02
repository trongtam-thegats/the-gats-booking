<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDeletion;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Xoa han dat ban - chi quan tri.
 *
 * Muc dich cua chuc nang la lam sach so lieu bao cao va phan tich khach hang
 * khi co don sai lot vao. Vi xoa that nen phai chac hai dieu: dung nguoi moi
 * xoa duoc, va sau khi xoa van con dau vet doc duoc.
 */
class XoaDatBanTest extends TestCase
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
            'name' => 'Giám đốc',
            'email' => 'admin@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::ADMIN,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Quản lý',
            'email' => 'quanly@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::MANAGER,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $this->viewer = User::create([
            'name' => 'Người xem',
            'email' => 'xem@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::VIEWER,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);
    }

    protected function makeBooking(array $overrides = []): Booking
    {
        return app(BookingService::class)->create($this->branch, array_merge([
            'customer_name' => 'Khách thử',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ], $overrides));
    }

    public function test_quan_tri_xoa_duoc_va_don_bien_mat_that(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Đơn trùng do khách bấm hai lần'])
            ->assertRedirect(route('admin.bookings.index'));

        $this->assertSame(0, Booking::count());
        $this->assertDatabaseMissing('bookings', ['code' => $booking->code]);
    }

    public function test_xoa_xong_thi_nha_ban_ra_cho_khach_khac(): void
    {
        $booking = $this->makeBooking();
        $this->assertSame(1, $booking->diningTables()->count());

        $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Đơn thử của nhân viên']);

        $this->assertDatabaseCount('booking_dining_table', 0);
    }

    public function test_moi_lan_xoa_deu_de_lai_mot_dong_nhat_ky_doc_duoc(): void
    {
        $booking = $this->makeBooking(['party_size' => 4, 'customer_name' => 'Nguyễn Văn A']);

        $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Khách gọi báo đặt nhầm ngày']);

        $nhatKy = BookingDeletion::firstOrFail();

        $this->assertSame($booking->code, $nhatKy->code);
        $this->assertSame('Nguyễn Văn A', $nhatKy->customer_name);
        $this->assertSame(4, $nhatKy->party_size);
        $this->assertSame('Quán Thử', $nhatKy->branch_name);
        $this->assertSame('Khách gọi báo đặt nhầm ngày', $nhatKy->reason);
        $this->assertSame($this->admin->id, $nhatKy->deleted_by);
        // Ten luu san, de con doc duoc ca khi tai khoan bi xoa sau nay.
        $this->assertSame('Giám đốc', $nhatKy->deleted_by_name);
    }

    public function test_ban_sao_du_de_dung_lai_don_neu_xoa_nham(): void
    {
        $booking = $this->makeBooking();
        $maBan = $booking->diningTables->pluck('id')->all();

        $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Xóa thử']);

        $duLieu = BookingDeletion::firstOrFail()->du_lieu;

        $this->assertSame($booking->code, $duLieu['booking']['code']);
        $this->assertSame($booking->customer_phone, $duLieu['booking']['customer_phone']);
        $this->assertSame($maBan, $duLieu['dining_table_ids']);
        $this->assertSame(['T1'], $duLieu['dining_table_codes']);
    }

    public function test_bat_buoc_nhap_ly_do(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(1, Booking::count(), 'Thieu ly do thi khong duoc xoa.');
    }

    public function test_quan_ly_khong_xoa_duoc(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->manager)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Thử xóa'])
            ->assertForbidden();

        $this->assertSame(1, Booking::count());
    }

    public function test_nguoi_chi_xem_khong_xoa_duoc(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->viewer)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Thử xóa'])
            ->assertForbidden();

        $this->assertSame(1, Booking::count());
    }

    public function test_khach_vang_lai_khong_cham_toi_duoc(): void
    {
        $booking = $this->makeBooking();

        $this->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Thử xóa'])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(1, Booking::count());
    }

    public function test_trang_nhat_ky_xoa_chi_quan_tri_vao_duoc(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Đơn trùng']);

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.deletions'))
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('Đơn trùng');

        $this->actingAs($this->manager)
            ->get(route('admin.bookings.deletions'))
            ->assertForbidden();
    }

    public function test_nut_xoa_chi_hien_voi_quan_tri(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Xóa vĩnh viễn');

        $this->actingAs($this->manager)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Xóa vĩnh viễn');
    }

    public function test_don_da_xoa_khong_con_trong_danh_sach_dat_ban(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->admin)
            ->delete(route('admin.bookings.destroy', $booking), ['reason' => 'Đơn trùng']);

        // Ma don van hien mot lan o bang bao "da xoa", nen soi vao chinh bang
        // danh sach thay vi tim ma tren toan trang.
        $this->actingAs($this->admin)
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('Không có đặt bàn nào khớp bộ lọc');
    }
}
