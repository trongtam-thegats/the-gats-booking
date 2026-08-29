<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /** Cho sai bao nhieu lan truoc khi khoa tam. */
    protected const SO_LAN_TOI_DA = 5;

    /** Khoa trong bao lau, tinh bang giay. */
    protected const KHOA_GIAY = 900;

    /**
     * Khoa dem theo cap (email + dia chi IP).
     *
     * Khong dem theo mot minh email: nguoi ngoai chi can thu sai lien tuc mot
     * dia chi la khoa duoc chinh chu ra ngoai. Cung khong dem theo mot minh IP:
     * ca quan dung chung mot duong mang thi mot nguoi go sai se khoa het.
     */
    protected function khoaDem(Request $request): string
    {
        return 'dang-nhap|'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $khoa = $this->khoaDem($request);

        if (RateLimiter::tooManyAttempts($khoa, self::SO_LAN_TOI_DA)) {
            $con = RateLimiter::availableIn($khoa);

            throw ValidationException::withMessages([
                'email' => 'Sai quá nhiều lần. Thử lại sau '
                    .($con >= 60 ? ceil($con / 60).' phút.' : $con.' giây.'),
            ]);
        }

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($khoa, self::KHOA_GIAY);

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        RateLimiter::clear($khoa);

        if (! $request->user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Tài khoản đã bị khóa. Liên hệ quản trị viên.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
