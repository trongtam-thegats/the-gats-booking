<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;

class PasswordResetController extends AdminController
{
    /** Hien thi form nhap email de nhan link dat lai mat khau. */
    public function showForgotPassword()
    {
        return view('admin.auth.forgot-password');
    }

    /** Xu ly yeu cau gui link dat lai mat khau ve email. */
    public function sendResetLinkEmail(Request $request, PasswordResetService $service)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
        ]);

        $user = User::where('email', $data['email'])->where('is_active', true)->first();

        if ($user) {
            $service->guiEmailDatLaiMatKhau($user);
        }

        // Luon hien thong bao thanh cong du tim thay email hay khong (tranh lo thong tin nguoi dung).
        return back()->with(
            'status',
            'Nếu địa chỉ email tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu trong hộp thư đến.'
        );
    }

    /** Hien thi form thiet lap mat khau moi tu link email. */
    public function showResetForm(Request $request, string $token)
    {
        $email = (string) $request->query('email', '');

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /** Xac thuc va luu mat khau moi. */
    public function resetPassword(Request $request, PasswordResetService $service)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $thanhCong = $service->xacNhanDatLaiMatKhau($data['email'], $data['token'], $data['password']);

        if (! $thanhCong) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',
            ]);
        }

        return redirect()->route('admin.login')->with(
            'status',
            'Đặt lại mật khẩu thành công! Vui lòng đăng nhập với mật khẩu mới.'
        );
    }
}
