<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GuestNote;
use Illuminate\Support\Collection;

/**
 * Ho so khach dung cho le tan: khach nay da den bao nhieu lan, co hay bo hen
 * khong, lan gan nhat ngoi dau.
 *
 * Khong co bang khach hang rieng - tat ca suy ra tu lich su dat ban, nen
 * khong bao gio lech voi du lieu that.
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
            ->whereRaw($this->digitsOnlyExpression().' = ?', [$digits])
            ->with(['branch.brand', 'diningTables'])
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->get();

        $visited = $bookings->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_SEATED]);

        $note = $brandId
            ? GuestNote::where('brand_id', $brandId)->where('phone', $digits)->first()
            : null;

        return [
            'phone' => $digits,
            'name' => $note?->name ?? $bookings->first()?->customer_name,
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
     * @param  array<int, int>|null  $branchIds
     * @return Collection<int, array{phone: string, name: string, total: int, last: ?Booking}>
     */
    public function search(string $term, ?array $branchIds, int $limit = 25): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $digits = GuestNote::normalize($term);
        $like = '%'.$term.'%';

        return Booking::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
            ->where(function ($q) use ($like, $digits) {
                $q->where('customer_name', 'like', $like)
                    ->orWhere('code', 'like', $like);

                if ($digits !== '') {
                    $q->orWhereRaw($this->digitsOnlyExpression().' like ?', ['%'.$digits.'%']);
                }
            })
            ->with(['branch', 'diningTables'])
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->limit(300)
            ->get()
            ->groupBy(fn (Booking $b) => GuestNote::normalize($b->customer_phone))
            ->map(fn (Collection $rows, string $phone) => [
                'phone' => $phone,
                'name' => $rows->first()->customer_name,
                'total' => $rows->count(),
                'last' => $rows->first(),
            ])
            ->sortByDesc(fn (array $row) => $row['last']->booking_date->timestamp)
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
