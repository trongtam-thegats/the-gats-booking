<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ThongBaoDayService;
use App\Support\Roles;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Thong bao day bao cho nhan vien khi co don moi, khach huy, hoac day sang
 * Sapo that bai.
 *
 * Phan ma hoa co test rieng (WebPushTest); o day chi lo dung nguoi, dung luc.
 */
class ThongBaoDayTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $gemination;

    protected Branch $drinkingHealing;

    protected function setUp(): void
    {
        parent::setUp();

        $khoa = WebPush::khoaMoi();
        config([
            'booking.push.public_key' => $khoa['public'],
            'booking.push.private_key' => $khoa['private'],
            'booking.push.subject' => 'mailto:datban@thegats.vn',
            'booking.sapo_dat_lich.bat' => false,
        ]);

        $gm = Brand::create([
            'name' => 'Gemination', 'slug' => 'gm', 'domain' => 'booking.gm.test',
            'mark' => 'GM', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);
        $dh = Brand::create([
            'name' => 'Drinking Healing', 'slug' => 'dh', 'domain' => 'booking.dh.test',
            'mark' => 'DH', 'accent_color' => '#a0703a', 'is_active' => true,
        ]);

        $this->gemination = $gm->branches()->create([
            'name' => 'Gemination Đà Lạt', 'slug' => 'gemination', 'open_time' => '18:00', 'close_time' => '02:00', 'is_active' => true, 'max_party_size' => 20, 'max_advance_days' => 30, 'slot_minutes' => 30,
        ]);
        $this->drinkingHealing = $dh->branches()->create([
            'name' => 'Drinking Healing', 'slug' => 'drinking-healing', 'open_time' => '17:00', 'close_time' => '02:00', 'is_active' => true, 'max_party_size' => 20, 'max_advance_days' => 30, 'slot_minutes' => 30,
        ]);

        $this->gemination->diningTables()->create(['code' => 'H8', 'seats_min' => 2, 'seats_max' => 6, 'is_active' => true]);
    }

    protected function nguoiDung(string $vai, ?Branch $branch = null, string $email = 'ai@thegats.vn'): User
    {
        return User::create([
            'name' => 'Người '.$vai, 'email' => $email, 'password' => 'matkhau123',
            'role' => $vai, 'is_active' => true,
            'brand_id' => $branch?->brand_id, 'branch_id' => $branch?->id,
        ]);
    }

    protected function thietBi(User $user, string $endpoint): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'p256dh' => $this->p256dh(),
            'auth' => WebPush::b64(random_bytes(16)),
        ]);
    }

    protected function p256dh(): string
    {
        $caiDat = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];

        if (filled($cnf = config('booking.push.openssl_cnf'))) {
            $caiDat['config'] = $cnf;
        }

        $ec = openssl_pkey_get_details(openssl_pkey_new($caiDat))['ec'];

        return WebPush::b64("\x04".str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT).str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT));
    }

    protected function don(?Branch $branch = null): Booking
    {
        return Booking::create([
            'branch_id' => ($branch ?? $this->gemination)->id,
            'code' => 'DB'.fake()->unique()->numberBetween(1000, 9999),
            'customer_name' => 'Chị Na',
            'customer_phone' => '0865683649',
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '19:00', 'end_time' => '21:00',
            'party_size' => 4,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_don_moi_bao_toi_dung_nguoi_cua_quan_do(): void
    {
        Http::fake(['*' => Http::response('', 201)]);

        $quanTri = $this->nguoiDung(Roles::ADMIN, null, 'admin@thegats.vn');
        $cuaGemination = $this->nguoiDung(Roles::MANAGER, $this->gemination, 'gm@thegats.vn');
        $cuaDH = $this->nguoiDung(Roles::MANAGER, $this->drinkingHealing, 'dh@thegats.vn');

        $this->thietBi($quanTri, 'https://web.push.apple.com/admin');
        $this->thietBi($cuaGemination, 'https://web.push.apple.com/gm');
        $this->thietBi($cuaDH, 'https://web.push.apple.com/dh');

        app(ThongBaoDayService::class)->donMoi($this->don());

        // Quan tri va nguoi cua Gemination nhan duoc; nguoi cua quan kia thi khong.
        Http::assertSent(fn ($r) => $r->url() === 'https://web.push.apple.com/admin');
        Http::assertSent(fn ($r) => $r->url() === 'https://web.push.apple.com/gm');
        Http::assertNotSent(fn ($r) => $r->url() === 'https://web.push.apple.com/dh');
    }

    public function test_dia_chi_chet_thi_xoa_khoi_co_so_du_lieu(): void
    {
        Http::fake(['*' => Http::response('', 410)]);

        $nguoi = $this->nguoiDung(Roles::ADMIN, null, 'admin@thegats.vn');
        $this->thietBi($nguoi, 'https://web.push.apple.com/cu');

        app(ThongBaoDayService::class)->donMoi($this->don());

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_dich_vu_tu_choi_tam_thoi_thi_giu_lai_dia_chi(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $nguoi = $this->nguoiDung(Roles::ADMIN, null, 'admin@thegats.vn');
        $this->thietBi($nguoi, 'https://web.push.apple.com/tam');

        app(ThongBaoDayService::class)->donMoi($this->don());

        $this->assertSame(1, PushSubscription::count());
        $this->assertNull(PushSubscription::first()->lan_cuoi_ok);
    }

    public function test_khach_dat_ban_tren_web_thi_nhan_vien_duoc_bao(): void
    {
        Http::fake(['*' => Http::response('', 201)]);

        $nguoi = $this->nguoiDung(Roles::ADMIN, null, 'admin@thegats.vn');
        $this->thietBi($nguoi, 'https://web.push.apple.com/admin');

        app(BookingService::class)->create($this->gemination, [
            'customer_name' => 'Khách web',
            'customer_phone' => '0900000001',
            'party_size' => 2,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '19:00',
        ]);

        Http::assertSent(function ($r) {

            return $r->url() === 'https://web.push.apple.com/admin'
                && $r->header('Content-Encoding')[0] === 'aes128gcm'
                && str_starts_with($r->header('Authorization')[0], 'vapid t=');
        });
    }

    public function test_khach_tu_huy_thi_bao_nhung_nhan_vien_huy_thi_thoi(): void
    {
        Http::fake(['*' => Http::response('', 201)]);

        $nguoi = $this->nguoiDung(Roles::ADMIN, null, 'admin@thegats.vn');
        $this->thietBi($nguoi, 'https://web.push.apple.com/admin');

        app(BookingService::class)->cancel($this->don(), 'Bận rồi', 'staff');
        Http::assertNothingSent();

        app(BookingService::class)->cancel($this->don(), 'Bận rồi', 'customer');
        Http::assertSentCount(1);
    }

    public function test_chua_khai_khoa_thi_khong_goi_di_dau(): void
    {
        config(['booking.push.public_key' => '', 'booking.push.private_key' => '']);
        Http::fake();

        $nguoi = $this->nguoiDung(Roles::ADMIN, null, 'admin@thegats.vn');
        $this->thietBi($nguoi, 'https://web.push.apple.com/admin');

        app(ThongBaoDayService::class)->donMoi($this->don());

        Http::assertNothingSent();
    }

    public function test_nhan_vien_dang_ky_va_huy_thiet_bi_cua_minh(): void
    {
        $nguoi = $this->nguoiDung(Roles::MANAGER, $this->gemination, 'gm@thegats.vn');

        $this->actingAs($nguoi)->postJson(route('admin.thong-bao.dang-ky'), [
            'endpoint' => 'https://web.push.apple.com/abc',
            'p256dh' => $this->p256dh(),
            'auth' => WebPush::b64(random_bytes(16)),
        ])->assertOk();

        $this->assertSame(1, $nguoi->pushSubscriptions()->count());

        $this->actingAs($nguoi)->postJson(route('admin.thong-bao.huy'), [
            'endpoint' => 'https://web.push.apple.com/abc',
        ])->assertOk();

        $this->assertSame(0, $nguoi->pushSubscriptions()->count());
    }

    public function test_khach_vang_lai_khong_dang_ky_duoc(): void
    {
        $this->postJson(route('admin.thong-bao.dang-ky'), [
            'endpoint' => 'https://web.push.apple.com/abc',
            'p256dh' => $this->p256dh(),
            'auth' => WebPush::b64(random_bytes(16)),
        ])->assertStatus(401);

        $this->assertSame(0, PushSubscription::count());
    }
}
