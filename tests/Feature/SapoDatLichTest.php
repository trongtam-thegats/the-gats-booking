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
            'booking.sapo_dat_lich.som_nhat_phut' => 30,
            'booking.sapo_dat_lich.xa_nhat_ngay' => 30,
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
        Http::fake([
            '*merchant-info*' => Http::response($this->quyDinhSapo(), 200),
            'dat-lich.test/*' => Http::response(['tableBooking' => ['code' => $ma]], 200),
        ]);
    }

    /**
     * Quy dinh nhan don cua Sapo, dang that cua /api/booking/merchant-info.
     * Chu quan doi duoc muc nay trong Sapo nen he thong doc thang tu do.
     */
    /** Dem so lan that su GUI DON sang Sapo, bo qua cac lan hoi quy dinh. */
    protected function soLanGuiDon(): int
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), '/api/booking/submit'))->count();
    }

    protected function quyDinhSapo(int $somNhatPhut = 30, int $xaNhatNgay = 30): array
    {
        return ['stores' => [[
            'id' => 46101,
            'name' => 'Gemination Đà Lạt',
            'storeSettings' => [[
                'settingKey' => 'features_booking',
                'settingValue' => json_encode(['reception_time' => [
                    'min_booking_time' => ['unit' => 'minute', 'time' => $somNhatPhut],
                    'max_booking_date' => $xaNhatNgay,
                ]]),
            ]],
        ]]];
    }

    /**
     * Hai cau tra loi khac nhau cho hai lan gui don.
     *
     * Hai bay cua Http::fake, da sap ca hai:
     *  - goi fake() lan sau KHONG thay the lan truoc, stub cu khop van thang;
     *  - tron Http::sequence() voi mot stub khac trong cung mot mang thi moi
     *    yeu cau deu RUT mot cau tra loi khoi day, ke ca yeu cau khop stub kia.
     * Nen o day dung mot closure duy nhat, tu chia duong.
     *
     * @param  array<int, mixed>  $traLoi
     */
    protected function traLoiLanLuot(array $traLoi): void
    {
        $con = $traLoi;

        Http::fake(function ($request) use (&$con) {
            if (str_contains($request->url(), 'merchant-info')) {
                return Http::response($this->quyDinhSapo(), 200);
            }

            $mot = array_shift($con) ?? ['het luot', 500];

            return Http::response(...$mot);
        });
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

        // Hai yeu cau: mot hoi quy dinh, mot gui don. Lan hai khong goi gi nua.
        $this->assertSame(1, $this->soLanGuiDon());
    }

    public function test_don_sat_gio_va_don_chua_xac_nhan_thi_khong_day(): void
    {
        // Ghim gio de khong phu thuoc luc chay test: 19:00, khach den 20:00.
        $this->travelTo(today()->setTime(19, 0));

        $this->traLoiOk();
        $sapo = app(SapoDatLichService::class);

        // Sapo dang nhan don truoc 30 phut: 19:10 cho gio den 19:30 la sat qua.
        $this->travelTo(today()->setTime(19, 10));
        $satGio = $this->don(['booking_date' => today()->toDateString(), 'start_time' => '19:30']);
        $choDuyet = $this->don(['status' => Booking::STATUS_PENDING]);

        $this->assertFalse($sapo->day($satGio));
        $this->assertFalse($sapo->day($choDuyet));

        $this->assertSame(0, $this->soLanGuiDon());
        $this->assertStringContainsString('ít nhất 30 phút', (string) $satGio->refresh()->sapo_error);
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

    public function test_lenh_bo_qua_don_cua_nhung_ngay_da_qua(): void
    {
        $this->traLoiOk();
        $homQua = $this->don(['booking_date' => today()->subDay()->toDateString()]);

        $this->artisan('sapo:day-dat-lich')->assertSuccessful();

        $this->assertSame(0, $this->soLanGuiDon());
        $this->assertNull($homQua->refresh()->sapo_code);
    }

    public function test_xem_truoc_thi_khong_gui_gi_sang_sapo(): void
    {
        $this->traLoiOk();
        $don = $this->don();

        $this->artisan('sapo:day-dat-lich --xem')
            ->expectsOutputToContain('1 đơn đang chờ đẩy sang Sapo')
            ->assertSuccessful();

        $this->assertSame(0, $this->soLanGuiDon());
        $this->assertNull($don->refresh()->sapo_code);
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

    public function test_doc_quy_dinh_gio_thang_tu_sapo(): void
    {
        // Chu quan doi muc nhan don trong Sapo thi he thong theo ngay, khong
        // phai sua .env: 18/09/2026 da doi tu 2 tieng xuong 30 phut.
        Http::fake([
            '*merchant-info*' => Http::response($this->quyDinhSapo(120, 7), 200),
            'dat-lich.test/*' => Http::response(['tableBooking' => ['code' => 'TB0007']], 200),
        ]);

        $this->travelTo(today()->setTime(18, 30));
        $sapo = app(SapoDatLichService::class);

        $this->assertSame(['som_nhat_phut' => 120, 'xa_nhat_ngay' => 7], $sapo->quyDinh(46101));

        $satGio = $this->don(['booking_date' => today()->toDateString(), 'start_time' => '19:30']);
        $quaXa = $this->don(['booking_date' => today()->addDays(9)->toDateString()]);

        $this->assertFalse($sapo->day($satGio));
        $this->assertFalse($sapo->day($quaXa));
        $this->assertStringContainsString('ít nhất 2 tiếng', (string) $satGio->refresh()->sapo_error);
        $this->assertStringContainsString('trong vòng 7 ngày', (string) $quaXa->refresh()->sapo_error);
    }

    public function test_hoi_khong_duoc_quy_dinh_thi_dung_muc_du_phong(): void
    {
        Http::fake([
            '*merchant-info*' => Http::response('sap', 500),
            'dat-lich.test/*' => Http::response(['tableBooking' => ['code' => 'TB0008']], 200),
        ]);

        $sapo = app(SapoDatLichService::class);

        $this->assertSame(['som_nhat_phut' => 30, 'xa_nhat_ngay' => 30], $sapo->quyDinh(46101));
        $this->assertTrue($sapo->day($this->don()));
    }

    public function test_tat_cong_thi_khong_goi_sapo(): void
    {
        config(['booking.sapo_dat_lich.bat' => false]);
        Http::fake();

        $this->assertFalse(app(SapoDatLichService::class)->day($this->don()));

        Http::assertNothingSent();
    }
}
