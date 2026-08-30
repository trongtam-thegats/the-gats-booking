<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh dau "da xem xet khach nay roi", de khoi cham soc trung nhieu lan.
 *
 * Co tinh KHONG luu trang thai "da roi bo" thanh mot nhan tinh: nhan tinh se
 * muc dan vi khach quay lai ma khong ai nho vao go. Thay vao do chi luu thoi
 * diem xem xet; he thong so voi lan ghe gan nhat de tu suy ra khach da quay
 * lai hay chua. Khach co hoa don moi sau ngay xem xet la tu dong chuyen sang
 * "da ghe lai", khong ai phai don tay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_notes', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('is_blocked');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('review_outcome', 30)->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('review_outcome');

            // Loc "chua xem xet" la thao tac chinh cua danh sach cham soc.
            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('guest_notes', function (Blueprint $table) {
            $table->dropIndex(['reviewed_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_outcome', 'review_note']);
        });
    }
};
