<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Tai khoan moi duoc tao voi mat khau bang chinh email, va bi buoc doi mat khau
 * ngay lan dang nhap dau tien.
 *
 * Cot must_change_password la cai chot: con bat thi moi trang trong khu quan ly
 * deu day nguoi dung ve trang doi mat khau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });

        // Ai con dung mat khau khoi tao (bang chinh email) thi bat co doi ngay.
        // Bang tai khoan chi vai dong nen kiem tung dong khong ton kem gi.
        foreach (DB::table('users')->select('id', 'email', 'password')->get() as $user) {
            if ($user->password && Hash::check($user->email, $user->password)) {
                DB::table('users')->where('id', $user->id)->update(['must_change_password' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_changed_at']);
        });
    }
};
