<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tung dong mat hang trong mot hoa don POS.
 *
 * Tep "danh sach hoa don" chi co cap hoa don; muon biet khach hay goi mon gi
 * thi phai nhap them tep "danh sach mat hang" - moi dong mot mon, kem lai toan
 * bo cot cua hoa don.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('sku', 60)->nullable();          // Ma mat hang
            $table->string('name');                          // Ten mat hang / combo
            $table->string('category', 80)->nullable();      // Danh muc: Classic Cocktail, Snack Bar...

            // POS cho ban nua phan nen so luong khong phai lúc nao cung nguyen.
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('unit', 30)->nullable();          // Ly, Chai, phan...

            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);    // Tien hang cua dong nay

            $table->timestamps();

            // Xep hang mon theo khach va theo quan deu di qua hai chi muc nay.
            $table->index(['invoice_id']);
            $table->index('name');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
