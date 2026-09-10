<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\GuestNote;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Luu ghi chu ve khach.
 *
 * Ghi chu gan theo QUAN, nen truoc khi luu phai biet khach thuoc quan nao.
 * Quan tri khong gan quan nao ca, phai suy ra tu du lieu cua chinh khach do.
 *
 * Loi that da gap tren may chu: ban dau chi suy tu lan DAT BAN gan nhat, ma
 * 3.099 tren 3.557 khach nhan dien duoc (87%) chua tung dat ban - ho chi co
 * hoa don. Quan tri bam Luu ghi chu la nhan trang loi 422.
 */
class LuuGhiChuKhachTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected const SDT = '0900000001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán thử', 'slug' => 'quan-thu', 'domain' => 'booking.quan-thu.test',
            'mark' => 'QT', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $this->brand->branches()->create([
            'name' => 'Quán thử', 'slug' => 'quan-thu-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
        ]);
    }

    protected function quanTri(): User
    {
        return User::create([
            'name' => 'Giám đốc', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);
    }

    protected function hoaDon(string $ma): Invoice
    {
        return Invoice::create([
            'branch_id' => $this->branch->id,
            'code' => $ma,
            'status' => 'Đã thanh toán',
            'paid_at' => '2026-09-01 22:00:00',
            'total' => 500_000,
            'customer_phone' => self::SDT,
            'customer_name' => 'Khách thử',
        ]);
    }

    protected function luu(User $u, array $them = [])
    {
        return $this->actingAs($u)->post(route('admin.guests.note'), array_merge([
            'phone' => self::SDT,
            'name' => 'Chloe',
            'note' => 'Thích ngồi quầy bar',
        ], $them));
    }

    public function test_khach_chi_co_hoa_don_van_luu_duoc_ghi_chu(): void
    {
        $this->hoaDon('HD001');

        $this->luu($this->quanTri())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.customers.show', self::SDT));

        $this->assertDatabaseHas('guest_notes', [
            'brand_id' => $this->brand->id,
            'phone' => self::SDT,
            'name' => 'Chloe',
            'note' => 'Thích ngồi quầy bar',
        ]);
    }

    public function test_khach_co_dat_ban_van_luu_duoc_nhu_cu(): void
    {
        Booking::create([
            'branch_id' => $this->branch->id,
            'code' => 'DB0001',
            'customer_name' => 'Khách thử',
            'customer_phone' => self::SDT,
            'booking_date' => '2026-09-01',
            'start_time' => '19:00',
            'end_time' => '21:00',
            'party_size' => 2,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        $this->luu($this->quanTri())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guest_notes', ['brand_id' => $this->brand->id, 'phone' => self::SDT]);
    }

    public function test_luu_duoc_ca_co_vip_va_chan_dat_online(): void
    {
        $this->hoaDon('HD002');

        $this->luu($this->quanTri(), ['is_vip' => '1', 'is_blocked' => '1'])
            ->assertSessionHasNoErrors();

        $ghiChu = GuestNote::where('phone', self::SDT)->firstOrFail();

        $this->assertTrue((bool) $ghiChu->is_vip);
        $this->assertTrue((bool) $ghiChu->is_blocked);
    }

    public function test_khach_khong_co_du_lieu_nao_thi_bao_loi_doc_duoc_chu_khong_vo_trang(): void
    {
        // Khong hoa don, khong dat ban - khong the biet khach thuoc quan nao.
        $this->luu($this->quanTri())
            ->assertRedirect()
            ->assertSessionHasErrors('note');

        $this->assertDatabaseCount('guest_notes', 0);
    }

    public function test_vai_chi_xem_khong_luu_duoc(): void
    {
        $this->hoaDon('HD003');

        $viewer = User::create([
            'name' => 'Chỉ xem', 'email' => 'xem@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::VIEWER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->luu($viewer)->assertForbidden();

        $this->assertDatabaseCount('guest_notes', 0);
    }
}
