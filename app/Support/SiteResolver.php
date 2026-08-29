<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Quyet dinh mot request thuoc ve trang nao, dua vao ten mien.
 *
 *   booking.gemination.vn        -> trang dat ban cua Gemination, khong vao duoc khu quan ly
 *   booking.drinkinghealing.com  -> trang dat ban cua Drinking Healing
 *   booking.thegats.vn           -> khu quan ly, nhin duoc ca chuoi
 *
 * Khi chay tren may (localhost) thi khong co ten mien that, nen mo ca hai
 * de con thu duoc; chon quan bang tham so ?brand=slug.
 */
class SiteResolver
{
    /** Cac host duoc coi la moi truong chay thu. */
    public const DEV_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]', '0.0.0.0'];

    /** Chuan hoa host: bo cong, bo www, ve chu thuong. */
    public function host(Request $request): string
    {
        $host = strtolower($request->getHost());

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** Ten mien cua khu quan ly, khai bao o trang Cai dat hoac trong .env. */
    public function adminDomain(): ?string
    {
        $domain = Setting::get('admin_domain', config('booking.admin_domain'));

        return $domain ? strtolower(trim($domain)) : null;
    }

    public function isDevHost(Request $request): bool
    {
        $host = $this->host($request);

        return in_array($host, self::DEV_HOSTS, true) || str_ends_with($host, '.localhost');
    }

    public function isAdminHost(Request $request): bool
    {
        $adminDomain = $this->adminDomain();

        return $adminDomain !== null && $this->host($request) === $adminDomain;
    }

    /** Quan gan voi ten mien dang truy cap, null neu khong khop cai nao. */
    public function brandForHost(Request $request): ?Brand
    {
        return Brand::where('domain', $this->host($request))->first();
    }

    /**
     * Quan ap dung cho trang khach.
     *
     * Uu tien ten mien. Tren may chay thu thi cho phep chon bang ?brand=slug,
     * khong co thi lay quan mac dinh.
     */
    public function brandForRequest(Request $request): ?Brand
    {
        if ($brand = $this->brandForHost($request)) {
            return $brand;
        }

        if (! $this->isDevHost($request)) {
            return null;
        }

        if ($slug = $request->query('brand')) {
            if ($brand = Brand::where('slug', $slug)->first()) {
                return $brand;
            }
        }

        return $this->defaultBrand();
    }

    public function defaultBrand(): ?Brand
    {
        return Brand::where('is_default', true)->first()
            ?? Brand::orderBy('sort_order')->orderBy('id')->first();
    }

    /**
     * Dia chi day du cua khu quan ly, de chuyen huong nhan vien vao dung cho.
     * Tra ve null khi chua khai bao ten mien quan tri - noi goi phai tu xu ly,
     * tuyet doi khong tra ve duong dan tuong doi (se chuyen huong vong tron).
     */
    public function adminUrl(string $path = '/quan-ly'): ?string
    {
        $domain = $this->adminDomain();

        return $domain ? 'https://'.$domain.$path : null;
    }
}
