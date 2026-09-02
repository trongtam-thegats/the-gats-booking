<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\BookingService;
use App\Support\NguonDatBan;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cac loi tim ra trong dot ra soat 2026-09-02, va rang buoc de chung khong
 * quay lai.
 *
 * Ba loi da sua:
 *  1. Xac nhan lai don da huy thi mat ban vinh vien, va khong kiem tra con cho.
 *  2. Danh dau nham "khach khong den" la khong go lai duoc.
 *  3. Form dat ban ho khach khong khoa nut, le tan bam hai lan ra hai don.
 */
class RaSoatTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected User $admin;

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
            'slug' => 'quan-thu-cn',
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

        foreach (['T1', 'T2', 'T3'] as $code) {
            $this->branch->diningTables()->create([
                'code' => $code,
                'table_type' => 'high_table',
                'seats_min' => 1,
                'seats_max' => 4,
                'combinable' => true,
            ]);
        }

        $this->admin = User::create([
            'name' => 'Giám đốc',
            'email' => 'admin@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::ADMIN,
            'is_active' => true,
        ]);
    }

    protected function makeBooking(array $overrides = []): Booking
    {
        return app(BookingService::class)->create($this->branch, array_merge([
            'customer_name' => 'Khách thử',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ], $overrides));
    }

    /** Lam kin ca ba ban o khung gio 19:00 bang khach khac. */
    protected function datKinKhungGio(): void
    {
        foreach (['0922222222', '0933333333', '0944444444'] as $sdt) {
            $this->makeBooking(['customer_phone' => $sdt, 'party_size' => 4]);
        }
    }

    // ---------- 1. Xac nhan lai don da huy ----------

    public function test_xac_nhan_lai_don_da_huy_thi_giu_lai_ban(): void
    {
        $booking = $this->makeBooking();
        app(BookingService::class)->cancel($booking, 'Khách báo hủy', 'staff');

        $this->assertCount(0, $booking->fresh()->diningTables, 'Huy thi nha ban - dung y do.');

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $sau = $booking->fresh(['diningTables']);

        $this->assertSame(Booking::STATUS_CONFIRMED, $sau->status);
        $this->assertCount(1, $sau->diningTables, 'Don quay lai phai cam duoc ban.');
    }

    public function test_khung_gio_da_kin_thi_bao_loi_thay_vi_xac_nhan_suong(): void
    {
        $don = $this->makeBooking(['customer_phone' => '0911111111']);
        app(BookingService::class)->cancel($don, 'Hủy', 'staff');

        // Trong luc do ba ban con lai bi khach khac dat het cung khung gio.
        $this->datKinKhungGio();

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.confirm', $don))
            ->assertSessionHasErrors('confirm');

        $sau = $don->fresh(['diningTables']);

        $this->assertSame(Booking::STATUS_CANCELLED, $sau->status, 'Khong con ban thi giu nguyen trang thai cu.');
        $this->assertCount(0, $sau->diningTables);
    }

    public function test_xac_nhan_don_cho_duyet_khong_lam_xao_tron_ban_da_xep(): void
    {
        $booking = $this->makeBooking();
        $banBanDau = $booking->diningTables->pluck('id')->all();

        $this->actingAs($this->admin)->post(route('admin.bookings.confirm', $booking));

        $this->assertSame(
            $banBanDau,
            $booking->fresh(['diningTables'])->diningTables->pluck('id')->all(),
            'Don da cam ban tu luc tao thi dung dong vao ket qua xep ban cua no.'
        );
    }

    // ---------- 2. Danh dau nham "khong den" ----------

    public function test_don_khong_den_dua_tro_lai_duoc_va_lay_lai_ban(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.transition', [$booking, 'no-show']));

        $this->assertSame(Booking::STATUS_NO_SHOW, $booking->fresh()->status);
        $this->assertCount(0, $booking->fresh()->diningTables);

        // Trang chi tiet phai co loi ra.
        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Đưa trở lại và giữ bàn');

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.confirm', $booking))
            ->assertSessionHasNoErrors();

        $sau = $booking->fresh(['diningTables']);

        $this->assertSame(Booking::STATUS_CONFIRMED, $sau->status);
        $this->assertCount(1, $sau->diningTables);
        $this->assertTrue($sau->isActive());
    }

    // ---------- 3. Khoa nut o khu quan tri ----------

    public function test_moi_trang_quan_tri_deu_co_lop_khoa_nut(): void
    {
        $trang = [
            route('admin.bookings.create'),
            route('admin.bookings.index'),
            route('admin.dashboard'),
        ];

        foreach ($trang as $duongDan) {
            $noiDung = $this->actingAs($this->admin)->get($duongDan)->assertOk()->getContent();

            $this->assertStringContainsString(
                "addEventListener('submit'",
                $noiDung,
                'Thieu lop khoa nut o '.$duongDan
            );
        }
    }

    public function test_nhan_vien_van_tao_duoc_hai_don_cung_gio_khi_thuc_su_muon(): void
    {
        // Lop khoa nut nam o trinh duyet. Phia may chu van cho phep, vi le tan
        // co ly do that: tach mot doan lon ra hai ban rieng. Rang buoc o day de
        // sau nay khong ai vo tinh chan luon duong do.
        $dulieu = [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách gọi điện',
            'customer_phone' => '0955555555',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '20:00',
            'source' => NguonDatBan::NHAN_VIEN_CHON[0],
        ];

        $this->actingAs($this->admin)->post(route('admin.bookings.store'), $dulieu);
        $this->actingAs($this->admin)->post(route('admin.bookings.store'), $dulieu);

        $this->assertSame(2, Booking::where('customer_phone', '0955555555')->count());
    }

    // ---------- 4. Tran tan suat cho moi duong cong khai ----------

    public function test_moi_duong_cong_khai_deu_co_tran_tan_suat(): void
    {
        $thieu = [];

        foreach (['booking.store', 'booking.lookup', 'booking.cancel', 'booking.slots'] as $ten) {
            $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === $ten);

            $coTran = collect($route->gatherMiddleware())
                ->contains(fn ($m) => str_starts_with((string) $m, 'throttle'));

            if (! $coTran) {
                $thieu[] = $ten;
            }
        }

        $this->assertSame([], $thieu, 'Con duong cong khai chua co tran tan suat.');
    }

    // ---------- Khong phai loi, giu lai lam rang buoc ----------

    public function test_khach_bam_huy_hai_lan_chi_gui_mot_tin(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);

        for ($i = 0; $i < 2; $i++) {
            $this->post(route('booking.cancel', $booking), ['customer_phone' => '0912345678']);
        }

        // Form huy khong khoa nut, nhung khong sao: lan hai bi customerCanCancel()
        // tu choi vi don khong con o trang thai pending/confirmed nua.
        $this->assertSame(1, $booking->notificationLogs()->where('event', 'cancelled')->count());
    }
}
