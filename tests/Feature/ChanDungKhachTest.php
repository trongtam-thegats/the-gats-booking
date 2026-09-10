<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang Tra cuu khach phai ghep duoc ca hai nguon: dat ban va hoa don POS.
 *
 * Truoc day trang nay chi doc dat ban, le tan phai nho sang trang Phan tich
 * khach hang moi biet khach chi bao nhieu hay hay ngoi ban nao.
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

    protected function tra(User $u, string $sdt = self::SDT)
    {
        return $this->actingAs($u)->get(route('admin.guests.index', ['phone' => $sdt]));
    }

    public function test_quan_ly_tra_so_dien_thoai_thay_ca_hanh_vi_lan_so_tien(): void
    {
        $this->hoaDon('A1');
        $this->hoaDon('A2');

        $this->tra($this->nguoiDung(Roles::MANAGER))
            ->assertOk()
            ->assertSee('Chân dung từ hóa đơn')
            ->assertSee('Bar 5')            // bàn hay ngồi
            ->assertSee('2 – 3 giờ')        // khoảng ngồi: đúng 120 phút rơi vào đây
            ->assertSee('2 người')          // hay đi 2 người
            ->assertSee('800,000');         // chi trung bình
    }

    public function test_vai_chi_xem_thay_hanh_vi_nhung_khong_thay_so_tien(): void
    {
        $this->hoaDon('B1');
        $this->hoaDon('B2');

        $phanHoi = $this->tra($this->nguoiDung(Roles::VIEWER))->assertOk();

        // Van thay duoc thoi quen - do la thu le tan can khi don khach.
        $phanHoi->assertSee('Chân dung từ hóa đơn')
            ->assertSee('Bar 5')
            ->assertSee('2 người');

        // Nhung khong thay tien, va khong co loi moi sang trang phan tich.
        $phanHoi->assertDontSee('800,000')
            ->assertDontSee('Chi trung bình')
            ->assertDontSee('Xem hồ sơ phân tích đầy đủ');
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
            ->assertDontSee('Chân dung từ hóa đơn')
            ->assertSee('Chưa có hóa đơn nào khớp số điện thoại này');
    }

    public function test_so_dien_thoai_go_kieu_khac_van_ghep_duoc_hoa_don(): void
    {
        $this->hoaDon('C1');

        // Le tan go co dau cach va dau cong; ca hai nguon deu di qua
        // SoDienThoai::chuan() nen van phai ra dung khach.
        $this->tra($this->nguoiDung(Roles::MANAGER), '090 000 0001')
            ->assertOk()
            ->assertSee('Chân dung từ hóa đơn');
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

    public function test_hoa_don_da_huy_khong_tinh_vao_chan_dung(): void
    {
        $this->hoaDon('E1');
        $this->hoaDon('E2')->update(['status' => Invoice::HUY, 'total' => 9_000_000]);

        $this->tra($this->nguoiDung(Roles::MANAGER))
            ->assertOk()
            ->assertSee('800,000')
            ->assertDontSee('9,000,000');
    }
}
