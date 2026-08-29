<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Trang dang nhap mo cong khai tren internet, nen phai chan do mat khau.
 * Khong co gioi han thi ai cung thu duoc vo han lan.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('dang-nhap|admin@thegats.vn|127.0.0.1');

        User::create([
            'name' => 'Quản trị',
            'email' => 'admin@thegats.vn',
            'password' => 'MatKhauDung!2026',
            'role' => Roles::ADMIN,
            'is_active' => true,
        ]);
    }

    protected function saiMatKhau()
    {
        return $this->post(route('admin.login.submit'), [
            'email' => 'admin@thegats.vn',
            'password' => 'sai-be-bet',
        ]);
    }

    public function test_sai_qua_nam_lan_thi_bi_khoa_tam(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->saiMatKhau()->assertSessionHasErrors('email');
        }

        $this->saiMatKhau()->assertSessionHasErrorsIn('default', ['email']);

        $loi = session('errors')->get('email')[0];

        $this->assertStringContainsString('quá nhiều lần', $loi);
    }

    /**
     * Diem de sai nhat: khoa roi ma van cho dang nhap bang mat khau dung
     * thi coi nhu khong khoa gi ca — ke do van thu tiep duoc.
     */
    public function test_dang_khoa_thi_mat_khau_dung_cung_khong_vao_duoc(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->saiMatKhau();
        }

        $this->post(route('admin.login.submit'), [
            'email' => 'admin@thegats.vn',
            'password' => 'MatKhauDung!2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dang_nhap_dung_thi_xoa_bo_dem(): void
    {
        $this->saiMatKhau();
        $this->saiMatKhau();

        $this->post(route('admin.login.submit'), [
            'email' => 'admin@thegats.vn',
            'password' => 'MatKhauDung!2026',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame(0, RateLimiter::attempts('dang-nhap|admin@thegats.vn|127.0.0.1'));
    }

    /**
     * Dem theo ca email lan IP: neu chi dem theo email thi nguoi ngoai
     * co the co tinh go sai de khoa chinh chu ra ngoai.
     */
    public function test_khoa_email_nay_khong_lam_ket_email_khac(): void
    {
        User::create([
            'name' => 'Quản lý', 'email' => 'quanly@thegats.vn', 'password' => 'MatKhauKhac!2026',
            'role' => Roles::MANAGER, 'is_active' => true,
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->saiMatKhau();
        }

        $this->post(route('admin.login.submit'), [
            'email' => 'quanly@thegats.vn',
            'password' => 'MatKhauKhac!2026',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }
}
