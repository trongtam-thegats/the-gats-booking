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

        $this->admin = User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@thegats.vn',
            'password' => 'matkhau123',
            'role' => \App\Support\Roles::ADMIN,
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

    public function test_day_slots_tinh_chinh_xac_max_party_size_theo_ban_don_va_ban_ghep(): void
    {
        $sofa1 = $this->branch->diningTables()->create([
            'code' => 'Sofa 1',
            'seats_min' => 4,
            'seats_max' => 6,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $sofa2 = $this->branch->diningTables()->create([
            'code' => 'Sofa 2',
            'seats_min' => 6,
            'seats_max' => 8,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $sofa3 = $this->branch->diningTables()->create([
            'code' => 'Sofa 3',
            'seats_min' => 6,
            'seats_max' => 8,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Chi ghep Sofa 1 <-> Sofa 2 (tong 14 cho). Khong ghep Sofa 2 voi Sofa 3.
        $sofa1->combinedTables()->syncWithoutDetaching([$sofa2->id]);
        $sofa2->combinedTables()->syncWithoutDetaching([$sofa1->id]);

        $avail = app(AvailabilityService::class);
        $date = now()->addDays(2)->toDateString();
        $slots = $avail->daySlots($this->branch, $date, 2);

        $slot1900 = collect($slots)->firstWhere('time', '19:00');
        $this->assertNotNull($slot1900);
        $this->assertTrue($slot1900['available']);
        // Max party size phai la 18 (do Sofa 1 + Sofa 2 ghep duoc 18 khach)
        $this->assertSame(18, $slot1900['max_party_size']);

        // Thu giu cho ca Sofa 1 va Sofa 2 luc 19:00
        $booking = app(BookingService::class)->create($this->branch, [
            'customer_name' => 'Doan Dong',
            'customer_phone' => '0912345678',
            'party_size' => 18,
            'booking_date' => $date,
            'start_time' => '19:00',
            'source' => 'online',
        ]);

        $slotsAfter = $avail->daySlots($this->branch, $date, 2);
        $slot1900After = collect($slotsAfter)->firstWhere('time', '19:00');

        // Chi con lai Sofa 3 (8 cho)
        $this->assertSame(8, $slot1900After['max_party_size']);
    }

    public function test_admin_co_the_them_va_xoa_cap_ban_ghep(): void
    {
        $b1 = $this->branch->diningTables()->create([
            'code' => 'B1',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $b2 = $this->branch->diningTables()->create([
            'code' => 'B2',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Them cap ghep B1 <-> B2
        $response = $this->actingAs($this->admin)
            ->post(route('admin.tables.combinations.store', $this->branch), [
                'table_id' => $b1->id,
                'combined_with_id' => $b2->id,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Kiem tra ca hai ban duoc bat combinable va co lien ket 2 chieu
        $this->assertTrue((bool) $b1->fresh()->combinable);
        $this->assertTrue((bool) $b2->fresh()->combinable);
        $this->assertTrue($b1->combinedTables()->where('combined_with_id', $b2->id)->exists());
        $this->assertTrue($b2->combinedTables()->where('combined_with_id', $b1->id)->exists());

        // Xoa cap ghep
        $deleteResponse = $this->actingAs($this->admin)
            ->delete(route('admin.tables.combinations.destroy', $this->branch), [
                'table_id' => $b1->id,
                'combined_with_id' => $b2->id,
            ]);

        $deleteResponse->assertSessionHasNoErrors();
        $this->assertFalse($b1->combinedTables()->where('combined_with_id', $b2->id)->exists());
        $this->assertFalse($b2->combinedTables()->where('combined_with_id', $b1->id)->exists());
    }

    public function test_admin_khong_the_ghep_mot_ban_voi_chinh_no(): void
    {
        $b1 = $this->branch->diningTables()->create([
            'code' => 'B1',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tables.combinations.store', $this->branch), [
                'table_id' => $b1->id,
                'combined_with_id' => $b1->id,
            ]);

        $response->assertSessionHasErrors('combined_with_id');
    }

    public function test_drinking_healing_quay_bar_ghep_toi_da_3_ghe_va_khong_ghep_4_ghe(): void
    {
        $barArea = $this->branch->areas()->create(['name' => 'Quầy Bar', 'bookable' => true]);

        $b1 = $this->branch->diningTables()->create([
            'area_id' => $barArea->id,
            'code' => 'Bar 1',
            'table_type' => 'bar_seat',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $b2 = $this->branch->diningTables()->create([
            'area_id' => $barArea->id,
            'code' => 'Bar 2',
            'table_type' => 'bar_seat',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $b3 = $this->branch->diningTables()->create([
            'area_id' => $barArea->id,
            'code' => 'Bar 3',
            'table_type' => 'bar_seat',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $b4 = $this->branch->diningTables()->create([
            'area_id' => $barArea->id,
            'code' => 'Bar 4',
            'table_type' => 'bar_seat',
            'seats_min' => 1,
            'seats_max' => 1,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 4,
        ]);

        // Ket noi lien ke: B1 <-> B2 <-> B3 <-> B4
        $b1->combinedTables()->attach($b2->id);
        $b2->combinedTables()->attach([$b1->id, $b3->id]);
        $b3->combinedTables()->attach([$b2->id, $b4->id]);
        $b4->combinedTables()->attach($b3->id);

        $tables = $this->branch->diningTables()->with('combinedTables')->whereIn('id', [$b1->id, $b2->id, $b3->id, $b4->id])->get();
        $avail = app(AvailabilityService::class);

        // Nhom 3 khach: ghep duoc 3 ghe lien ke
        $picked3 = $avail->pickTables($tables, 3);
        $this->assertCount(3, $picked3);

        // Nhom 4 khach: KHONG ghep 4 ghe quay bar (toi da chi 3 ghe)
        $picked4 = $avail->pickTables($tables, 4);
        $this->assertEmpty($picked4);

        // Suc chua toi da cua quay bar tinh ra la 3
        $maxSeats = $avail->maxPartySizeForTables($tables);
        $this->assertSame(3, $maxSeats);
    }

    public function test_drinking_healing_ban_cao_ghep_toi_da_2_ban_noi_tiep(): void
    {
        $highArea = $this->branch->areas()->create(['name' => 'Bàn Cao', 'bookable' => true]);

        $t1 = $this->branch->diningTables()->create([
            'area_id' => $highArea->id,
            'code' => 'T1',
            'table_type' => 'high_table',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $t2 = $this->branch->diningTables()->create([
            'area_id' => $highArea->id,
            'code' => 'T2',
            'table_type' => 'high_table',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $t3 = $this->branch->diningTables()->create([
            'area_id' => $highArea->id,
            'code' => 'T3',
            'table_type' => 'high_table',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Ket noi lien ke: T1 <-> T2 <-> T3
        $t1->combinedTables()->attach($t2->id);
        $t2->combinedTables()->attach([$t1->id, $t3->id]);
        $t3->combinedTables()->attach($t2->id);

        $tables = $this->branch->diningTables()->with('combinedTables')->whereIn('id', [$t1->id, $t2->id, $t3->id])->get();
        $avail = app(AvailabilityService::class);

        // Nhom 7 khach: ghep duoc 2 ban T1 + T2 (8 cho)
        $picked7 = $avail->pickTables($tables, 7);
        $this->assertCount(2, $picked7);

        // Nhom 10 khach: KHONG the ghep 3 ban cao T1 + T2 + T3 (toi da chi 2 ban)
        $picked10 = $avail->pickTables($tables, 10);
        $this->assertEmpty($picked10);

        // Suc chua toi da chi la 2 ban = 8 khach
        $maxSeats = $avail->maxPartySizeForTables($tables);
        $this->assertSame(8, $maxSeats);
    }

    public function test_drinking_healing_sofa_chi_ghep_s1_s2_va_s3_s4_khong_ghep_s2_s3(): void
    {
        $sofaArea = $this->branch->areas()->create(['name' => 'Sofa', 'bookable' => true]);

        $s1 = $this->branch->diningTables()->create([
            'area_id' => $sofaArea->id,
            'code' => 'Sofa 1',
            'table_type' => 'sofa',
            'seats_min' => 4,
            'seats_max' => 6,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $s2 = $this->branch->diningTables()->create([
            'area_id' => $sofaArea->id,
            'code' => 'Sofa 2',
            'table_type' => 'sofa',
            'seats_min' => 5,
            'seats_max' => 8,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $s3 = $this->branch->diningTables()->create([
            'area_id' => $sofaArea->id,
            'code' => 'Sofa 3',
            'table_type' => 'sofa',
            'seats_min' => 5,
            'seats_max' => 8,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $s4 = $this->branch->diningTables()->create([
            'area_id' => $sofaArea->id,
            'code' => 'Sofa 4',
            'table_type' => 'sofa',
            'seats_min' => 5,
            'seats_max' => 8,
            'combinable' => true,
            'is_active' => true,
            'sort_order' => 4,
        ]);

        // Chi lien ket S1 <-> S2 va S3 <-> S4 (TUYET DOI khong lien ket S2 voi S3)
        $s1->combinedTables()->attach($s2->id);
        $s2->combinedTables()->attach($s1->id);

        $s3->combinedTables()->attach($s4->id);
        $s4->combinedTables()->attach($s3->id);

        $avail = app(AvailabilityService::class);

        // Truong hop 1: Tat ca sofa deu trong, khach 18 nguoi -> ghep duoc S1 + S2 hoac S3 + S4 (18 cho)
        $allSofas = $this->branch->diningTables()->with('combinedTables')->whereIn('id', [$s1->id, $s2->id, $s3->id, $s4->id])->get();
        $picked18 = $avail->pickTables($allSofas, 18);
        $this->assertCount(2, $picked18);
        $this->assertSame(18, $avail->tongSucChuaToHop($picked18));

        // Suc chua toi da cua cum sofa khi ghep la 18 khach
        $this->assertSame(18, $avail->maxPartySizeForTables($allSofas));

        // Truong hop 2: S1 va S4 ban, chi con S2 va S3 trong
        // Khach 14 nguoi -> KHONG duoc ghep S2 voi S3
        $onlyS2S3 = $this->branch->diningTables()->with('combinedTables')->whereIn('id', [$s2->id, $s3->id])->get();
        $pickedFail = $avail->pickTables($onlyS2S3, 14);
        $this->assertEmpty($pickedFail);
    }

    public function test_drinking_healing_ban_cao_khong_ghep_khi_combinable_false(): void
    {
        $area = $this->branch->areas()->create(['name' => 'Bàn Cao', 'bookable' => true]);

        $t1 = $this->branch->diningTables()->create([
            'area_id' => $area->id,
            'code' => 'T1',
            'table_type' => 'high_table',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $t2 = $this->branch->diningTables()->create([
            'area_id' => $area->id,
            'code' => 'T2',
            'table_type' => 'high_table',
            'seats_min' => 2,
            'seats_max' => 4,
            'combinable' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $tables = $this->branch->diningTables()->with('combinedTables')->whereIn('id', [$t1->id, $t2->id])->get();
        $avail = app(AvailabilityService::class);

        // Do combinable = false nen khong ghep duoc cho khach 6 nguoi
        $picked6 = $avail->pickTables($tables, 6);
        $this->assertEmpty($picked6);
        $this->assertSame(4, $avail->maxPartySizeForTables($tables));
    }
}
