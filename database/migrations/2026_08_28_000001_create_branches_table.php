<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->string('map_url')->nullable();

            // Khung gio nhan khach
            $table->time('open_time')->default('17:00:00');
            $table->time('close_time')->default('23:30:00');
            $table->unsignedSmallInteger('slot_minutes')->default(30);       // buoc chia khung gio
            $table->unsignedSmallInteger('turn_minutes')->default(120);      // thoi luong giu ban mac dinh
            $table->unsignedSmallInteger('min_lead_minutes')->default(60);   // dat truoc toi thieu
            $table->unsignedSmallInteger('max_advance_days')->default(30);   // dat truoc toi da
            $table->unsignedSmallInteger('max_party_size')->default(20);     // vuot so nay -> yeu cau lien he
            $table->boolean('auto_confirm')->default(false);                 // tu dong xac nhan, khong cho quan ly duyet

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
