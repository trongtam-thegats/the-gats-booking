<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * So do cho ngoi that cua Drinking Healing, dung theo ban do quan gui:
 * 24 cho dat duoc, tong 84 cho. Vi tri DJ khong phai ban nen khong khai bao.
 */
class DrinkingHealingSeeder extends Seeder
{
    /**
     * [ma ban, loai, so cho toi thieu, so cho toi da, cho ghep ban]
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: int, 4: bool}>
     */
    protected array $tables = [
        // Ghe quay bar - moi ghe mot khach, ghep duoc voi nhau cho nhom ngoi quay
        ['B1', 'bar_seat', 1, 1, true],
        ['B2', 'bar_seat', 1, 1, true],
        ['B3', 'bar_seat', 1, 1, true],
        ['B4', 'bar_seat', 1, 1, true],
        ['B5', 'bar_seat', 1, 1, true],
        ['B6', 'bar_seat', 1, 1, true],
        ['B7', 'bar_seat', 1, 1, true],
        ['B8', 'bar_seat', 1, 1, true],
        ['B9', 'bar_seat', 1, 1, true],
        ['B10', 'bar_seat', 1, 1, true],

        // Ban an trong phong K
        ['K1', 'dining', 2, 4, true],
        ['K2', 'dining', 2, 4, true],
        ['K3', 'dining', 2, 4, true],
        ['K4', 'dining', 2, 4, true],

        // Sofa - khong ghep, moi bo la mot khong gian rieng
        ['S1', 'sofa', 4, 6, false],
        ['S2', 'sofa', 5, 8, false],
        ['S3', 'sofa', 5, 8, false],
        ['S4', 'sofa', 5, 8, false],

        // Ban cao
        ['T1', 'high_table', 2, 4, true],
        ['T2', 'high_table', 2, 4, true],
        ['T3', 'high_table', 2, 4, true],
        ['T4', 'high_table', 4, 6, true],
        ['T5', 'high_table', 4, 6, true],
        ['T6', 'high_table', 2, 4, true],
    ];

    public function run(): void
    {
        $brand = Brand::where('slug', 'drinking-healing')->first();

        if (! $brand) {
            $this->command?->warn('Chưa có quán Drinking Healing, bỏ qua.');

            return;
        }

        $branch = $brand->branches()->firstOrCreate(
            ['slug' => 'drinking-healing'],
            [
                'name' => 'Drinking Healing',
                'open_time' => '17:00',
                'close_time' => '00:00',
                'slot_minutes' => 30,
                'turn_minutes' => 120,
                'min_lead_minutes' => 60,
                'max_advance_days' => 30,
                'max_party_size' => 20,
                'auto_confirm' => false,
                'is_active' => true,
            ]
        );

        $area = $branch->areas()->firstOrCreate(
            ['name' => 'Khu chính'],
            ['bookable' => true, 'sort_order' => 1]
        );

        foreach ($this->tables as $index => [$code, $type, $min, $max, $combinable]) {
            $branch->diningTables()->updateOrCreate(
                ['code' => $code],
                [
                    'area_id' => $area->id,
                    'table_type' => $type,
                    'seats_min' => $min,
                    'seats_max' => $max,
                    'combinable' => $combinable,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        $this->command?->info(
            'Drinking Healing: '.count($this->tables).' bàn, '
            .$branch->totalSeats().' chỗ.'
        );
    }
}
