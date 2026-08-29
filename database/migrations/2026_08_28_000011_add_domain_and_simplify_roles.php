<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            // Ten mien rieng cua quan, vi du booking.drinkinghealing.com.
            // Mot ban cai phuc vu nhieu ten mien; vao ten mien nao thi chi thay quan do.
            $table->string('domain')->nullable()->unique()->after('slug');
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            // Nguoi dung thuoc ve mot quan. Quan tri de trong = thay tat ca.
            $table->foreignId('brand_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        // Ba vai tro moi: admin / manager / viewer.
        DB::table('users')->where('role', 'branch_manager')->update(['role' => 'manager']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'viewer']);

        // Nguoi dung dang gan dia diem thi suy ra quan tu dia diem do.
        // Viet bang query builder de chay duoc tren ca MySQL lan SQLite.
        DB::table('branches')
            ->whereNotNull('brand_id')
            ->select('id', 'brand_id')
            ->orderBy('id')
            ->each(function ($branch) {
                DB::table('users')
                    ->where('branch_id', $branch->id)
                    ->whereNull('brand_id')
                    ->update(['brand_id' => $branch->brand_id]);
            });

        // Quan dau tien lam mac dinh, dung khi ten mien khong khop cai nao.
        $firstBrandId = DB::table('brands')->orderBy('sort_order')->orderBy('id')->value('id');

        if ($firstBrandId) {
            DB::table('brands')->where('id', $firstBrandId)->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'manager')->update(['role' => 'branch_manager']);
        DB::table('users')->where('role', 'viewer')->update(['role' => 'staff']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->dropColumn(['domain', 'is_default']);
        });
    }
};
