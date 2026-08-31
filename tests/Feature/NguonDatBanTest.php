<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Support\NguonDatBan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Nguon don dat ban: khach den voi quan tu kenh nao.
 *
 * Khach tu dat thi nguon lay tu tham so tren duong dan, ghi nho trong phien
 * de khong mat khi khach doi ngon ngu hay doi dia diem giua chung.
 */
class NguonDatBanTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-31 18:00:00'));

        $brand = Brand::create([
            'name' => 'Quán A', 'slug' => 'quan-a', 'domain' => 'booking.quan-a.test',
            'mark' => 'QA', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Quán A', 'slug' => 'quan-a', 'open_time' => '17:00', 'close_time' => '02:00',
            'slot_minutes' => 30, 'turn_minutes' => 120, 'min_lead_minutes' => 60,
            'max_advance_days' => 30, 'max_party_size' => 20, 'is_active' => true,
        ]);

        $area = $this->branch->areas()->create(['name' => 'Khu chính', 'bookable' => true]);

        $this->branch->diningTables()->create([
            'area_id' => $area->id, 'code' => 'B1', 'table_type' => 'high_table',
            'seats_min' => 1, 'seats_max' => 4, 'combinable' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $them */
    protected function datBan(array $them = []): TestResponse
    {
        return $this->post(route('booking.store', ['branch' => $this->branch]), $them + [
            'customer_name' => 'Khách thử',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => '2026-08-31',
            'start_time' => '21:00',
        ]);
    }

    public function test_khach_vao_thang_trang_thi_nguon_la_website(): void
    {
        $this->get('/');
        $this->datBan()->assertRedirect();

        $this->assertSame(NguonDatBan::WEBSITE, Booking::first()->source);
    }

    public function test_duong_dan_co_duoi_kenh_thi_ghi_dung_nguon(): void
    {
        $this->get('/?nguon=ig');
        $this->datBan()->assertRedirect();

        $this->assertSame(NguonDatBan::INSTAGRAM, Booking::first()->source);
    }

    public function test_nguon_khong_mat_khi_khach_doi_ngon_ngu_giua_chung(): void
    {
        $this->get('/?nguon=fb');

        // Doi ngon ngu va bam sang trang tra cuu: duong dan khong con tham so.
        $this->get('/?lang=en');
        $this->get('/tra-cuu');

        $this->datBan()->assertRedirect();

        $this->assertSame(NguonDatBan::FACEBOOK, Booking::first()->source);
    }

    public function test_vao_lai_bang_kenh_khac_thi_tinh_theo_kenh_moi(): void
    {
        $this->get('/?nguon=ig');
        $this->get('/?nguon=google');

        $this->datBan()->assertRedirect();

        $this->assertSame(NguonDatBan::GOOGLE, Booking::first()->source);
    }

    public function test_duoi_la_gia_tri_la_thi_bo_qua_chu_khong_luu_bay(): void
    {
        $this->get('/?nguon=tiktok-khong-co-that');
        $this->datBan()->assertRedirect();

        $this->assertSame(NguonDatBan::WEBSITE, Booking::first()->source);
    }

    public function test_cac_cach_viet_tat_deu_ve_cung_mot_nguon(): void
    {
        $this->assertSame(NguonDatBan::FACEBOOK, NguonDatBan::chuan('fb'));
        $this->assertSame(NguonDatBan::FACEBOOK, NguonDatBan::chuan('Facebook'));
        $this->assertSame(NguonDatBan::INSTAGRAM, NguonDatBan::chuan('IG'));
        $this->assertSame(NguonDatBan::WALK_IN, NguonDatBan::chuan('walking'));

        // Gia tri cu cua he thong va cua Nightify van doc duoc.
        $this->assertSame(NguonDatBan::WEBSITE, NguonDatBan::chuan('online'));
        $this->assertSame(NguonDatBan::PHONE, NguonDatBan::chuan('venue_initiated_booking'));

        $this->assertNull(NguonDatBan::chuan('khong-co-that'));
        $this->assertNull(NguonDatBan::chuan(''));
    }
}
