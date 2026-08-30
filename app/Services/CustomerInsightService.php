<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\GuestNote;
use App\Models\Invoice;
use App\Models\PosCustomer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ghep ba nguon du lieu ve cung mot khach qua so dien thoai:
 *
 *   invoices      - khach tieu bao nhieu, ngoi cho nao, tra bang gi
 *   bookings      - khach dat ban bao nhieu lan, co den khong
 *   pos_customers - hang the, diem, sinh nhat
 *
 * Moi con so tinh trong PHP chu khong dung ham ngay thang cua MySQL, giong
 * ReportService, de chay duoc tren ca co so du lieu khac.
 */
class CustomerInsightService
{
    /** Bao lau khong ghe thi coi la khach moi den lan dau, tinh bang ngay. */
    public const NGAY_KHACH_MOI = 60;

    /** Cach xep loai tinh trang khach => nhan hien thi. */
    public const TINH_TRANG = [
        'deu_dan' => 'Đều đặn',
        'thua_dan' => 'Đang thưa dần',
        'nguy_co' => 'Nguy cơ rời bỏ',
        'mot_lan' => 'Mới ghé một lần',
        'khach_moi' => 'Khách mới',
    ];

    /**
     * Ket qua xem xet duoc coi la mot tinh trang da xac nhan, de len tinh trang
     * may tu suy ra.
     *
     * Nguoi da goi va biet chac thi lu du lieu doan gi cung khong bang. Rieng
     * "da lien he" khong nam day: no chi ghi nhan da goi, chua noi len dieu gi
     * ve quan he cua khach voi quan.
     *
     * @var array<string, string>
     */
    public const XAC_NHAN = [
        'se_quay_lai' => 'Hẹn sẽ quay lại',
        'khong_quan_tam' => 'Không quan tâm',
        'da_chuyen_di' => 'Đã chuyển đi xa',
        'so_sai' => 'Số sai, không liên lạc được',
        'da_roi_bo' => 'Đã rời bỏ',
        'khong_can' => 'Không cần chăm sóc',
    ];

    /** Tien to cua khoa tinh trang da xac nhan, de khong dung khoa voi TINH_TRANG. */
    public const TIEN_TO_XAC_NHAN = 'xn_';

    /**
     * Trang thai xem xet cua mot khach.
     *
     * "Da ghe lai" khong ai bam tay ca - he thong so lan ghe gan nhat voi thoi
     * diem danh dau de tu suy ra. Nho vay nhan khong bao gio muc: khach quay
     * lai la tu roi khoi danh sach can cham soc.
     *
     * @var array<string, string>
     */
    public const XEM_XET = [
        'chua_xem_xet' => 'Chưa xem xét',
        'da_xem_xet' => 'Đã xem xét',
        'da_ghe_lai' => 'Đã ghé lại',
    ];

    /**
     * Moi tinh trang co the hien ra, ca may suy ra lan nguoi xac nhan.
     *
     * @return array<string, string>
     */
    public static function moiTinhTrang(): array
    {
        $ket = self::TINH_TRANG;

        foreach (self::XAC_NHAN as $ma => $nhan) {
            $ket[self::TIEN_TO_XAC_NHAN.$ma] = $nhan;
        }

        return $ket;
    }

    public static function nhanTinhTrang(string $ma): string
    {
        return self::moiTinhTrang()[$ma] ?? $ma;
    }

    public static function laXacNhan(string $ma): bool
    {
        return str_starts_with($ma, self::TIEN_TO_XAC_NHAN);
    }

    /**
     * Tong quan do phu du lieu va quy mo.
     *
     * @param  array<int>|null  $branchIds
     * @return array<string, mixed>
     */
    public function overview(?array $branchIds, ?Carbon $tuNgay = null): array
    {
        $hoaDon = Invoice::query()->choDiaDiem($branchIds)->thanhCong()
            ->when($tuNgay, fn ($q) => $q->where('paid_at', '>=', $tuNgay));

        $tong = (clone $hoaDon)->count();
        $coSdt = (clone $hoaDon)->coKhach()->count();
        $doanhThu = (float) (clone $hoaDon)->sum('total');
        $doanhThuNhanDien = (float) (clone $hoaDon)->coKhach()->sum('total');

        $khach = $this->tongHopHoaDon($branchIds, $tuNgay);
        $quayLai = $khach->filter(fn ($k) => $k['visits'] >= 2);

        return [
            'invoices' => $tong,
            'invoices_with_phone' => $coSdt,
            'phone_rate' => $tong ? round($coSdt / $tong * 100, 1) : 0.0,
            'revenue' => $doanhThu,
            'revenue_identified' => $doanhThuNhanDien,
            'revenue_identified_rate' => $doanhThu > 0 ? round($doanhThuNhanDien / $doanhThu * 100, 1) : 0.0,
            'customers' => $khach->count(),
            'returning' => $quayLai->count(),
            'returning_revenue' => (float) $quayLai->sum('spend'),
            'first_paid_at' => (clone $hoaDon)->min('paid_at'),
            'last_paid_at' => (clone $hoaDon)->max('paid_at'),
        ];
    }

