<?php

use App\Http\Middleware\EnsureAdminSite;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ResolveBrandSite;
use App\Http\Middleware\SetGuestLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'brand.site' => ResolveBrandSite::class,
            'guest.locale' => SetGuestLocale::class,
            'admin.site' => EnsureAdminSite::class,
        ]);

        // Kiem tra ten mien phai chay TRUOC khi kiem tra dang nhap. Neu khong,
        // khach vao booking.gemination.vn/quan-ly se bi day sang trang dang nhap
        // ngay tren ten mien cua quan thay vi duoc dua sang khu quan ly.
        // Laravel xep uu tien cho middleware dang nhap qua interface nay,
        // khong phai qua ten class Authenticate.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureAdminSite::class);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
