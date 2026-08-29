<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Support\SiteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ten mien khu quan tri phai doc qua config, khong goi env() thang.
 *
 * Tren may that co chay `php artisan config:cache`, moi loi goi env() ngoai
 * thu muc config/ deu tra ve null — khu quan tri se 404 tren dung ten mien
 * cua no. Loi nay khong the hien tren may lap trinh vi o do khong cache.
 */
class AdminDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_lay_ten_mien_quan_tri_tu_config(): void
    {
        config(['booking.admin_domain' => 'booking.thegats.vn']);

        $this->assertSame('booking.thegats.vn', (new SiteResolver)->adminDomain());
    }

    public function test_cai_dat_trong_csdl_duoc_uu_tien_hon_config(): void
    {
        config(['booking.admin_domain' => 'booking.thegats.vn']);
        \App\Models\Setting::putMany(['admin_domain' => 'quanly.thegats.vn']);

        $this->assertSame('quanly.thegats.vn', (new SiteResolver)->adminDomain());
    }

    public function test_ma_nguon_khong_duoc_goi_env_ngoai_thu_muc_config(): void
    {
        $loi = [];

        foreach (['app', 'routes', 'database', 'bootstrap'] as $thuMuc) {
            $duyet = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($thuMuc))
            );

            foreach ($duyet as $tep) {
                if ($tep->isFile() && $tep->getExtension() === 'php'
                    && preg_match('/\benv\s*\(/', (string) file_get_contents($tep->getPathname()))) {
                    $loi[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $tep->getPathname());
                }
            }
        }

        $this->assertSame([], $loi,
            'Các tệp sau gọi env() ngoài config/ — sẽ trả về null khi máy thật cache cấu hình: '
            .implode(', ', $loi));
    }

    public function test_ten_mien_quan_tri_mo_duoc_trang_dang_nhap(): void
    {
        config(['booking.admin_domain' => 'booking.thegats.vn']);

        Brand::create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu', 'domain' => 'booking.quanthu.test',
            'mark' => 'QT', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->get('http://booking.thegats.vn/quan-ly/dang-nhap')->assertOk();
    }

    public function test_ten_mien_cua_quan_khong_vao_duoc_khu_quan_tri(): void
    {
        config(['booking.admin_domain' => 'booking.thegats.vn']);

        Brand::create([
            'name' => 'Quán Thử', 'slug' => 'quan-thu', 'domain' => 'booking.quanthu.test',
            'mark' => 'QT', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->get('http://booking.quanthu.test/quan-ly')->assertRedirect();
    }
}
