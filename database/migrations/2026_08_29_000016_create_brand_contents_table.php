<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'key']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });

        Schema::dropIfExists('brand_contents');
    }
};
