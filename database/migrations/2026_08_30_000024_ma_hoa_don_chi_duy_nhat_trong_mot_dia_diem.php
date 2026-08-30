<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ma hoa don chi duy nhat trong pham vi mot dia diem, khong phai toan he thong.
 *
 * POS danh so hoa don rieng cho tung quan nen hai quan de trung ma: doi chieu
 * tep xuat cua Gemination va Drinking Healing thay 21 ma trung nhau, ma la hai
 * hoa don hoan toan khac (khac ngay, khac tien, khac ban). De unique tren mot
 * cot code thi lan nhap sau se ghi de mat hoa don cua quan truoc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Tao khoa moi truoc roi moi bo khoa cu: khoa ngoai branch_id dang
            // dua vao mot chi muc, bo truoc se gay loi.
            $table->unique(['branch_id', 'code'], 'invoices_branch_code_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_code_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Van can tra cuu nhanh theo ma khi nhan vien go tay.
            $table->index('code', 'invoices_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_code_index');
            $table->unique('code', 'invoices_code_unique');
            $table->dropUnique('invoices_branch_code_unique');
        });
    }
};
