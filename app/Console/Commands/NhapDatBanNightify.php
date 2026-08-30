<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DiningTable;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Support\SoDienThoai;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nhap lich su dat ban xuat tu Nightify (tep CSV) vao he thong.
 *
 * Mac dinh chi xem truoc, khong ghi gi ca. Them --ghi moi that su luu.
 *
 *   php artisan dat-ban:nhap-nightify duong-dan.csv --quan=drinking-healing
 *   php artisan dat-ban:nhap-nightify duong-dan.csv --quan=drinking-healing --ghi
 */
class NhapDatBanNightify extends Command
{
    protected $signature = 'dat-ban:nhap-nightify
        {tep? : Duong dan toi tep CSV xuat tu Nightify}
        {--quan= : Slug cua quan, vi du drinking-healing}
        {--dia-diem= : Slug dia diem, mac dinh lay dia diem dau tien cua quan}
        {--ghi : That su ghi vao co so du lieu (khong co thi chi xem truoc)}
        {--xoa : Xoa cac don da nhap tu Nightify roi dung}';

    protected $description = 'Nhap lich su dat ban tu tep CSV cua Nightify';

    /** Dau danh cho don nhap tu Nightify, de sau nay loc hoac xoa lai duoc. */
    public const DAU = '[nhap-tu-nightify]';

    /** Trang thai cua Nightify doi sang trang thai cua he thong. */
    protected const TRANG_THAI = [
        'completed' => Booking::STATUS_COMPLETED,
        'no_show' => Booking::STATUS_NO_SHOW,
        'cancelled' => Booking::STATUS_CANCELLED,
        'booked' => Booking::STATUS_CONFIRMED,
        'confirmed' => Booking::STATUS_CONFIRMED,
        'seated' => Booking::STATUS_SEATED,
    ];

    /**
     * Nguon don cua Nightify doi sang ba nguon cua he thong.
     * Nguon goc van duoc ghi lai nguyen van trong ghi chu noi bo.
     */
    protected const NGUON = [
        'walk_in' => 'walk_in',
        'venue_initiated_booking' => 'phone',
    ];

    public function handle(AvailabilityService $availability): int
    {
        if ($this->option('xoa')) {
            return $this->xoaDonDaNhap();
        }

        $tep = (string) $this->argument('tep');

        if (! is_file($tep)) {
            $this->error('Khong thay tep: '.$tep);

            return self::FAILURE;
        }

        $branch = $this->diaDiem();

        if (! $branch) {
            return self::FAILURE;
        }

        $ghi = (bool) $this->option('ghi');

        $this->info('Dia diem: '.$branch->name.' ('.$branch->brand?->name.')');
        $this->line($ghi ? 'Che do: GHI THAT' : 'Che do: chi xem truoc, khong ghi gi');

        $dong = $this->docCsv($tep);
        $this->line('Doc duoc '.count($dong).' dong.');

        $bang = $this->chiMucBan($branch);

        $nguoiDung = User::pluck('id', 'email');
        $daCo = Booking::whereIn('code', array_column($dong, 'RESERVATION_CODE'))->pluck('code')->all();

        $ketQua = ['moi' => 0, 'bo_qua' => 0, 'thieu_ban' => 0, 'loi' => 0];
        $banThieu = [];

        $viec = function () use ($dong, $branch, $bang, $nguoiDung, $daCo, $availability, &$ketQua, &$banThieu, $ghi) {
            foreach ($dong as $r) {
                $ma = trim((string) ($r['RESERVATION_CODE'] ?? ''));

                if ($ma === '' || in_array($ma, $daCo, true)) {
                    $ketQua['bo_qua']++;

                    continue;
                }

                $banCsv = array_values(array_filter(array_map('trim', explode(';', (string) $r['TABLE_CODE']))));
                $ban = [];

                foreach ($banCsv as $code) {
                    $tim = $bang[$this->chuanMa($code)] ?? null;

                    if ($tim) {
                        $ban[] = $tim;
                    } else {
                        $banThieu[$code] = ($banThieu[$code] ?? 0) + 1;
                    }
                }

                if ($banCsv && ! $ban) {
                    $ketQua['thieu_ban']++;
                }

                $don = $this->duLieuDon($r, $branch, $ban, $nguoiDung, $availability);

                if (! $don) {
                    $ketQua['loi']++;

                    continue;
                }

                $ketQua['moi']++;

                if (! $ghi) {
                    continue;
                }

                $booking = Booking::create($don);
                $booking->diningTables()->sync(array_map(fn (DiningTable $t) => $t->id, $ban));
            }
        };

        $ghi ? DB::transaction($viec) : $viec();

        $this->newLine();
        $this->line('Se nhap moi     : '.$ketQua['moi']);
        $this->line('Bo qua (da co)  : '.$ketQua['bo_qua']);
        $this->line('Khong khop ban  : '.$ketQua['thieu_ban']);
        $this->line('Dong loi        : '.$ketQua['loi']);

        if ($banThieu) {
            $this->warn('Ma ban khong co trong he thong: '.collect($banThieu)
                ->map(fn ($n, $code) => $code.' ('.$n.')')->implode(', '));
        }

        if (! $ghi) {
            $this->newLine();
            $this->comment('Chua ghi gi ca. Chay lai kem --ghi de luu that.');
        }

        return self::SUCCESS;
    }

