<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\GuestNote;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tra cuu khach, ghi chu ve khach, va doi lich mot dat ban da co.
 */
class GuestAndRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected User $manager;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu',
            'domain' => 'booking.quanthu.test', 'mark' => 'QT',
            'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $this->brand->branches()->create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu-cn', 'phone' => '0900000000',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);

        foreach (['T1', 'T2'] as $code) {
            $this->branch->diningTables()->create([
                'code' => $code, 'table_type' => 'high_table',
                'seats_min' => 1, 'seats_max' => 4, 'combinable' => true,
            ]);
        }

        $this->manager = User::create([
            'name' => 'Quản lý', 'email' => 'ql@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->viewer = User::create([
            'name' => 'Người xem', 'email' => 'xem@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::VIEWER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);
    }

    protected function makeBooking(array $overrides = []): Booking
    {
        return app(BookingService::class)->create($this->branch, array_merge([
            'customer_name' => 'Lê Văn Quen',
            'customer_phone' => '0987 654 321',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ], $overrides));
    }

    /** @return array<string, mixed> */
    protected function rescheduleData(Booking $booking, array $overrides = []): array
    {
        return array_merge([
            'booking_date' => $booking->booking_date->toDateString(),
            'start_time' => substr($booking->start_time, 0, 5),
            'party_size' => $booking->party_size,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
        ], $overrides);
    }

    // ---------- Doi lich ----------

    public function test_doi_gio_giu_nguyen_ban_neu_khung_gio_moi_van_trong(): void
    {
        $booking = $this->makeBooking();
        $tableId = $booking->diningTables->first()->id;

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.reschedule', $booking),
                $this->rescheduleData($booking, ['start_time' => '20:00']))
            ->assertRedirect();

        $booking->refresh()->load('diningTables');

        $this->assertSame('20:00', substr($booking->start_time, 0, 5));
        $this->assertSame('22:00', substr($booking->end_time, 0, 5));
        $this->assertSame($tableId, $booking->diningTables->first()->id);
    }

    public function test_doi_lich_gui_tin_cap_nhat_cho_khach(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.reschedule', $booking),
                $this->rescheduleData($booking, ['start_time' => '20:00', 'notify_guest' => '1']));

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'event' => 'updated',
        ]);
    }

    public function test_khong_gui_tin_khi_bo_chon_bao_khach(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.reschedule', $booking),
                $this->rescheduleData($booking, ['start_time' => '20:00']));

        $this->assertDatabaseMissing('notification_logs', [
            'booking_id' => $booking->id,
            'event' => 'updated',
        ]);
    }

    public function test_tang_so_khach_thi_doi_sang_bo_ban_du_cho(): void
    {
        $booking = $this->makeBooking(['party_size' => 2]);

        $this->assertCount(1, $booking->diningTables);

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.reschedule', $booking),
                $this->rescheduleData($booking, ['party_size' => 7]));

        $booking->refresh()->load('diningTables');

        $this->assertSame(7, $booking->party_size);
        $this->assertCount(2, $booking->diningTables);
    }

    public function test_doi_sang_khung_gio_da_kin_thi_giu_nguyen_dat_ban_cu(): void
    {
        // Hai ban deu bi chiem luc 21:00.
        $this->makeBooking(['customer_phone' => '0911111111', 'party_size' => 4, 'start_time' => '21:00']);
        $this->makeBooking(['customer_phone' => '0922222222', 'party_size' => 4, 'start_time' => '21:00']);

        $booking = $this->makeBooking(['customer_phone' => '0933333333', 'party_size' => 4, 'start_time' => '17:00']);

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.reschedule', $booking),
                $this->rescheduleData($booking, ['start_time' => '21:00']))
            ->assertSessionHasErrors('start_time');

        $booking->refresh();

        $this->assertSame('17:00', substr($booking->start_time, 0, 5));
        $this->assertCount(1, $booking->diningTables);
    }

    public function test_vai_chi_xem_khong_doi_lich_duoc(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->viewer)
            ->post(route('admin.bookings.reschedule', $booking), $this->rescheduleData($booking, [
                'start_time' => '20:00',
            ]))
            ->assertForbidden();

        $this->assertSame('19:00', substr($booking->fresh()->start_time, 0, 5));
    }

    // ---------- Tra cuu khach ----------

    public function test_tim_khach_duoc_du_go_so_khong_co_khoang_trang(): void
    {
        $this->makeBooking();

        $this->actingAs($this->manager)
            ->get(route('admin.guests.index', ['q' => '0987654321']))
            ->assertOk()
            ->assertSee('Lê Văn Quen');
    }

    public function test_ho_so_khach_dem_dung_so_lan_hen_ma_khong_toi(): void
    {
        $first = $this->makeBooking(['start_time' => '17:00']);
        $second = $this->makeBooking(['start_time' => '19:00']);
        $this->makeBooking(['start_time' => '21:00']);

        $first->update(['status' => Booking::STATUS_NO_SHOW]);
        $second->update(['status' => Booking::STATUS_COMPLETED]);

        $response = $this->actingAs($this->manager)
            ->get(route('admin.guests.index', ['phone' => '0987654321']));

        $response->assertOk()
            ->assertSee('Hẹn mà không tới')
            ->assertSee('Lê Văn Quen');

        $profile = app(\App\Services\GuestProfileService::class)
            ->forPhone('0987654321', null, $this->brand->id);

        $this->assertSame(3, $profile['total']);
        $this->assertSame(1, $profile['no_show']);
        $this->assertSame(1, $profile['completed']);
    }

    public function test_quan_ly_khong_thay_khach_cua_quan_khac(): void
    {
        $this->makeBooking();

        $otherBrand = Brand::create([
            'name' => 'Quán Khác', 'slug' => 'quan-khac',
            'domain' => 'booking.quankhac.test', 'mark' => 'QK',
            'accent_color' => '#7fb59b', 'is_active' => true,
        ]);

        $otherManager = User::create([
            'name' => 'Quản lý khác', 'email' => 'qlk@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $otherBrand->id, 'is_active' => true,
        ]);

        $this->actingAs($otherManager)
            ->get(route('admin.guests.index', ['q' => '0987654321']))
            ->assertOk()
            ->assertDontSee('Lê Văn Quen');
    }

    // ---------- Ghi chu ve khach ----------

    public function test_luu_ghi_chu_va_danh_dau_vip(): void
    {
        $this->makeBooking();

        $this->actingAs($this->manager)
            ->post(route('admin.guests.note'), [
                'phone' => '0987 654 321',
                'name' => 'Anh Quen',
                'note' => 'Thích bàn cạnh cửa sổ',
                'is_vip' => '1',
            ])->assertRedirect();

        $this->assertDatabaseHas('guest_notes', [
            'brand_id' => $this->brand->id,
            'phone' => '0987654321',
            'name' => 'Anh Quen',
            'is_vip' => true,
        ]);
    }

    public function test_vai_chi_xem_khong_luu_ghi_chu_duoc(): void
    {
        $this->makeBooking();

        $this->actingAs($this->viewer)
            ->post(route('admin.guests.note'), [
                'phone' => '0987654321',
                'note' => 'thử',
            ])->assertForbidden();

        $this->assertDatabaseCount('guest_notes', 0);
    }

    // ---------- Chan dat ban ----------

    public function test_khach_bi_chan_khong_tu_dat_online_duoc(): void
    {
        GuestNote::create([
            'brand_id' => $this->brand->id,
            'phone' => '0987654321',
            'is_blocked' => true,
        ]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Lê Văn Quen',
            'customer_phone' => '0987 654 321',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(0, Booking::count());
    }

    public function test_nhan_vien_van_dat_ho_duoc_cho_khach_bi_chan(): void
    {
        GuestNote::create([
            'brand_id' => $this->brand->id,
            'phone' => '0987654321',
            'is_blocked' => true,
        ]);

        $this->actingAs($this->manager)
            ->post(route('admin.bookings.store'), [
                'branch_id' => $this->branch->id,
                'customer_name' => 'Lê Văn Quen',
                'customer_phone' => '0987 654 321',
                'party_size' => 2,
                'booking_date' => Carbon::tomorrow()->toDateString(),
                'start_time' => '19:00',
                'source' => 'phone',
            ])->assertRedirect();

        $this->assertSame(1, Booking::count());
    }
}
