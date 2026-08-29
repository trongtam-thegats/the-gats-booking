<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bao cao dat ban. Cac test o day dung du lieu dung san de con so mong doi
 * tinh duoc bang tay, thay vi tin vao chinh cong thuc dang kiem tra.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán A', 'slug' => 'quan-a', 'domain' => 'booking.quana.test',
            'mark' => 'QA', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $this->brand->branches()->create([
            'name' => 'Quán A', 'slug' => 'quan-a-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);

        $this->branch->diningTables()->create([
            'code' => 'T1', 'table_type' => 'high_table',
            'seats_min' => 1, 'seats_max' => 4, 'combinable' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Quản trị', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);
    }

    /** Tao mot don dat ban thang vao CSDL, khong qua luong kiem tra suc chua. */
    protected function booking(string $status, string $date, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'code' => Booking::generateCode(),
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => $date,
            'start_time' => '19:00',
            'end_time' => '21:00',
            'status' => $status,
            'source' => 'online',
        ], $overrides));
    }

    protected function report(int $daysBack = 29, ?array $branchIds = null): array
    {
        return app(ReportService::class)->build(
            Carbon::today()->subDays($daysBack)->toDateString(),
            Carbon::today()->toDateString(),
            $branchIds
        );
    }

    // ---------- Con so co ban ----------

    public function test_dem_dung_tung_ket_qua(): void
    {
        $day = Carbon::today()->subDays(3)->toDateString();

        foreach (range(1, 6) as $i) {
            $this->booking(Booking::STATUS_COMPLETED, $day, ['party_size' => 4]);
        }
        $this->booking(Booking::STATUS_SEATED, $day, ['party_size' => 2]);
        $this->booking(Booking::STATUS_NO_SHOW, $day);
        $this->booking(Booking::STATUS_CANCELLED, $day);
        $this->booking(Booking::STATUS_PENDING, $day);

        $t = $this->report()['totals'];

        $this->assertSame(10, $t['bookings']);
        $this->assertSame(7, $t['arrived']);
        $this->assertSame(1, $t['no_show']);
        $this->assertSame(1, $t['cancelled']);
        $this->assertSame(1, $t['pending']);
        // Khach da phuc vu: 6 don 4 khach + 1 don 2 khach.
        $this->assertSame(26, $t['guests']);
    }

    /**
     * Mau so cua ti le den la nhung don da co ket qua (den + khong den),
     * khong tinh don bi huy va don con cho duyet - neu khong, quan nao bi khach
     * huy nhieu se trong nhu la khach khong den.
     */
    public function test_ti_le_den_khong_tinh_don_huy_va_don_cho_duyet(): void
    {
        $day = Carbon::today()->subDay()->toDateString();

        foreach (range(1, 8) as $i) {
            $this->booking(Booking::STATUS_COMPLETED, $day);
        }
        foreach (range(1, 2) as $i) {
            $this->booking(Booking::STATUS_NO_SHOW, $day);
        }
        // Nhung don nay khong duoc lam thay doi ti le den.
        foreach (range(1, 5) as $i) {
            $this->booking(Booking::STATUS_CANCELLED, $day);
        }
        $this->booking(Booking::STATUS_PENDING, $day);

        $t = $this->report()['totals'];

        $this->assertSame(80.0, $t['arrival_rate']);
        $this->assertSame(20.0, $t['no_show_rate']);
        // Ti le huy thi tinh tren tong so don.
        $this->assertSame(31.3, $t['cancel_rate']);
    }

    public function test_phieu_quy_trinh_giam_dan_va_dem_dung(): void
    {
        $day = Carbon::today()->subDays(2)->toDateString();

        foreach (range(1, 5) as $i) {
            $this->booking(Booking::STATUS_COMPLETED, $day);
        }
        $this->booking(Booking::STATUS_SEATED, $day);
        $this->booking(Booking::STATUS_NO_SHOW, $day);
        $this->booking(Booking::STATUS_CANCELLED, $day);
        $this->booking(Booking::STATUS_PENDING, $day);

        $funnel = collect($this->report()['funnel'])->keyBy('key');

        $this->assertSame(9, $funnel['requested']['value']);
        // Xac nhan = tat ca tru don cho duyet va don huy.
        $this->assertSame(7, $funnel['confirmed']['value']);
        $this->assertSame(6, $funnel['arrived']['value']);
        $this->assertSame(5, $funnel['completed']['value']);

        $values = collect($this->report()['funnel'])->pluck('value')->all();
        $sorted = $values;
        rsort($sorted);
        $this->assertSame($sorted, $values, 'Phễu phải giảm dần qua từng bước');
    }

    public function test_thoi_gian_duyet_lay_trung_vi_chu_khong_lay_trung_binh(): void
    {
        $day = Carbon::today()->subDay()->toDateString();
        $created = Carbon::today()->subDays(2)->setTime(10, 0);

        // 10, 20, 30 phut va mot don bi quen ca ngay.
        // created_at khong nam trong fillable nen phai gan sau khi tao.
        foreach ([10, 20, 30, 1440] as $minutes) {
            $booking = $this->booking(Booking::STATUS_COMPLETED, $day, [
                'confirmed_at' => $created->copy()->addMinutes($minutes),
            ]);
            $booking->forceFill(['created_at' => $created])->save();
        }

        // Trung vi cua 10, 20, 30, 1440 la 25. Trung binh se la 375 - lech han.
        $this->assertSame(25, $this->report()['totals']['median_confirm_minutes']);
    }

    // ---------- Cac lat cat ----------

    public function test_cong_cac_lat_cat_deu_bang_tong(): void
    {
        foreach (range(0, 20) as $back) {
            $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->subDays($back)->toDateString());
        }

        $report = $this->report();
        $total = $report['totals']['bookings'];

        $this->assertSame($total, array_sum(array_column($report['series']['rows'], 'bookings')));
        $this->assertSame($total, array_sum(array_column($report['by_weekday'], 'bookings')));
        $this->assertSame($total, array_sum(array_column($report['by_hour'], 'bookings')));
        $this->assertSame($total, array_sum(array_column($report['by_source'], 'bookings')));
        $this->assertSame($total, array_sum(array_column($report['lead_time'], 'bookings')));
    }

    public function test_khoang_ngan_giu_theo_ngay_khoang_dai_gop_theo_tuan(): void
    {
        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->toDateString());

        $short = $this->report(29);
        $this->assertSame('day', $short['series']['granularity']);
        $this->assertCount(30, $short['series']['rows']);

        $long = $this->report(89);
        $this->assertSame('week', $long['series']['granularity']);
        // 90 ngay gop lai chi con khoang 13-14 cot, du rong de bam tren dien thoai.
        $this->assertLessThanOrEqual(15, count($long['series']['rows']));
        $this->assertGreaterThanOrEqual(12, count($long['series']['rows']));
    }

    public function test_gop_theo_tuan_khong_lam_mat_so_lieu(): void
    {
        foreach (range(0, 60) as $back) {
            $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->subDays($back)->toDateString());
        }

        $report = $this->report(89);

        $this->assertSame(
            $report['totals']['bookings'],
            array_sum(array_column($report['series']['rows'], 'bookings'))
        );
    }

    public function test_khach_moi_va_khach_quay_lai(): void
    {
        // Khach nay da tung den truoc ky bao cao.
        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->subDays(200)->toDateString(), [
            'customer_phone' => '0900000001',
        ]);

        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->toDateString(), ['customer_phone' => '0900000001']);
        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->toDateString(), ['customer_phone' => '0900000002']);

        $guests = $this->report()['guests'];

        $this->assertSame(2, $guests['unique']);
        $this->assertSame(1, $guests['returning']);
        $this->assertSame(1, $guests['new']);
    }

    public function test_so_sanh_voi_ky_truoc_do_dai_bang_nhau(): void
    {
        // Ky hien tai 7 ngay: 3 don. Ky truoc do 7 ngay: 1 don.
        foreach ([0, 1, 2] as $back) {
            $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->subDays($back)->toDateString());
        }
        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->subDays(9)->toDateString());

        $report = $this->report(6);

        $this->assertSame(3, $report['totals']['bookings']);
        $this->assertSame(1, $report['previous']['bookings']);
    }

    // ---------- Truong hop bien ----------

    public function test_khoang_khong_co_du_lieu_khong_gay_loi(): void
    {
        $report = app(ReportService::class)->build('2020-01-01', '2020-01-31', null);

        $this->assertSame(0, $report['totals']['bookings']);
        $this->assertSame(0.0, $report['totals']['arrival_rate']);
        $this->assertNull($report['totals']['median_confirm_minutes']);
        $this->assertCount(31, $report['series']['rows']);
        $this->assertSame([], $report['by_hour']);
    }

    public function test_dao_nguoc_ngay_van_ra_dung_khoang(): void
    {
        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->toDateString());

        // Nguoi dung go nguoc tu/den - bao cao phai tu doi lai chu khong tra ve rong.
        $report = app(ReportService::class)->build(
            Carbon::today()->toDateString(),
            Carbon::today()->subDays(6)->toDateString(),
            null
        );

        $this->assertSame(7, $report['range']['days']);
        $this->assertSame(1, $report['totals']['bookings']);
    }

    // ---------- Phan quyen ----------

    public function test_quan_ly_chi_thay_so_lieu_cua_quan_minh(): void
    {
        $otherBrand = Brand::create([
            'name' => 'Quán B', 'slug' => 'quan-b', 'domain' => 'booking.quanb.test',
            'mark' => 'QB', 'accent_color' => '#7fb59b', 'is_active' => true,
        ]);

        $otherBranch = $otherBrand->branches()->create([
            'name' => 'Quán B', 'slug' => 'quan-b-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);

        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->toDateString());
        Booking::create([
            'code' => Booking::generateCode(), 'branch_id' => $otherBranch->id,
            'customer_name' => 'Khách quán B', 'customer_phone' => '0999999999',
            'party_size' => 2, 'booking_date' => Carbon::today()->toDateString(),
            'start_time' => '19:00', 'end_time' => '21:00',
            'status' => Booking::STATUS_COMPLETED, 'source' => 'online',
        ]);

        $manager = User::create([
            'name' => 'Quản lý A', 'email' => 'ql@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Quán A')
            ->assertDontSee('Khách quán B');

        // Quan tri thi thay ca hai.
        $this->assertSame(2, $this->report()['totals']['bookings']);
    }

    public function test_vai_chi_xem_van_mo_duoc_bao_cao(): void
    {
        $viewer = User::create([
            'name' => 'Người xem', 'email' => 'xem@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::VIEWER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Báo cáo đặt bàn');
    }

    // ---------- Trang hien thi ----------

    public function test_trang_bao_cao_hien_du_cac_phan(): void
    {
        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->toDateString());

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Quy trình xử lý đặt bàn')
            ->assertSee('Kết quả từng')
            ->assertSee('Khung giờ khách chọn')
            ->assertSee('Ngày trong tuần')
            ->assertSee('Khách đặt qua đâu')
            ->assertSee('Khách đặt trước bao lâu')
            ->assertSee('Sức chứa đã dùng');
    }

    public function test_moi_bieu_do_deu_co_bang_so_lieu_kem_theo(): void
    {
        $this->booking(Booking::STATUS_COMPLETED, Carbon::today()->toDateString());

        $html = $this->actingAs($this->admin)->get(route('admin.reports.index'))->getContent();

        // Bay bieu do, moi cai mot bang so lieu va mot nut chuyen doi.
        $this->assertSame(7, substr_count($html, 'class="viz-figure"'));
        $this->assertSame(7, substr_count($html, 'data-viz-toggle'));
        $this->assertSame(7, substr_count($html, 'class="viz-table table-wrap"'));
    }
}
