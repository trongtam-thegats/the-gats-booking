<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhat ky xoa dat ban.
 *
 * Xoa dat ban la xoa han khoi bang bookings - co y nhu vay, vi muc dich cua
 * chuc nang la lam sach so lieu bao cao va phan tich khach hang. Neu chi danh
 * dau "da xoa" thi moi truy van thong ke deu phai nho loc them mot dieu kien,
 * som muon cung co cho quen.
 *
 * Bu lai, moi lan xoa deu de lai mot dong o day kem ban sao day du cua don
 * (cot du_lieu), de con truy nguoc duoc ai xoa, xoa vi ly do gi, va dung lai
 * duoc neu xoa nham.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_deletions', function (Blueprint $table) {
            $table->id();

            $table->string('code', 12);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // Giu ca ten dia diem dang chu: con doc duoc ke ca khi dia diem bi xoa.
            $table->string('branch_name')->nullable();

            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->unsignedSmallInteger('party_size');
            $table->date('booking_date');
            $table->time('start_time');
            $table->string('status', 20);
            $table->string('source', 20);

            // Ban sao nguyen ven cua dong bookings luc xoa, de dung lai duoc.
            $table->json('du_lieu');

            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_by_name')->nullable();
            $table->string('reason');

            $table->timestamps();

            $table->index('code');
            $table->index(['branch_id', 'booking_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_deletions');
    }
};
