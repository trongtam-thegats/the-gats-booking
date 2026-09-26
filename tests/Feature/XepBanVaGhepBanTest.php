<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XepBanVaGhepBanTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;
    protected Branch $branch;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Drinking Healing',
            'slug' => 'drinking-healing',
            'domain' => 'dh.test',
        ]);

        $this->branch = Branch::create([
            'brand_id' => $this->brand->id,
            'name' => 'Drinking Healing',
            'slug' => 'drinking-healing',
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

        $this->manager = User::create([
            'name' => 'Quản lý A',
            'email' => 'quanlya@thegats.vn',
            'password' => 'matkhau123',
            'role' => \App\Support\Roles::MANAGER,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    public function test_khach_2_nguoi_uu_tien_ban_vua_khit_thay_vi_sofa(): void
    {
        $sofa = $this->branch->diningTables()->create([
            'code' => 'Sofa 1',
            'seats_min' => 4,
            'seats_max' => 6,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $banCao = $this->branch->diningTables()->create([
            'code' => 'T1',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $booking = app(BookingService::class)->create($this->branch, [
            'customer_name' => 'Nguyen Van A',
            'customer_phone' => '0901234567',
            'party_size' => 2,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '19:00',
            'source' => 'online',
        ]);

        $this->assertCount(1, $booking->diningTables);
        $this->assertSame($banCao->id, $booking->diningTables->first()->id);
        $this->assertNotSame($sofa->id, $booking->diningTables->first()->id);
    }

    public function test_khach_2_nguoi_ghep_2_ghe_bar_lien_ke_khi_het_ban_cao_va_khong_nhay_vao_sofa(): void
    {
        $sofa = $this->branch->diningTables()->create([
            'code' => 'Sofa 1',
            'seats_min' => 4,
            'seats_max' => 6,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $bar1 = $this->branch->diningTables()->create([
            'code' => 'Bar 1',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $bar2 = $this->branch->diningTables()->create([
            'code' => 'Bar 2',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Thiet lap lien ke giua Bar 1 va Bar 2
        $bar1->combinedTables()->attach($bar2->id);
        $bar2->combinedTables()->attach($bar1->id);

        $booking = app(BookingService::class)->create($this->branch, [
            'customer_name' => 'Tran Thi B',
            'customer_phone' => '0909876543',
            'party_size' => 2,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '19:00',
            'source' => 'online',
        ]);

        $this->assertCount(2, $booking->diningTables);
        $codes = $booking->diningTables->pluck('code')->sort()->values()->all();
        $this->assertSame(['Bar 1', 'Bar 2'], $codes);
        $this->assertFalse($booking->diningTables->contains('id', $sofa->id));
    }

    public function test_hai_ghe_bar_khong_lien_ke_thi_khong_duoc_ghep(): void
    {
        $bar1 = $this->branch->diningTables()->create([
            'code' => 'Bar 1',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $bar2 = $this->branch->diningTables()->create([
            'code' => 'Bar 2',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $bar5 = $this->branch->diningTables()->create([
            'code' => 'Bar 5',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        // Bar 1 chi lien ke voi Bar 2, khong lien ke voi Bar 5
        $bar1->combinedTables()->attach($bar2->id);
        $bar2->combinedTables()->attach($bar1->id);

        // Gia su Bar 1 va Bar 5 con trong (Bar 2 bi chiem)
        $avail = app(AvailabilityService::class);
        $picked = $avail->pickTables(collect([$bar1, $bar5]), 2);

        $this->assertEmpty($picked, 'Hai ban khong lien ke khong duoc tu dong ghep.');
    }

    public function test_khach_2_nguoi_khong_bao_gio_tu_dong_vao_sofa_khi_het_ban_khac(): void
    {
        $this->branch->diningTables()->create([
            'code' => 'Sofa 1',
            'seats_min' => 4,
            'seats_max' => 6,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->expectException(\App\Exceptions\BookingUnavailableException::class);

        app(BookingService::class)->create($this->branch, [
            'customer_name' => 'Le Van C',
            'customer_phone' => '0912345678',
            'party_size' => 2,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '19:00',
            'source' => 'online',
        ]);
    }

    public function test_nhan_vien_co_the_xep_thu_cong_vao_sofa_cho_khach_2_nguoi(): void
    {
        $sofa = $this->branch->diningTables()->create([
            'code' => 'Sofa 1',
            'seats_min' => 4,
            'seats_max' => 6,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $t1 = $this->branch->diningTables()->create([
            'code' => 'T1',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Don dat ban ban dau xep vao T1
        $booking = app(BookingService::class)->create($this->branch, [
            'customer_name' => 'Pham Thi D',
            'customer_phone' => '0987654321',
            'party_size' => 2,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '19:00',
            'source' => 'online',
        ]);

        $this->assertSame($t1->id, $booking->diningTables->first()->id);

        // Nhan vien doi thu cong sang Sofa 1 qua form xep ban
        $response = $this->actingAs($this->manager)
            ->post(route('admin.bookings.tables', $booking), [
                'table_ids' => [$sofa->id],
            ]);

        $response->assertRedirect();
        $this->assertSame($sofa->id, $booking->fresh()->diningTables->first()->id);
    }
}
