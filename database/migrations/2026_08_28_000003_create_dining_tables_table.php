<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');                        // B01, VIP2...
            $table->unsignedSmallInteger('seats_min')->default(1);
            $table->unsignedSmallInteger('seats_max')->default(4);
            $table->boolean('combinable')->default(true);  // co the ghep voi ban khac
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
