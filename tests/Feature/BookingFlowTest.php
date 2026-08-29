<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Luong dat ban phia khach.
 *
 * Test chay tren host "localhost" nen SiteResolver coi day la moi truong thu
 * va dung quan mac dinh - giong het khi ten mien that tro toi quan do.
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

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
            'slug' => 'quan-thu',
            'phone' => '0900000000',
            'open_time' => '17:00',
            'close_time' => '23:30',
            'slot_minutes' => 30,
            'turn_minutes' => 120,
            'min_lead_minutes' => 60,
            'max_advance_days' => 30,
            'max_party_size' => 20,
            'is_active' => true,
        ]);

        $area = $this->branch->areas()->create(['name' => 'Tầng 1', 'bookable' => true]);

        // Hai ban 4 cho - suc chua nho de de kiem tra truong hop het ban.
        foreach (['A01', 'A02'] as $code) {
            $this->branch->diningTables()->create([
                'area_id' => $area->id,
                'code' => $code,
                'table_type' => 'high_table',
                'seats_min' => 1,
                'seats_max' => 4,
                'combinable' => true,
            ]);
        }
    }

    protected function tomorrow(): string
    {
        return Carbon::tomorrow()->toDateString();
    }

    /** @param array<string, mixed> $overrides */
    protected function book(array $overrides = [])
    {
        return $this->post(route('booking.store', $this->branch), array_merge([
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => $this->tomorrow(),
            'start_time' => '19:00',
        ], $overrides));
    }

    /** @return array<int, array{time: string, available: bool}> */
    protected function slots(int $partySize = 2, ?string $date = null): array
    {
        return $this->getJson(route('booking.slots', $this->branch).'?'.http_build_query([
            'date' => $date ?? $this->tomorrow(),
            'party_size' => $partySize,
        ]))->json('slots');
    }

    public function test_trang_chu_hien_thang_form_dat_ban_cua_quan(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Quán Thử')
            ->assertSee('Đặt bàn');
    }

    public function test_ten_mien_chua_gan_quan_nao_thi_khong_vao_duoc(): void
    {
        // Host la mot ten mien that nhung chua khai bao trong bang quan.
        $this->get('http://booking.khongtontai.test/')->assertNotFound();
    }

    public function test_ten_mien_cua_quan_mo_dung_quan_do(): void
    {
        $this->get('http://booking.quanthu.test/')
            ->assertOk()
            ->assertSee('Quán Thử');
    }

    public function test_khung_gio_sinh_theo_cau_hinh_cua_quan(): void
    {
        $slots = $this->slots();

        // 17:00 den 21:30, buoc 30 phut => 10 moc.
        $this->assertCount(10, $slots);
        $this->assertSame('17:00', $slots[0]['time']);
        $this->assertSame('21:30', $slots[9]['time']);
        $this->assertTrue($slots[0]['available']);
    }

    public function test_khach_dat_ban_thanh_cong_va_duoc_giu_ban(): void
    {
        $response = $this->book(['party_size' => 3, 'customer_email' => 'a@example.com', 'note' => 'Sinh nhật']);

        $booking = Booking::firstOrFail();
        $response->assertRedirect(route('booking.show', $booking));

        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame('19:00', substr($booking->start_time, 0, 5));
        $this->assertSame('21:00', substr($booking->end_time, 0, 5));
        $this->assertCount(1, $booking->diningTables);

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'event' => 'created',
        ]);
    }

    public function test_chon_ban_vua_khit_thay_vi_ban_lon_hon(): void
    {
        $this->branch->diningTables()->create([
            'code' => 'BIG', 'table_type' => 'sofa',
            'seats_min' => 1, 'seats_max' => 12, 'combinable' => true,
        ]);

        $this->book(['party_size' => 2]);

        $this->assertSame(4, Booking::firstOrFail()->diningTables->first()->seats_max);
    }

    public function test_ghep_ban_khi_doan_dong_hon_mot_ban(): void
    {
        $this->book(['party_size' => 7]);

        $this->assertCount(2, Booking::firstOrFail()->diningTables);
    }

    public function test_khung_gio_bi_khoa_khi_het_ban(): void
    {
        foreach (['0911111111', '0922222222'] as $phone) {
            $this->book(['customer_phone' => $phone, 'party_size' => 4]);
        }

        $this->assertSame(2, Booking::count());

        $byTime = collect($this->slots())->keyBy('time');

        $this->assertFalse($byTime['19:00']['available']);
        $this->assertFalse($byTime['20:30']['available']);
        // 21:00 la luc ban dau tien nha ra.
        $this->assertTrue($byTime['21:00']['available']);
        $this->assertTrue($byTime['17:00']['available']);
    }

    public function test_khong_dat_duoc_khi_da_het_ban(): void
    {
        foreach (['0911111111', '0922222222'] as $phone) {
            $this->book(['customer_phone' => $phone, 'party_size' => 4]);
        }

        $this->book(['customer_phone' => '0933333333', 'party_size' => 4])
            ->assertSessionHasErrors('start_time');

        $this->assertSame(2, Booking::count());
    }

    public function test_khong_dat_duoc_qua_sat_gio(): void
    {
        // 18:30 => moc 19:00 chi con 30 phut, duoi muc 60 phut toi thieu.
        Carbon::setTestNow(Carbon::today()->setTime(18, 30));

        $this->book(['booking_date' => Carbon::today()->toDateString()])
            ->assertSessionHasErrors('start_time');

        $this->assertSame(0, Booking::count());

        Carbon::setTestNow();
    }

    public function test_doan_qua_dong_duoc_moi_goi_dien(): void
    {
        $this->book(['party_size' => 40])->assertSessionHasErrors('start_time');
    }

    public function test_quan_nghi_thi_khong_con_khung_gio_nao(): void
    {
        $this->branch->closures()->create([
            'date' => $this->tomorrow(),
            'reason' => 'Sự kiện riêng',
        ]);

        $this->assertTrue(collect($this->slots())->every(fn ($slot) => $slot['available'] === false));
    }

    public function test_khach_tu_huy_bang_ma_va_so_dien_thoai(): void
    {
        $this->book();
        $booking = Booking::firstOrFail();

        $this->post(route('booking.cancel', $booking), ['customer_phone' => '0900000000'])
            ->assertSessionHasErrors('customer_phone');

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);

        $this->post(route('booking.cancel', $booking), ['customer_phone' => '0912345678'])
            ->assertRedirect(route('booking.show', $booking));

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertCount(0, $booking->diningTables);
    }

    public function test_ban_duoc_nha_ra_sau_khi_huy(): void
    {
        foreach (['0911111111', '0922222222'] as $phone) {
            $this->book(['customer_phone' => $phone, 'party_size' => 4]);
        }

        $first = Booking::orderBy('id')->first();
        $this->post(route('booking.cancel', $first), ['customer_phone' => $first->customer_phone]);

        $this->assertTrue(collect($this->slots(4))->keyBy('time')['19:00']['available']);
    }

    public function test_tra_cuu_can_dung_ca_ma_va_so_dien_thoai(): void
    {
        $this->book();
        $booking = Booking::firstOrFail();

        $this->get(route('booking.lookup', ['code' => $booking->code, 'phone' => '0912345678']))
            ->assertOk()
            ->assertSee($booking->code);

        $this->get(route('booking.lookup', ['code' => $booking->code, 'phone' => '0900000000']))
            ->assertOk()
            ->assertSee('Không tìm thấy');
    }

    public function test_khong_tra_cuu_duoc_dat_ban_cua_quan_khac(): void
    {
        $this->book();
        $booking = Booking::firstOrFail();

        $other = Brand::create([
            'name' => 'Quán Khác', 'slug' => 'quan-khac',
            'domain' => 'booking.quankhac.test', 'mark' => 'QK',
            'accent_color' => '#7fb59b', 'is_active' => true,
        ]);
        $other->branches()->create([
            'name' => 'Quán Khác', 'slug' => 'quan-khac-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);

        // Mo ma dat ban cua Quan Thu tren ten mien cua Quan Khac.
        $this->get('http://booking.quankhac.test/ma/'.$booking->code)->assertNotFound();

        $this->get('http://booking.quankhac.test/tra-cuu?'.http_build_query([
            'code' => $booking->code,
            'phone' => '0912345678',
        ]))->assertOk()->assertSee('Không tìm thấy');
    }

    public function test_quan_tam_ngung_thi_bao_chua_mo_dat_ban(): void
    {
        $this->branch->update(['is_active' => false]);

        $this->get('/')->assertOk()->assertSee('Chưa mở đặt bàn trực tuyến');
    }

    public function test_auto_confirm_bo_qua_buoc_duyet_tay(): void
    {
        $this->branch->update(['auto_confirm' => true]);

        $this->book();

        $this->assertSame(Booking::STATUS_CONFIRMED, Booking::firstOrFail()->status);
    }

    public function test_quan_dong_cua_sau_nua_dem_van_sinh_du_khung_gio(): void
    {
        $this->branch->update(['open_time' => '18:00', 'close_time' => '02:00', 'turn_minutes' => 120]);

        $times = collect($this->slots())->pluck('time');

        // 18:00 -> 00:00 (dong 02:00, giu ban 2 tieng) => moc cuoi la 00:00.
        $this->assertSame('18:00', $times->first());
        $this->assertSame('00:00', $times->last());
        $this->assertTrue($times->contains('23:30'));
    }

    /**
     * API khung gio phai tu choi dia diem cua quan khac, ke ca khi doan dung
     * duong dan - neu khong, tu ten mien quan nay doc duoc lich cua quan kia.
     */
    public function test_api_khung_gio_tu_choi_dia_diem_cua_quan_khac(): void
    {
        $other = Brand::create([
            'name' => 'Quán Khác', 'slug' => 'quan-khac',
            'domain' => 'booking.quankhac.test', 'mark' => 'QK',
            'accent_color' => '#7fb59b', 'is_active' => true,
        ]);

        $otherBranch = $other->branches()->create([
            'name' => 'Quán Khác', 'slug' => 'quan-khac-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);

        $this->getJson('http://booking.quanthu.test'.route('booking.slots', $otherBranch, false).'?'
            .http_build_query(['date' => $this->tomorrow(), 'party_size' => 2]))
            ->assertNotFound();
    }

    /**
     * Gio chot nhan dat ban tach roi khoi gio dong cua: quan mo den 02:00
     * nhung chi nhan khach dat den 01:00.
     */
    public function test_gio_chot_nhan_dat_ban_quyet_dinh_moc_cuoi(): void
    {
        $this->branch->update([
            'open_time' => '17:00',
            'close_time' => '02:00',
            'last_booking_time' => '01:00',
        ]);

        $times = collect($this->slots())->pluck('time');

        $this->assertSame('17:00', $times->first());
        $this->assertSame('01:00', $times->last());
        // Khong co gio chot thi cong thuc cu se dung o 00:00.
        $this->assertTrue($times->contains('00:30'));
    }

    public function test_dat_ban_sau_gio_chot_bi_tu_choi(): void
    {
        $this->branch->update([
            'open_time' => '17:00',
            'close_time' => '02:00',
            'last_booking_time' => '01:00',
        ]);

        $this->book(['start_time' => '01:30'])->assertSessionHasErrors('start_time');

        $this->assertSame(0, Booking::count());
    }

    public function test_luot_dat_cuoi_ket_thuc_dung_gio_dong_cua(): void
    {
        $this->branch->update([
            'open_time' => '17:00',
            'close_time' => '02:00',
            'last_booking_time' => '01:00',
            'turn_minutes' => 120,
        ]);

        $this->book(['start_time' => '01:00']);

        $booking = Booking::firstOrFail();

        // 01:00 + 2 tieng la 03:00, nhung phai bi cat ve gio dong cua.
        $this->assertSame('02:00', substr($booking->end_time, 0, 5));
    }

    public function test_khong_khai_bao_gio_chot_thi_tinh_theo_gio_dong_cua(): void
    {
        $this->branch->update([
            'open_time' => '17:00',
            'close_time' => '02:00',
            'last_booking_time' => null,
            'turn_minutes' => 120,
        ]);

        $this->assertSame('00:00', collect($this->slots())->pluck('time')->last());
    }

    public function test_khu_quan_ly_khong_mo_tren_ten_mien_cua_quan(): void
    {
        $this->get('http://booking.quanthu.test/quan-ly')
            ->assertRedirect();
    }
}
