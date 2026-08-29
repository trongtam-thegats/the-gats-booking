<?php

namespace App\Http\Middleware;

use App\Support\SiteResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gac cho trang khach: xac dinh quan tu ten mien va chia se cho moi view.
 *
 * Vao bang ten mien cua khu quan ly thi day thang ve /quan-ly, vi ten mien do
 * khong phai cho khach dat ban.
 */
class ResolveBrandSite
{
    public function __construct(protected SiteResolver $site) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->site->isAdminHost($request)) {
            return redirect('/quan-ly');
        }

        $brand = $this->site->brandForRequest($request);

        abort_if(! $brand, 404, 'Tên miền này chưa được gắn với quán nào.');
        abort_unless($brand->is_active, 503, 'Quán đang tạm ngưng nhận đặt bàn trực tuyến.');

        // Dung chung cho controller va view, khong phai truyen tay tung cho.
        $request->attributes->set('brand', $brand);
        app()->instance('current_brand', $brand);
        View::share('brand', $brand);

        return $next($request);
    }
}
