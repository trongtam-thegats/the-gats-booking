<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chon ngon ngu cho trang khach.
 *
 * Thu tu uu tien: tham so ?lang tren dia chi > lua chon da luu trong phien >
 * ngon ngu trinh duyet khach dang dung > tieng Viet.
 */
class SetGuestLocale
{
    public const SESSION_KEY = 'guest_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);
        $request->session()->put(self::SESSION_KEY, $locale);

        return $next($request);
    }

    protected function resolve(Request $request): string
    {
        $requested = $request->query('lang');

        if (Locales::supported($requested)) {
            return $requested;
        }

        $saved = $request->session()->get(self::SESSION_KEY);

        if (Locales::supported($saved)) {
            return $saved;
        }

        // Khach nuoc ngoai vao lan dau thi mo thang ban tieng Anh.
        $preferred = $request->getPreferredLanguage(Locales::codes());

        return Locales::supported($preferred) ? $preferred : Locales::DEFAULT;
    }
}
