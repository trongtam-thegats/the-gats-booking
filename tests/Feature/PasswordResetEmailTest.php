<?php

namespace Tests\Feature;

use App\Mail\DatLaiMatKhauMail;
use App\Models\Brand;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetEmailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $nhanVien;

    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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

        $this->nhanVien = User::create([
            'name' => 'Nhân viên B',
            'email' => 'nhanvienb@thegats.vn',
            'password' => 'matkhaucu123',
            'role' => Roles::MANAGER,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    public function test_quan_tri_dat_lai_mat_khau_se_gui_email_chua_link_cho_nhan_vien(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/quan-ly/tai-khoan/{$this->nhanVien->id}/dat-lai-mat-khau");

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Mail::assertSent(DatLaiMatKhauMail::class, function (DatLaiMatKhauMail $mail) {
            return $mail->hasTo('nhanvienb@thegats.vn')
                && str_contains($mail->resetUrl, '/quan-ly/dat-lai-mat-khau/')
                && str_contains($mail->resetUrl, 'email=nhanvienb%40thegats.vn');
        });
    }

    public function test_nhan_vien_yeu_cau_quen_mat_khau_tu_form_dang_nhap(): void
    {
        $this->get('/quan-ly/quen-mat-khau')->assertOk();

        $response = $this->post('/quan-ly/quen-mat-khau', [
            'email' => 'nhanvienb@thegats.vn',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Mail::assertSent(DatLaiMatKhauMail::class, function (DatLaiMatKhauMail $mail) {
            return $mail->hasTo('nhanvienb@thegats.vn');
        });
    }

    public function test_quen_mat_khau_voi_email_khong_ton_tai_khong_bao_loi_va_khong_gui_mail(): void
    {
        $response = $this->post('/quan-ly/quen-mat-khau', [
            'email' => 'khongtontai@thegats.vn',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Mail::assertNotSent(DatLaiMatKhauMail::class);
    }

    public function test_truy_cap_form_dat_lai_mat_khau_voi_token_hop_le(): void
    {
        $token = Password::broker()->createToken($this->nhanVien);

        $response = $this->get("/quan-ly/dat-lai-mat-khau/{$token}?email=".urlencode($this->nhanVien->email));

        $response->assertOk();
        $response->assertSee('Đặt lại mật khẩu');
        $response->assertSee($this->nhanVien->email);
    }

    public function test_dat_lai_mat_khau_thanh_cong_cap_nhat_mat_khau_va_cho_phep_dang_nhap(): void
    {
        $token = Password::broker()->createToken($this->nhanVien);

        $response = $this->post('/quan-ly/dat-lai-mat-khau', [
            'token' => $token,
            'email' => $this->nhanVien->email,
            'password' => 'matkhaumoi2026',
            'password_confirmation' => 'matkhaumoi2026',
        ]);

        $response->assertRedirect('/quan-ly/dang-nhap');
        $response->assertSessionHas('status');

        $this->nhanVien->refresh();

        $this->assertTrue(Hash::check('matkhaumoi2026', $this->nhanVien->password));
        $this->assertFalse($this->nhanVien->must_change_password);
        $this->assertNotNull($this->nhanVien->password_changed_at);

        // Dang nhap duoc bang mat khau moi
        $loginRes = $this->post('/quan-ly/dang-nhap', [
            'email' => $this->nhanVien->email,
            'password' => 'matkhaumoi2026',
        ]);

        $loginRes->assertRedirect('/quan-ly');
        $this->assertAuthenticatedAs($this->nhanVien);
    }

    public function test_dat_lai_mat_khau_that_bai_khi_token_sai(): void
    {
        $response = $this->post('/quan-ly/dat-lai-mat-khau', [
            'token' => 'tokensaihoantoan',
            'email' => $this->nhanVien->email,
            'password' => 'matkhaumoi2026',
            'password_confirmation' => 'matkhaumoi2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');

        $this->nhanVien->refresh();
        $this->assertFalse(Hash::check('matkhaumoi2026', $this->nhanVien->password));
    }

    public function test_tao_tai_khoan_moi_gui_email_thiet_lap_mat_khau(): void
    {
        $this->actingAs($this->admin)
            ->post('/quan-ly/tai-khoan', [
                'name' => 'Nhân viên Mới',
                'email' => 'nhanvienmoi@thegats.vn',
                'role' => Roles::MANAGER,
                'brand_id' => $this->brand->id,
            ])
            ->assertRedirect();

        Mail::assertSent(DatLaiMatKhauMail::class, function (DatLaiMatKhauMail $mail) {
            return $mail->hasTo('nhanvienmoi@thegats.vn')
                && $mail->laTaiKhoanMoi === true;
        });
    }
}
