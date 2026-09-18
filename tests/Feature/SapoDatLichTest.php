<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SapoDatLichService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Day don dat ban da xac nhan sang trang dat lich cua Sapo FnB.
 *
 * Sapo chi mo duong TAO don, khong co duong huy hay sua - nen don huy hoac
 * doi gio phai bat co cho nhan vien xu ly tay.
 */
class SapoDatLichTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'Gemination', 'slug' => 'gemination-brand', 'domain' => 'booking.gemination.test',
            'mark' => 'GM', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Gemination Đà Lạt', 'slug' => 'gemination',
            'open_time' => '18:00', 'close_time' => '02:00',
        ]);

        $this->branch->diningTables()->create([
            'code' => 'H8', 'name' => 'Bàn H8', 'seats_min' => 2, 'seats_max' => 6, 'is_active' => true,
        ]);

        config([
            'booking.sapo_dat_lich.bat' => true,
            'booking.sapo_dat_lich.url' => 'https://dat-lich.test',
            'booking.sapo_dat_lich.merchant_id' => 19579,
            'booking.sapo_dat_lich.som_nhat_gio' => 2,
            'booking.sapo_stores' => ['46101' => 'gemination'],
        ]);
    }

    protected function don(array $them = []): Booking
    {
        return Booking::create(array_replace([
            'branch_id' => $this->branch->id,
            'code' => 'DB'.fake()->unique()->numberBetween(1000, 9999),
            'customer_name' => 'Nguyễn Trọng Tâm',
            'customer_phone' => '0865683649',
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'party_size' => 4,
            'status' => Booking::STATUS_CONFIRMED,
        ], $them));
    }

    protected function traLoiOk(string $ma = 'TB123'): void
    {
        Http::fake(['dat-lich.test/*' => Http::response(['tableBooking' => ['code' => $ma]], 200)]);
    }

    /**
     * Http::fake goi lan sau KHONG thay the lan truoc - stub cu khop truoc van
     * thang. Muon hai cau tra loi khac nhau thi phai khai mot day.
     *
     * @param  array<int, mixed>  $traLoi
     */
    protected function traLoiLanLuot(array $traLoi): void
    {
        $day = Http::sequence();

        foreach ($traLoi as $mot) {
            $day->push(...$mot);
        }

        Http::fake(['dat-lich.test/*' => $day]);
    }

    public function test_day_don_gui_dung_noi_dung_sang_sapo(): void
    {
        $this->traLoiOk('TB0001');
        $don = $this->don(['note' => 'Ngồi gần cửa sổ']);

        $this->assertTrue(app(SapoDatLichService::class)->day($don));

        Http::assertSent(function ($request) use ($don) {
            $g = $request->data();

            return $request->url() === 'https://dat-lich.test/api/booking/submit'
                && $g['storeId'] === 46101
                && $g['merchantId'] === 19579
                && $g['customerPhone'] === '0865683649'
                && $g['customerCount'] === 4
                && $g['receptionTime'] === $don->startsAt()->getTimestamp()
                && str_contains($g['note'], $don->code)
                && str_contains($g['note'], 'Ngồi gần cửa sổ')
                && $g['type'] === 'website';
        });

        $don->refresh();
        $this->assertSame('TB0001', $don->sapo_code);
        $this->assertNotNull($don->sapo_pushed_at);
        $this->assertNull($don->sapo_error);
    }

    public function test_khong_day_hai_lan(): void
    {
        $this->traLoiOk();
        $don = $this->don();
        $sapo = app(SapoDatLichService::class);

        $sapo->day($don);
        $this->assertFalse($sapo->day($don->refresh()));

        Http::assertSentCount(1);
    }

    public function test_don_sat_gio_va_don_chua_xac_nhan_thi_khong_day(): void
    {
        // Ghim gio de khong phu thuoc luc chay test: 19:00, khach den 20:00.
        $this->travelTo(today()->setTime(19, 0));

        $this->traLoiOk();
        $sapo = app(SapoDatLichService::class);

        $satGio = $this->don(['booking_date' => today()->toDateString(), 'start_time' => '20:00']);
        $choDuyet = $this->don(['status' => Booking::STATUS_PENDING]);

        $this->assertFalse($sapo->day($satGio));
        $this->assertFalse($sapo->day($choDuyet));

        Http::assertNothingSent();
        $this->assertStringContainsString('ít nhất 2 tiếng', (string) $satGio->refresh()->sapo_error);
    }

    public function test_sapo_loi_thi_ghi_lai_va_khong_lam_hong_luong_dat_ban(): void
    {
        Http::fake(['dat-lich.test/*' => Http::response('Service unavailable', 503)]);
        $don = $this->don();

        $this->assertFalse(app(SapoDatLichService::class)->day($don));

        $don->refresh();
        $this->assertNull($don->sapo_code);
        $this->assertStringContainsString('503', (string) $don->sapo_error);
    }

    public function test_lenh_day_lai_cac_don_con_sot(): void
    {
        $this->traLoiLanLuot([
            ['loi', 500],
            [['tableBooking' => ['code' => 'TB0009']], 200],
        ]);

        $don = $this->don();
        app(SapoDatLichService::class)->day($don);

        $this->artisan('sapo:day-dat-lich')->assertSuccessful();

        $this->assertSame('TB0009', $don->refresh()->sapo_code);
    }

    public function test_huy_don_da_day_thi_bat_co_xu_ly_tay(): void
    {
        $this->traLoiOk('TB0002');
        $don = $this->don();
        app(SapoDatLichService::class)->day($don);

        app(BookingService::class)->cancel($don->refresh(), 'Khách bận', 'staff');

        $this->assertStringContainsString('Vào Sapo huỷ', (string) $don->refresh()->sapo_can_xu_ly);
    }

    public function test_doi_lich_thi_day_don_moi_va_nhac_huy_don_cu(): void
    {
        $this->traLoiLanLuot([
            [['tableBooking' => ['code' => 'TB0003']], 200],
            [['tableBooking' => ['code' => 'TB0004']], 200],
        ]);

        $don = $this->don();
        app(SapoDatLichService::class)->day($don);

        $nhanVien = User::create([
            'name' => 'Quản trị', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);

        app(BookingService::class)->reschedule($don->refresh(), [
            'booking_date' => now()->addDays(3)->toDateString(),
            'start_time' => '20:00',
            'party_size' => 4,
        ], $nhanVien, false);

        $don->refresh();
        $this->assertSame('TB0004', $don->sapo_code);
        $this->assertStringContainsString('TB0003', (string) $don->sapo_can_xu_ly);
    }

    public function test_nhan_vien_bo_duoc_nhac_viec(): void
    {
        $this->traLoiOk();
        $don = $this->don();
        $sapo = app(SapoDatLichService::class);
        $sapo->day($don);
        $sapo->canXuLyTay($don->refresh(), 'Cần huỷ bên Sapo');

        $nhanVien = User::create([
            'name' => 'Quản trị', 'email' => 'admin2@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);

        $this->actingAs($nhanVien)
            ->from(route('admin.bookings.show', $don))
            ->post(route('admin.bookings.sapo-da-xu-ly', $don))
            ->assertRedirect();

        $this->assertNull($don->refresh()->sapo_can_xu_ly);
    }

    public function test_don_xac_nhan_truoc_khi_bat_thi_khong_day(): void
    {
        config(['booking.sapo_dat_lich.tu_luc' => now()->toDateTimeString()]);
        $this->traLoiOk();

        $cu = $this->don(['confirmed_at' => now()->subDay()]);
        $moi = $this->don(['confirmed_at' => now()->addMinute()]);

        $sapo = app(SapoDatLichService::class);

        $this->assertFalse($sapo->day($cu));
        $this->assertTrue($sapo->day($moi));

        $this->artisan('sapo:day-dat-lich')->assertSuccessful();
        $this->assertNull($cu->refresh()->sapo_code);
    }

    public function test_tat_cong_thi_khong_goi_sapo(): void
    {
        config(['booking.sapo_dat_lich.bat' => false]);
        Http::fake();

        $this->assertFalse(app(SapoDatLichService::class)->day($this->don()));

        Http::assertNothingSent();
    }
}
