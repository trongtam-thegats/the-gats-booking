<?php

use App\Models\Branch;
use App\Models\DiningTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tao bang luu cac cap ban co the ghep truc tiep voi nhau (vi tri gan nhau / lien ke).
 * Va nap du lieu so do ban lien ke cho Drinking Healing va Gemination Da Lat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_table_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('dining_tables')->cascadeOnDelete();
            $table->foreignId('combined_with_id')->constrained('dining_tables')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['table_id', 'combined_with_id']);
        });

        // Nap lien ket lien ke cho Drinking Healing
        $dh = Branch::where('slug', 'drinking-healing')->first();
        if ($dh) {
            $this->napLienKeTheoChuoi($dh, [
                'Bar 1', 'Bar 2', 'Bar 3', 'Bar 4', 'Bar 5',
                'Bar 6', 'Bar 7', 'Bar 8', 'Bar 9', 'Bar 10',
            ]);
            $this->napLienKeTheoChuoi($dh, ['T1', 'T2', 'T3']);
            $this->napLienKeTheoChuoi($dh, ['T4', 'T5', 'T6']);
        }

        // Nap lien ket lien ke cho Gemination Da Lat
        $gemi = Branch::where('slug', 'gemination')->first();
        if ($gemi) {
            $this->napLienKeTheoChuoi($gemi, [
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
                'B11', 'B12', 'B13', 'B14', 'B15', 'B16', 'B17', 'B18', 'B19',
            ]);
            $this->napLienKeTheoChuoi($gemi, ['T1', 'T2', 'T3', 'T4', 'T5']);
            $this->napLienKeTheoChuoi($gemi, ['H1', 'H2', 'H3', 'H4', 'H5']);
            $this->napLienKeTheoChuoi($gemi, ['H6', 'H7', 'H8', 'H9']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_table_combinations');
    }

    /**
     * Tao lien ket 2 chieu cho cac ban ke nhau trong mot chuoi.
     *
     * @param  array<int, string>  $codes
     */
    protected function napLienKeTheoChuoi(Branch $branch, array $codes): void
    {
        $tables = $branch->diningTables()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $count = count($codes);
        $now = now();

        for ($i = 0; $i < $count - 1; $i++) {
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
};
