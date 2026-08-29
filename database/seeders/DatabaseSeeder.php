<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Du lieu khoi tao: hai quan cua chuoi, tai khoan quan tri, va so do cho ngoi
 * that cua tung quan (xem GeminationSeeder / DrinkingHealingSeeder).
 *
 * Moi quan mot ten mien rieng; khu quan ly nam o mot ten mien khac va nhin
 * duoc ca hai.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'trongtam@thegats.vn'],
            [
                'name' => 'Trọng Tâm',
                'password' => 'ThayMatKhauNgay!2026',
                'role' => Roles::ADMIN,
                'is_active' => true,
            ]
        );

        $gemination = Brand::firstOrCreate(
            ['slug' => 'gemination'],
            [
                'name' => 'Gemination',
                'domain' => 'booking.gemination.vn',
                'tagline' => 'Không gian cocktail',
                'mark' => 'GE',
                'accent_color' => '#c8a15a',
                'sort_order' => 1,
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $drinkingHealing = Brand::firstOrCreate(
            ['slug' => 'drinking-healing'],
            [
                'name' => 'Drinking Healing',
                'domain' => 'booking.drinkinghealing.com',
                'tagline' => 'Chậm lại một chút',
                'mark' => 'DH',
                'accent_color' => '#7fb59b',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        // Dia diem cu chua gan quan thi xep vao quan dau tien de khong bi mat.
        Branch::whereNull('brand_id')->update(['brand_id' => $gemination->id]);

        // Don du lieu mau dung de chay thu truoc khi co so do that.
        Branch::where('slug', 'chi-nhanh-mau')->delete();

        // So do cho ngoi that cua tung quan.
        $this->call([
            GeminationSeeder::class,
            DrinkingHealingSeeder::class,
            VenueDetailsSeeder::class,
        ]);

        // Moi quan mot tai khoan quan ly de anh gui cho nguoi phu trach.
        foreach ([$gemination, $drinkingHealing] as $brand) {
            User::firstOrCreate(
                ['email' => 'quanly.'.$brand->slug.'@thegats.vn'],
                [
                    'name' => 'Quản lý '.$brand->name,
                    'password' => 'ThayMatKhauNgay!2026',
                    'role' => Roles::MANAGER,
                    'brand_id' => $brand->id,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Tài khoản quản trị: '.$admin->email);
        $this->command?->info('Tài khoản quản lý: quanly.gemination@thegats.vn, quanly.drinking-healing@thegats.vn');
        $this->command?->warn('Mật khẩu khởi tạo cho tất cả: ThayMatKhauNgay!2026 — đổi ngay sau lần đăng nhập đầu.');
    }
}
