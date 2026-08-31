<?php

namespace App\Http\Middleware;

use App\Support\NguonDatBan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nho khach den tu kenh nao, doc tu tham so ?nguon= tren duong dan.
 *
 * Phai luu vao phien chu khong doc lai luc gui form: khach bam link tu
 * Instagram, vao trang, doi ngon ngu hoac doi dia diem mot cai la tham so bay
 * khoi duong dan. Phien giu duoc suot lan ghe tham.
 *
 * Ghi de moi lan co tham so moi: khach quay lai qua Facebook sau khi tung vao
 * qua Instagram thi lan dat nay tinh cho Facebook.
 */
class GhiNhoNguonKhach
{
    public function handle(Request $request, Closure $next): Response
    {
        $nguon = NguonDatBan::chuan($request->query(NguonDatBan::THAM_SO));

        if ($nguon) {
            $request->session()->put(NguonDatBan::KHOA_PHIEN, $nguon);
        }

        return $next($request);
    }
}
