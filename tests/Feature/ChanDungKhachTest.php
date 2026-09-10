<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CustomerInsightService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chi tiet mot khach chi con MOT trang duy nhat.
 *
 * Truoc day co hai trang gan het giong nhau: Tra cuu khach (chi doc dat ban)
 * va Phan tich khach hang (chi doc hoa don). Nay go so o o tim la chuyen thang
 * sang trang chi tiet, va trang do co ca hai nguon.
 *
 * Vai "chi xem" cung vao duoc trang nay - le tan can biet khach hay ngoi ban
 * nao, uong gi - nhung con so tien thi giau di.
 */
class ChanDungKhachTest extends TestCase
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

    /**
     * Vai khong phai quan tri phai gan vao mot quan, neu khong
     * visibleBranchIds() tra ve mang rong va ho khong thay du lieu nao.
     */
    protected function nguoiDung(string $vai): User
    {
        return User::create([
            'name' => 'Người '.$vai,
            'email' => $vai.'@thegats.vn',
            'password' => 'matkhau123',
            'role' => $vai,
            'brand_id' => $vai === Roles::ADMIN ? null : $this->brand->id,
            'is_active' => true,
        ]);
    }

    protected function hoaDon(string $ma, string $sdt = self::SDT, int $tien = 800_000, int $soNguoi = 2): Invoice
    {
        return Invoice::create([
            'branch_id' => $this->branch->id,
            'code' => $ma,
            'status' => 'Đã thanh toán',
            'ordered_at' => '2026-09-01 20:00:00',
            'paid_at' => '2026-09-01 22:00:00',
            'total' => $tien,
            'party_size' => $soNguoi,
            'payment_method' => 'Tiền mặt',
            'area' => 'Quầy bar',
            'table_code' => 'Bar 5',
            'customer_phone' => $sdt,
            'customer_name' => 'Khách thử',
        ]);
    }

    /**
     * Di dung duong nguoi dung di: go so o Tra cuu khach, roi de he thong
     * chuyen sang trang chi tiet khach (chi con MOT trang chi tiet duy nhat).
     */
    protected function tra(User $u, string $sdt = self::SDT)
    {
        return $this->actingAs($u)
            ->followingRedirects()
            ->get(route('admin.guests.index', ['phone' => $sdt]));
    }

    public function test_quan_ly_tra_so_dien_thoai_thay_ca_hanh_vi_lan_so_tien(): void
    {
        $this->hoaDon('A1');
        $this->hoaDon('A2');

        $this->tra($this->nguoiDung(Roles::MANAGER))
            ->assertOk()
            ->assertSee('Thói quen')
            ->assertSee('Bar 5')            // bàn hay ngồi
            ->assertSee('2 – 3 giờ')        // khoảng ngồi: đúng 120 phút rơi vào đây
            ->assertSee('2 người')          // hay đi 2 người
            ->assertSee('Tổng chi tiêu')
            ->assertSee('800,000');         // chi trung bình
    }

    public function test_vai_chi_xem_thay_hanh_vi_nhung_khong_thay_so_tien(): void
    {
        $this->hoaDon('B1');
        $this->hoaDon('B2');

        $phanHoi = $this->tra($this->nguoiDung(Roles::VIEWER))->assertOk();

        // Van thay duoc thoi quen - do la thu le tan can khi don khach.
        $phanHoi->assertSee('Thói quen')
            ->assertSee('Bar 5')
            ->assertSee('2 người')
            ->assertSee('Số lần đã ghé');

        // Nhung khong thay tien, va khong thay ca danh sach hoa don.
        $phanHoi->assertDontSee('800,000')
            ->assertDontSee('Tổng chi tiêu')
            ->assertDontSee('Trung bình mỗi lần');
    }

    public function test_khach_chua_co_hoa_don_thi_bao_ro_chu_khong_vo_trang(): void
    {
        Booking::create([
            'branch_id' => $this->branch->id,
            'code' => 'DB0001',
            'customer_name' => 'Khách mới',
            'customer_phone' => self::SDT,
            'booking_date' => '2026-09-01',
            'start_time' => '19:00',
            'end_time' => '21:00',
            'party_size' => 2,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        $this->tra($this->nguoiDung(Roles::MANAGER))
            ->assertOk()
            ->assertSee('Khách mới')
            ->assertSee('chưa khớp được hóa đơn nào');
    }

    public function test_so_dien_thoai_go_kieu_khac_van_ghep_duoc_hoa_don(): void
    {
        $this->hoaDon('C1');

        // Le tan go co dau cach va dau cong; ca hai nguon deu di qua
        // SoDienThoai::chuan() nen van phai ra dung khach.
        $this->tra($this->nguoiDung(Roles::MANAGER), '090 000 0001')
            ->assertOk()
            ->assertSee('Bar 5');
    }

    public function test_hoa_don_cua_khach_khac_khong_lan_sang(): void
    {
        $this->hoaDon('D1', self::SDT, 800_000);
        $this->hoaDon('D2', '0900000002', 5_000_000);

        $this->tra($this->nguoiDung(Roles::MANAGER))
            ->assertOk()
            ->assertSee('800,000')
            ->assertDontSee('5,000,000');
    }

    public function test_hoa_don_da_huy_khong_tinh_vao_tong_chi_tieu(): void
    {
        $this->hoaDon('E1');
        $this->hoaDon('E2')->update(['status' => Invoice::HUY, 'total' => 9_000_000]);

        // Trang VAN liet ke hoa don da huy (kem nhan "Da huy") - dung nhu vay,
        // nhan vien can thay. Nhung no khong duoc cong vao chi so nao.
        $this->tra($this->nguoiDung(Roles::MANAGER))
            ->assertOk()
            ->assertSee('Đã hủy');

        $co = app(CustomerInsightService::class)
            ->profile(self::SDT, null)['stats'];

        $this->assertSame(1, $co['visits']);
        $this->assertEqualsWithDelta(800_000, $co['spend'], 0.01);
    }
}
