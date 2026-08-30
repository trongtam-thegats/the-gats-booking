<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * So do cho ngoi that cua Drinking Healing (user chot 2026-08-30): 21 ban
 * trong 4 khu. Vi tri DJ khong phai ban nen khong khai bao.
 *
 * Ten ban dung dung ten nhan vien goi hang ngay. Cot "ten cu" giu lai ma cua
 * he thong dat ban truoc day (B1, S4, K1-K4) de con nhap duoc lich su.
 */
class DrinkingHealingSeeder extends Seeder
{
    /**
     * [ma ban, ten cu, khu vuc, loai, so cho toi thieu, so cho toi da, cho ghep ban]
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: bool}>
     */
    protected array $tables = [
        // Ghe quay bar - moi ghe mot khach, ghep duoc voi nhau cho nhom ngoi quay
        ['Bar 1', 'B1,B01', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 2', 'B2,B02', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 3', 'B3,B03', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 4', 'B4,B04', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 5', 'B5,B05', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 6', 'B6,B06', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 7', 'B7,B07', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 8', 'B8,B08', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 9', 'B9,B09', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 10', 'B10,B010', 'Quầy Bar', 'bar_seat', 1, 1, true],

        // Bon ban K cu (K1-K4) da duoc quan gop thanh mot ban dai duy nhat.
        ['Dining Room', 'K1,K2,K3,K4', 'Dining Room', 'dining', 6, 16, false],

        // Sofa - khong ghep, moi bo la mot khong gian rieng
        ['Sofa 1', 'S1', 'Sofa', 'sofa', 4, 6, false],
        ['Sofa 2', 'S2', 'Sofa', 'sofa', 5, 8, false],
        ['Sofa 3', 'S3', 'Sofa', 'sofa', 5, 8, false],
        ['Sofa 4', 'S4', 'Sofa', 'sofa', 5, 8, false],

        // Ban cao
        ['T1', '', 'Bàn Cao', 'high_table', 2, 4, true],
        ['T2', '', 'Bàn Cao', 'high_table', 2, 4, true],
        ['T3', '', 'Bàn Cao', 'high_table', 2, 4, true],
        ['T4', '', 'Bàn Cao', 'high_table', 4, 6, true],
        ['T5', '', 'Bàn Cao', 'high_table', 4, 6, true],
        ['T6', '', 'Bàn Cao', 'high_table', 2, 4, true],
    ];

    /** Thu tu hien thi cua cac khu. */
    protected array $areas = ['Quầy Bar', 'Dining Room', 'Sofa', 'Bàn Cao'];

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

        $khu = [];

        foreach ($this->areas as $thuTu => $ten) {
            $khu[$ten] = $branch->areas()->firstOrCreate(
                ['name' => $ten],
                ['bookable' => true, 'sort_order' => $thuTu + 1]
            );
        }

        foreach ($this->tables as $index => [$code, $tenCu, $ten, $type, $min, $max, $combinable]) {
            $branch->diningTables()->updateOrCreate(
                ['code' => $code],
                [
                    'aliases' => $tenCu ?: null,
                    'area_id' => $khu[$ten]->id,
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
