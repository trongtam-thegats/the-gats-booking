<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchClosure;
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
     * @param  Collection<int, BranchClosure>  $closures
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
            ->with(['area', 'combinedTables'])
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
        // 1. Uu tien ban don vua khit nhat:
        // Ban phai thoa man: seats_min <= partySize <= seats_max.
        // Uu tien ban co seats_max nho nhat (it thua cho nhat), roi den sort_order.
        $singleFit = $tables
            ->filter(fn (DiningTable $t) => $t->seats_min <= $partySize && $t->seats_max >= $partySize)
            ->sortBy([
                fn (DiningTable $a, DiningTable $b) => $a->seats_max <=> $b->seats_max,
                fn (DiningTable $a, DiningTable $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->first();

        if ($singleFit) {
            return [$singleFit];
        }

        // 2. Ghep cac ban nho phu hop (trong cung khu vuc, uu tien cac cap ban lien ke nhau).
        $combo = $this->timComboGhepBan($tables, $partySize);
        if ($combo) {
            return $combo;
        }

        // 3. Neu khong co ban don vua khit va khong the ghep ban nho:
        // Chi cho phep xep ban don lon hon neu seats_min khong vuot qua nguong cho phep
        // va tuyet doi khong xep khach it nguoi (<= 3 khach) vao ban lon/Sofa (seats_min >= 4).
        $singleTolerant = $tables
            ->filter(function (DiningTable $t) use ($partySize) {
                if ($t->seats_max < $partySize) {
                    return false;
                }
                // Khach tu 3 nguoi tro xuong: TUYET DOI KHONG xep vao ban co seats_min >= 4 (nhu Sofa, Dining Room)
                if ($partySize <= 3 && $t->seats_min >= 4) {
                    return false;
                }
                // Khong chenh lech qua 2 cho so voi seats_min
                return ($t->seats_min - $partySize) <= 2;
            })
            ->sortBy([
                fn (DiningTable $a, DiningTable $b) => $a->seats_max <=> $b->seats_max,
                fn (DiningTable $a, DiningTable $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->first();

        if ($singleTolerant) {
            return [$singleTolerant];
        }

        return [];
    }

    /**
     * Tim to hop ban ghep phu hop nhat trong cung khu vuc.
     * Uu tien cac ban lien ke nhau (neu quan da dinh nghia so do ban lien ke).
     *
     * @param  Collection<int, DiningTable>  $tables
     * @return array<int, DiningTable>
     */
    protected function timComboGhepBan(Collection $tables, int $partySize): array
    {
        $combinableTables = $tables->filter(fn (DiningTable $t) => $t->combinable);

        $groups = $combinableTables->groupBy(fn (DiningTable $t) => (string) ($t->area_id ?? 'none'));

        $bestCombo = null;
        $bestExcess = PHP_INT_MAX;
        $bestCount = PHP_INT_MAX;
        $bestSort = PHP_INT_MAX;

        foreach ($groups as $group) {
            $groupTables = $group->values();
            $tableCount = $groupTables->count();

            if ($tableCount < 2) {
                continue;
            }

            $firstTable = $groupTables->first();
            $maxLimit = $this->maxTablesPerCombo($firstTable);

            // Kiem tra xem trong nhom nay co ban nao da duoc khai bao ban lien ke khong
            $coKhaiBaoLienKe = $groupTables->contains(fn (DiningTable $t) => $t->combinedTables->isNotEmpty());

            $candidates = [];

            if ($coKhaiBaoLienKe) {
                // Duyet theo do thi lien ke: BFS tu tung ban de tim cac chuoi lien ke
                $idMap = $groupTables->keyBy('id');
                $visitedSets = [];

                foreach ($groupTables as $startTable) {
                    $this->timChuoiLienKe(
                        $startTable,
                        [$startTable],
                        $idMap,
                        $partySize,
                        $candidates,
                        $visitedSets
                    );
                }
            } else {
                // Truong hop chi nhanh chua thiet lap cap ban lien ke:
                // Tim tap hop ban trong cung khu vuc (uu tien thu tu sort_order gan nhau)
                $sorted = $groupTables->sortBy('sort_order')->values();
                $picked = [];
                $seats = 0;

                foreach ($sorted as $table) {
                    if (count($picked) >= $maxLimit) {
                        break;
                    }

                    $picked[] = $table;
                    $seats += (int) $table->seats_max;

                    if ($seats >= $partySize) {
                        $candidates[] = $picked;
                        break;
                    }
                }
            }

            // Danh gia cac to hop ung vien
            foreach ($candidates as $combo) {
                $totalSeats = $this->tongSucChuaToHop($combo);
                if ($totalSeats < $partySize) {
                    continue;
                }

                $excess = $totalSeats - $partySize;
                $count = count($combo);
                $sort = array_sum(array_map(fn (DiningTable $t) => (int) $t->sort_order, $combo));

                // So sanh: it thua cho nhat -> it ban nhat -> sort_order nho nhat
                if ($excess < $bestExcess ||
                    ($excess === $bestExcess && $count < $bestCount) ||
                    ($excess === $bestExcess && $count === $bestCount && $sort < $bestSort)) {
                    $bestCombo = $combo;
                    $bestExcess = $excess;
                    $bestCount = $count;
                    $bestSort = $sort;
                }
            }
        }

        return $bestCombo ?? [];
    }

    /**
     * Tinh tong suc chua thuc te cua mot to hop ban ghep.
     * Ho tro quy tac dac thu khi ghep cap sofa o Drinking Healing (S1+S2 = 18 khach, S3+S4 = 18 khach).
     *
     * @param  array<int, DiningTable>|\Illuminate\Support\Collection<int, DiningTable>  $tables
     */
    public function tongSucChuaToHop($tables): int
    {
        $list = is_array($tables) ? $tables : $tables->values()->all();
        if (count($list) === 2) {
            $codes = array_map(fn (DiningTable $t) => $t->code, $list);
            sort($codes);
            if ($codes === ['Sofa 1', 'Sofa 2'] || $codes === ['Sofa 3', 'Sofa 4']) {
                return 18;
            }
        }

        return (int) array_sum(array_map(fn (DiningTable $t) => (int) $t->seats_max, $list));
    }

    /**
     * So luong ban ghep toi da cho tung loai cho ngoi.
     * Quay bar cho phep ghep toi da 3 ghe canh nhau; cac loai ban khac
     * (ban cao, sofa...) chi cho phep ghep toi da 2 ban noi tiep nhau.
     */
    public function maxTablesPerCombo(?DiningTable $table): int
    {
        if ($table && $table->table_type === 'bar_seat') {
            return 3;
        }

        return 2;
    }

    /**
     * De quy tim cac to hop ban lien ke nhau tu 2 toi so ban toi da cho phep.
     *
     * @param  array<int, DiningTable>  $currentCombo
     * @param  Collection<int, DiningTable>  $availableMap
     * @param  array<int, array<int, DiningTable>>  &$candidates
     * @param  array<string, bool>  &$visitedSets
     */
    protected function timChuoiLienKe(
        DiningTable $latestTable,
        array $currentCombo,
        Collection $availableMap,
        int $partySize,
        array &$candidates,
        array &$visitedSets
    ): void {
        $firstTable = $currentCombo[0] ?? null;
        $maxLimit = $this->maxTablesPerCombo($firstTable);

        $seats = $this->tongSucChuaToHop($currentCombo);

        // Neu da du cho cho khach, luu lai to hop
        if (count($currentCombo) >= 2 && $seats >= $partySize) {
            $candidates[] = $currentCombo;
            return;
        }

        // Neu da dat so ban toi da cho loai cho ngoi nay, dung lai
        if (count($currentCombo) >= $maxLimit) {
            return;
        }

        // Duyet cac ban ke can voi cac ban hien co trong to hop
        $currentIds = array_map(fn (DiningTable $t) => $t->id, $currentCombo);

        foreach ($currentCombo as $tableInCombo) {
            foreach ($tableInCombo->combinedTables as $neighbor) {
                if (! $availableMap->has($neighbor->id) || in_array($neighbor->id, $currentIds, true)) {
                    continue;
                }

                $nextTable = $availableMap->get($neighbor->id);
                $newCombo = array_merge($currentCombo, [$nextTable]);

                $ids = array_map(fn (DiningTable $t) => $t->id, $newCombo);
                sort($ids);
                $key = implode('-', $ids);

                if (! isset($visitedSets[$key])) {
                    $visitedSets[$key] = true;
                    $this->timChuoiLienKe($nextTable, $newCombo, $availableMap, $partySize, $candidates, $visitedSets);
                }
            }
        }
    }

    /**
     * Suc chua toi da (so khach lon nhat) co the phuc vu tu tap hop cac ban con trong.
     * Tinh ca ban don va to hop ghep toi da theo tung loai cho ngoi.
     *
     * @param  Collection<int, DiningTable>  $freeTables
     */
    public function maxPartySizeForTables(Collection $freeTables): int
    {
        if ($freeTables->isEmpty()) {
            return 0;
        }

        $maxSingle = (int) $freeTables->max('seats_max');

        $combinableTables = $freeTables->filter(fn (DiningTable $t) => $t->combinable);
        $groups = $combinableTables->groupBy(fn (DiningTable $t) => (string) ($t->area_id ?? 'none'));

        $maxCombo = 0;

        foreach ($groups as $group) {
            $groupTables = $group->values();
            if ($groupTables->count() < 2) {
                continue;
            }

            $coKhaiBaoLienKe = $groupTables->contains(fn (DiningTable $t) => $t->combinedTables->isNotEmpty());

            if ($coKhaiBaoLienKe) {
                $idMap = $groupTables->keyBy('id');
                foreach ($groupTables as $table) {
                    $limit = $this->maxTablesPerCombo($table);
                    foreach ($table->combinedTables as $neighbor) {
                        if (! $idMap->has($neighbor->id)) {
                            continue;
                        }
                        $seats2 = $this->tongSucChuaToHop([$table, $neighbor]);
                        if ($seats2 > $maxCombo) {
                            $maxCombo = $seats2;
                        }

                        if ($limit >= 3) {
                            foreach ($neighbor->combinedTables as $third) {
                                if ($third->id !== $table->id && $idMap->has($third->id)) {
                                    $seats3 = $this->tongSucChuaToHop([$table, $neighbor, $third]);
                                    if ($seats3 > $maxCombo) {
                                        $maxCombo = $seats3;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $first = $groupTables->first();
                $limit = $this->maxTablesPerCombo($first);
                $top = $groupTables->sortByDesc('seats_max')->take($limit);
                $seats = (int) $top->sum('seats_max');
                if ($seats > $maxCombo) {
                    $maxCombo = $seats;
                }
            }
        }

        return max($maxSingle, $maxCombo);
    }

    /**
     * Trang thai tung khung gio trong ngay cho so khach cu the.
     *
     * @return array<int, array{time: string, available: bool, tables_left: int, max_party_size: int, reason: ?string}>
     */
    public function daySlots(
        Branch $branch,
        string $date,
        int $partySize,
        ?int $areaId = null,
        bool $onlineOnly = false,
    ): array {
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
            $slotMaxParty = 0;

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
                $slotMaxParty = $this->maxPartySizeForTables($free);

                if (! $picked) {
                    $available = false;
                    $reason = 'Hết bàn phù hợp';
                }
            }

            $result[] = [
                'time' => $time,
                'available' => $available,
                'tables_left' => $tablesLeft,
                'max_party_size' => $slotMaxParty,
                'reason' => $reason,
            ];
        }

        return $result;
    }

    /** Thoi diem thuc te cua mot moc gio (co tinh truong hop qua nua dem). */
    public function slotStartsAt(string $date, string $time, int $openMinutes): Carbon
    {
        return Branch::thoiDiemTrongDem($date, $time, $openMinutes);
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
