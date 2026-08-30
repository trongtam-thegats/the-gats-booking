<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ten cu cua ban, ngan cach bang dau phay.
 *
 * Quan doi ten ban theo thoi gian (B1 thanh "Bar 1", K1-K4 gop thanh "Dining
 * Room"). Cac tep xuat tu he thong cu van ghi ten cu, nen phai nho lai de con
 * nhap du lieu lich su vao dung ban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('aliases')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
