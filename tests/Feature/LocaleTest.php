<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Trang khach co hai ban: tieng Viet va tieng Anh.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu',
            'domain' => 'booking.quanthu.test', 'mark' => 'QT',
            'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu-cn', 'phone' => '0900000000',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);

        $this->branch->diningTables()->create([
            'code' => 'T1', 'table_type' => 'high_table',
            'seats_min' => 1, 'seats_max' => 4, 'combinable' => true,
        ]);
    }

    public function test_mac_dinh_la_tieng_viet(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('lang="vi"', false)
            ->assertSee('Chọn ngày')
            ->assertSee('Bao nhiêu khách?');
    }

    public function test_chon_tieng_anh_thi_ca_trang_doi_sang_tieng_anh(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Pick a date')
            ->assertSee('How many guests?')
            ->assertSee('Your details')
            ->assertDontSee('Chọn ngày');
    }

    public function test_lua_chon_ngon_ngu_duoc_nho_giua_cac_trang(): void
    {
        $this->get('/?lang=en')->assertOk();

        // Trang sau khong co ?lang van phai giu tieng Anh.
        $this->get(route('booking.lookup'))
            ->assertOk()
            ->assertSee('Check your booking')
            ->assertDontSee('Kiểm tra đặt bàn');
    }

    public function test_trinh_duyet_tieng_anh_duoc_mo_ban_tieng_anh_ngay_lan_dau(): void
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get('/')
            ->assertOk()
            ->assertSee('Pick a date');
    }

    public function test_nut_doi_ngon_ngu_giu_nguyen_trang_dang_xem(): void
    {
        $this->get(route('booking.lookup'))
            ->assertOk()
            ->assertSee('/tra-cuu?lang=en', false);
    }

    public function test_loi_bao_cho_khach_dung_thu_tieng_dang_xem(): void
    {
        $payload = [
            'customer_name' => 'Khách',
            'customer_phone' => '0912345678',
            'party_size' => 99,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ];

        $this->post(route('booking.store', $this->branch).'?lang=en', $payload)
            ->assertSessionHasErrors(['start_time' => 'For parties of 21 or more, please call 0900000000 so we can arrange it.']);
    }

    public function test_dat_ban_ghi_lai_ngon_ngu_khach_da_dung(): void
    {
        $this->post(route('booking.store', $this->branch).'?lang=en', [
            'customer_name' => 'Guest',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ]);

        $this->assertSame('en', Booking::firstOrFail()->locale);
    }

    public function test_tin_gui_khach_theo_ngon_ngu_luc_dat_chu_khong_theo_nhan_vien(): void
    {
        $this->post(route('booking.store', $this->branch).'?lang=en', [
            'customer_name' => 'Guest',
            'customer_phone' => '0912345678',
            'customer_email' => 'guest@example.com',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ]);

        $booking = Booking::firstOrFail()->load(['branch', 'diningTables']);

        // Nhan vien dang xem khu quan tri bang tieng Viet.
        app()->setLocale('vi');

        $subject = \App\Services\Notifications\BookingMessage::subject($booking, 'confirmed');
        $body = \App\Services\Notifications\BookingMessage::body($booking, 'confirmed');

        $this->assertStringContainsString('Booking confirmed', $subject);
        $this->assertStringContainsString('your table is confirmed', $body);
        $this->assertStringNotContainsString('Xác nhận', $subject);
    }

    public function test_khung_gio_het_ban_bao_bang_tieng_anh(): void
    {
        $this->branch->update(['turn_minutes' => 480]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Khách', 'customer_phone' => '0912345678',
            'party_size' => 4,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '17:00',
        ]);

        $message = $this->getJson(route('booking.slots', $this->branch).'?'.http_build_query([
            'date' => Carbon::tomorrow()->toDateString(),
            'party_size' => 4,
            'lang' => 'en',
        ]))->json('message');

        $this->assertSame('We are fully booked on this date. Please try another day.', $message);
    }
}