    /**
     * Danh sach khach da xep hang, kem moi chi so can de cham soc.
     *
     * @param  array<int>|null  $branchIds
     * @return Collection<int, array<string, mixed>>
     */
    public function ranking(
        ?array $branchIds,
        string $sapXep = 'spend',
        int $gioiHan = 100,
        array $loc = [],
        ?Carbon $tuNgay = null,
    ): Collection {
        return $this->locVaXep($this->tatCaKhach($branchIds, $tuNgay), $sapXep, $loc)->take($gioiHan);
    }

    /**
     * Moi khach da nhan dien duoc, kem chi so va trang thai - chua loc, chua xep.
     *
     * Tach rieng de trang co the vua dem tong theo tung nhom, vua hien mot phan
     * da loc, ma chi phai tinh mot lan.
     *
     * @param  array<int>|null  $branchIds
     * @return Collection<int, array<string, mixed>>
     */
    public function tatCaKhach(?array $branchIds, ?Carbon $tuNgay = null): Collection
    {
        $khach = $this->tongHopHoaDon($branchIds, $tuNgay);

        if ($khach->isEmpty()) {
            return collect();
        }

        $sdt = $khach->keys()->all();
        $datBan = $this->tongHopDatBan($sdt, $branchIds);
        $the = PosCustomer::whereIn('phone', $sdt)->get()->keyBy('phone');
        $ghiChu = $this->ghiChuKhach($sdt, $branchIds);

        return $khach
            ->map(function (array $k) use ($datBan, $the, $ghiChu) {
                $k += $this->nhipGhe($k);
                $k['booking'] = $datBan[$k['phone']] ?? null;
                $k['card'] = $the[$k['phone']] ?? null;
                $k['note'] = $ghiChu[$k['phone']] ?? null;
                $k['review'] = $this->trangThaiXemXet($k['note'], $k['last_at'], $k['booking']);
                $k['trang_thai'] = $this->tinhTrangHienThi($k['segment'], $k['note'], $k['review']);

                return $k;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $khach
     * @param  array<string, mixed>  $loc
     * @return Collection<int, array<string, mixed>>
     */
    public function locVaXep(Collection $khach, string $sapXep, array $loc = []): Collection
    {
        return $khach
            ->filter(fn (array $k) => $this->hopLoc($k, $loc))
            ->sortByDesc(fn (array $k) => match ($sapXep) {
                'visits' => [$k['visits'], $k['spend']],
                'recent' => [$k['last_at']?->getTimestamp() ?? 0, $k['spend']],
                // Da xac nhan xong thi khong con "gap" nua - do chinh la muc dich
                // cua viec danh dau: khoi goi lai nguoi vua goi.
                'risk' => [$k['visits'] >= 2 && $k['trang_thai'] === 'nguy_co' ? 1 : 0, $k['spend']],
                'avg' => [$k['avg'], $k['visits']],
                'vang' => [$k['days_since'] ?? -1, $k['spend']],
                default => [$k['spend'], $k['visits']],
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $k
     * @param  array<string, mixed>  $loc
     */
    protected function hopLoc(array $k, array $loc): bool
    {
        if (! empty($loc['segment']) && ! in_array($k['trang_thai'], (array) $loc['segment'], true)) {
            return false;
        }

        if (! empty($loc['review']) && ! in_array($k['review'], (array) $loc['review'], true)) {
            return false;
        }

        if (! empty($loc['tier'])) {
            $hang = $k['card']?->tier ?: '(không hạng)';

            if (! in_array($hang, (array) $loc['tier'], true)) {
                return false;
            }
        }

        if (isset($loc['visits_min']) && $k['visits'] < (int) $loc['visits_min']) {
            return false;
        }

        if (isset($loc['spend_min']) && $k['spend'] < (float) $loc['spend_min']) {
            return false;
        }

        if (isset($loc['vang_min']) && ($k['days_since'] ?? 0) < (int) $loc['vang_min']) {
            return false;
        }

        if (! empty($loc['co_dat_ban']) && ! $k['booking']) {
            return false;
        }

        if (! empty($loc['co_vang_mat']) && (int) ($k['booking']['no_show'] ?? 0) < 1) {
            return false;
        }

        if (! empty($loc['tim'])) {
            $tim = mb_strtolower(trim((string) $loc['tim']));
            $trong = mb_strtolower(($k['name'] ?? '').' '.$k['phone']);

            if ($tim !== '' && ! str_contains($trong, $tim)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ghi chu cua quan ve khach, tra cuu duoc theo so dien thoai.
     *
     * Ghi chu gan theo tung quan; khi dang xem ca chuoi thi lay ban duoc xem
     * xet gan nhat de khong bao mot khach "chua xem xet" trong khi quan khac
     * da xem roi.
     *
     * @param  array<int, string>  $sdt
     * @param  array<int>|null  $branchIds
     * @return Collection<string, GuestNote>
     */
    protected function ghiChuKhach(array $sdt, ?array $branchIds): Collection
    {
        $brandIds = Branch::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
            ->pluck('brand_id')
            ->filter()
            ->unique()
            ->all();

        return GuestNote::whereIn('phone', $sdt)
            ->when($brandIds, fn ($q) => $q->whereIn('brand_id', $brandIds))
            ->orderByRaw('reviewed_at is null')
            ->orderByDesc('reviewed_at')
            ->get()
            ->unique('phone')
            ->keyBy('phone');
    }

    /**
     * Khach da duoc xem xet chua, va da quay lai sau khi xem xet chua.
     *
     * @param  array<string, mixed>|null  $datBan
     */
    protected function trangThaiXemXet(?GuestNote $ghiChu, ?Carbon $lanCuoi, ?array $datBan): string
    {
        if (! $ghiChu?->reviewed_at) {
            return 'chua_xem_xet';
        }

        // Co hoa don moi sau khi danh dau thi khach da quay lai.
        if ($lanCuoi && $lanCuoi->gt($ghiChu->reviewed_at)) {
            return 'da_ghe_lai';
        }

        // Chua co hoa don moi nhung da dat ban lai cung tinh la quay lai.
        $ngayDat = $datBan['last_date'] ?? null;

        if ($ngayDat && Carbon::parse($ngayDat)->endOfDay()->gt($ghiChu->reviewed_at)) {
            return 'da_ghe_lai';
        }

        return 'da_xem_xet';
    }

    /**
     * Chan dung day du cua mot khach.
     *
     * @param  array<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    public function profile(string $phone, ?array $branchIds): ?array
    {
        $hoaDon = Invoice::query()
            ->choDiaDiem($branchIds)
            ->where('customer_phone', $phone)
            ->orderByDesc('paid_at')
            ->get();

        $don = Booking::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
            ->where('customer_phone', $phone)
            ->with('diningTables:id,code')
            ->orderByDesc('booking_date')
            ->get();

        if ($hoaDon->isEmpty() && $don->isEmpty()) {
            return null;
        }

        $dung = $hoaDon->reject(fn (Invoice $i) => $i->daHuy());
        $co = $this->chiSoHoaDon($dung, $phone, $hoaDon->firstWhere('customer_name', '!=', null)?->customer_name);
        $co += $this->nhipGhe($co);

        $datBan = $this->chiSoDatBan($don);
        $ghiChu = $this->ghiChuKhach([$phone], $branchIds)->get($phone);

        return [
            'phone' => $phone,
            'name' => $co['name'],
            'card' => PosCustomer::where('phone', $phone)->first(),
            'note' => $ghiChu,
            'review' => $xemXet = $this->trangThaiXemXet($ghiChu, $co['last_at'], [
                'last_date' => $don->max('booking_date'),
            ]),
            'trang_thai' => $this->tinhTrangHienThi($co['segment'], $ghiChu, $xemXet),
            'stats' => $co,
            'habits' => $this->thoiQuen($dung),
            'invoices' => $hoaDon,
            'bookings' => $don,
            'booking_stats' => $datBan,
        ];
    }

    /**
     * So lieu theo tung thang de ve bieu do.
     *
     * Gom trong PHP chu khong dung ham ngay thang cua MySQL, giong ReportService.
     *
     * @param  array<int>|null  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function theoThang(?array $branchIds, int $soThang = 18): array
    {
        $moc = now()->startOfMonth()->subMonths($soThang - 1);

        $hoaDon = Invoice::query()
            ->choDiaDiem($branchIds)
            ->thanhCong()
            ->where('paid_at', '>=', $moc)
            ->orderBy('paid_at')
            ->get(['paid_at', 'total', 'customer_phone']);

        // Lan ghe dau tien cua tung khach, tinh tren toan bo lich su chu khong
        // chi trong khoang dang xem - neu khong thi khach cu se bi goi la moi.
        $lanDau = $this->tongHopHoaDon($branchIds)->map(fn ($k) => $k['first_at']?->format('Y-m'));

        $thang = [];

        foreach ($hoaDon as $hd) {
            if (! $hd->paid_at) {
                continue;
            }

            $khoa = $hd->paid_at->format('Y-m');
            $thang[$khoa] ??= [
                'label' => $hd->paid_at->format('m/y'),
                'month' => $khoa,
                'invoices' => 0,
                'revenue' => 0.0,
                'identified' => 0,
                'new_customers' => [],
                'returning' => [],
            ];

            $thang[$khoa]['invoices']++;
            $thang[$khoa]['revenue'] += (float) $hd->total;

            $sdt = (string) $hd->customer_phone;

            if ($sdt === '') {
                continue;
            }

            $thang[$khoa]['identified']++;

            if (($lanDau[$sdt] ?? null) === $khoa) {
                $thang[$khoa]['new_customers'][$sdt] = true;
            } else {
                $thang[$khoa]['returning'][$sdt] = true;
            }
        }

        ksort($thang);

        return array_values(array_map(fn (array $t) => [
            'label' => $t['label'],
            'month' => $t['month'],
            'invoices' => $t['invoices'],
            'revenue' => $t['revenue'],
            'identified' => $t['identified'],
            'anonymous' => $t['invoices'] - $t['identified'],
            'new_customers' => count($t['new_customers']),
            'returning' => count($t['returning']),
        ], $thang));
    }

    /**
     * Phan bo khach theo so lan ghe.
     *
     * @param  Collection<int, array<string, mixed>>  $khach
     * @return array<int, array{label: string, value: int, spend: float}>
     */
    public function theoSoLanGhe(Collection $khach): array
    {
        $bac = [
            '1 lần' => fn (int $n) => $n === 1,
            '2–4 lần' => fn (int $n) => $n >= 2 && $n <= 4,
            '5–9 lần' => fn (int $n) => $n >= 5 && $n <= 9,
            '10–19 lần' => fn (int $n) => $n >= 10 && $n <= 19,
            'Từ 20 lần' => fn (int $n) => $n >= 20,
        ];

        $ket = [];

        foreach ($bac as $nhan => $hop) {
            $nhom = $khach->filter(fn (array $k) => $hop((int) $k['visits']));

            $ket[] = [
                'label' => $nhan,
                'value' => $nhom->count(),
                'spend' => (float) $nhom->sum('spend'),
            ];
        }

        return $ket;
    }

    /**
     * Gom hoa don theo so dien thoai.
     *
     * @param  array<int>|null  $branchIds
     * @return Collection<string, array<string, mixed>>
     */
    protected function tongHopHoaDon(?array $branchIds, ?Carbon $tuNgay = null): Collection
    {
        // Lan ghe dau tien tinh tren TOAN BO lich su, khong theo khoang dang xem.
        // Neu khong thi xem "1 thang gan nhat" se thay ai cung la khach moi,
        // ke ca nguoi da gan bo voi quan tu hai nam truoc.
        $lanDauThat = $tuNgay ? $this->lanGheDauTien($branchIds) : collect();

        return Invoice::query()
            ->choDiaDiem($branchIds)
            ->thanhCong()
            ->coKhach()
            ->when($tuNgay, fn ($q) => $q->where('paid_at', '>=', $tuNgay))
            ->selectRaw('customer_phone, MAX(customer_name) as ten, COUNT(*) as so_lan, '
                .'SUM(total) as tong_chi, SUM(tip) as tong_tip, '
                .'MIN(paid_at) as lan_dau, MAX(paid_at) as lan_cuoi, '
                .'MAX(total) as cao_nhat, SUM(party_size) as tong_khach')
            ->groupBy('customer_phone')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->customer_phone => [
                'phone' => $r->customer_phone,
                'name' => $r->ten ?: null,
                'visits' => (int) $r->so_lan,
                'spend' => (float) $r->tong_chi,
                'tip' => (float) $r->tong_tip,
                'avg' => (int) $r->so_lan > 0 ? (float) $r->tong_chi / (int) $r->so_lan : 0.0,
                'max' => (float) $r->cao_nhat,
                'guests' => (int) $r->tong_khach,
                'first_at' => $r->lan_dau ? Carbon::parse($r->lan_dau) : null,
                'last_at' => $r->lan_cuoi ? Carbon::parse($r->lan_cuoi) : null,
                'first_ever' => ($lanDauThat[$r->customer_phone] ?? null)
                    ? Carbon::parse($lanDauThat[$r->customer_phone])
                    : ($r->lan_dau ? Carbon::parse($r->lan_dau) : null),
            ]]);
    }

    /**
     * Lan ghe dau tien cua tung khach tren toan bo lich su.
     *
     * @param  array<int>|null  $branchIds
     * @return Collection<string, string>
     */
    protected function lanGheDauTien(?array $branchIds): Collection
    {
        return Invoice::query()
            ->choDiaDiem($branchIds)
            ->thanhCong()
            ->coKhach()
            ->selectRaw('customer_phone, MIN(paid_at) as lan_dau')
            ->groupBy('customer_phone')
            ->pluck('lan_dau', 'customer_phone');
    }

    /**
     * Gom don dat ban theo so dien thoai.
     *
     * @param  array<int, string>  $sdt
     * @param  array<int>|null  $branchIds
     * @return Collection<string, array<string, mixed>>
     */
    protected function tongHopDatBan(array $sdt, ?array $branchIds): Collection
    {
        return Booking::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
            ->whereIn('customer_phone', $sdt)
            ->selectRaw('customer_phone, COUNT(*) as so_don, '
                ."SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as vang, "
                ."SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as huy, "
                .'MAX(booking_date) as gan_nhat')
            ->groupBy('customer_phone')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->customer_phone => [
                'bookings' => (int) $r->so_don,
                'no_show' => (int) $r->vang,
                'cancelled' => (int) $r->huy,
                'last_date' => $r->gan_nhat,
            ]]);
    }

    /**
     * @param  Collection<int, Invoice>  $hoaDon
     * @return array<string, mixed>
     */
    protected function chiSoHoaDon(Collection $hoaDon, string $phone, ?string $ten): array
    {
        $soLan = $hoaDon->count();
        $tongChi = (float) $hoaDon->sum('total');

        return [
            'phone' => $phone,
            'name' => $ten ?: $hoaDon->first()?->customer_name,
            'visits' => $soLan,
            'spend' => $tongChi,
            'tip' => (float) $hoaDon->sum('tip'),
            'avg' => $soLan ? $tongChi / $soLan : 0.0,
            'max' => (float) $hoaDon->max('total'),
            'guests' => (int) $hoaDon->sum('party_size'),
            'first_at' => $hoaDon->min('paid_at') ? Carbon::parse($hoaDon->min('paid_at')) : null,
            'last_at' => $hoaDon->max('paid_at') ? Carbon::parse($hoaDon->max('paid_at')) : null,
            'first_ever' => $hoaDon->min('paid_at') ? Carbon::parse($hoaDon->min('paid_at')) : null,
        ];
    }

    /**
     * Tinh trang hien ra cho nguoi dung.
     *
     * Nguoi xac nhan de len may suy ra: da goi va biet chac khach chuyen di xa
     * thi khong con ly do gi hien "nguy co roi bo" nua.
     *
     * Nhung khach quay lai roi thi xac nhan cu het hieu luc - luc do lai lay
     * theo du lieu, vi du lieu moi hon loi xac nhan.
     */
    protected function tinhTrangHienThi(string $segment, ?GuestNote $ghiChu, string $xemXet): string
    {
        $ketQua = $ghiChu?->review_outcome;

        if ($xemXet === 'da_ghe_lai' || ! $ketQua || ! isset(self::XAC_NHAN[$ketQua])) {
            return $segment;
        }

        return self::TIEN_TO_XAC_NHAN.$ketQua;
    }

    /**
     * Nhip ghe quan va tinh trang cua khach.
     *
     * Nhip = khoang cach trung binh giua cac lan ghe. Vang qua ba lan nhip cua
     * chinh khach do thi coi la co nguy co roi bo - do moi la cai dang bao dong,
     * chu "hai thang khong ghe" voi khach thang nao cung den la chuyen khac han
     * voi khach nua nam moi den mot lan.
     *
     * @param  array<string, mixed>  $k
     * @return array<string, mixed>
     */
    protected function nhipGhe(array $k): array
    {
        $lanCuoi = $k['last_at'] ?? null;
        $lanDau = $k['first_at'] ?? null;
        $soLan = (int) ($k['visits'] ?? 0);

        $vang = $lanCuoi ? (int) $lanCuoi->diffInDays(now()) : null;
        $nhip = null;

        if ($soLan >= 2 && $lanDau && $lanCuoi) {
            $khoang = (int) $lanDau->diffInDays($lanCuoi);
            $nhip = $khoang > 0 ? (int) round($khoang / ($soLan - 1)) : 0;
        }

        return [
            'days_since' => $vang,
            'cadence' => $nhip,
            'segment' => $this->tinhTrang($soLan, $vang, $nhip, $k['first_ever'] ?? $lanDau),
        ];
    }

    protected function tinhTrang(int $soLan, ?int $vang, ?int $nhip, ?Carbon $lanDau): string
    {
        if ($lanDau && $lanDau->diffInDays(now()) <= self::NGAY_KHACH_MOI && $soLan <= 2) {
            return 'khach_moi';
        }

        if ($soLan < 2) {
            return 'mot_lan';
        }

        if ($vang === null || $nhip === null) {
            return 'thua_dan';
        }

        // Nhip 0 ngay (nhieu hoa don trong cung mot ngay) thi lay moc 30 ngay
        // cho khoi chia cho 0.
        $moc = max($nhip, 7);

        if ($vang <= $moc * 1.5) {
            return 'deu_dan';
        }

        return $vang > $moc * 3 ? 'nguy_co' : 'thua_dan';
    }

    /**
     * Thoi quen cua khach: hay ghe thu may, gio nao, ngoi dau, tra bang gi.
     *
     * @param  Collection<int, Invoice>  $hoaDon
     * @return array<string, array<int, array{label: string, count: int, share: float}>>
     */
    protected function thoiQuen(Collection $hoaDon): array
    {
        $thu = [];
        $gio = [];

        foreach ($hoaDon as $hd) {
            if (! $hd->paid_at) {
                continue;
            }

            // Hoa don chot sau nua dem van thuoc ve dem hom truoc.
            $dem = $hd->paid_at->hour < 6 ? $hd->paid_at->copy()->subDay() : $hd->paid_at;

            $thu[$dem->dayOfWeek] = ($thu[$dem->dayOfWeek] ?? 0) + 1;
            $gio[$hd->paid_at->hour] = ($gio[$hd->paid_at->hour] ?? 0) + 1;
        }

        $tenThu = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

        return [
            'weekday' => $this->xepHang(collect($thu)->mapWithKeys(fn ($n, $i) => [$tenThu[$i] => $n])),
            'hour' => $this->xepHang(collect($gio)->mapWithKeys(fn ($n, $h) => [sprintf('%02d:00', $h) => $n])),
            'area' => $this->xepHang($hoaDon->groupBy('area')->map->count()),
            'table' => $this->xepHang($hoaDon->groupBy('table_code')->map->count()),
            'payment' => $this->xepHang($hoaDon->groupBy('payment_method')->map->count()),
        ];
    }

    /**
     * @param  Collection<string, int>  $dem
     * @return array<int, array{label: string, count: int, share: float}>
     */
    protected function xepHang(Collection $dem): array
    {
        $tong = max(1, (int) $dem->sum());

        return $dem
            ->reject(fn ($n, $nhan) => trim((string) $nhan) === '')
            ->sortDesc()
            ->take(6)
            ->map(fn ($n, $nhan) => [
                'label' => (string) $nhan,
                'count' => (int) $n,
                'share' => (int) round($n / $tong * 100),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Booking>  $don
     * @return array<string, mixed>
     */
    protected function chiSoDatBan(Collection $don): array
    {
        $den = $don->where('status', Booking::STATUS_COMPLETED)->count();
        $vang = $don->where('status', Booking::STATUS_NO_SHOW)->count();

        return [
            'total' => $don->count(),
            'arrived' => $den,
            'no_show' => $vang,
            'cancelled' => $don->where('status', Booking::STATUS_CANCELLED)->count(),
            // Mau so la den + khong den, giong cach ReportService tinh ti le den.
            'show_rate' => ($den + $vang) > 0 ? (int) round($den / ($den + $vang) * 100) : null,
        ];
    }
}
