<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GuestNote;
use App\Models\Invoice;
use App\Models\PosCustomer;
use App\Support\SoDienThoai;
use App\Support\TenKhach;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ho so khach dung cho le tan: khach nay da den bao nhieu lan, co hay bo hen
 * khong, lan gan nhat ngoi dau.
 *
 * Cac con so deu suy ra tu lich su dat ban nen khong bao gio lech voi du lieu
 * that. Rieng ten va hang the thi doi chieu them voi the khach hang ben POS.
 */
class GuestProfileService
{
    /**
     * Ho so cua mot so dien thoai trong pham vi cac dia diem duoc phep xem.
     *
     * @param  array<int, int>|null  $branchIds  null = xem tat ca
     * @return array{
     *     phone: string,
     *     name: ?string,
     *     bookings: Collection<int, Booking>,
     *     total: int, completed: int, no_show: int, cancelled: int, upcoming: int,
     *     guests_served: int, first_visit: ?string, last_visit: ?string,
     *     note: ?GuestNote
     * }
     */
    public function forPhone(string $phone, ?array $branchIds, ?int $brandId = null): array
    {
        $digits = GuestNote::normalize($phone);

        $bookings = Booking::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
            // So dien thoai luu nguyen van khach nhap, nen so sanh phan chi so.
            ->cuaSoDienThoai($digits)
            ->with(['branch.brand', 'diningTables'])
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->get();

        $visited = $bookings->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_SEATED]);

        $note = $brandId
            ? GuestNote::where('brand_id', $brandId)->where('phone', $digits)->first()
            : null;

        // The khach hang ben POS: nguon ten dang tin hon ten khach tu go luc
        // dat ban, va la cho duy nhat co hang the va sinh nhat.
        $card = PosCustomer::where('phone', $digits)->first();

        return [
            'phone' => $digits,
            // Quy tac chon ten dung chung toan he thong, xem App\Support\TenKhach.
            'name' => TenKhach::chon($note, $card, $bookings->first()?->customer_name),
            'name_source' => TenKhach::nguon($note, $card),
            'card' => $card,
            'bookings' => $bookings,
            'total' => $bookings->count(),
            'completed' => $visited->count(),
            'no_show' => $bookings->where('status', Booking::STATUS_NO_SHOW)->count(),
            'cancelled' => $bookings->where('status', Booking::STATUS_CANCELLED)->count(),
            'upcoming' => $bookings->filter(fn (Booking $b) => $b->isActive() && $b->startsAt()->isFuture())->count(),
            'guests_served' => (int) $visited->sum('party_size'),
            'first_visit' => $visited->last()?->booking_date?->format('d/m/Y'),
            'last_visit' => $visited->first()?->booking_date?->format('d/m/Y'),
            'note' => $note,
        ];
    }

    /**
     * Tim khach theo so dien thoai, ten hoac ma dat ban.
     * Gom theo so dien thoai de moi khach chi hien mot dong.
     *
     * Quet ca BA nguon: dat ban, hoa don POS va danh sach khach hang POS. Ban
     * cu chi quet dat ban, trong khi 87% khach nhan dien duoc chua tung dat
     * ban nao - ho co trong danh sach khach hang ma go so vao thi "khong tim
     * thay". Dung bo nguon nao ra nua.
     *
     * @param  array<int, int>|null  $branchIds
     * @return Collection<int, array{phone: string, name: ?string, bookings: int, visits: int, last: ?Carbon, card: ?PosCustomer}>
     */
    public function search(string $term, ?array $branchIds, int $limit = 25): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $like = '%'.$term.'%';
        // Mot hai chu so lan trong ten hay ma thi dung dem di so dien thoai,
        // khong thi go "E7" cung khop hang nghin so.
        $chiSo = strlen((string) preg_replace('/\D/', '', $term)) >= 4
            ? SoDienThoai::bienTheChiSo(GuestNote::normalize($term))
            : [];

        /** @var array<string, array<string, mixed>> $khach */
        $khach = [];
        $gap = function (string $phone) use (&$khach): ?string {
            $phone = GuestNote::normalize($phone);

            if ($phone === '') {
                return null;
            }

            $khach[$phone] ??= ['phone' => $phone, 'names' => [], 'bookings' => 0, 'visits' => 0, 'last' => null];

            return $phone;
        };
        $moiHon = fn (?Carbon $a, ?Carbon $b) => $a === null || ($b !== null && $b->gt($a)) ? $b : $a;

        // 1. Dat ban.
        $datBan = Booking::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
            ->where(function ($q) use ($like, $chiSo) {
                $q->where('customer_name', 'like', $like)
                    ->orWhere('code', 'like', $like);

                foreach ($chiSo as $so) {
                    $q->orWhereRaw($this->digitsOnlyExpression().' like ?', ['%'.$so.'%']);
                }
            })
            ->orderByDesc('booking_date')
            ->limit(300)
            ->get(['customer_name', 'customer_phone', 'booking_date']);

        foreach ($datBan as $b) {
            if ($phone = $gap((string) $b->customer_phone)) {
                $khach[$phone]['bookings']++;
                $khach[$phone]['names'][] = $b->customer_name;
                $khach[$phone]['last'] = $moiHon($khach[$phone]['last'], $b->booking_date);
            }
        }

        // 2. Hoa don POS - dem bang SQL de khach ghe 400 lan van dem dung.
        // customer_phone cua hoa don da duoc chuan hoa luc nhap.
        $hoaDon = Invoice::query()
            ->choDiaDiem($branchIds)
            ->thanhCong()
            ->coKhach()
            ->where(function ($q) use ($like, $chiSo) {
                $q->where('customer_name', 'like', $like);

                foreach ($chiSo as $so) {
                    $q->orWhere('customer_phone', 'like', '%'.$so.'%');
                }
            })
            ->selectRaw('customer_phone, COUNT(*) as so_lan, MAX(paid_at) as gan_nhat, MAX(customer_name) as ten')
            ->groupBy('customer_phone')
            ->orderByDesc('gan_nhat')
            ->limit(300)
            ->get();

        foreach ($hoaDon as $h) {
            if ($phone = $gap((string) $h->customer_phone)) {
                $khach[$phone]['visits'] += (int) $h->so_lan;
                $khach[$phone]['names'][] = $h->ten;
                $khach[$phone]['last'] = $moiHon($khach[$phone]['last'], $h->gan_nhat ? Carbon::parse($h->gan_nhat) : null);
            }
        }

        // 3. Danh sach khach hang POS (toan chuoi, khong gan dia diem). Nguoi
        // chi xem mot quan thi chi dung de lay ten cho khach da gap o tren,
        // khong mo rong ra khach cua quan khac.
        $the = PosCustomer::query()
            ->where(function ($q) use ($like, $chiSo) {
                $q->where('name', 'like', $like);

                foreach ($chiSo as $so) {
                    $q->orWhere('phone', 'like', '%'.$so.'%');
                }
            })
            ->limit(300)
            ->get();

        if ($branchIds === null) {
            foreach ($the as $c) {
                $gap((string) $c->phone);
            }
        }

        if ($khach === []) {
            return collect();
        }

        $sdt = array_keys($khach);
        $theTheoSo = $the->keyBy('phone')
            ->union(PosCustomer::whereIn('phone', $sdt)->get()->keyBy('phone'));
        $ghiChu = GuestNote::whereIn('phone', $sdt)->whereNotNull('name')->get()->keyBy('phone');

        return collect($khach)
            ->map(function (array $k) use ($theTheoSo, $ghiChu) {
                $card = $theTheoSo[$k['phone']] ?? null;
                $k['card'] = $card;
                $k['name'] = TenKhach::chon($ghiChu[$k['phone']] ?? null, $card, ...$k['names']);
                unset($k['names']);

                return $k;
            })
            ->sortByDesc(fn (array $k) => [$k['last']?->timestamp ?? 0, $k['visits'] + $k['bookings']])
            ->take($limit)
            ->values();
    }

    /**
     * Bieu thuc SQL bo moi ky tu khong phai chu so khoi customer_phone.
     * Viet tay vi MySQL va SQLite khong co chung ham chuan hoa.
     */
    protected function digitsOnlyExpression(string $column = 'customer_phone'): string
    {
        $expression = $column;

        foreach ([' ', '-', '.', '(', ')', '+'] as $character) {
            $expression = "REPLACE($expression, '$character', '')";
        }

        return $expression;
    }
}
