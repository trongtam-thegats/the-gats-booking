<?php

use App\Support\NguonDatBan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chuan hoa nguon don dat ban thanh sau kenh that: Facebook, Instagram,
 * Google, Website, Dien thoai, Walking.
 *
 * Truoc do chi co ba gia tri online / phone / walk_in, nen moi don tu mang xa
 * hoi deu bi gop chung vao "online".
 *
 * May man la lenh nhap tu Nightify co giu nguon goc nguyen van trong ghi chu
 * noi bo, nen khoi phuc lai duoc: 93 don Facebook, 49 Google, 34 Instagram...
 * thay vi de tat ca thanh "website".
 */
return new class extends Migration
{
    public function up(): void
    {
        // online -> website
        DB::table('bookings')->where('source', 'online')->update(['source' => NguonDatBan::WEBSITE]);

        // Khoi phuc nguon that tu ghi chu cua lan nhap Nightify.
        $don = DB::table('bookings')
            ->where('internal_note', 'like', '[nhap-tu-nightify]%')
            ->select('id', 'internal_note')
            ->get();

        foreach ($don as $mot) {
            if (! preg_match('/nguồn: ([a-z_]+)/u', (string) $mot->internal_note, $khop)) {
                continue;
            }

            $nguon = NguonDatBan::chuan($khop[1]);

            if ($nguon) {
                DB::table('bookings')->where('id', $mot->id)->update(['source' => $nguon]);
            }
        }
    }

    public function down(): void
    {
        foreach ([NguonDatBan::FACEBOOK, NguonDatBan::INSTAGRAM, NguonDatBan::GOOGLE, NguonDatBan::WEBSITE] as $nguon) {
            DB::table('bookings')->where('source', $nguon)->update(['source' => 'online']);
        }
    }
};
