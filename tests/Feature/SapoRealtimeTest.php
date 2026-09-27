<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\Setting;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\SapoRealtimeService;
use App\Services\ZaloNhomService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SapoRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected DiningTable $ban1;

    protected DiningTable $ban2;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'Drinking & Healing',
            'slug' => 'drinking-healing-brand',
            'domain' => 'booking.drinkinghealing.test',
            'mark' => 'DH',
            'accent_color' => '#1a1a1a',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Drinking Healing',
            'slug' => 'drinking-healing',
            'open_time' => '17:00',
            'close_time' => '02:00',
            'turn_minutes' => 120,
        ]);

        $this->ban1 = $this->branch->diningTables()->create([
            'code' => 'Bar 1',
            'aliases' => 'B1,B01',
            'seats_min' => 1,
            'seats_max' => 2,
            'is_active' => true,
        ]);

        $this->ban2 = $this->branch->diningTables()->create([
            'code' => 'T1',
            'seats_min' => 2,
            'seats_max' => 4,
            'is_active' => true,
        ]);

        Config::set('booking.sapo_realtime.bat_dh', true);
        Config::set('booking.sapo_realtime.cookie_dh', 'test_cookie_session=xyz123');
        Config::set('booking.zalo_group.nhom.drinking-healing', "DH's Booking");
        Config::set('booking.admin_domain', 'admin.thegats.test');
    }

    public function test_bat_duoc_khi_co_cau_hinh_va_cookie(): void
    {
        $service = app(SapoRealtimeService::class);
        $this->assertTrue($service->batDuoc('drinking-healing'));

        Config::set('booking.sapo_realtime.bat_dh', false);
        $this->assertFalse($service->batDuoc('drinking-healing'));

        Config::set('booking.sapo_realtime.bat_dh', true);
        Config::set('booking.sapo_realtime.cookie_dh', '');
        $this->assertFalse($service->batDuoc('drinking-healing'));
    }

    public function test_tinh_dem_kinh_doanh_chuan_xac(): void
    {
        $service = app(SapoRealtimeService::class);

        // 20:00 ngay 28/09 -> dem 28/09
        $toi = Carbon::parse('2026-09-28 20:00:00');
        $this->assertSame('2026-09-28', $service->demKinhDoanh($this->branch, $toi));

        // 01:30 ngay 29/09 -> thuoc dem 28/09
        $khuya = Carbon::parse('2026-09-29 01:30:00');
        $this->assertSame('2026-09-28', $service->demKinhDoanh($this->branch, $khuya));
    }

    public function test_chuan_ma_ban_khop_duoc_cac_dang_ten_sapo(): void
    {
        $service = app(SapoRealtimeService::class);

        $this->assertSame('BAR1', $service->chuanMaBan('Bar 1'));
        $this->assertSame('BAR1', $service->chuanMaBan('Bàn Bar 1'));
        $this->assertSame('BAR1', $service->chuanMaBan('Quầy Bar 1'));
        $this->assertSame('T1', $service->chuanMaBan('Bàn T1'));
        $this->assertSame('T1', $service->chuanMaBan('T1'));

        $bang = $service->bangTraCuuBan($this->branch);
        $this->assertSame($this->ban1->id, $bang['BAR1']->id);
        $this->assertSame($this->ban1->id, $bang['B1']->id);
        $this->assertSame($this->ban1->id, $bang['B01']->id);
        $this->assertSame($this->ban2->id, $bang['T1']->id);
    }

    public function test_canh_bao_zalo_khi_cookie_het_han_401(): void
    {
        Http::fake([
            'https://fnb.mysapo.vn/admin/orders.json*' => Http::response('Unauthorized', 401),
            'https://zalo.thegats.vn/api/v1/zalo/send-message' => Http::response(['success' => true], 200),
        ]);

        $zaloNhomMock = $this->createMock(ZaloNhomService::class);
        $zaloNhomMock->expects($this->once())
            ->method('guiTin')
            ->with(
                $this->equalTo("DH's Booking"),
                $this->stringContains('CẢNH BÁO SAPO - DRINKING & HEALING'),
                $this->callback(fn ($ctx) => ($ctx['status'] ?? null) === 401)
            );

        $service = new SapoRealtimeService($zaloNhomMock);
        $ketQua = $service->keoDonMo('drinking-healing');

        $this->assertFalse($ketQua['thanhCong']);
        $this->assertSame(401, $ketQua['maLoi']);

        // Thu lai lan nua ngay sau do -> cache throttle phai chan, khong ban tin Zalo lan thu 2
        $service->canhBaoLoiCookie('drinking-healing', 401);
    }

    public function test_dong_bo_tao_booking_walk_in_va_khoa_ban_tren_availability(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 19:30:00'));

        Http::fake([
            'https://fnb.mysapo.vn/admin/orders.json*' => Http::response([
                'orders' => [
                    [
                        'id' => 99101,
                        'name' => 'E99101',
                        'status' => 'open',
                        'dine_type' => 'dine_in',
                        'customer_count' => 2,
                        'order_time' => Carbon::now()->timestamp,
                        'table' => [
                            'table_name' => 'Bàn Bar 1',
                        ],
                        'customer' => [
                            'first_name' => 'Văn B',
                            'last_name' => 'Nguyễn',
                            'phone' => '0912345678',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(SapoRealtimeService::class);
        $ketQua = $service->dongBo('drinking-healing', true);

        $this->assertSame(1, $ketQua['tongDonMo']);
        $this->assertSame(1, $ketQua['banKhoaMoi']);
        $this->assertSame(0, $ketQua['banDangNgoi']);
        $this->assertSame(0, $ketQua['banGiaiPhong']);

        // Kiem tra booking walk-in da duoc tao
        $booking = Booking::where('sapo_code', 'ORD-99101')->first();
        $this->assertNotNull($booking);
        $this->assertSame(Booking::STATUS_SEATED, $booking->status);
        $this->assertSame('19:30:00', $booking->start_time);
        $this->assertSame('21:30:00', $booking->end_time);
        $this->assertTrue($booking->diningTables->contains($this->ban1->id));

        // Kiem tra AvailabilityService: Ban 1 phai bi khoa trong khung gio 19:30 - 21:30
        $availService = app(AvailabilityService::class);
        $tablesAvailable = $availService->availableTables($this->branch, '2026-09-28', 1200, 1320);
        $this->assertFalse($tablesAvailable->contains('id', $this->ban1->id));
        $this->assertTrue($tablesAvailable->contains('id', $this->ban2->id));

        // Lan quet tiep theo: don hang van dang mo -> ban tiep tuc o trang thai dang ngoi
        $ketQuaLan2 = $service->dongBo('drinking-healing', true);
        $this->assertSame(1, $ketQuaLan2['banDangNgoi']);
        $this->assertSame(0, $ketQuaLan2['banKhoaMoi']);
    }

    public function test_dong_bo_tu_dong_giai_phong_ban_khi_sapo_dong_don(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 19:30:00'));

        // Tao san booking seated cua don Sapo truoc do
        $booking = Booking::create([
            'code' => Booking::generateCode(),
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách cũ',
            'customer_phone' => '0900000000',
            'party_size' => 2,
            'booking_date' => '2026-09-28',
            'start_time' => '19:30:00',
            'end_time' => '21:30:00',
            'status' => Booking::STATUS_SEATED,
            'sapo_code' => 'ORD-88888',
        ]);
        $booking->diningTables()->sync([$this->ban1->id]);

        // Sapo tra ve danh sach rong (don da thanh toan)
        Http::fake([
            'https://fnb.mysapo.vn/admin/orders.json*' => Http::response([
                'orders' => [],
            ], 200),
        ]);

        $service = app(SapoRealtimeService::class);
        $ketQua = $service->dongBo('drinking-healing', true);

        $this->assertSame(1, $ketQua['banGiaiPhong']);

        $booking->refresh();
        $this->assertSame(Booking::STATUS_COMPLETED, $booking->status);
        $this->assertNotNull($booking->completed_at);

        // AvailabilityService mo lai ban
        $availService = app(AvailabilityService::class);
        $tablesAvailable = $availService->availableTables($this->branch, '2026-09-28', 1200, 1320);
        $this->assertTrue($tablesAvailable->contains('id', $this->ban1->id));
    }

    public function test_dong_bo_chuyen_booking_online_co_san_sang_seated(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 20:00:00'));

        $donOnline = Booking::create([
            'code' => 'TGONLINE1',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Nguyễn Khách Đặt Trước',
            'customer_phone' => '0988776655',
            'party_size' => 2,
            'booking_date' => '2026-09-28',
            'start_time' => '20:00:00',
            'end_time' => '22:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $donOnline->diningTables()->sync([$this->ban2->id]);

        Http::fake([
            'https://fnb.mysapo.vn/admin/orders.json*' => Http::response([
                'orders' => [
                    [
                        'id' => 77777,
                        'name' => 'E77777',
                        'status' => 'open',
                        'dine_type' => 'dine_in',
                        'customer_count' => 2,
                        'order_time' => Carbon::now()->timestamp,
                        'table' => [
                            'table_name' => 'T1',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(SapoRealtimeService::class);
        $ketQua = $service->dongBo('drinking-healing', true);

        $this->assertSame(1, $ketQua['banKhoaMoi']);

        $donOnline->refresh();
        $this->assertSame(Booking::STATUS_SEATED, $donOnline->status);
        $this->assertSame('ORD-77777', $donOnline->sapo_code);
    }

    public function test_artisan_command_sapo_realtime_chay_thanh_cong(): void
    {
        Http::fake([
            'https://fnb.mysapo.vn/admin/orders.json*' => Http::response([
                'orders' => [],
            ], 200),
        ]);

        $this->artisan('sapo:realtime', ['--quan' => 'drinking-healing', '--xem' => true])
            ->assertSuccessful();

        $this->artisan('sapo:realtime', ['--quan' => 'drinking-healing'])
            ->assertSuccessful();
    }

    public function test_admin_co_the_cap_nhat_cookie_va_kiem_tra_ket_noi(): void
    {
        Config::set('booking.admin_domain', 'admin.thegats.test');
        $admin = User::factory()->create([
            'role' => Roles::ADMIN,
            'is_active' => true,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put('http://admin.thegats.test/quan-ly/cai-dat', [
                'notify_channels' => ['email'],
                'reminder_lead_minutes' => 180,
                'mail_mailer' => 'log',
                'sapo_realtime_dh_bat' => '1',
                'sapo_cookie_dh' => 'moi_cap_nhat_cookie=123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('moi_cap_nhat_cookie=123', Setting::get('sapo_cookie_dh'));
        $this->assertSame('1', Setting::get('sapo_realtime_dh_bat'));

        // Kiem tra nut kiem tra ket noi Sapo
        Http::fake([
            'https://fnb.mysapo.vn/admin/orders.json*' => Http::response([
                'orders' => [
                    [
                        'id' => 1,
                        'dine_type' => 'dine_in',
                        'table' => ['table_name' => 'Bar 1'],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->post('http://admin.thegats.test/quan-ly/cai-dat/kiem-tra-sapo', [
                'sapo_cookie_dh' => 'moi_cap_nhat_cookie=123',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Kết nối Sapo thành công! Đang có 1 bàn có khách tại quán.');
    }

    public function test_tu_dong_chuan_hoa_raw_jwt_token(): void
    {
        $service = app(SapoRealtimeService::class);

        Config::set('booking.sapo_realtime.cookie_dh', 'raw_jwt_token_without_equal_sign');
        $this->assertSame('token=raw_jwt_token_without_equal_sign', $service->layCookie('drinking-healing'));

        Http::fake([
            'https://fnb.mysapo.vn/admin/orders.json*' => function ($request) {
                $this->assertSame('token=raw_jwt_token_without_equal_sign', $request->header('Cookie')[0] ?? '');

                return Http::response(['orders' => []], 200);
            },
        ]);

        $res = $service->kiemTraKetNoi('raw_jwt_token_without_equal_sign');
        $this->assertTrue($res['thanhCong']);
    }
}

