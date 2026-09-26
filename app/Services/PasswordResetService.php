<?php

namespace App\Services;

use App\Mail\DatLaiMatKhauMail;
use App\Models\User;
use App\Support\SiteResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetService
{
    public function __construct(
        protected SiteResolver $site
    ) {}

    /**
     * Tao token bao mat va gui email dat lai mat khau cho nhan vien.
     */
    public function guiEmailDatLaiMatKhau(User $user, bool $laTaiKhoanMoi = false): bool
    {
        try {
            $token = Password::broker()->createToken($user);
            $resetUrl = $this->taoUrlDatLaiMatKhau($user->email, $token);

            Mail::to($user->email)->send(new DatLaiMatKhauMail($user, $resetUrl, $laTaiKhoanMoi));

            Log::info('Da gui email dat lai mat khau thanh cong', [
                'user_id' => $user->id,
                'email' => $user->email,
                'la_tai_khoan_moi' => $laTaiKhoanMoi,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Khong the gui email dat lai mat khau qua SMTP', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Xac thuc token va cap nhat mat khau moi cho nguoi dung.
     */
    public function xacNhanDatLaiMatKhau(string $email, string $token, string $password): bool
    {
        $user = User::where('email', $email)->where('is_active', true)->first();

        if (! $user) {
            return false;
        }

        if (! Password::broker()->tokenExists($user, $token)) {
            return false;
        }

        $user->update([
            'password' => $password,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        Password::broker()->deleteToken($user);

        Log::info('Nguoi dung da doi mat khau thanh cong qua link email', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return true;
    }

    /**
     * Tao duong dan day du cho man hinh dat lai mat khau tren khu quan ly.
     */
    public function taoUrlDatLaiMatKhau(string $email, string $token): string
    {
        $path = '/quan-ly/dat-lai-mat-khau/'.$token.'?email='.urlencode($email);

        $adminUrl = $this->site->adminUrl($path);

        return $adminUrl ?: url($path);
    }
}
