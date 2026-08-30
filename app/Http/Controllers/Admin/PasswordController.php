<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Doi mat khau cua chinh minh. Vao duoc tu menu, va la trang duy nhat mo ra khi
 * tai khoan van con dung mat khau khoi tao.
 */
class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.password', [
            'batBuoc' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
                // Mat khau moi ma van bang email thi coi nhu chua doi.
                Rule::notIn([$user->email, mb_strtolower($user->email)]),
            ],
        ], [
            'current_password.required' => 'Nhập mật khẩu hiện tại.',
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.required' => 'Nhập mật khẩu mới.',
            'password.confirmed' => 'Hai ô mật khẩu mới chưa khớp nhau.',
            'password.not_in' => 'Mật khẩu mới không được trùng với email.',
        ], [
            'current_password' => 'mật khẩu hiện tại',
            'password' => 'mật khẩu mới',
        ]);

        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Mật khẩu mới phải khác mật khẩu đang dùng.']);
        }

        $user->update([
            'password' => $request->input('password'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        // Doi mat khau xong thi cac phien dang nhap khac tren may khac het hieu luc.
        $request->session()->regenerate();

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Đã đổi mật khẩu.');
    }
}
