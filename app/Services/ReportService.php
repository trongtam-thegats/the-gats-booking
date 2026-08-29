<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * So lieu bao cao, bam theo dung quy trinh xu ly mot don dat ban:
 *
 *   Khach gui yeu cau -> Quan xac nhan -> Khach den -> Hoan tat
 *                    \-> Huy          \-> Khong den
 *
 * Cac con so deu tinh trong PHP thay vi viet ham ngay thang cua MySQL, de
 * chay dung tren ca MySQL lan SQLite (moi truong test) va de doc lai khi can
 * doi cach tinh. Luong du lieu mot quan trong vai thang chi vai tram dong nen
 * khong co van de ve toc do.
 */
class ReportService
{
    /** Trang thai duoc tinh la "khach da den". */
    public const ARRIVED = [Booking::STATUS_SEATED, Booking::STATUS_COMPLETED];

    /**
     * Toan bo so lieu cho mot ky bao cao.
     *
     * @param  array<int, int>|null  $branchIds  null = tat ca
     * @return array<string, mixed>
     */
    public function build(string $from, string $to, ?array $branchIds): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        // Carbon 3 tra ve so thuc; ep ve so nguyen de so sanh === o cho khac khong bi lech.
        $days = (int) $start->diffInDays($end) + 1;

        $current = $this->load($start, $end, $branchIds);
        $previous = $this->load(
            $start->copy()->subDays($days),
            $start->copy()->subDay(),
            $branchIds
        );

