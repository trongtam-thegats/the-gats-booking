<?php

namespace App\Http\Middleware;

use App\Support\SiteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gac cho khu quan ly: chi mo tren ten mien quan tri.
 *
 * Khach vao booking.gemination.vn go them /quan-ly thi khong thay gi ca -
 * ho duoc dua sang dung dia chi khu quan ly, khong lo bat ky du lieu nao.
 */
class EnsureAdminSite
{
    public function __construct(protected SiteResolver $site) {}

    public function handle(Request $request, Closure $next): Response
    {
        $adminDomain = $this->site->adminDomain();

        // Ten mien cua mot quan cu the thi khong phai cho quan ly.
        if ($this->site->brandForHost($request)) {
            // Co ten mien quan tri thi dua nhan vien sang dung dia chi.
            // Chua khai bao thi phai tra 404, neu khong se chuyen huong vong tron
            // giua /quan-ly va /quan-ly/dang-nhap tren chinh ten mien nay.
            abort_if(! $adminDomain, 404);

            return redirect($this->site->adminUrl($request->getRequestUri()));
        }

        // Chua khai bao ten mien quan tri thi chi cho chay tren may.
        if (! $adminDomain) {
            abort_unless(
                $this->site->isDevHost($request),
                404,
                'Chưa khai báo tên miền khu quản lý trong phần Cài đặt.'
            );

            return $next($request);
        }

        abort_unless(
            $this->site->isAdminHost($request) || $this->site->isDevHost($request),
            404
        );

        return $next($request);
    }
}
