<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * So do cho ngoi that cua Gemination: mot dia diem, hai tang.
 * 36 cho dat duoc, tong 75 cho.
 */
class GeminationSeeder extends Seeder
{
    /**
     * Khu vuc => danh sach [ma ban, loai, cho toi thieu, cho toi da, cho ghep ban]
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: int, 3: int, 4: bool}>>
     */
    protected array $layout = [
        'Tầng 1' => [
            // Ghe quay bar chay doc quay
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
            ['B11', 'bar_seat', 1, 1, true],
            ['B12', 'bar_seat', 1, 1, true],
            ['B13', 'bar_seat', 1, 1, true],
            ['B14', 'bar_seat', 1, 1, true],
            ['B15', 'bar_seat', 1, 1, true],
            ['B16', 'bar_seat', 1, 1, true],
            ['B17', 'bar_seat', 1, 1, true],
            ['B18', 'bar_seat', 1, 1, true],
            ['B19', 'bar_seat', 1, 1, true],

            // Bo sofa lon canh cau thang
            ['SOFA', 'sofa', 6, 10, false],

            // Ban don doi dien quay
            ['T1', 'high_table', 1, 2, true],
            ['T2', 'high_table', 1, 2, true],
            ['T3', 'high_table', 1, 2, true],
            ['T4', 'high_table', 1, 2, true],
            ['T5', 'high_table', 1, 2, true],
        ],

        'Tầng 2' => [
            ['H1', 'high_table', 2, 4, true],
            ['H2', 'high_table', 2, 4, true],
            ['H3', 'high_table', 2, 4, true],
            ['H4', 'high_table', 2, 4, true],
            ['H5', 'high_table', 2, 4, true],

            ['H6', 'dining', 2, 3, true],
            ['H7', 'dining', 2, 3, true],
            ['H8', 'dining', 2, 3, true],
            ['H9', 'dining', 2, 3, true],

            ['H10', 'sofa', 1, 2, false],
            ['H11', 'sofa', 1, 2, false],
        ],
    ];

    public function run(): void
    {
        $brand = Brand::where('slug', 'gemination')->first();

        if (! $brand) {
            $this->command?->warn('Chưa có quán Gemination, bỏ qua.');

            return;
        }

        $branch = $brand->branches()->firstOrCreate(
            ['slug' => 'gemination'],
            [
                'name' => 'Gemination',
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

        $order = 0;

        foreach ($this->layout as $areaName => $tables) {
            $area = $branch->areas()->firstOrCreate(
                ['name' => $areaName],
                ['bookable' => true, 'sort_order' => $areaName === 'Tầng 1' ? 1 : 2]
            );

            foreach ($tables as [$code, $type, $min, $max, $combinable]) {
                $branch->diningTables()->updateOrCreate(
                    ['code' => $code],
                    [
                        'area_id' => $area->id,
                        'table_type' => $type,
                        'seats_min' => $min,
                        'seats_max' => $max,
                        'combinable' => $combinable,
                        'is_active' => true,
                        'sort_order' => ++$order,
                    ]
                );
            }
        }

        $this->command?->info('Gemination: '.$order.' bàn, '.$branch->totalSeats().' chỗ, 2 tầng.');
    }
}
