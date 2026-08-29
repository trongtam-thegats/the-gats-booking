<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moi quan mot ten mien rieng nen thu xac nhan cung phai gui tu dia chi cua
 * chinh quan do: khach dat o gemination.vn ma nhan thu tu drinkinghealing.com
 * thi vua lac nhan dien vua de bi danh dau la thu rac.
 *
 * De trong thi dung dia chi chung khai o trang Cai dat gui tin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('mail_from_address', 180)->nullable()->after('phone');
            $table->string('mail_from_name', 120)->nullable()->after('mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['mail_from_address', 'mail_from_name']);
        });
    }
};
