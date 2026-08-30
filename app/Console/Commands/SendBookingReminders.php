<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nhac lich cho khach truoc gio hen. Chay dinh ky bang cron:
 *   php artisan booking:remind
 */
class SendBookingReminders extends Command
{
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
            })
            ->reject(function (Booking $booking) {
                // Da nhac roi thi thoi.
                return $booking->notificationLogs()
                    ->where('event', 'reminder')
                    ->where('status', 'sent')
                    ->exists();
            });

        foreach ($bookings as $booking) {
            $notifier->send($booking, 'reminder');
            $this->line('Đã nhắc '.$booking->code.' — '.$booking->customer_phone);
        }

        $this->info('Xong. Đã xử lý '.$bookings->count().' đặt bàn.');

        return self::SUCCESS;
    }
}
