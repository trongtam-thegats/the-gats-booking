<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Gio nhan dat ban tre nhat. Khac voi gio dong cua: quan van mo
            // sau moc nay nhung khong nhan booking moi.
            // De trong = suy tu gio dong cua tru thoi luong giu ban.
            $table->time('last_booking_time')->nullable()->after('close_time');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('last_booking_time');
        });
    }
};
