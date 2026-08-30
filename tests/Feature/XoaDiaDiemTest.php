<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\GuestNote;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chot chan khi xoa dia diem va quan.
 *
 * Xoa dia diem keo theo khu vuc, ban va lich su dat ban; hoa don thi mat lien
 * ket nen bien mat khoi moi trang. Vi vay con du lieu thi phai chan lai.
 */
class XoaDiaDiemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán A', 'slug' => 'quan-a', 'domain' => 'booking.quan-a.test',
            'mark' => 'QA', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Giám đốc', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);
    }

    protected function diaDiem(string $slug = 'quan-a'): Branch
    {
        return $this->brand->branches()->create([
            'name' => 'Địa điểm '.$slug, 'slug' => $slug,
            'open_time' => '17:00', 'close_time' => '00:00',
            'slot_minutes' => 30, 'turn_minutes' => 120, 'min_lead_minutes' => 60,
            'max_advance_days' => 30, 'max_party_size' => 20, 'is_active' => true,
        ]);
    }

    public function test_dia_diem_trong_thi_xoa_duoc(): void
    {
        $branch = $this->diaDiem();

        $this->actingAs($this->admin)
            ->delete('/quan-ly/chi-nhanh/'.$branch->slug)
            ->assertRedirect();

        $this->assertModelMissing($branch);
    }

    public function test_dia_diem_con_hoa_don_thi_khong_xoa_duoc(): void
    {
        $branch = $this->diaDiem();

        // Chua co don dat ban nao, chi co hoa don - truong hop tung lot luoi.
        Invoice::create([
            'branch_id' => $branch->id, 'code' => 'HD1', 'status' => 'Đã thanh toán',
            'paid_at' => now(), 'total' => 500000, 'customer_phone' => '0900000001',
        ]);

        $this->actingAs($this->admin)
            ->delete('/quan-ly/chi-nhanh/'.$branch->slug)
            ->assertSessionHasErrors('branch');

        $this->assertModelExists($branch);
        $this->assertSame(1, Invoice::where('branch_id', $branch->id)->count());
    }

    public function test_quan_con_ghi_chu_khach_thi_khong_xoa_duoc(): void
    {
        // Ghi chu khach bi xoa theo quan, keo theo ca danh dau "da xem xet".
        GuestNote::create([
            'brand_id' => $this->brand->id, 'phone' => '0900000002',
            'reviewed_at' => now(), 'review_outcome' => 'da_roi_bo',
        ]);

        $this->actingAs($this->admin)
            ->delete('/quan-ly/quan/'.$this->brand->slug)
            ->assertSessionHasErrors('brand');

        $this->assertModelExists($this->brand);
    }

    public function test_chi_quan_tri_moi_duoc_xoa_dia_diem(): void
    {
        $branch = $this->diaDiem();

        $quanLy = User::create([
            'name' => 'Quản lý', 'email' => 'ql@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->actingAs($quanLy)
            ->delete('/quan-ly/chi-nhanh/'.$branch->slug)
            ->assertForbidden();

        $this->assertModelExists($branch);
    }
}
