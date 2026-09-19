<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moi thiet bi cua nhan vien dang ky mot dia chi day rieng.
 *
 * Mot nguoi co the co nhieu thiet bi (dien thoai + may ban), moi cai mot dong.
 * Dia chi la duy nhat toan he thong: trinh duyet cap cho tung thiet bi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('thiet_bi', 255)->nullable();   // User-Agent rut gon, de nguoi dung biet may nao
            $table->timestamp('lan_cuoi_ok')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
