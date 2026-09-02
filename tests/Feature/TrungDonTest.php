<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Chan don trung tren trang khach.
 *
 * Boi canh: 1/9/2026 quan bi mot loat don trung vi khach bam nut gui hai ba
 * lan trong luc mang cham. Moi lan bam tao ra mot don that va an mot ban that.
 * Ba lop da duoc dung len - khoa nut, tran tan suat, va chan o BookingService.
 * Lop thu ba la lop khong lach duoc, nen phan lon test o day nham vao no.
 */
class TrungDonTest extends TestCase
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

        foreach (['A01', 'A02', 'A03', 'A04', 'A05'] as $code) {
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

    public function test_bam_ba_lan_chi_ra_mot_don_va_giu_mot_ban(): void
    {
        $this->book();
        $this->book();
        $this->book();

        $this->assertSame(1, Booking::count(), 'Ba lan bam chi duoc tao mot don.');
        $this->assertCount(1, Booking::first()->diningTables, 'Chi mot ban bi giu.');
    }

    public function test_ca_ba_lan_bam_deu_dan_khach_ve_dung_mot_trang_xac_nhan(): void
    {
        $lan1 = $this->book();
        $lan2 = $this->book();

        $booking = Booking::firstOrFail();

        // Khach khong duoc thay bao loi - voi ho thi lan bam nao cung "thanh cong".
        $lan1->assertRedirect(route('booking.show', $booking));
        $lan2->assertRedirect(route('booking.show', $booking));
        $lan2->assertSessionHasNoErrors();
    }

    public function test_chi_gui_mot_thong_bao_xac_nhan_du_bam_nhieu_lan(): void
    {
        $this->book(['customer_email' => 'a@example.com']);
        $this->book(['customer_email' => 'a@example.com']);
        $this->book(['customer_email' => 'a@example.com']);

        // Khach chi duoc lam phien mot lan.
        $this->assertSame(1, Booking::firstOrFail()->notificationLogs()->where('event', 'created')->count());
    }

    public function test_cung_so_dat_nhieu_khung_gio_khac_nhau_van_duoc(): void
    {
        // Mot nguoi dat ban hiep mot roi dat them hiep hai la chuyen co that.
        // Chan don trung tuyet doi khong duoc nuot nhung don nay.
        $this->book(['start_time' => '17:00']);
        $this->book(['start_time' => '19:00']);
        $this->book(['start_time' => '21:00']);

        $this->assertSame(3, Booking::count());
    }

    public function test_don_da_huy_khong_chan_lan_dat_lai(): void
    {
        $this->book();
        $booking = Booking::firstOrFail();
        app(BookingService::class)->cancel($booking, 'Khách đổi ý', 'customer');

        $this->book();

        $this->assertSame(2, Booking::count(), 'Huy roi dat lai cung gio thi phai duoc.');
        $this->assertSame(1, Booking::where('status', Booking::STATUS_PENDING)->count());
    }

    public function test_so_dien_thoai_viet_khac_dinh_dang_van_nhan_ra_la_mot_nguoi(): void
    {
        $this->book(['customer_phone' => '0912345678']);
        $this->book(['customer_phone' => '+84 912 345 678']);

        $this->assertSame(1, Booking::count(), 'Cung mot so, chi khac cach go.');
    }

    public function test_khach_khac_dat_cung_khung_gio_van_binh_thuong(): void
    {
        $this->book(['customer_phone' => '0912345678']);
        $this->book(['customer_phone' => '0987654321', 'customer_name' => 'Trần Thị B']);

        $this->assertSame(2, Booking::count(), 'Chan don trung khong duoc chan nham khach khac.');
    }

    public function test_nhan_vien_van_tao_duoc_hai_don_cung_so_cung_gio(): void
    {
        // Le tan co ly do that: tach mot doan lon ra hai ban rieng.
        $nhanVien = User::create([
            'name' => 'Lễ tân',
            'email' => 'letan@quanthu.test',
            'password' => bcrypt('x'),
            'role' => Roles::MANAGER,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $chung = [
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0912345678',
            'party_size' => 4,
            'booking_date' => $this->tomorrow(),
            'start_time' => '19:00',
        ];

        app(BookingService::class)->create($this->branch, $chung, $nhanVien);
        app(BookingService::class)->create($this->branch, $chung, $nhanVien);

        $this->assertSame(2, Booking::count(), 'Chan don trung chi ap cho khach tu dat.');
    }

    public function test_gui_lien_tuc_qua_nhieu_thi_bi_chan_tan_suat(): void
    {
        // Moi lan mot so khac nhau de vuot qua lop chan don trung, con lai
        // dung mot IP - dung tinh huong cua mot con bot hoac mot nguoi pha.
        for ($i = 0; $i < 10; $i++) {
            $this->book(['customer_phone' => '09000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        // Tran dem theo IP + so dien thoai, nen phai lap lai mot so da dung.
        for ($i = 0; $i < 10; $i++) {
            $this->book(['customer_phone' => '0900000001', 'start_time' => '20:00']);
        }

        $this->book(['customer_phone' => '0900000001', 'start_time' => '20:30'])
            ->assertSessionHasErrors('start_time');
    }
}
