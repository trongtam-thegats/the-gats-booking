<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NightBookingAndLookupTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán Đêm',
            'slug' => 'quan-dem',
            'domain' => 'booking.quandem.test',
            'mark' => 'QD',
            'accent_color' => '#c8a15a',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->branch = $this->brand->branches()->create([
            'name' => 'Quán Đêm Đà Lạt',
            'slug' => 'quan-dem-da-lat',
            'phone' => '0900000000',
            'open_time' => '17:00',
            'close_time' => '02:00',
            'slot_minutes' => 30,
            'turn_minutes' => 120,
            'min_lead_minutes' => 60,
            'max_advance_days' => 30,
            'max_party_size' => 20,
            'is_active' => true,
        ]);

        $area = $this->branch->areas()->create(['name' => 'Tầng 1', 'bookable' => true]);

        $this->branch->diningTables()->create([
            'area_id' => $area->id,
            'code' => 'N01',
            'table_type' => 'high_table',
            'seats_min' => 1,
            'seats_max' => 4,
            'combinable' => true,
        ]);
    }

    public function test_starts_at_correctly_advances_day_for_post_midnight_slots(): void
    {
        $booking = Booking::create([
            'code' => 'TGTEST01',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách Đêm',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-08-30',
            'start_time' => '01:00:00',
            'end_time' => '02:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Branch opens at 17:00, slot 01:00 is on the early morning of 2026-08-31
        $startsAt = $booking->startsAt();
        $this->assertEquals('2026-08-31 01:00:00', $startsAt->toDateTimeString());
    }

    public function test_customer_can_cancel_post_midnight_booking_in_future(): void
    {
        // Simulate current time: 2026-08-30 20:00:00 (8pm on the night of the booking)
        Carbon::setTestNow(Carbon::parse('2026-08-30 20:00:00'));

        $booking = Booking::create([
            'code' => 'TGTEST02',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách Đêm Hủy',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-08-30',
            'start_time' => '01:00:00',
            'end_time' => '02:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->assertTrue($booking->customerCanCancel());

        // Cancel via web endpoint with spaces in phone number
        $response = $this->post(route('booking.cancel', $booking), [
            'customer_phone' => '091 234 5678',
            'reason' => 'Bận đột xuất',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_customer_lookup_with_spaces_or_international_prefix(): void
    {
        $booking = Booking::create([
            'code' => 'TGTEST03',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách Tra Cứu',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-08-30',
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Lookup with +84 prefix and spaces
        $response = $this->get(route('booking.lookup', [
            'code' => 'TGTEST03',
            'phone' => '+84 912 345 678',
        ]));

        $response->assertOk();
        $response->assertSee('TGTEST03');
        $response->assertSee('Quán Đêm Đà Lạt');

        // Detail page shows customer name
        $detail = $this->get(route('booking.show', $booking));
        $detail->assertOk();
        $detail->assertSee('Khách Tra Cứu');
    }

    public function test_so_dien_thoai_duoc_chuan_hoa_ngay_luc_khach_dat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 18:00:00'));

        $this->post(route('booking.store', ['branch' => $this->branch]), [
            'customer_name' => 'Khách Mới',
            'customer_phone' => '+84 912 345 678',
            'party_size' => 2,
            'booking_date' => '2026-08-30',
            'start_time' => '21:00',
        ])->assertRedirect();

        // Ba nguon du lieu chi ghep duoc voi nhau neu so luu vao da chuan hoa.
        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Khách Mới',
            'customer_phone' => '0912345678',
        ]);

        Carbon::setTestNow();
    }

    public function test_chi_co_ma_dat_ban_thi_khong_tra_cuu_duoc(): void
    {
        Booking::create([
            'code' => 'TGTEST05',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách Riêng Tư',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-08-30',
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Bo trong o so dien thoai thi khong duoc lo ten khach.
        $this->get(route('booking.lookup', ['code' => 'TGTEST05']))
            ->assertOk()
            ->assertDontSee('Khách Riêng Tư');

        // Sai so cung khong ra.
        $this->get(route('booking.lookup', ['code' => 'TGTEST05', 'phone' => '0900000001']))
            ->assertOk()
            ->assertDontSee('Khách Riêng Tư');
    }

    public function test_hai_cach_tinh_dem_kinh_doanh_cho_ra_cung_mot_ket_qua(): void
    {
        $booking = Booking::create([
            'code' => 'TGTEST06',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách Đêm',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-08-30',
            'start_time' => '01:00:00',
            'end_time' => '02:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Booking::startsAt() va AvailabilityService::slotStartsAt() phai luon
        // khop nhau - hai ban sao lech nhau la nguon goc cua ca ba loi tren.
        $quaDichVu = app(AvailabilityService::class)
            ->slotStartsAt('2026-08-30', '01:00', $this->branch->openMinutes());

        $this->assertSame(
            $quaDichVu->toDateTimeString(),
            $booking->startsAt()->toDateTimeString()
        );
        $this->assertSame('2026-08-31 01:00:00', $quaDichVu->toDateTimeString());
    }

    public function test_remind_command_catches_post_midnight_bookings(): void
    {
        // Current time: 2026-08-31 00:30:00 (30 mins before 1am slot of the night 2026-08-30)
        Carbon::setTestNow(Carbon::parse('2026-08-31 00:30:00'));

        $booking = Booking::create([
            'code' => 'TGTEST04',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách Nhắc Lịch',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-08-30',
            'start_time' => '01:00:00',
            'end_time' => '02:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Run reminder with 60 minutes lead
        $this->artisan('booking:remind', ['--minutes' => 60])
            ->assertSuccessful();

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'event' => 'reminder',
        ]);

        Carbon::setTestNow();
    }
}
