<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\BookingDeletion;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\GuestNote;
use App\Models\User;
use App\Services\Notifications\BookingNotifier;
use App\Support\NguonDatBan;
use App\Support\SoDienThoai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availability,
        protected BookingNotifier $notifier,
    ) {}

    /**
     * Tao booking moi va giu ban.
     *
     * @param  array{customer_name: string, customer_phone: string, customer_email?: ?string,
     *               party_size: int, booking_date: string, start_time: string,
     *               area_id?: ?int, note?: ?string, source?: string}  $data
     *
     * @throws BookingUnavailableException
     */
    public function create(Branch $branch, array $data, ?User $actor = null): Booking
    {
        $partySize = (int) $data['party_size'];
        $date = $data['booking_date'];
        $openMin = $this->availability->openMinutes($branch);
        $startMin = $this->availability->normalize(
            $this->availability->toMinutes($data['start_time']),
            $openMin
        );
        $endMin = $this->availability->endMinutesFor($branch, $startMin);

        $this->guardBookingWindow($branch, $date, $data['start_time'], $partySize, $actor);
        $this->guardBlockedGuest($branch, $data['customer_phone'], $actor);

        // Don da co san neu day chi la mot lan bam lap. Gan trong closure ben
        // duoi de phan sau biet duong khong gui thong bao them lan nua.
        $daCo = null;

        // Khoa theo chi nhanh + ngay de hai khach dat cung luc khong an cung mot ban.
        $booking = DB::transaction(function () use (
            $branch, $data, $date, $partySize, $startMin, $endMin, $actor, &$daCo
        ) {
            $branch->bookings()
                ->forDate($date)
                ->lockForUpdate()
                ->get(['id']);

            // Phai nam SAU lockForUpdate: hai request bam cung mot khac se lan
            // luot di qua day, neu kiem tra truoc khoa thi ca hai cung thay
            // "chua co don nao" va cung tao moi.
            //
            // Chi ap cho khach tu dat. Nhan vien co ly do that de tao hai don
            // cung so dien thoai cung gio - vi du tach doan lon ra hai ban rieng.
            if ($actor === null) {
                $daCo = $this->donTrungKhitGio($branch, $data['customer_phone'], $date, $startMin);

                if ($daCo) {
                    return $daCo;
                }
            }

            $free = $this->availability->availableTables(
                $branch, $date, $startMin, $endMin, $data['area_id'] ?? null,
                null,
                // Khach tu dat tren web thi khong xep vao khu chi nhan dat qua dien thoai.
                $actor === null
            );

            $tables = $this->availability->pickTables($free, $partySize);

            if (! $tables) {
                throw new BookingUnavailableException(
                    __('booking.errors.no_tables', ['count' => $partySize])
                );
            }

            $booking = Booking::create([
                'code' => Booking::generateCode(),
                'branch_id' => $branch->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'party_size' => $partySize,
                'booking_date' => $date,
                'start_time' => $this->availability->toTimeString($startMin),
                'end_time' => $this->availability->toTimeString($endMin),
                'area_id' => $data['area_id'] ?? null,
                'status' => $branch->auto_confirm ? Booking::STATUS_CONFIRMED : Booking::STATUS_PENDING,
                'source' => $data['source'] ?? NguonDatBan::MAC_DINH,
                // Nho ngon ngu khach dung luc dat de tin xac nhan gui dung thu tieng.
                'locale' => $data['locale'] ?? app()->getLocale(),
                'note' => $data['note'] ?? null,
                'created_by' => $actor?->id,
                'confirmed_at' => $branch->auto_confirm ? now() : null,
                'confirmed_by' => $branch->auto_confirm ? $actor?->id : null,
            ]);

            $booking->diningTables()->sync(collect($tables)->pluck('id')->all());

            return $booking;
        });

        // Bam lap: tra lai chinh don cu de khach thay dung trang xac nhan da
        // thay, va tuyet doi khong gui them mot email xac nhan thu hai.
        if ($daCo) {
            return $daCo->load(['branch', 'diningTables', 'area']);
        }

        $booking->load(['branch', 'diningTables', 'area']);
        $this->notifier->send($booking, $booking->status === Booking::STATUS_CONFIRMED ? 'confirmed' : 'created');

        return $booking;
    }

    /**
     * Don dang con hieu luc cua chinh so dien thoai nay, cho dung khung gio ay.
     *
     * Dinh nghia trung co y hep: cung quan, cung so, cung ngay, cung khung gio,
     * va don cu con hieu luc. Hep den muc khong the bat nham - mot so dien thoai
     * khong co ly do gi de giu hai ban cho cung mot khung gio, vi doan dong hon
     * thi he thong tu ghep ban chu khong bat khach dat hai lan.
     *
     * Da tung dinh bat rong hon: "cung so, cung ngay, gui cach nhau duoi hai
     * phut" - ke ca khi khac khung gio. Bo di, vi no nuot mat don that: khach
     * dat 17:00 roi dat them 21:00 cho hiep hai la chuyen co that, va cai gia
     * cua viec bat nham la khach bi day sang trang xac nhan cua mot don khac
     * ma khong he duoc bao gi. Truong hop doi khung gio giua hai lan bam da co
     * lop khoa nut o trang khach lo, va no lo dung cho hon.
     */
    protected function donTrungKhitGio(Branch $branch, string $phone, string $date, int $startMin): ?Booking
    {
        $chuan = SoDienThoai::chuan($phone);

        if ($chuan === '') {
            return null;
        }

        // Don cu luu so nguyen van khach go, don moi luu so da chuan hoa.
        $soCanTim = array_values(array_unique(array_filter([$chuan, trim($phone)])));

        return $branch->bookings()
            ->blocking()
            ->forDate($date)
            ->whereIn('customer_phone', $soCanTim)
            ->whereTime('start_time', $this->availability->toTimeString($startMin))
            ->latest('id')
            ->first();
    }

    /**
     * Kiem tra cac rang buoc khong lien quan den suc chua.
     *
     * @throws BookingUnavailableException
     */
    protected function guardBookingWindow(
        Branch $branch,
        string $date,
        string $time,
        int $partySize,
        ?User $actor,
    ): void {
        // Nhan vien dat ho qua dien thoai duoc bo qua cac gioi han danh cho khach online.
        $isStaff = $actor !== null;

        if (! $branch->is_active) {
            throw new BookingUnavailableException(__('booking.errors.closed'));
        }

        if ($partySize < 1) {
            throw new BookingUnavailableException(__('booking.errors.party_invalid'));
        }

        if (! $isStaff && $partySize > $branch->max_party_size) {
            throw new BookingUnavailableException(__('booking.errors.party_too_big', [
                'count' => $branch->max_party_size + 1,
                'phone' => $branch->phone ?: __('booking.form.the_venue'),
            ]));
        }

        if (! in_array(substr($time, 0, 5), $this->availability->slotTimes($branch), true)) {
            throw new BookingUnavailableException(__('booking.errors.bad_slot'));
        }

        $openMin = $this->availability->openMinutes($branch);
        $startMin = $this->availability->normalize($this->availability->toMinutes($time), $openMin);
        $endMin = $this->availability->endMinutesFor($branch, $startMin);

        if ($this->availability->isClosed($branch, $date, $startMin, $endMin)) {
            throw new BookingUnavailableException(__('booking.errors.closed_slot'));
        }

        if ($isStaff) {
            return;
        }

        $startsAt = $this->availability->slotStartsAt($date, $time, $openMin);

        if ($startsAt->lt(Carbon::now()->addMinutes((int) $branch->min_lead_minutes))) {
            throw new BookingUnavailableException(
                __('booking.errors.too_soon', ['minutes' => $branch->min_lead_minutes])
            );
        }

        if (Carbon::parse($date)->gt(Carbon::today()->addDays((int) $branch->max_advance_days))) {
            throw new BookingUnavailableException(
                __('booking.errors.too_far', ['days' => $branch->max_advance_days])
            );
        }
    }

    /**
     * Doi lich, doi so khach hoac sua thong tin lien he cua mot booking da co.
     *
     * Giu nguyen bo ban dang xep neu chung van trong va van du cho; nguoc lai
     * thi chon lai bo ban khac. Khong bao gio de booking roi vao trang thai
     * "co gio moi nhung khong con ban".
     *
     * @param  array{booking_date: string, start_time: string, party_size: int,
     *               area_id?: ?int, customer_name?: string, customer_phone?: string,
     *               customer_email?: ?string, note?: ?string}  $data
     *
     * @throws BookingUnavailableException
     */
    public function reschedule(Booking $booking, array $data, User $actor, bool $notify = true): Booking
    {
        $branch = $booking->branch;
        $partySize = (int) $data['party_size'];
        $date = $data['booking_date'];

        if ($partySize < 1) {
            throw new BookingUnavailableException(__('booking.errors.party_invalid'));
        }

        if (! in_array(substr($data['start_time'], 0, 5), $this->availability->slotTimes($branch), true)) {
            throw new BookingUnavailableException(__('booking.errors.bad_slot'));
        }

        $openMin = $this->availability->openMinutes($branch);
        $startMin = $this->availability->normalize(
            $this->availability->toMinutes($data['start_time']),
            $openMin
        );
        $endMin = $this->availability->endMinutesFor($branch, $startMin);

        if ($this->availability->isClosed($branch, $date, $startMin, $endMin)) {
            throw new BookingUnavailableException(__('booking.errors.closed_slot'));
        }

        DB::transaction(function () use ($booking, $branch, $data, $date, $partySize, $startMin, $endMin) {
            $branch->bookings()->forDate($date)->lockForUpdate()->get(['id']);

            $free = $this->availability->availableTables(
                $branch, $date, $startMin, $endMin, $data['area_id'] ?? null, $booking->id
            );

            $current = $booking->diningTables;
            $currentStillFree = $current->isNotEmpty()
                && $current->every(fn ($table) => $free->contains('id', $table->id));
            $currentSeats = (int) $current->sum('seats_max');

            if ($currentStillFree && $currentSeats >= $partySize) {
                $tableIds = $current->pluck('id')->all();
            } else {
                $picked = $this->availability->pickTables($free, $partySize);

                if (! $picked) {
                    throw new BookingUnavailableException(
                        __('booking.errors.no_tables', ['count' => $partySize])
                    );
                }

                $tableIds = collect($picked)->pluck('id')->all();
            }

            $changes = [
                'booking_date' => $date,
                'start_time' => $this->availability->toTimeString($startMin),
                'end_time' => $this->availability->toTimeString($endMin),
                'party_size' => $partySize,
                // Ba truong nay duoc phep xoa trang, nen null la gia tri hop le.
                'area_id' => $data['area_id'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'note' => $data['note'] ?? null,
            ];

            // Ten va so dien thoai chi ghi de khi nguoi dung thuc su nhap gia tri moi.
            foreach (['customer_name', 'customer_phone'] as $field) {
                if (filled($data[$field] ?? null)) {
                    $changes[$field] = $data[$field];
                }
            }

            $booking->update($changes);

            $booking->diningTables()->sync($tableIds);
        });

        $booking->refresh()->load(['branch', 'diningTables']);

        if ($notify) {
            $this->notifier->send($booking, 'updated');
        }

        return $booking;
    }

    /**
     * Chan dat ban online voi so dien thoai quan da danh dau.
     * Nhan vien van dat ho duoc, vi ho biet ro truong hop cua tung khach.
     *
     * @throws BookingUnavailableException
     */
    protected function guardBlockedGuest(Branch $branch, string $phone, ?User $actor): void
    {
        if ($actor !== null || ! $branch->brand_id) {
            return;
        }

        $blocked = GuestNote::where('brand_id', $branch->brand_id)
            ->where('phone', GuestNote::normalize($phone))
            ->where('is_blocked', true)
            ->exists();

        if ($blocked) {
            throw new BookingUnavailableException(__('booking.errors.blocked'));
        }
    }

    /**
     * Xac nhan mot dat ban, ke ca don truoc do da huy hoac da bi danh dau
     * khach khong den.
     *
     * Huy va "khong den" deu nha ban ra cho khach khac. Nen khi dua don quay
     * lai, phai giu bàn lai - truoc day khong lam viec nay, don duoc xac nhan
     * lai ma khong cam bàn nao, khach van nhan tin bao da xac nhan, roi den noi
     * khong co cho ngoi. Neu khung gio da kin that thi bao loi cho nhan vien
     * biet, chu khong xac nhan suong.
     *
     * @throws BookingUnavailableException
     */
    public function confirm(Booking $booking, User $actor): Booking
    {
        DB::transaction(function () use ($booking, $actor) {
            $branch = $booking->branch;
            $date = $booking->booking_date->toDateString();

            // Chi phai giu lai bàn khi don dang trang tay. Don cho duyet thi da
            // cam bàn tu luc tao, dung dong vao ket qua xep bàn cua no.
            if ($booking->diningTables()->count() === 0) {
                $branch->bookings()->forDate($date)->lockForUpdate()->get(['id']);

                $openMin = $this->availability->openMinutes($branch);
                $startMin = $this->availability->normalize(
                    $this->availability->toMinutes((string) $booking->start_time),
                    $openMin
                );
                $endMin = $this->availability->endMinutesFor($branch, $startMin);

                // Nhan vien dang thao tac nen duoc xep ca vao khu chi nhan dat
                // qua dien thoai, giong nhu khi ho dat ho khach.
                $free = $this->availability->availableTables(
                    $branch, $date, $startMin, $endMin, $booking->area_id, $booking->id
                );

                $tables = $this->availability->pickTables($free, (int) $booking->party_size);

                if (! $tables) {
                    throw new BookingUnavailableException(
                        __('booking.errors.no_tables', ['count' => $booking->party_size])
                    );
                }

                $booking->diningTables()->sync(collect($tables)->pluck('id')->all());
            }

            $booking->update([
                'status' => Booking::STATUS_CONFIRMED,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'cancelled_by_type' => null,
            ]);
        });

        $this->notifier->send($booking->fresh(['branch', 'diningTables']), 'confirmed');

        return $booking;
    }

    public function cancel(Booking $booking, ?string $reason, string $byType = 'staff', ?User $actor = null): Booking
    {
        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'cancelled_by_type' => $byType,
        ]);

        // Nha ban ra cho khach khac.
        $booking->diningTables()->detach();

        $this->notifier->send($booking->fresh(['branch', 'diningTables']), 'cancelled');

        return $booking;
    }

    /**
     * Xoa han mot dat ban khoi he thong (chi quan tri goi toi).
     *
     * Khac han voi huy: huy giu lai dong du lieu va van hien trong bao cao voi
     * trang thai "da huy". Xoa la de dung cho don SAI - don trung, don go nham,
     * don thu - nhung thu khong duoc phep lam lech bao cao va phan tich khach.
     *
     * Dong bookings bien mat that; ban, nhat ky gui tin di theo bang khoa ngoai
     * cascade. Ban sao day du duoc cat sang bang booking_deletions truoc khi xoa.
     */
    public function delete(Booking $booking, string $reason, User $actor): BookingDeletion
    {
        return DB::transaction(function () use ($booking, $reason, $actor) {
            $booking->loadMissing(['branch', 'diningTables']);

            $nhatKy = BookingDeletion::create([
                'code' => $booking->code,
                'branch_id' => $booking->branch_id,
                'branch_name' => $booking->branch?->name,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'party_size' => $booking->party_size,
                'booking_date' => $booking->booking_date->toDateString(),
                'start_time' => $booking->start_time,
                'status' => $booking->status,
                'source' => $booking->source,
                'du_lieu' => [
                    // Gia tri tho tu CSDL, de dung lai la khop nguyen ven.
                    'booking' => $booking->getAttributes(),
                    'dining_table_ids' => $booking->diningTables->pluck('id')->all(),
                    'dining_table_codes' => $booking->diningTables->pluck('code')->all(),
                ],
                'deleted_by' => $actor->id,
                'deleted_by_name' => $actor->name,
                'reason' => $reason,
            ]);

            // Nha ban ra truoc cho tuong minh, du khoa ngoai cascade cung don not.
            $booking->diningTables()->detach();
            $booking->delete();

            return $nhatKy;
        });
    }

    public function markSeated(Booking $booking): Booking
    {
        $booking->update(['status' => Booking::STATUS_SEATED, 'seated_at' => now()]);

        return $booking;
    }

    public function markCompleted(Booking $booking): Booking
    {
        $booking->update(['status' => Booking::STATUS_COMPLETED, 'completed_at' => now()]);

        return $booking;
    }

    public function markNoShow(Booking $booking): Booking
    {
        $booking->update(['status' => Booking::STATUS_NO_SHOW]);
        $booking->diningTables()->detach();

        return $booking;
    }

    /**
     * Gan lai ban cho booking (quan ly keo bang tay tren so do).
     *
     * @param  array<int, int>  $tableIds
     *
     * @throws BookingUnavailableException
     */
    public function assignTables(Booking $booking, array $tableIds): Booking
    {
        $branch = $booking->branch;
        $openMin = $this->availability->openMinutes($branch);
        $startMin = $this->availability->normalize(
            $this->availability->toMinutes((string) $booking->start_time),
            $openMin
        );
        $endMin = $this->availability->normalize(
            $this->availability->toMinutes((string) $booking->end_time),
            $openMin
        );

        if ($endMin <= $startMin) {
            $endMin += 1440;
        }

        DB::transaction(function () use ($booking, $branch, $tableIds, $startMin, $endMin) {
            $busy = $this->availability->busyTableIds(
                $branch,
                $booking->booking_date->toDateString(),
                $startMin,
                $endMin,
                $booking->id
            );

            $clash = array_intersect($tableIds, $busy);

            if ($clash) {
                $codes = DiningTable::whereIn('id', $clash)->pluck('code')->implode(', ');
                throw new BookingUnavailableException('Bàn '.$codes.' đã có khách khác giữ trong khung giờ này.');
            }

            $valid = DiningTable::whereIn('id', $tableIds)
                ->where('branch_id', $branch->id)
                ->pluck('id')
                ->all();

            $booking->diningTables()->sync($valid);
        });

        return $booking->fresh(['diningTables']);
    }
}
