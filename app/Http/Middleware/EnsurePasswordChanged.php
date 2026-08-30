<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tai khoan con dung mat khau khoi tao thi khong di dau duoc trong khu quan ly
 * ngoai trang doi mat khau.
 *
 * Mat khau khoi tao chinh la email, nguoi tao tai khoan cung biet, nen de nguyen
 * la ai cung dang nhap ho duoc.
 */
class EnsurePasswordChanged
{
    /** Cac trang van vao duoc khi dang bi buoc doi mat khau. */
    protected const DUOC_PHEP = [
        'admin.password.edit',
        'admin.password.update',
        'admin.logout',
        'admin.login',
        'admin.login.submit',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::DUOC_PHEP, true)) {
            return $next($request);
        }

        return redirect()->route('admin.password.edit');
    }
}
