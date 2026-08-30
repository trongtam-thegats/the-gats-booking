<?php

use App\Models\Area;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dua so do ban cua Drinking Healing ve dung thuc te (user chot 2026-08-30).
 *
 * Truoc do may that co 33 ban do nhieu dot khai bao chong len nhau: B01-B09
 * trung voi B1-B9, moi ban deu bi don vao mot khu "Ban Cao", va S4 bi doi ten
 * tay thanh "Sofa 4" khien don nhap tu he thong cu khong tim ra ban.
 *
 * Ket qua dung: 21 ban trong 4 khu.
 *   Quay Bar   - Bar 1..Bar 10 (ghe bar, moi ghe mot khach)
 *   Dining Room- mot ban duy nhat 6-16 khach, thay cho K1-K4 cu
 *   Sofa       - Sofa 1..Sofa 4
 *   Ban Cao    - T1..T6
 *
 * Chay lai duoc nhieu lan ma khong hong them: moi buoc deu tim theo ten cu
 * lan ten moi.
 */
return new class extends Migration
{
    /** [ten moi, ten cu (ngan bang phay), khu, loai, cho it nhat, cho nhieu nhat, ghep duoc] */
    protected array $banDung = [
        ['Bar 1', 'B1,B01', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 2', 'B2,B02', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 3', 'B3,B03', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 4', 'B4,B04', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 5', 'B5,B05', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 6', 'B6,B06', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 7', 'B7,B07', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 8', 'B8,B08', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 9', 'B9,B09', 'Quầy Bar', 'bar_seat', 1, 1, true],
        ['Bar 10', 'B10,B010', 'Quầy Bar', 'bar_seat', 1, 1, true],

        // Bon ban K cu da duoc quan gop thanh mot ban dai duy nhat.
        ['Dining Room', 'K1,K2,K3,K4', 'Dining Room', 'dining', 6, 16, false],

        ['Sofa 1', 'S1', 'Sofa', 'sofa', 4, 6, false],
        ['Sofa 2', 'S2', 'Sofa', 'sofa', 5, 8, false],
        ['Sofa 3', 'S3', 'Sofa', 'sofa', 5, 8, false],
        ['Sofa 4', 'S4', 'Sofa', 'sofa', 5, 8, false],

        ['T1', '', 'Bàn Cao', 'high_table', 2, 4, true],
        ['T2', '', 'Bàn Cao', 'high_table', 2, 4, true],
        ['T3', '', 'Bàn Cao', 'high_table', 2, 4, true],
        ['T4', '', 'Bàn Cao', 'high_table', 4, 6, true],
        ['T5', '', 'Bàn Cao', 'high_table', 4, 6, true],
        ['T6', '', 'Bàn Cao', 'high_table', 2, 4, true],
    ];

    public function up(): void
    {
        $branch = $this->diaDiem();

        if (! $branch) {
            return;
        }

        DB::transaction(function () use ($branch) {
            $khuVuc = $this->khuVuc($branch);
            $giuLai = [];

            foreach ($this->banDung as $thuTu => [$ten, $tenCu, $khu, $loai, $min, $max, $ghep]) {
                $tenCuMang = array_values(array_filter(explode(',', $tenCu)));

                // Tim ban dang co theo ten moi truoc, khong thay thi lan theo ten cu.
                $ban = $this->timBan($branch, array_merge([$ten], $tenCuMang));

                if (! $ban) {
                    $ban = new DiningTable(['branch_id' => $branch->id, 'code' => $ten]);
                }

                $ban->fill([
                    'code' => $ten,
                    'aliases' => $tenCu ?: null,
                    'area_id' => $khuVuc[$khu]->id,
                    'table_type' => $loai,
                    'seats_min' => $min,
                    'seats_max' => $max,
                    'combinable' => $ghep,
                    'is_active' => true,
                    'sort_order' => $thuTu + 1,
                ]);

                $ban->branch_id = $branch->id;
                $ban->save();

                $giuLai[] = $ban->id;

                // Cac ban trung ten cu (B01 trung B1, K2-K4 gop vao Dining Room)
                // duoc don sang ban giu lai roi xoa, khong de mat lich su.
                foreach ($tenCuMang as $ma) {
                    $trung = DiningTable::where('branch_id', $branch->id)
                        ->where('code', $ma)
                        ->whereKeyNot($ban->id)
                        ->first();

                    if ($trung) {
                        $this->donDonSangBanKhac($trung, $ban);
                        $trung->delete();
                    }
                }
            }

            // Ban nao khong con trong so do that thi tat di chu khong xoa,
            // phong khi con lich su dat ban gan vao.
            DiningTable::where('branch_id', $branch->id)
                ->whereNotIn('id', $giuLai)
                ->update(['is_active' => false]);
        });
    }

    public function down(): void
    {
        // Khong dung lai duoc so do cu, va cung khong nen: so do cu la du lieu sai.
    }

    protected function diaDiem(): ?Branch
    {
        $brand = Brand::where('slug', 'drinking-healing')->first();

        return $brand?->branches()->orderBy('id')->first();
    }

    /**
     * Bon khu vuc dung, tao neu chua co.
     *
     * @return array<string, Area>
     */
    protected function khuVuc(Branch $branch): array
    {
        $ket = [];
        $thuTu = 1;

        foreach (['Quầy Bar', 'Dining Room', 'Sofa', 'Bàn Cao'] as $ten) {
            $ket[$ten] = Area::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $ten],
                ['bookable' => true, 'sort_order' => $thuTu]
            );

            $thuTu++;
        }

        return $ket;
    }

    /**
     * Ban dang co, tim theo thu tu uu tien cua danh sach ma.
     * Khong dung FIELD() cua MySQL de con chay duoc tren co so du lieu khac.
     *
     * @param  array<int, string>  $ma
     */
    protected function timBan(Branch $branch, array $ma): ?DiningTable
    {
        foreach ($ma as $mot) {
            $ban = DiningTable::where('branch_id', $branch->id)->where('code', $mot)->first();

            if ($ban) {
                return $ban;
            }
        }

        return null;
    }

    /** Chuyen moi don dang gan vao ban cu sang ban moi, tranh gan trung. */
    protected function donDonSangBanKhac(DiningTable $tu, DiningTable $sang): void
    {
        $donCuaBanMoi = DB::table('booking_dining_table')
            ->where('dining_table_id', $sang->id)
            ->pluck('booking_id')
            ->all();

        DB::table('booking_dining_table')
            ->where('dining_table_id', $tu->id)
            ->whereIn('booking_id', $donCuaBanMoi ?: [0])
            ->delete();

        DB::table('booking_dining_table')
            ->where('dining_table_id', $tu->id)
            ->update(['dining_table_id' => $sang->id]);

        // Don nao dang tro toi khu cua ban cu thi keo theo ve khu cua ban moi.
        Booking::where('area_id', $tu->area_id)
            ->whereHas('diningTables', fn ($q) => $q->whereKey($sang->id))
            ->update(['area_id' => $sang->area_id]);
    }
};
