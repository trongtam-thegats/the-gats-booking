<?php

namespace App\Services;

use App\Models\Booking;
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
     * Tong quan do phu du lieu va quy mo.
     *
     * @param  array<int>|null  $branchIds
     * @return array<string, mixed>
     */
    public function overview(?array $branchIds): array
    {
        $hoaDon = Invoice::query()->choDiaDiem($branchIds)->thanhCong();

        $tong = (clone $hoaDon)->count();
        $coSdt = (clone $hoaDon)->coKhach()->count();
        $doanhThu = (float) (clone $hoaDon)->sum('total');
        $doanhThuNhanDien = (float) (clone $hoaDon)->coKhach()->sum('total');

        $khach = $this->tongHopHoaDon($branchIds);
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
    public function ranking(?array $branchIds, string $sapXep = 'spend', int $gioiHan = 100): Collection
    {
        $khach = $this->tongHopHoaDon($branchIds);

        if ($khach->isEmpty()) {
            return collect();
        }

        $sdt = $khach->keys()->all();
        $datBan = $this->tongHopDatBan($sdt, $branchIds);
        $the = PosCustomer::whereIn('phone', $sdt)->get()->keyBy('phone');

        return $khach
            ->map(function (array $k) use ($datBan, $the) {
                $k += $this->nhipGhe($k);
                $k['booking'] = $datBan[$k['phone']] ?? null;
                $k['card'] = $the[$k['phone']] ?? null;

                return $k;
            })
            ->sortByDesc(fn (array $k) => match ($sapXep) {
                'visits' => [$k['visits'], $k['spend']],
                'recent' => [$k['last_at']?->getTimestamp() ?? 0, $k['spend']],
                'risk' => [$k['visits'] >= 2 && $k['segment'] === 'nguy_co' ? 1 : 0, $k['spend']],
                default => [$k['spend'], $k['visits']],
            })
            ->take($gioiHan)
            ->values();
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

        return [
            'phone' => $phone,
            'name' => $co['name'],
            'card' => PosCustomer::where('phone', $phone)->first(),
            'stats' => $co,
            'habits' => $this->thoiQuen($dung),
            'invoices' => $hoaDon,
            'bookings' => $don,
            'booking_stats' => $this->chiSoDatBan($don),
        ];
    }

    /**
     * Gom hoa don theo so dien thoai.
     *
     * @param  array<int>|null  $branchIds
     * @return Collection<string, array<string, mixed>>
     */
    protected function tongHopHoaDon(?array $branchIds): Collection
    {
        return Invoice::query()
            ->choDiaDiem($branchIds)
            ->thanhCong()
            ->coKhach()
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
            ]]);
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
        ];
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
            'segment' => $this->tinhTrang($soLan, $vang, $nhip, $lanDau),
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
