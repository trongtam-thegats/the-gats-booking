<?php

namespace App\Providers;

use App\Support\SettingsApplier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Phan trang dung giao dien rieng thay vi ban Tailwind mac dinh
        // (du an nay khong co buoc build front-end).
        Paginator::defaultView('pagination::thegats');

        Carbon::setLocale('vi');

        $this->gioiHanGuiDatBan();

        // Cau hinh sua tren trang Cai dat ghi de .env.
        (new SettingsApplier)->apply();
    }

    /**
     * Tran tan suat gui form dat ban.
     *
     * Day chi la luoi thu hai - viec chan don trung nam o BookingService. Muc
     * dich o day la chan bam lien tuc va bot spam, nen nguong de rong: ca mot
     * quan dong khach chung mot wifi van dat duoi nguong nay.
     *
     * Dem theo IP + so dien thoai chu khong rieng IP, de vai nguoi ngoi cung
     * mot quan cafe khong khoa nhau.
     */
    protected function gioiHanGuiDatBan(): void
    {
        RateLimiter::for('dat-ban', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip().'|'.$request->input('customer_phone'))
            ->response(fn () => back()
                ->withInput()
                ->withErrors(['start_time' => __('booking.errors.too_many')])));
    }
}