    /**
     * Ban cua dia diem, tra cuu duoc bang ca ten hien tai lan ten cu.
     *
     * Quan doi ten ban theo thoi gian (B1 thanh "Bar 1", K1-K4 gop thanh
     * "Dining Room") nhung tep xuat tu he thong cu van ghi ten cu.
     *
     * @return array<string, DiningTable>
     */
    protected function chiMucBan(Branch $branch): array
    {
        $ban = DiningTable::where('branch_id', $branch->id)->get();
        $chiMuc = [];

        foreach ($ban as $mot) {
            $chiMuc[$this->chuanMa((string) $mot->code)] = $mot;
        }

        // Ten cu chi dien vao cho trong, khong duoc de len ten dang dung.
        foreach ($ban as $mot) {
            foreach (explode(',', (string) $mot->aliases) as $cu) {
                $cu = $this->chuanMa($cu);

                if ($cu !== '' && ! isset($chiMuc[$cu])) {
                    $chiMuc[$cu] = $mot;
                }
            }
        }

        return $chiMuc;
    }

    /** Ma ban bo dau cach va ve chu hoa, de "Bar 1" va "BAR1" la mot. */
    protected function chuanMa(string $ma): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', trim($ma)));
    }

    /**
     * Mot dong CSV thanh mang du lieu de tao don.
     *
     * @param  array<string, string>  $r
     * @param  array<int, DiningTable>  $ban
     * @param  \Illuminate\Support\Collection<string, int>  $nguoiDung
     * @return array<string, mixed>|null
     */
    protected function duLieuDon(array $r, Branch $branch, array $ban, $nguoiDung, AvailabilityService $availability): ?array
    {
        $ngay = trim((string) $r['BOOKING_DATE']);
        $den = trim((string) $r['ARRIVAL_TIME']);

        if ($ngay === '' || $den === '') {
            return null;
        }

        try {
            $gioDen = Carbon::parse($den);
            $taoLuc = trim((string) $r['CREATED_AT']) !== '' ? Carbon::parse($r['CREATED_AT']) : $gioDen->copy();
        } catch (\Throwable) {
            return null;
        }

        $open = $availability->openMinutes($branch);
        $batDau = $availability->normalize($availability->toMinutes($gioDen->format('H:i')), $open);
        $ketThuc = $availability->endMinutesFor($branch, $batDau);

        $trangThaiGoc = mb_strtolower(trim((string) $r['STATUS']));
        $trangThai = self::TRANG_THAI[$trangThaiGoc] ?? Booking::STATUS_COMPLETED;

        $nguonGoc = trim((string) $r['SOURCE']);
        $nguoiTao = trim((string) $r['CREATED_BY']);

        return [
            'code' => trim((string) $r['RESERVATION_CODE']),
            'branch_id' => $branch->id,
            'customer_name' => trim((string) $r['CUSTOMER_NAME']) ?: 'Khách',
            'customer_phone' => SoDienThoai::chuan((string) $r['CUSTOMER_PHONE']),
            'customer_email' => filter_var(trim((string) $r['CUSTOMER_EMAIL']), FILTER_VALIDATE_EMAIL) ?: null,
            'party_size' => max(1, (int) $r['PAX']),
            'booking_date' => $ngay,
            'start_time' => $gioDen->format('H:i:s'),
            'end_time' => $availability->toTimeString($ketThuc).':00',
            'area_id' => $ban ? ($ban[0]->area_id ?? null) : null,
            'status' => $trangThai,
            'source' => self::NGUON[$nguonGoc] ?? 'online',
            'locale' => 'vi',
            'internal_note' => trim(self::DAU.' nguồn: '.($nguonGoc ?: 'không rõ')
                .($nguoiTao !== '' ? ' · người tạo: '.$nguoiTao : '')
                .' · trạng thái gốc: '.($trangThaiGoc ?: 'không rõ')),
            'completed_at' => $trangThai === Booking::STATUS_COMPLETED ? $gioDen : null,
            'cancelled_at' => $trangThai === Booking::STATUS_CANCELLED ? $taoLuc : null,
            'cancelled_by_type' => $trangThai === Booking::STATUS_CANCELLED ? 'staff' : null,
            'created_by' => $nguoiDung[$nguoiTao] ?? null,
            'created_at' => $taoLuc,
            'updated_at' => $taoLuc,
        ];
    }

    /**
     * Doc CSV thanh mang cac dong co khoa la ten cot.
     *
     * @return array<int, array<string, string>>
     */
    protected function docCsv(string $tep): array
    {
        $noiDung = (string) file_get_contents($tep);
        $noiDung = (string) preg_replace('/^\xEF\xBB\xBF/', '', $noiDung);

        $tam = fopen('php://temp', 'r+');
        fwrite($tam, $noiDung);
        rewind($tam);

        $cot = fgetcsv($tam, 0, ',', '"', '');
        $dong = [];

        while (($hang = fgetcsv($tam, 0, ',', '"', '')) !== false) {
            if ($hang === [null] || $hang === []) {
                continue;
            }

            $dong[] = array_combine($cot, array_pad(array_slice($hang, 0, count($cot)), count($cot), ''));
        }

        fclose($tam);

        return $dong;
    }

    protected function diaDiem(): ?Branch
    {
        if ($slug = $this->option('dia-diem')) {
            $branch = Branch::where('slug', $slug)->first();

            if (! $branch) {
                $this->error('Khong thay dia diem: '.$slug);
            }

            return $branch;
        }

        $quan = $this->option('quan');

        if (! $quan) {
            $this->error('Phai chi ro --quan=slug hoac --dia-diem=slug.');

            return null;
        }

        $brand = Brand::where('slug', $quan)->first();

        if (! $brand) {
            $this->error('Khong thay quan: '.$quan);

            return null;
        }

        $branch = $brand->branches()->orderBy('id')->first();

        if (! $branch) {
            $this->error('Quan '.$brand->name.' chua co dia diem nao.');
        }

        return $branch;
    }

    protected function xoaDonDaNhap(): int
    {
        $truyVan = Booking::where('internal_note', 'like', self::DAU.'%');
        $so = $truyVan->count();

        if ($so === 0) {
            $this->info('Khong co don nao nhap tu Nightify.');

            return self::SUCCESS;
        }

        if (! $this->option('ghi') && ! $this->confirm('Xóa '.$so.' đơn đã nhập từ Nightify?', false)) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($truyVan) {
            $ids = $truyVan->pluck('id');
            DB::table('booking_dining_table')->whereIn('booking_id', $ids)->delete();
            Booking::whereIn('id', $ids)->delete();
        });

        $this->info('Đã xóa '.$so.' đơn.');

        return self::SUCCESS;
    }
}
