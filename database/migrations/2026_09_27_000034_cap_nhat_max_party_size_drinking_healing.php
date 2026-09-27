<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Branch::where('slug', 'drinking-healing')->update([
            'max_party_size' => 16,
        ]);
    }

    public function down(): void
    {
        Branch::where('slug', 'drinking-healing')->update([
            'max_party_size' => 20,
        ]);
    }
};
