<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\NotificationLog;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Nhac lich cho khach truoc gio hen. Chay dinh ky bang cron:
 *   php artisan booking:remind
 *
 * Cron chay 5 phut mot lan trong suot khung nhac (mac dinh 3 tieng), nen moi
 * dat ban se bi quet lai hang chuc lan. Viec chong trung o day la bat buoc:
 * truoc kia no chi coi ban ghi "sent" la da nhac, nen don nao khach khong de
 * email (ghi "skipped") thi lan chay nao cung nhac lai, de ra vai chuc dong
 * nhat ky rac cho moi don. Xem kenhCanNhac().
 */
class SendBookingReminders extends Command
{
    /** So lan thu lai toi da khi kenh bao loi (loi mang, SMTP chet tam thoi...). */
    protected const SO_LAN_THU_LAI = 3;

    protected $signature = 'booking:remind {--minutes= : Nhac truoc bao nhieu phut (mac dinh lay tu config)}';

    protected $description = 'Gửi tin nhắc lịch cho khách sắp đến giờ đặt bàn';

    public function handle(BookingNotifier $notifier): int
    {
        $lead = (int) ($this->option('minutes') ?: config('booking.reminder_lead_minutes'));
        $now = Carbon::now();
        $until = $now->copy()->addMinutes($lead);

        $bookings = Booking::query()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereIn('booking_date', [
                $now->copy()->subDay()->toDateString(),
                $now->toDateString(),
                $until->toDateString(),
            ])
            ->with(['branch', 'diningTables'])
            ->get()
            ->filter(function (Booking $booking) use ($now, $until) {
                $startsAt = $booking->startsAt();

                return $startsAt->betweenIncluded($now, $until);
            });

        $kenhBat = $notifier->channels();
        $daNhac = 0;

        foreach ($bookings as $booking) {
            $kenh = $this->kenhCanNhac($booking, $kenhBat);

            if ($kenh === []) {
                continue;
            }

            $notifier->send($booking, 'reminder', $kenh);
            $daNhac++;
            $this->line('Đã nhắc '.$booking->code.' — '.$booking->customer_phone.' ('.implode(', ', $kenh).')');
        }

        $this->info('Xong. Đã xử lý '.$daNhac.' đặt bàn.');

        return self::SUCCESS;
    }

    /**
     * Nhung kenh con dang cho nhac cho dat ban nay.
     *
     * Xet rieng tung kenh chu khong xet chung ca don: neu email bo qua vi khach
     * khong de dia chi thi Zalo van phai duoc gui.
     *
     * @param  array<int, string>  $kenhBat
     * @return array<int, string>
     */
    protected function kenhCanNhac(Booking $booking, array $kenhBat): array
    {
        /** @var Collection<int, NotificationLog> $log */
        $log = $booking->notificationLogs()
            ->where('event', 'reminder')
            ->get(['id', 'booking_id', 'channel', 'status']);

        return array_values(array_filter($kenhBat, function (string $kenh) use ($log) {
            $cuaKenh = $log->where('channel', $kenh);

            // Da gui duoc roi, hoac da biet chac la khong gui duoc (khach khong
            // co thong tin lien he, kenh chua khai bao trong .env) thi thoi -
            // chay lai lan nua cung ra dung ket qua do.
            if ($cuaKenh->whereIn('status', ['sent', 'skipped'])->isNotEmpty()) {
                return false;
            }

            // Loi thi thu lai, nhung dung thu mai den het khung nhac.
            return $cuaKenh->where('status', 'failed')->count() < self::SO_LAN_THU_LAI;
        }));
    }
}
