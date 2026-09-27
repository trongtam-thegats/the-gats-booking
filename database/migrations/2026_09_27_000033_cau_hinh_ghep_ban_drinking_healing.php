<?php

use App\Models\Branch;
use App\Models\DiningTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cau hinh so do ghep ban chuan cho Drinking Healing:
 * 1. Quay bar (Bar 1..10): cho phep ghep cac ghe canh nhau (toi da 3 ghe).
 * 2. Ban cao (T1..T6): ghep 2 ban noi tiep nhau (T1-T2-T3 va T4-T5-T6).
 * 3. Sofa (S1..S4): ghep S1-S2 va S3-S4 (khong ghep S2-S3).
 */
return new class extends Migration
{
    public function up(): void
    {
        $dh = Branch::where('slug', 'drinking-healing')->first();
        if (! $dh) {
            return;
        }

        $tables = $dh->diningTables()->get()->keyBy('code');

        // 1. Quay Bar: Bar 1 den Bar 10
        $barCodes = ['Bar 1', 'Bar 2', 'Bar 3', 'Bar 4', 'Bar 5', 'Bar 6', 'Bar 7', 'Bar 8', 'Bar 9', 'Bar 10'];
        foreach ($barCodes as $code) {
            if (isset($tables[$code])) {
                $tables[$code]->update(['combinable' => true]);
            }
        }
        $this->napLienKeTheoChuoi($tables, $barCodes);

        // 2. Ban Cao: T1..T3 va T4..T6
        $highTableCodes = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6'];
        foreach ($highTableCodes as $code) {
            if (isset($tables[$code])) {
                $tables[$code]->update(['combinable' => true]);
            }
        }
        $this->napLienKeTheoChuoi($tables, ['T1', 'T2', 'T3']);
        $this->napLienKeTheoChuoi($tables, ['T4', 'T5', 'T6']);

        // Dam bao T3 va T4 khong lien ket voi nhau
        if (isset($tables['T3'], $tables['T4'])) {
            $this->xoaLienKeGiuaHaiBan($tables['T3']->id, $tables['T4']->id);
        }

        // 3. Sofa: S1-S2 va S3-S4
        $sofaCodes = ['Sofa 1', 'Sofa 2', 'Sofa 3', 'Sofa 4'];
        foreach ($sofaCodes as $code) {
            if (isset($tables[$code])) {
                $tables[$code]->update(['combinable' => true]);
            }
        }
        $this->napLienKeTheoChuoi($tables, ['Sofa 1', 'Sofa 2']);
        $this->napLienKeTheoChuoi($tables, ['Sofa 3', 'Sofa 4']);

        // Dam bao S2 va S3 khong lien ket voi nhau
        if (isset($tables['Sofa 2'], $tables['Sofa 3'])) {
            $this->xoaLienKeGiuaHaiBan($tables['Sofa 2']->id, $tables['Sofa 3']->id);
        }
    }

    public function down(): void
    {
        $dh = Branch::where('slug', 'drinking-healing')->first();
        if (! $dh) {
            return;
        }

        $tables = $dh->diningTables()->get()->keyBy('code');

        // Go lien ket Sofa
        if (isset($tables['Sofa 1'], $tables['Sofa 2'])) {
            $this->xoaLienKeGiuaHaiBan($tables['Sofa 1']->id, $tables['Sofa 2']->id);
        }
        if (isset($tables['Sofa 3'], $tables['Sofa 4'])) {
            $this->xoaLienKeGiuaHaiBan($tables['Sofa 3']->id, $tables['Sofa 4']->id);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, DiningTable>  $tables
     * @param  array<int, string>  $codes
     */
    protected function napLienKeTheoChuoi($tables, array $codes): void
    {
        $now = now();
        for ($i = 0; $i < count($codes) - 1; $i++) {
            $codeA = $codes[$i];
            $codeB = $codes[$i + 1];

            if (! isset($tables[$codeA], $tables[$codeB])) {
                continue;
            }

            $idA = $tables[$codeA]->id;
            $idB = $tables[$codeB]->id;

            DB::table('dining_table_combinations')->updateOrInsert(
                ['table_id' => $idA, 'combined_with_id' => $idB],
                ['created_at' => $now, 'updated_at' => $now]
            );
            DB::table('dining_table_combinations')->updateOrInsert(
                ['table_id' => $idB, 'combined_with_id' => $idA],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    protected function xoaLienKeGiuaHaiBan(int $idA, int $idB): void
    {
        DB::table('dining_table_combinations')
            ->where(function ($q) use ($idA, $idB) {
                $q->where('table_id', $idA)->where('combined_with_id', $idB);
            })
            ->orWhere(function ($q) use ($idA, $idB) {
                $q->where('table_id', $idB)->where('combined_with_id', $idA);
            })
            ->delete();
    }
};
