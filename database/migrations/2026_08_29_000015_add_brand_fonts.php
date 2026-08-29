<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            // Font rieng cua tung quan. De trong thi dung bo font he thong.
            $table->string('display_font_path')->nullable()->after('logo_path'); // tieu de
            $table->string('body_font_path')->nullable()->after('display_font_path'); // noi dung
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['display_font_path', 'body_font_path']);
        });
    }
};
