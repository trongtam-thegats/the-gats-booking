<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tai khoan tao ra co mat khau bang chinh email va bi buoc doi ngay lan dau.
 */
class DoiMatKhauTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán A',
            'slug' => 'quan-a',
            'domain' => 'booking.quan-a.test',
            'mark' => 'QA',
            'accent_color' => '#c8a15a',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Giám đốc',
            'email' => 'admin@thegats.vn',
            'password' => 'matkhau123',
            'role' => Roles::ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_tai_khoan_moi_co_mat_khau_bang_email_va_bi_bat_doi(): void
    {
        $this->actingAs($this->admin)
            ->post('/quan-ly/tai-khoan', [
                'name' => 'Quản lý A',
                'email' => 'quanlya@thegats.vn',
                'role' => Roles::MANAGER,
                'brand_id' => $this->brand->id,
            ])
            ->assertRedirect();

        $user = User::where('email', 'quanlya@thegats.vn')->firstOrFail();

        $this->assertTrue(Hash::check('quanlya@thegats.vn', $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_dang_nhap_bang_email_lam_mat_khau_thi_bi_day_ve_trang_doi_mat_khau(): void
    {
        $user = $this->taoNguoiDungMoi();

        $this->post('/quan-ly/dang-nhap', [
            'email' => $user->email,
            'password' => $user->email,
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);

        // Moi trang khac deu bi day ve trang doi mat khau.
        $this->get('/quan-ly')->assertRedirect('/quan-ly/doi-mat-khau');
        $this->get('/quan-ly/dat-ban')->assertRedirect('/quan-ly/doi-mat-khau');
        $this->get('/quan-ly/doi-mat-khau')->assertOk();
    }

    public function test_doi_xong_mat_khau_thi_vao_duoc_khu_quan_ly(): void
    {
        $user = $this->taoNguoiDungMoi();

        $this->actingAs($user)
            ->post('/quan-ly/doi-mat-khau', [
                'current_password' => $user->email,
                'password' => 'matkhaurieng2026',
                'password_confirmation' => 'matkhaurieng2026',
            ])
            ->assertRedirect('/quan-ly');

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('matkhaurieng2026', $user->password));

        $this->actingAs($user)->get('/quan-ly')->assertOk();
    }

    public function test_khong_duoc_dat_mat_khau_moi_trung_email(): void
    {
        $user = $this->taoNguoiDungMoi();

        $this->actingAs($user)
            ->post('/quan-ly/doi-mat-khau', [
                'current_password' => $user->email,
                'password' => $user->email,
                'password_confirmation' => $user->email,
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_quan_tri_dat_lai_mat_khau_ve_email(): void
    {
        $user = $this->taoNguoiDungMoi();
        $user->update(['password' => 'matkhaurieng2026', 'must_change_password' => false]);

        $this->actingAs($this->admin)
            ->post('/quan-ly/tai-khoan/'.$user->id.'/dat-lai-mat-khau')
            ->assertRedirect();

        $user->refresh();

        $this->assertTrue(Hash::check($user->email, $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_van_dang_xuat_duoc_khi_dang_bi_bat_doi_mat_khau(): void
    {
        $user = $this->taoNguoiDungMoi();

        $this->actingAs($user)
            ->post('/quan-ly/dang-xuat')
            ->assertRedirect('/quan-ly/dang-nhap');

        $this->assertGuest();
    }

    protected function taoNguoiDungMoi(): User
    {
        return User::create([
            'name' => 'Quản lý A',
            'email' => 'quanlya@thegats.vn',
            'password' => 'quanlya@thegats.vn',
            'role' => Roles::MANAGER,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'must_change_password' => true,
        ]);
    }
}
