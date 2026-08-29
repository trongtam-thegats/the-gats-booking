<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * Thong tin that cua tung quan: dia chi, gio mo cua, hotline, mang xa hoi.
 * Lay tu website chinh thuc va Google Maps cua quan.
 */
class VenueDetailsSeeder extends Seeder
{
    /**
     * @var array<string, array{brand: array<string, mixed>, branch: array<string, mixed>}>
     */
    protected array $venues = [
        'gemination' => [
            'brand' => [
                'phone' => '0868 879 499',
                'tagline' => 'Một trạm dừng để hồi phục',
                'description' => 'Không gian mang hơi thở rừng Đà Lạt giữa lòng phố.',
                'website_url' => 'https://gemination.vn',
                'facebook_url' => 'https://www.facebook.com/Geminationbar',
                'instagram_url' => 'https://www.instagram.com/geminationbar',
                'tiktok_url' => 'https://www.tiktok.com/@gemination_dalat',
            ],
            'branch' => [
                'name' => 'Gemination Đà Lạt',
                'address' => '24 Trương Công Định, Phường 1, TP. Đà Lạt, Lâm Đồng',
                'phone' => '0868 879 499',
                'map_url' => 'https://maps.app.goo.gl/QMR81JGkwNYRp9Pz9',
                'open_time' => '18:00',
                'close_time' => '02:00',
                'last_booking_time' => '01:00',
            ],
        ],

        'drinking-healing' => [
            'brand' => [
                'phone' => '0934 110 110',
                'tagline' => 'A social cocktail space',
                'description' => 'Nằm trên lầu 2 một toà nhà Pháp hơn 150 năm tuổi, nơi văn hoá, '
                    .'nghệ thuật và trải nghiệm cocktail được tạo ra để cùng chia sẻ.',
                'website_url' => 'https://drinkinghealing.com',
                'facebook_url' => 'https://www.facebook.com/drinkinghealing',
                'tiktok_url' => 'https://www.tiktok.com/@drinkinghealing.sg',
            ],
            'branch' => [
                'name' => 'Drinking Healing',
                'address' => 'Lầu 2, 25 Hồ Tùng Mậu, Quận 1, TP. Hồ Chí Minh',
                'phone' => '0934 110 110',
                'map_url' => 'https://maps.app.goo.gl/adjc92xuXeuAGiQKA',
                'open_time' => '17:00',
                'close_time' => '02:00',
                'last_booking_time' => '01:00',
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->venues as $slug => $data) {
            $brand = Brand::where('slug', $slug)->first();

            if (! $brand) {
                $this->command?->warn('Chưa có quán '.$slug.', bỏ qua.');

                continue;
            }

            $brand->update($data['brand']);

            $branch = $brand->branches()->first();

            if (! $branch) {
                $this->command?->warn($brand->name.' chưa có địa điểm, bỏ qua phần địa chỉ.');

                continue;
            }

            $branch->update($data['branch']);

            $this->command?->info(sprintf(
                '%s: %s – %s, %s',
                $branch->name,
                substr($branch->open_time, 0, 5),
                substr($branch->close_time, 0, 5),
                $branch->address
            ));
        }
    }
}
