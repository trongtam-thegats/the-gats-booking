<?php

use App\Models\Branch;
use App\Models\DiningTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dh = Branch::where('slug', 'drinking-healing')->first();
        if (! $dh) {
            return;
        }

        // 1. Nang gioi han so khach toi da len 18 khach
        $dh->update(['max_party_size' => 18]);

        // 2. Tam xoa viec ghep ban cho cac ban cao (T1 -> T6)
        $highTables = $dh->diningTables()->whereIn('code', ['T1', 'T2', 'T3', 'T4', 'T5', 'T6'])->get();
        $highTableIds = $highTables->pluck('id')->all();

        if (! empty($highTableIds)) {
            DB::table('dining_table_combinations')
                ->whereIn('table_id', $highTableIds)
                ->orWhereIn('combined_with_id', $highTableIds)
                ->delete();

            DiningTable::whereIn('id', $highTableIds)->update(['combinable' => false]);
        }
    }

    public function down(): void
    {
        $dh = Branch::where('slug', 'drinking-healing')->first();
        if (! $dh) {
            return;
        }

        $dh->update(['max_party_size' => 16]);

        $highTables = $dh->diningTables()->whereIn('code', ['T1', 'T2', 'T3', 'T4', 'T5', 'T6'])->get();
        $highTableIds = $highTables->pluck('id')->all();

        if (! empty($highTableIds)) {
            DiningTable::whereIn('id', $highTableIds)->update(['combinable' => true]);

            $this->napLienKeTheoChuoi($dh, ['T1', 'T2', 'T3']);
            $this->napLienKeTheoChuoi($dh, ['T4', 'T5', 'T6']);
        }
    }

    /**
     * @param  array<int, string>  $codes
     */
    protected function napLienKeTheoChuoi(Branch $branch, array $codes): void
    {
        $tables = $branch->diningTables()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        for ($i = 0; $i < count($codes) - 1; $i++) {
            $codeA = $codes[$i];
            $codeB = $codes[$i + 1];

            if (isset($tables[$codeA], $tables[$codeB])) {
                $tableA = $tables[$codeA];
                $tableB = $tables[$codeB];

                DB::table('dining_table_combinations')->insertOrIgnore([
                    ['table_id' => $tableA->id, 'combined_with_id' => $tableB->id, 'created_at' => now(), 'updated_at' => now()],
                    ['table_id' => $tableB->id, 'combined_with_id' => $tableA->id, 'created_at' => now(), 'updated_at' => now()],
                ]);
            }
        }
    }
};
