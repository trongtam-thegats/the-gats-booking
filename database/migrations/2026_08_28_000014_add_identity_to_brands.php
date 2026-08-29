<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            // Logo hien o dau trang dat ban, thay cho huy hieu chu.
            $table->string('logo_path')->nullable()->after('mark');
            // Mau nen cua quan. De trong thi dung mau nen mac dinh cua he thong.
            $table->string('ground_color', 7)->nullable()->after('accent_color');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'ground_color']);
        });
    }
};
