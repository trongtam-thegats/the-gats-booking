<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingCreationWithTablesTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'The Gats',
            'slug' => 'the-gats',
            'domain' => 'booking.thegats.test',
            'mark' => 'TG',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Drinking Healing',
            'slug' => 'drinking-healing',
            'open_time' => '17:00',
            'close_time' => '02:00',
            'slot_minutes' => 30,
            'turn_minutes' => 120,
            'min_lead_minutes' => 60,
            'max_advance_days' => 30,
            'max_party_size' => 20,
            'is_active' => true,
            'auto_confirm' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Quản lý',
            'email' => 'manager@thegats.vn',
            'password' => 'secret123',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_api_ban_trong_tra_ve_danh_sach_ban_kha_dung(): void
    {
        $area = $this->branch->areas()->create(['name' => 'Quầy Bar', 'bookable' => true]);
        $bar1 = $this->branch->diningTables()->create([
            'area_id' => $area->id,
            'code' => 'Bar 1',
            'seats_min' => 1,
            'seats_max' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson(route('admin.bookings.available-tables', [
                'branch_id' => $this->branch->id,
                'booking_date' => now()->addDays(2)->toDateString(),
                'start_time' => '19:00',
            ]));

        $response->assertOk()
            ->assertJsonStructure(['tables' => [['id', 'code', 'area_name', 'seats_min', 'seats_max']]]);

        $this->assertTrue(collect($response->json('tables'))->contains('code', 'Bar 1'));
    }

    public function test_nhan_vien_dat_ho_va_chi_dinh_ban_cu_the_thanh_cong(): void
    {
        $area = $this->branch->areas()->create(['name' => 'Bàn Cao', 'bookable' => true]);
        $t1 = $this->branch->diningTables()->create([
            'area_id' => $area->id,
            'code' => 'T1',
            'seats_min' => 2,
            'seats_max' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $t2 = $this->branch->diningTables()->create([
            'area_id' => $area->id,
            'code' => 'T2',
            'seats_min' => 2,
            'seats_max' => 4,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $date = now()->addDays(2)->toDateString();

        $response = $this->actingAs($this->manager)
            ->post(route('admin.bookings.store'), [
                'branch_id' => $this->branch->id,
                'customer_name' => 'Nguyen Van Test',
                'customer_phone' => '0901112233',
                'party_size' => 2,
                'booking_date' => $date,
                'start_time' => '19:00',
                'source' => 'phone',
                'table_ids' => [$t2->id],
            ]);

        $response->assertRedirect();

        $booking = $this->branch->bookings()->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertCount(1, $booking->diningTables);
        $this->assertSame($t2->id, $booking->diningTables->first()->id);
        $this->assertSame($area->id, $booking->area_id);
    }

    public function test_nhan_vien_dat_ho_chon_ban_da_co_khach_thi_bao_loi(): void
    {
        $area = $this->branch->areas()->create(['name' => 'Bàn Cao', 'bookable' => true]);
        $t1 = $this->branch->diningTables()->create([
            'area_id' => $area->id,
            'code' => 'T1',
            'seats_min' => 2,
            'seats_max' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $date = now()->addDays(2)->toDateString();

        // Don 1 da giu T1 tu 19:00 den 21:00
        app(BookingService::class)->create($this->branch, [
            'customer_name' => 'Khach A',
            'customer_phone' => '0901112233',
            'party_size' => 2,
            'booking_date' => $date,
            'start_time' => '19:00',
            'source' => 'phone',
            'table_ids' => [$t1->id],
        ], $this->manager);

        // Don 2 co tinh chon lai T1 luc 19:30
        $response = $this->actingAs($this->manager)
            ->from(route('admin.bookings.create'))
            ->post(route('admin.bookings.store'), [
                'branch_id' => $this->branch->id,
                'customer_name' => 'Khach B',
                'customer_phone' => '0909998877',
                'party_size' => 2,
                'booking_date' => $date,
                'start_time' => '19:30',
                'source' => 'phone',
                'table_ids' => [$t1->id],
            ]);

        $response->assertRedirect(route('admin.bookings.create'));
        $response->assertSessionHasErrors(['start_time', 'table_ids']);
    }

    public function test_nhan_vien_chu_dong_chon_sofa_cho_2_khach_thi_he_thong_chap_nhan(): void
    {
        $sofaArea = $this->branch->areas()->create(['name' => 'Sofa', 'bookable' => true]);
        $sofa1 = $this->branch->diningTables()->create([
            'area_id' => $sofaArea->id,
            'code' => 'Sofa 1',
            'seats_min' => 4,
            'seats_max' => 6,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $date = now()->addDays(2)->toDateString();

        $response = $this->actingAs($this->manager)
            ->post(route('admin.bookings.store'), [
                'branch_id' => $this->branch->id,
                'customer_name' => 'Khach VIP',
                'customer_phone' => '0905556677',
                'party_size' => 2,
                'booking_date' => $date,
                'start_time' => '20:00',
                'source' => 'walk_in',
                'table_ids' => [$sofa1->id],
            ]);

        $response->assertRedirect();

        $booking = $this->branch->bookings()->latest('id')->first();
        $this->assertCount(1, $booking->diningTables);
        $this->assertSame($sofa1->id, $booking->diningTables->first()->id);
    }
}
