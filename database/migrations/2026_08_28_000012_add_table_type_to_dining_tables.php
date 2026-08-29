<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            // Loai cho ngoi, dung de le tan doc nhanh so do va de loc khi xep ban.
            // bar_seat | high_table | dining | sofa | booth | other
            $table->string('table_type', 20)->default('dining')->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('table_type');
        });
    }
};
