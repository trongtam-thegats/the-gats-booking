<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chu tren trang dat ban tach theo ngon ngu: moi quan co ban tieng Viet
        // va ban tieng Anh rieng.
        if (! Schema::hasColumn('brand_contents', 'locale')) {
            Schema::table('brand_contents', function (Blueprint $table) {
                $table->string('locale', 5)->default('vi')->after('key');
            });
        }

        // Phai tao chi muc moi TRUOC khi xoa chi muc cu: khoa ngoai brand_id
        // dang dua vao chi muc cu, xoa truoc se bi MySQL tu choi.
        if (! $this->hasIndex('brand_contents', 'brand_contents_brand_id_key_locale_unique')) {
            Schema::table('brand_contents', function (Blueprint $table) {
                $table->unique(['brand_id', 'key', 'locale']);
            });
        }

        if ($this->hasIndex('brand_contents', 'brand_contents_brand_id_key_unique')) {
            Schema::table('brand_contents', function (Blueprint $table) {
                $table->dropUnique('brand_contents_brand_id_key_unique');
            });
        }

        if (! Schema::hasColumn('bookings', 'locale')) {
            Schema::table('bookings', function (Blueprint $table) {
                // Ngon ngu khach dung luc dat, de tin xac nhan gui dung thu tieng.
                $table->string('locale', 5)->default('vi')->after('source');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('brand_contents', function (Blueprint $table) {
            $table->unique(['brand_id', 'key']);
        });

        Schema::table('brand_contents', function (Blueprint $table) {
            $table->dropUnique('brand_contents_brand_id_key_locale_unique');
            $table->dropColumn('locale');
        });
    }

    /** SQLite va MySQL bao chi muc khac nhau nen hoi qua lop truu tuong cua Laravel. */
    protected function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $row) => $row['name'] === $index);
    }
};
