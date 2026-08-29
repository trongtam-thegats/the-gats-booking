<?php

namespace App\Providers;

use App\Support\SettingsApplier;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
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

        // Cau hinh sua tren trang Cai dat ghi de .env.
        (new SettingsApplier)->apply();
    }
}
