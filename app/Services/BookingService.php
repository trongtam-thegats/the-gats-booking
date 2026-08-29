<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\GuestNote;
use App\Models\User;
use App\Services\Notifications\BookingNotifier;
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

        // Khoa theo chi nhanh + ngay de hai khach dat cung luc khong an cung mot ban.
        $booking = DB::transaction(function () use (
            $branch, $data, $date, $partySize, $startMin, $endMin, $actor
        ) {
            $branch->bookings()
                ->forDate($date)
                ->lockForUpdate()
                ->get(['id']);

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
                'source' => $data['source'] ?? 'online',
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

        $booking->load(['branch', 'diningTables', 'area']);
        $this->notifier->send($booking, $booking->status === Booking::STATUS_CONFIRMED ? 'confirmed' : 'created');

        return $booking;
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

    public function confirm(Booking $booking, User $actor): Booking
    {
        $booking->update([
            'status' => Booking::STATUS_CONFIRMED,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'cancelled_at' => null,
            'cancel_reason' => null,
            'cancelled_by_type' => null,
        ]);

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

        DB::transaction(function () use ($booking, $branch, $tableIds, $startMin, $endMin, $openMin) {
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
