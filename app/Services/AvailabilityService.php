<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\DiningTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tinh khung gio va ban trong cho mot chi nhanh.
 *
 * Quy uoc thoi gian: moi booking thuoc ve mot "dem kinh doanh" (booking_date).
 * Chi nhanh co the dong cua sau nua dem (vi du 17:00 -> 02:00), nen moi moc gio
 * duoc quy doi ve so phut tinh tu 00:00 cua dem do; gio nao nho hon gio mo cua
 * thi cong them 1440 phut (thuoc rang sang hom sau).
 */
class AvailabilityService
{
    public const MAX_TABLES_PER_BOOKING = 4;

    public function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $h * 60 + $m;
    }

    public function toTimeString(int $minutes): string
    {
        $minutes %= 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Quy doi ve truc thoi gian cua dem kinh doanh. */
    public function normalize(int $minutes, int $openMinutes): int
    {
        return $minutes < $openMinutes ? $minutes + 1440 : $minutes;
    }

    public function openMinutes(Branch $branch): int
    {
        return $this->toMinutes((string) $branch->open_time);
    }

    /** Gio dong cua tren truc dem kinh doanh (luon > gio mo cua). */
    public function closeMinutes(Branch $branch): int
    {
        $open = $this->openMinutes($branch);
        $close = $this->toMinutes((string) $branch->close_time);

        return $close <= $open ? $close + 1440 : $close;
    }

    /**
     * Danh sach moc gio nhan khach cua mot ngay, dang ['14:30', '15:00', ...].
     *
     * @return array<int, string>
     */
    public function slotTimes(Branch $branch): array
    {
        $open = $this->openMinutes($branch);
        $close = $this->closeMinutes($branch);
        $step = max(15, (int) $branch->slot_minutes);

        // Moc nhan khach cuoi cung phai con du mot luot ngoi truoc gio dong cua,
        // nhung luon giu it nhat mot moc.
        $lastStart = max($open, $close - (int) $branch->turn_minutes);

        // Quan khai bao gio chot booking rieng thi lay theo moc do. Vi du quan
        // dong cua 02:00 nhung chi nhan dat ban den 01:00: sau 01:00 quan van
        // mo, chi khong nhan khach dat moi.
        if ($branch->last_booking_time) {
            $lastStart = $this->normalize($this->toMinutes((string) $branch->last_booking_time), $open);
            $lastStart = min($lastStart, $close);
        }

        $slots = [];
        for ($m = $open; $m <= $lastStart; $m += $step) {
            $slots[] = $this->toTimeString($m);
        }

        return $slots;
    }

    /**
     * Moc gio chot nhan dat ban, dang "01:00". Tra ve null neu quan khong khai
     * bao rieng (khi do gio chot chinh la moc cuoi trong danh sach khung gio).
     */
    public function lastBookingLabel(Branch $branch): ?string
    {
        return $branch->last_booking_time
            ? substr((string) $branch->last_booking_time, 0, 5)
            : null;
    }

    /** Gio ket thuc du kien cua mot luot dat, khong vuot qua gio dong cua. */
    public function endMinutesFor(Branch $branch, int $startMinutes): int
    {
        return min($startMinutes + (int) $branch->turn_minutes, $this->closeMinutes($branch));
    }

    /**
     * Chi nhanh co nghi trong khoang thoi gian nay khong.
     */
    public function isClosed(Branch $branch, string $date, ?int $startMinutes = null, ?int $endMinutes = null): bool
    {
        return $this->closedIn(
            $this->closuresFor($branch, $date),
            $this->openMinutes($branch),
            $startMinutes,
            $endMinutes
        );
    }

    /** Lich nghi cua mot ngay. Tach rieng de goi mot lan roi dung lai cho ca ngay. */
    protected function closuresFor(Branch $branch, string $date): Collection
    {
        return $branch->closures()->whereDate('date', $date)->get();
    }

    /**
     * Tinh tren danh sach lich nghi da doc san, khong cham vao co so du lieu.
     *
     * @param  Collection<int, \App\Models\BranchClosure>  $closures
     */
    protected function closedIn(Collection $closures, int $open, ?int $startMinutes, ?int $endMinutes): bool
    {
        foreach ($closures as $closure) {
            if ($closure->isFullDay()) {
                return true;
            }

            if ($startMinutes === null || $endMinutes === null) {
                continue;
            }

            $cStart = $this->normalize($this->toMinutes((string) $closure->start_time), $open);
            $cEnd = $this->normalize($this->toMinutes((string) $closure->end_time), $open);
            if ($cEnd <= $cStart) {
                $cEnd += 1440;
            }

            if ($startMinutes < $cEnd && $cStart < $endMinutes) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cac ban con trong trong khung gio yeu cau.
     *
     * @return Collection<int, DiningTable>
     */
    public function availableTables(
        Branch $branch,
        string $date,
        int $startMinutes,
        int $endMinutes,
        ?int $areaId = null,
        ?int $ignoreBookingId = null,
        bool $onlineOnly = false,
    ): Collection {
        $tables = $this->bookableTables($branch, $areaId, $onlineOnly);

        $busyIds = $this->busyTableIds($branch, $date, $startMinutes, $endMinutes, $ignoreBookingId);

        return $tables->reject(fn (DiningTable $t) => in_array($t->id, $busyIds, true))->values();
    }

    /**
     * Cac ban co the xep khach, chua tinh den lich dat. Danh sach nay khong doi
     * theo khung gio nen chi can doc mot lan cho ca ngay.
     *
     * @return Collection<int, DiningTable>
     */
    protected function bookableTables(Branch $branch, ?int $areaId = null, bool $onlineOnly = false): Collection
    {
        return $branch->diningTables()
            ->where('is_active', true)
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            // Khu vuc tat "nhan dat online" chi danh cho khach goi dien hoac
            // khach vang lai. Ban chua phan khu van nhan dat online binh thuong -
            // chi loai nhung ban nam trong khu da tat ro rang.
            ->when($onlineOnly, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('area_id')
                    ->orWhereHas('area', fn ($a) => $a->where('bookable', true))
            ))
            ->with('area')
            ->get();
    }

    /**
     * Id cac ban da bi giu trong khung gio yeu cau.
     *
     * @return array<int, int>
     */
    public function busyTableIds(
        Branch $branch,
        string $date,
        int $startMinutes,
        int $endMinutes,
        ?int $ignoreBookingId = null,
    ): array {
        return $this->busyIdsIn(
            $this->blockingIntervals($branch, $date, $ignoreBookingId),
            $startMinutes,
            $endMinutes
        );
    }

    /**
     * Cac luot dat dang giu ban trong mot dem kinh doanh, da quy ve so phut
     * va kem san id cac ban. Doc mot lan cho ca ngay thay vi tung khung gio.
     *
     * @return array<int, array{start: int, end: int, tables: array<int, int>}>
     */
    protected function blockingIntervals(Branch $branch, string $date, ?int $ignoreBookingId = null): array
    {
        $open = $this->openMinutes($branch);

        $bookings = $branch->bookings()
            ->blocking()
            ->forDate($date)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->with('diningTables:id')
            ->get(['id', 'start_time', 'end_time']);

        $intervals = [];

        foreach ($bookings as $booking) {
            $start = $this->normalize($this->toMinutes((string) $booking->start_time), $open);
            $end = $this->normalize($this->toMinutes((string) $booking->end_time), $open);
            if ($end <= $start) {
                $end += 1440;
            }

            $intervals[] = [
                'start' => $start,
                'end' => $end,
                'tables' => $booking->diningTables->pluck('id')->map('intval')->all(),
            ];
        }

        return $intervals;
    }

    /**
     * Id cac ban dang ban trong khung gio, tinh tren danh sach da doc san.
     *
     * @param  array<int, array{start: int, end: int, tables: array<int, int>}>  $intervals
     * @return array<int, int>
     */
    protected function busyIdsIn(array $intervals, int $startMinutes, int $endMinutes): array
    {
        $busy = [];

        foreach ($intervals as $interval) {
            if ($startMinutes < $interval['end'] && $interval['start'] < $endMinutes) {
                foreach ($interval['tables'] as $id) {
                    $busy[$id] = true;
                }
            }
        }

        return array_map('intval', array_keys($busy));
    }

    /**
     * Chon bo ban phu hop nhat cho so khach. Tra ve mang rong neu khong du ban.
     *
     * Uu tien mot ban vua khit; neu khong co thi ghep cac ban cho phep ghep
     * trong cung khu vuc, toi da MAX_TABLES_PER_BOOKING ban.
     *
     * @param  Collection<int, DiningTable>  $tables
     * @return array<int, DiningTable>
     */
    public function pickTables(Collection $tables, int $partySize): array
    {
        // 1. Ban don vua khit nhat: du cho, thua it nhat.
        $single = $tables
            ->filter(fn (DiningTable $t) => $t->seats_max >= $partySize)
            ->sortBy([
                fn (DiningTable $a, DiningTable $b) => $a->seats_max <=> $b->seats_max,
                fn (DiningTable $a, DiningTable $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->first();

        if ($single) {
            return [$single];
        }

        // 2. Ghep ban trong cung khu vuc.
        $groups = $tables
            ->filter(fn (DiningTable $t) => $t->combinable)
            ->groupBy(fn (DiningTable $t) => (string) ($t->area_id ?? 'none'));

        foreach ($groups as $group) {
            $sorted = $group->sortByDesc('seats_max')->values();
            $picked = [];
            $seats = 0;

            foreach ($sorted as $table) {
                if (count($picked) >= self::MAX_TABLES_PER_BOOKING) {
                    break;
                }

                $picked[] = $table;
                $seats += (int) $table->seats_max;

                if ($seats >= $partySize) {
                    return $picked;
                }
            }
        }

        return [];
    }

    /**
     * Trang thai tung khung gio trong ngay cho so khach cu the.
     *
     * @return array<int, array{time: string, available: bool, tables_left: int, reason: ?string}>
     */
    public function daySlots(
        Branch $branch,
        string $date,
        int $partySize,
        ?int $areaId = null,
        bool $onlineOnly = false,
    ): array
    {
        $open = $this->openMinutes($branch);
        $now = Carbon::now();
        $earliest = $now->copy()->addMinutes((int) $branch->min_lead_minutes);
        $isToday = Carbon::parse($date)->isSameDay($now);

        // Ba truy van cho ca ngay, khong phai ba truy van cho moi khung gio.
        // Truoc day moi lan khach bam doi ngay hay doi so khach la chay vai chuc
        // cau lenh; gio la ba, phan con lai tinh trong bo nho.
        $closures = $this->closuresFor($branch, $date);
        $tables = $this->bookableTables($branch, $areaId, $onlineOnly);
        $intervals = $this->blockingIntervals($branch, $date);

        $closedAllDay = $closures->contains(fn ($closure) => $closure->isFullDay());

        $result = [];

        foreach ($this->slotTimes($branch) as $time) {
            $startMin = $this->normalize($this->toMinutes($time), $open);
            $endMin = $this->endMinutesFor($branch, $startMin);

            $reason = null;
            $available = true;
            $tablesLeft = 0;

            if ($closedAllDay) {
                $available = false;
                $reason = 'Chi nhánh nghỉ';
            } elseif ($this->closedIn($closures, $open, $startMin, $endMin)) {
                $available = false;
                $reason = 'Ngoài giờ phục vụ';
            } elseif ($isToday && $this->slotStartsAt($date, $time, $open)->lt($earliest)) {
                $available = false;
                $reason = 'Quá sát giờ';
            } else {
                $busyIds = $this->busyIdsIn($intervals, $startMin, $endMin);
                $free = $tables->reject(fn (DiningTable $t) => in_array((int) $t->id, $busyIds, true))->values();
                $picked = $this->pickTables($free, $partySize);
                $tablesLeft = $free->count();

                if (! $picked) {
                    $available = false;
                    $reason = 'Hết bàn phù hợp';
                }
            }

            $result[] = [
                'time' => $time,
                'available' => $available,
                'tables_left' => $tablesLeft,
                'reason' => $reason,
            ];
        }

        return $result;
    }

    /** Thoi diem thuc te cua mot moc gio (co tinh truong hop qua nua dem). */
    public function slotStartsAt(string $date, string $time, int $openMinutes): Carbon
    {
        $minutes = $this->toMinutes($time);
        $day = Carbon::parse($date);

        if ($minutes < $openMinutes) {
            $day->addDay();
        }

        return $day->setTime(intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Ngay som nhat / muon nhat khach duoc dat.
     *
     * @return array{min: string, max: string}
     */
    public function bookableDateRange(Branch $branch): array
    {
        return [
            'min' => Carbon::today()->toDateString(),
            'max' => Carbon::today()->addDays((int) $branch->max_advance_days)->toDateString(),
        ];
    }

    /**
     * So cho da nhan / tong so cho cua chi nhanh trong mot khung gio.
     *
     * @return array{booked: int, total: int}
     */
    public function occupancy(Branch $branch, string $date, int $startMinutes, int $endMinutes): array
    {
        $busyIds = $this->busyTableIds($branch, $date, $startMinutes, $endMinutes);

        $total = (int) $branch->diningTables()->where('is_active', true)->sum('seats_max');
        $booked = (int) DiningTable::whereIn('id', $busyIds ?: [0])->sum('seats_max');

        return ['booked' => $booked, 'total' => $total];
    }
}
