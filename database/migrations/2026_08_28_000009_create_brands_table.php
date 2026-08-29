<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Gemination, Drinking Healing
            $table->string('slug')->unique();           // dung lam duong dan rieng: /gemination
            $table->string('tagline')->nullable();      // cau dan o dau trang dat ban
            $table->text('description')->nullable();
            $table->string('mark', 8)->default('TG');   // 2-3 ky tu trong huy hieu tron
            $table->string('accent_color', 7)->default('#c8a15a'); // mau nhan dien, dang #rrggbb
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table) {
            // Nullable de khong lam hong du lieu chi nhanh da co; man hinh quan tri
            // se yeu cau gan thuong hieu cho tung chi nhanh.
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
