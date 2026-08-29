<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 30);
            $table->string('name')->nullable();       // ten quan tu dat, uu tien hon ten khach tu dien
            $table->text('note')->nullable();         // di ung, ban ua thich, khach kho tinh...
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_blocked')->default(false); // chan dat ban online
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Ho so khach tinh theo tung quan: khach cua Gemination khong lo sang quan khac.
            $table->unique(['brand_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_notes');
    }
};
