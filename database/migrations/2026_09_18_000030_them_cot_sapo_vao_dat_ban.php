<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dau vet cua don dat ban ben Sapo.
 *
 * Sapo chi mo dia chi TAO don cho trang dat lich cua no, khong co duong huy
 * hay sua. Nen khi don doi gio hoac bi huy, he thong bat co "can xu ly tay"
 * de nhan vien thay ngay trong khu quan tri roi tu vao Sapo dieu chinh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('sapo_code', 40)->nullable()->after('internal_note');
            $table->timestamp('sapo_pushed_at')->nullable()->after('sapo_code');
            $table->string('sapo_error', 255)->nullable()->after('sapo_pushed_at');
            $table->string('sapo_can_xu_ly', 255)->nullable()->after('sapo_error');
            $table->index('sapo_can_xu_ly');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['sapo_can_xu_ly']);
            $table->dropColumn(['sapo_code', 'sapo_pushed_at', 'sapo_error', 'sapo_can_xu_ly']);
        });
    }
};
