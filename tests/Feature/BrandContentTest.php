<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandContent;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sua chu tren trang dat ban tu khu quan tri.
 */
class BrandContentTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected User $admin;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu',
            'domain' => 'booking.quanthu.test', 'mark' => 'QT',
            'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $branch = $this->brand->branches()->create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu-cn',
            'open_time' => '17:00', 'close_time' => '23:30',
            'slot_minutes' => 30, 'turn_minutes' => 120,
            'min_lead_minutes' => 60, 'max_advance_days' => 30,
            'max_party_size' => 20, 'is_active' => true,
        ]);

        $branch->diningTables()->create([
            'code' => 'T1', 'table_type' => 'high_table',
            'seats_min' => 1, 'seats_max' => 4, 'combinable' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Quản trị', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::ADMIN, 'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Quản lý', 'email' => 'ql@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::MANAGER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);
    }

    public function test_trang_dung_chu_mac_dinh_khi_chua_sua(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Đặt bàn');
    }

    public function test_chu_da_sua_hien_tren_trang_khach(): void
    {
        $this->actingAs($this->manager)
            ->put(route('admin.content.update', $this->brand), [
                'locale' => 'vi',
                'texts' => [
                    'hero_title' => 'Giữ bàn tại Quán Thử',
                    'terms' => 'Bàn giữ trong 15 phút kể từ giờ hẹn.',
                    'submit_label' => 'Gửi yêu cầu',
                ],
            ])->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('Giữ bàn tại Quán Thử')
            ->assertSee('Bàn giữ trong 15 phút kể từ giờ hẹn.')
            ->assertSee('Gửi yêu cầu');
    }

    public function test_de_trong_thi_quay_ve_chu_mac_dinh(): void
    {
        BrandContent::create([
            'brand_id' => $this->brand->id,
            'key' => 'hero_title',
            'locale' => 'vi',
            'value' => 'Chữ cũ',
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.content.update', $this->brand), [
                'locale' => 'vi',
                'texts' => ['hero_title' => ''],
            ]);

        // Xoa han ban ghi de sau nay doi mac dinh thi trang tu cap nhat theo.
        $this->assertDatabaseMissing('brand_contents', [
            'brand_id' => $this->brand->id,
            'key' => 'hero_title',
            'locale' => 'vi',
        ]);

        $this->get('/')->assertOk()->assertSee('Đặt bàn')->assertDontSee('Chữ cũ');
    }

    public function test_loi_nhan_het_ban_lay_theo_chu_da_sua(): void
    {
        $this->actingAs($this->manager)
            ->put(route('admin.content.update', $this->brand), [
                'locale' => 'vi',
                'texts' => ['no_slots' => 'Hôm nay kín rồi, bạn gọi quán nhé.'],
            ]);

        $branch = $this->brand->branches()->first();

        // Chiem het ban duy nhat trong moi khung gio bang mot luot dat dai.
        $branch->update(['turn_minutes' => 480]);

        $this->post(route('booking.store', $branch), [
            'customer_name' => 'Khách', 'customer_phone' => '0912345678',
            'party_size' => 4,
            'booking_date' => \Illuminate\Support\Carbon::tomorrow()->toDateString(),
            'start_time' => '17:00',
        ]);

        $response = $this->getJson(route('booking.slots', $branch).'?'.http_build_query([
            'date' => \Illuminate\Support\Carbon::tomorrow()->toDateString(),
            'party_size' => 4,
        ]));

        $response->assertOk();
        $this->assertSame('Hôm nay kín rồi, bạn gọi quán nhé.', $response->json('message'));
    }

    public function test_vai_chi_xem_khong_sua_duoc_noi_dung(): void
    {
        $viewer = User::create([
            'name' => 'Người xem', 'email' => 'xem@thegats.vn', 'password' => 'matkhau123',
            'role' => Roles::VIEWER, 'brand_id' => $this->brand->id, 'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->put(route('admin.content.update', $this->brand), [
                'locale' => 'vi',
                'texts' => ['hero_title' => 'Không được phép'],
            ])->assertForbidden();

        $this->assertDatabaseCount('brand_contents', 0);
    }

    public function test_quan_ly_khong_sua_duoc_noi_dung_quan_khac(): void
    {
        $other = Brand::create([
            'name' => 'Quán Khác', 'slug' => 'quan-khac',
            'domain' => 'booking.quankhac.test', 'mark' => 'QK',
            'accent_color' => '#7fb59b', 'is_active' => true,
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.content.update', $other), [
                'locale' => 'vi',
                'texts' => ['hero_title' => 'Không được phép'],
            ])->assertForbidden();
    }

    public function test_quan_tri_mo_duoc_trang_noi_dung(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.content.index'))
            ->assertOk()
            ->assertSee('Nội dung trang đặt bàn');
    }

    public function test_ban_tieng_anh_va_tieng_viet_doc_lap_nhau(): void
    {
        $this->actingAs($this->manager)
            ->put(route('admin.content.update', $this->brand), [
                'locale' => 'vi',
                'texts' => ['hero_title' => 'Giữ bàn'],
            ]);

        $this->actingAs($this->manager)
            ->put(route('admin.content.update', $this->brand), [
                'locale' => 'en',
                'texts' => ['hero_title' => 'Reserve a table'],
            ]);

        $this->get('/?lang=vi')->assertOk()->assertSee('Giữ bàn')->assertDontSee('Reserve a table');
        $this->get('/?lang=en')->assertOk()->assertSee('Reserve a table')->assertDontSee('Giữ bàn');
    }

    /**
     * Chu tieng Viet KHONG duoc dung bu cho trang tieng Anh - hien tieng Viet
     * tren trang tieng Anh con te hon la hien cau mac dinh da dich.
     */
    public function test_thieu_ban_tieng_anh_thi_dung_cau_mac_dinh_chu_khong_lay_tieng_viet(): void
    {
        $this->actingAs($this->manager)
            ->put(route('admin.content.update', $this->brand), [
                'locale' => 'vi',
                'texts' => ['hero_title' => 'Giữ bàn ngay'],
            ]);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('Book a table')
            ->assertDontSee('Giữ bàn ngay');
    }
}
