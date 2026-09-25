<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Services\BookingService;
use App\Services\ZaloNhomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZaloNhomTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $drinkingHealing;

    protected Branch $gemination;

    protected Branch $geminationBaNa;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'booking.zalo_group.bat' => true,
            'booking.zalo_group.engine_url' => 'https://zalo.thegats.vn',
            'booking.zalo_group.nhom' => [
                'drinking-healing' => "DH's Booking",
                'gemination' => 'Gemination Đà Lạt - Booking',
                'gemination-ba-na' => "Gemination Bà Nà's Booking",
            ],
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
        $this->geminationBaNa = $gm->branches()->create([
            'name' => 'Gemination Bà Nà', 'slug' => 'gemination-ba-na', 'open_time' => '10:00', 'close_time' => '22:00', 'is_active' => true, 'max_party_size' => 30, 'max_advance_days' => 30, 'slot_minutes' => 30,
        ]);

        $this->gemination->diningTables()->create(['code' => 'T1', 'seats_min' => 2, 'seats_max' => 4, 'is_active' => true]);
        $this->drinkingHealing->diningTables()->create(['code' => 'DH1', 'seats_min' => 2, 'seats_max' => 4, 'is_active' => true]);
        $this->geminationBaNa->diningTables()->create(['code' => 'BN1', 'seats_min' => 2, 'seats_max' => 6, 'is_active' => true]);
    }

    public function test_gui_thong_bao_don_moi_vao_dung_nhom_zalo_cua_tung_quan(): void
    {
        Http::fake([
            'https://zalo.thegats.vn/api/v1/zalo/send-message' => Http::response(['success' => true, 'message' => 'ok'], 200),
        ]);

        $ngayMai = now()->addDay()->format('Y-m-d');

        // 1. Quán Drinking Healing -> Nhóm DH's Booking
        app(BookingService::class)->create($this->drinkingHealing, [
            'customer_name' => 'Anh Hải DH',
            'customer_phone' => '0901234567',
            'party_size' => 4,
            'booking_date' => $ngayMai,
            'start_time' => '19:00',
            'note' => 'Bàn view đẹp',
        ]);

        Http::assertSent(function (Request $req) {
            return $req->url() === 'https://zalo.thegats.vn/api/v1/zalo/send-message'
                && $req['group_name'] === "DH's Booking"
                && str_contains($req['message'], 'Anh Hải DH')
                && str_contains($req['message'], 'Drinking Healing')
                && str_contains($req['message'], 'Bàn view đẹp');
        });

        // 2. Quán Gemination Đà Lạt -> Nhóm Gemination Đà Lạt - Booking
        app(BookingService::class)->create($this->gemination, [
            'customer_name' => 'Chị Lan Gemi',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => $ngayMai,
            'start_time' => '20:00',
        ]);

        Http::assertSent(function (Request $req) {
            return $req->url() === 'https://zalo.thegats.vn/api/v1/zalo/send-message'
                && $req['group_name'] === 'Gemination Đà Lạt - Booking'
                && str_contains($req['message'], 'Chị Lan Gemi')
                && str_contains($req['message'], 'Gemination Đà Lạt');
        });

        // 3. Quán Gemination Bà Nà -> Nhóm Gemination Bà Nà's Booking
        app(BookingService::class)->create($this->geminationBaNa, [
            'customer_name' => 'Đoàn khách Bà Nà',
            'customer_phone' => '0988776655',
            'party_size' => 5,
            'booking_date' => $ngayMai,
            'start_time' => '11:30',
        ]);

        Http::assertSent(function (Request $req) {
            return $req->url() === 'https://zalo.thegats.vn/api/v1/zalo/send-message'
                && $req['group_name'] === "Gemination Bà Nà's Booking"
                && str_contains($req['message'], 'Đoàn khách Bà Nà');
        });
    }

    public function test_gui_thong_bao_khi_khach_tu_huy_don(): void
    {
        Http::fake([
            'https://zalo.thegats.vn/api/v1/zalo/send-message' => Http::response(['success' => true], 200),
        ]);

        $booking = app(BookingService::class)->create($this->drinkingHealing, [
            'customer_name' => 'Khách Bận',
            'customer_phone' => '0909999999',
            'party_size' => 2,
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '20:00',
        ]);

        // Đánh dấu đơn có mã Sapo để kiểm tra cảnh báo
        $booking->forceFill(['sapo_code' => 'SP123456'])->saveQuietly();

        // Khách tự hủy
        app(BookingService::class)->cancel($booking, 'Bận đột xuất', 'customer');

        Http::assertSent(function (Request $req) {
            return $req['group_name'] === "DH's Booking"
                && str_contains($req['message'], 'KHÁCH HUỶ ĐẶT BÀN')
                && str_contains($req['message'], 'Khách Bận')
                && str_contains($req['message'], 'Bận đột xuất')
                && str_contains($req['message'], 'SP123456');
        });
    }

    public function test_loi_zalo_engine_khong_lam_hong_luong_dat_ban(): void
    {
        // Giả lập máy chủ Zalo Engine lỗi 500
        Http::fake([
            'https://zalo.thegats.vn/api/v1/zalo/send-message' => Http::response('Internal Server Error', 500),
        ]);

        $booking = app(BookingService::class)->create($this->gemination, [
            'customer_name' => 'Khách Vẫn Đặt Thành Công',
            'customer_phone' => '0933445566',
            'party_size' => 2,
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:30',
        ]);

        // Đơn vẫn phải được tạo và lưu CSDL bình thường
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'customer_name' => 'Khách Vẫn Đặt Thành Công',
        ]);
    }

    public function test_khong_gui_khi_tinh_nang_bi_tat(): void
    {
        config(['booking.zalo_group.bat' => false]);
        Http::fake();

        app(BookingService::class)->create($this->drinkingHealing, [
            'customer_name' => 'Khách Tắt Zalo',
            'customer_phone' => '0977889900',
            'party_size' => 2,
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
        ]);

        Http::assertNothingSent();
    }
}