        return [
            'range' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'days' => $days,
                'previous_from' => $start->copy()->subDays($days)->toDateString(),
                'previous_to' => $start->copy()->subDay()->toDateString(),
            ],
            'totals' => $this->totals($current),
            'previous' => $this->totals($previous),
            'funnel' => $this->funnel($current),
            'series' => $this->series($current, $start, $end, $days),
            'by_hour' => $this->byHour($current),
            'by_weekday' => $this->byWeekday($current),
            'by_source' => $this->bySource($current),
            'guests' => $this->guestMix($current, $start),
            'lead_time' => $this->leadTime($current),
            'no_show_guests' => $this->worstNoShows($current),
            'by_branch' => $this->byBranch($current, $branchIds),
            'capacity' => $this->capacity($current, $branchIds, $days),
        ];
    }

    /** @param array<int, int>|null $branchIds */
    protected function load(Carbon $start, Carbon $end, ?array $branchIds): Collection
    {
        return Booking::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->with('branch:id,name,brand_id')
            ->get([
                'id', 'branch_id', 'customer_phone', 'party_size', 'booking_date',
                'start_time', 'status', 'source', 'created_at', 'confirmed_at',
            ]);
    }

    /** @return array<string, float|int> */
    protected function totals(Collection $rows): array
    {
        $total = $rows->count();
        $arrived = $rows->whereIn('status', self::ARRIVED);
        $cancelled = $rows->where('status', Booking::STATUS_CANCELLED)->count();
        $noShow = $rows->where('status', Booking::STATUS_NO_SHOW)->count();
        $pending = $rows->where('status', Booking::STATUS_PENDING)->count();

        // Mau so cua ti le den: nhung don da duoc quan nhan, tuc la bo di
        // cac don khach tu huy va cac don con dang cho duyet.
        $settled = $arrived->count() + $noShow;

        return [
            'bookings' => $total,
            'guests' => (int) $arrived->sum('party_size'),
            'arrived' => $arrived->count(),
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'pending' => $pending,
            'arrival_rate' => $settled > 0 ? round($arrived->count() / $settled * 100, 1) : 0.0,
            'no_show_rate' => $settled > 0 ? round($noShow / $settled * 100, 1) : 0.0,
            'cancel_rate' => $total > 0 ? round($cancelled / $total * 100, 1) : 0.0,
            'avg_party' => $total > 0 ? round($rows->avg('party_size'), 1) : 0.0,
            'median_confirm_minutes' => $this->medianConfirmMinutes($rows),
        ];
    }

    /**
     * Thoi gian tu luc khach gui yeu cau den luc quan bam xac nhan, tinh bang phut.
     * Dung trung vi thay vi trung binh vi mot don bi quen ca ngay se keo lech
     * trung binh, con trung vi van phan anh dung thoi quen xu ly hang ngay.
     */
    protected function medianConfirmMinutes(Collection $rows): ?int
    {
        $minutes = $rows
            ->filter(fn (Booking $b) => $b->confirmed_at && $b->created_at)
            ->map(fn (Booking $b) => (int) $b->created_at->diffInMinutes($b->confirmed_at, false))
            // Xac nhan truoc ca luc tao la du lieu hong; bo qua chu khong ep ve 0,
            // vi mot loat so 0 gia se keo trung vi xuong sai.
            ->filter(fn (int $minutes) => $minutes >= 0)
            ->sort()
            ->values();

        if ($minutes->isEmpty()) {
            return null;
        }

        $middle = intdiv($minutes->count(), 2);

        return $minutes->count() % 2 === 1
            ? (int) $minutes[$middle]
            : (int) round(($minutes[$middle - 1] + $minutes[$middle]) / 2);
    }

    /**
     * Bon buoc cua quy trinh, kem ti le di tiep va so roi lai o moi buoc.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function funnel(Collection $rows): array
    {
        $requested = $rows->count();
        $confirmed = $rows->whereNotIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CANCELLED])->count();
        $arrived = $rows->whereIn('status', self::ARRIVED)->count();
        $completed = $rows->where('status', Booking::STATUS_COMPLETED)->count();

        $steps = [
            ['key' => 'requested', 'label' => 'Khách gửi yêu cầu', 'value' => $requested],
            ['key' => 'confirmed', 'label' => 'Quán xác nhận', 'value' => $confirmed],
            ['key' => 'arrived', 'label' => 'Khách đến', 'value' => $arrived],
            ['key' => 'completed', 'label' => 'Hoàn tất', 'value' => $completed],
        ];

        foreach ($steps as $index => &$step) {
            $step['share'] = $requested > 0 ? round($step['value'] / $requested * 100, 1) : 0.0;
            $previous = $index === 0 ? null : $steps[$index - 1]['value'];
            $step['dropped'] = $previous === null ? null : $previous - $step['value'];
            $step['step_rate'] = $previous ? round($step['value'] / $previous * 100, 1) : null;
        }

        return $steps;
    }

    /**
     * Chuoi so lieu theo thoi gian. Khoang tren 45 ngay thi gop theo tuan:
     * ve 90 cot tren man hinh dien thoai se ra nhung soi chi 4px, khong doc
     * duoc ma cung khong bam duoc.
     *
     * @return array{granularity: string, unit: string, rows: array<int, array<string, mixed>>}
     */
    protected function series(Collection $rows, Carbon $start, Carbon $end, int $days): array
    {
        $daily = $this->byDay($rows, $start, $end);

        if ($days <= 45) {
            return ['granularity' => 'day', 'unit' => 'ngày', 'rows' => $daily];
        }

        $weeks = [];

        foreach ($daily as $day) {
            $monday = Carbon::parse($day['date'])->startOfWeek()->toDateString();

            if (! isset($weeks[$monday])) {
                $weeks[$monday] = [
                    'date' => $monday,
                    'label' => Carbon::parse($monday)->format('d/m'),
                    'bookings' => 0, 'guests' => 0, 'arrived' => 0, 'cancelled' => 0, 'no_show' => 0,
                ];
            }

            foreach (['bookings', 'guests', 'arrived', 'cancelled', 'no_show'] as $key) {
                $weeks[$monday][$key] += $day[$key];
            }
        }

        return ['granularity' => 'week', 'unit' => 'tuần', 'rows' => array_values($weeks)];
    }

    /** @return array<int, array<string, mixed>> */
    protected function byDay(Collection $rows, Carbon $start, Carbon $end): array
    {
        $grouped = $rows->groupBy(fn (Booking $b) => $b->booking_date->toDateString());
        $out = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $slice = $grouped->get($key, collect());

            $out[] = [
                'date' => $key,
                'label' => $day->format('d/m'),
                'weekday' => $day->dayOfWeekIso,
                'bookings' => $slice->count(),
                'guests' => (int) $slice->whereIn('status', self::ARRIVED)->sum('party_size'),
                'arrived' => $slice->whereIn('status', self::ARRIVED)->count(),
                'cancelled' => $slice->where('status', Booking::STATUS_CANCELLED)->count(),
                'no_show' => $slice->where('status', Booking::STATUS_NO_SHOW)->count(),
            ];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    protected function byHour(Collection $rows): array
    {
        $counts = [];

        foreach ($rows as $booking) {
            $hour = (int) substr((string) $booking->start_time, 0, 2);
            $counts[$hour] = ($counts[$hour] ?? 0) + 1;
        }

        if (! $counts) {
            return [];
        }

        // Gio mo cua vat qua nua dem, nen xep 00-06 xuong cuoi cho dung thu tu dem.
        $order = array_merge(range(7, 23), range(0, 6));
        $out = [];

        foreach ($order as $hour) {
            if (! isset($counts[$hour])) {
                continue;
            }

            $out[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'bookings' => $counts[$hour],
            ];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    protected function byWeekday(Collection $rows): array
    {
        $names = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'CN'];
        $out = [];

        foreach ($names as $iso => $name) {
            $slice = $rows->filter(fn (Booking $b) => $b->booking_date->dayOfWeekIso === $iso);

            $out[] = [
                'weekday' => $iso,
                'label' => $name,
                'bookings' => $slice->count(),
                'guests' => (int) $slice->whereIn('status', self::ARRIVED)->sum('party_size'),
            ];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    protected function bySource(Collection $rows): array
    {
        $labels = ['online' => 'Đặt online', 'phone' => 'Điện thoại', 'walk_in' => 'Khách vãng lai'];
        $total = $rows->count();
        $out = [];

        foreach ($labels as $key => $label) {
            $slice = $rows->where('source', $key);
            $settled = $slice->whereIn('status', self::ARRIVED)->count() + $slice->where('status', Booking::STATUS_NO_SHOW)->count();

            $out[] = [
                'source' => $key,
                'label' => $label,
                'bookings' => $slice->count(),
                'share' => $total > 0 ? round($slice->count() / $total * 100, 1) : 0.0,
                'no_show_rate' => $settled > 0
                    ? round($slice->where('status', Booking::STATUS_NO_SHOW)->count() / $settled * 100, 1)
                    : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Khach moi hay khach quay lai: xet xem so dien thoai do da tung dat truoc
     * ngay bat dau ky bao cao chua.
     *
     * @return array<string, int|float>
     */
    protected function guestMix(Collection $rows, Carbon $start): array
    {
        $phones = $rows->pluck('customer_phone')->unique()->values();

        if ($phones->isEmpty()) {
            return ['unique' => 0, 'returning' => 0, 'new' => 0, 'returning_rate' => 0.0];
        }

        $seenBefore = Booking::query()
            ->whereIn('customer_phone', $phones->all())
            ->where('booking_date', '<', $start->toDateString())
            ->pluck('customer_phone')
            ->unique();

        $returning = $phones->filter(fn ($phone) => $seenBefore->contains($phone))->count();

        return [
            'unique' => $phones->count(),
            'returning' => $returning,
            'new' => $phones->count() - $returning,
            'returning_rate' => round($returning / $phones->count() * 100, 1),
        ];
    }

    /**
     * Khach dat truoc bao lau, chia theo nhom. Giup quyet dinh nen mo nhan dat
     * truoc bao nhieu ngay va co nen nhac lich som hon khong.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function leadTime(Collection $rows): array
    {
        $buckets = [
            ['key' => 'same_day', 'label' => 'Trong ngày', 'min' => 0, 'max' => 0],
            ['key' => 'next_day', 'label' => 'Trước 1 ngày', 'min' => 1, 'max' => 1],
            ['key' => 'week', 'label' => 'Trước 2–7 ngày', 'min' => 2, 'max' => 7],
            ['key' => 'far', 'label' => 'Trước hơn 7 ngày', 'min' => 8, 'max' => null],
        ];

        $counts = array_fill_keys(array_column($buckets, 'key'), 0);

        foreach ($rows as $booking) {
            if (! $booking->created_at) {
                continue;
            }

            $days = $booking->created_at->copy()->startOfDay()
                ->diffInDays($booking->booking_date->copy()->startOfDay(), false);
            $days = max(0, (int) $days);

            foreach ($buckets as $bucket) {
                if ($days >= $bucket['min'] && ($bucket['max'] === null || $days <= $bucket['max'])) {
                    $counts[$bucket['key']]++;
                    break;
                }
            }
        }

        $total = array_sum($counts);

        return array_map(fn (array $bucket) => [
            'label' => $bucket['label'],
            'bookings' => $counts[$bucket['key']],
            'share' => $total > 0 ? round($counts[$bucket['key']] / $total * 100, 1) : 0.0,
        ], $buckets);
    }

    /** @return array<int, array<string, mixed>> */
    protected function worstNoShows(Collection $rows, int $limit = 8): array
    {
        return $rows->where('status', Booking::STATUS_NO_SHOW)
            ->groupBy('customer_phone')
            ->map(fn (Collection $group, string $phone) => [
                'phone' => $phone,
                'no_show' => $group->count(),
                'guests' => (int) $group->sum('party_size'),
            ])
            ->sortByDesc('no_show')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function byBranch(Collection $rows, ?array $branchIds): array
    {
        if ($branchIds !== null && count($branchIds) <= 1) {
            return [];
        }

        return $rows->groupBy('branch_id')
            ->map(function (Collection $group) {
                $arrived = $group->whereIn('status', self::ARRIVED);
                $noShow = $group->where('status', Booking::STATUS_NO_SHOW)->count();
                $settled = $arrived->count() + $noShow;

                return [
                    'branch' => $group->first()->branch?->name ?? '—',
                    'bookings' => $group->count(),
                    'guests' => (int) $arrived->sum('party_size'),
                    'no_show_rate' => $settled > 0 ? round($noShow / $settled * 100, 1) : 0.0,
                    'cancel_rate' => $group->count() > 0
                        ? round($group->where('status', Booking::STATUS_CANCELLED)->count() / $group->count() * 100, 1)
                        : 0.0,
                ];
            })
            ->sortByDesc('bookings')
            ->values()
            ->all();
    }

    /**
     * Ti le lap day cho ngoi: so cho da nhan chia cho tong so cho quan co,
     * tinh gop ca ky. Con so nay tra loi cau "quan con du cho de day them khach
     * khong", chu khong phai suc chua tung khung gio.
     *
     * @param  array<int, int>|null  $branchIds
     * @return array<string, int|float>
     */
    protected function capacity(Collection $rows, ?array $branchIds, int $days): array
    {
        $seats = (int) Branch::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
            ->where('is_active', true)
            ->withSum(['diningTables as seats' => fn ($q) => $q->where('is_active', true)], 'seats_max')
            ->get()
            ->sum('seats');

        $booked = (int) $rows->whereIn('status', self::ARRIVED)->sum('party_size');
        // Mau so la so cho-dem: tong so cho nhan voi so dem trong ky.
        $seatNights = $seats * max(1, $days);

        return [
            'seats' => $seats,
            'guests' => $booked,
            'avg_per_night' => $days > 0 ? round($booked / $days, 1) : 0.0,
            'fill_rate' => $seats > 0 ? round($booked / $seatNights * 100, 1) : 0.0,
        ];
    }
}
