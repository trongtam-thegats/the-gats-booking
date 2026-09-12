<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cron nhac lich chay 5 phut mot lan trong suot khung nhac 3 tieng, nen moi
 * dat ban bi quet lai hang chuc lan. Bo test nay giu cho no chi ghi nhat ky
 * dung mot lan cho moi kenh.
 */
class NhacLichKhongLapTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'Quán Nhắc Lịch',
            'slug' => 'quan-nhac-lich',
            'domain' => 'booking.quannhaclich.test',
            'mark' => 'QN',
            'accent_color' => '#c8a15a',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Quán Nhắc Lịch Đà Lạt',
            'slug' => 'quan-nhac-lich-da-lat',
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

        config(['booking.channels' => ['email']]);

        // 18:00, don dat luc 20:00 => nam gon trong khung nhac 180 phut.
        Carbon::setTestNow(Carbon::parse('2026-09-12 18:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function datBan(array $them = []): Booking
    {
        return Booking::create(array_merge([
            'code' => 'TGNHAC01',
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách Nhắc Lịch',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-09-12',
            'start_time' => '20:00:00',
            'end_time' => '22:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ], $them));
    }

    protected function ghiLog(Booking $booking, string $status, int $soDong = 1): void
    {
        for ($i = 0; $i < $soDong; $i++) {
            NotificationLog::create([
                'booking_id' => $booking->id,
                'channel' => 'email',
                'event' => 'reminder',
                'recipient' => $booking->customer_email,
                'status' => $status,
                'message' => 'Tin nhắc',
            ]);
        }
    }

    protected function demLog(Booking $booking): int
    {
        return NotificationLog::where('booking_id', $booking->id)
            ->where('event', 'reminder')
            ->count();
    }

    /** Day chinh la con bug: khach khong de email, cron chay lai de ra hang chuc dong rac. */
    public function test_khach_khong_co_email_chi_ghi_mot_dong_bo_qua(): void
    {
        $booking = $this->datBan(['customer_email' => null]);

        foreach (range(1, 5) as $ignored) {
            $this->artisan('booking:remind')->assertSuccessful();
        }

        $this->assertSame(1, $this->demLog($booking));
        $this->assertSame('skipped', NotificationLog::where('booking_id', $booking->id)->first()->status);
    }

    public function test_da_gui_duoc_roi_thi_khong_nhac_lai(): void
    {
        $booking = $this->datBan(['customer_email' => 'khach@example.test']);

        $this->artisan('booking:remind')->assertSuccessful();
        $this->assertSame(1, $this->demLog($booking));

        $this->artisan('booking:remind')->assertSuccessful();
        $this->assertSame(1, $this->demLog($booking));
    }

    public function test_loi_thi_van_thu_lai_nhung_toi_da_ba_lan(): void
    {
        $booking = $this->datBan(['customer_email' => 'khach@example.test']);

        // Hai lan loi truoc do: van con quota, phai thu tiep.
        $this->ghiLog($booking, 'failed', 2);
        $this->artisan('booking:remind')->assertSuccessful();
        $this->assertSame(3, $this->demLog($booking));

        // Don khac da loi du ba lan: thoi, dung thu nua.
        $khac = $this->datBan(['code' => 'TGNHAC02', 'customer_email' => 'khach2@example.test']);
        $this->ghiLog($khac, 'failed', 3);
        $this->artisan('booking:remind')->assertSuccessful();
        $this->assertSame(3, $this->demLog($khac));
    }

    public function test_kenh_bi_bo_qua_khong_chan_kenh_con_lai(): void
    {
        config(['booking.channels' => ['email', 'sms']]);

        $booking = $this->datBan(['customer_email' => null]);

        // Lan chay dau: ca hai kenh deu ghi mot dong.
        $this->artisan('booking:remind')->assertSuccessful();
        $kenh = NotificationLog::where('booking_id', $booking->id)->pluck('channel')->sort()->values()->all();

        $this->assertSame(['email', 'sms'], $kenh);

        // Lan chay sau khong de them dong nao.
        $this->artisan('booking:remind')->assertSuccessful();
        $this->assertSame(2, $this->demLog($booking));
    }

    public function test_lenh_don_nhat_ky_giu_lai_dong_dau_tien_moi_loai(): void
    {
        $booking = $this->datBan(['customer_email' => null]);

        $this->ghiLog($booking, 'skipped', 12);
        $this->ghiLog($booking, 'failed', 3);

        $giuLaiBoQua = NotificationLog::where('booking_id', $booking->id)
            ->where('status', 'skipped')->orderBy('id')->first()->id;

        $this->artisan('booking:don-nhat-ky')->assertSuccessful();
        $this->assertSame(15, $this->demLog($booking), 'Khong co --ghi thi khong duoc xoa gi');

        $this->artisan('booking:don-nhat-ky', ['--ghi' => true])->assertSuccessful();

        $conLai = NotificationLog::where('booking_id', $booking->id)->orderBy('id')->get();

        $this->assertSame(2, $conLai->count());
        $this->assertSame(['skipped', 'failed'], $conLai->pluck('status')->all());
        $this->assertSame($giuLaiBoQua, $conLai->first()->id, 'Phai giu dong bo qua dau tien');
    }

    public function test_lenh_don_nhat_ky_khong_dung_toi_su_kien_khac(): void
    {
        $booking = $this->datBan(['customer_email' => null]);

        // Hai lan sua don that su => hai dong, khong phai rac.
        foreach (range(1, 2) as $ignored) {
            NotificationLog::create([
                'booking_id' => $booking->id,
                'channel' => 'email',
                'event' => 'updated',
                'status' => 'skipped',
                'message' => 'Tin sửa đơn',
            ]);
        }

        $this->artisan('booking:don-nhat-ky', ['--ghi' => true])->assertSuccessful();

        $this->assertSame(2, NotificationLog::where('booking_id', $booking->id)->where('event', 'updated')->count());
    }
}
