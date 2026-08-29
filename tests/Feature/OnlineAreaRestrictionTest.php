<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Khu vuc tat "nhan khach dat online" chi danh cho khach goi dien hoac khach
 * vang lai - he thong khong tu xep ban trong khu do cho don dat tren web.
 */
class OnlineAreaRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu',
            'domain' => 'booking.quanthu.test', 'mark' => 'QT',
            'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $this->brand->branches()->create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);
    }

    protected function addTable(string $code, ?int $areaId): void
    {
        $this->branch->diningTables()->create([
            'code' => $code, 'area_id' => $areaId, 'table_type' => 'high_table',
            'seats_min' => 1, 'seats_max' => 4, 'combinable' => true,
        ]);
    }

    protected function book(): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Khách',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ]);
    }

    public function test_khu_tat_dat_online_khong_duoc_xep_cho_khach_web(): void
    {
        $private = $this->branch->areas()->create(['name' => 'Phòng riêng', 'bookable' => false]);
        $this->addTable('VIP1', $private->id);

        $this->book()->assertSessionHasErrors('start_time');

        $this->assertSame(0, Booking::count());
    }

    public function test_ban_chua_phan_khu_van_nhan_dat_online(): void
    {
        $this->addTable('T1', null);

        $this->book();

        $this->assertSame(1, Booking::count());
        $this->assertSame('T1', Booking::first()->diningTables->first()->code);
    }

    public function test_chi_xep_ban_o_khu_dang_bat_dat_online(): void
    {
        $private = $this->branch->areas()->create(['name' => 'Phòng riêng', 'bookable' => false]);
        $open = $this->branch->areas()->create(['name' => 'Tầng trệt', 'bookable' => true]);

        $this->addTable('VIP1', $private->id);
        $this->addTable('A01', $open->id);

        $this->book();

        $this->assertSame('A01', Booking::firstOrFail()->diningTables->first()->code);
    }

    public function test_nhan_vien_van_dat_ho_duoc_vao_khu_tat_dat_online(): void
    {
        $private = $this->branch->areas()->create(['name' => 'Phòng riêng', 'bookable' => false]);
        $this->addTable('VIP1', $private->id);

        $manager = User::create([
            'name' => 'Quản lý', 'email' => 'ql@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->actingAs($manager)->post(route('admin.bookings.store'), [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Khách gọi điện',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
            'source' => 'phone',
        ])->assertRedirect();

        $this->assertSame('VIP1', Booking::firstOrFail()->diningTables->first()->code);
    }

    public function test_form_dat_ban_khong_con_o_chon_khu_vuc(): void
    {
        $this->branch->areas()->create(['name' => 'Tầng trệt', 'bookable' => true]);
        $this->addTable('A01', $this->branch->areas()->first()->id);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Khu vực mong muốn')
            ->assertSee('Ghi chú');
    }
}
