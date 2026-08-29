<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gui thong bao cho khach qua cac kenh da bat trong config/booking.php.
 *
 * Nguyen tac: khong bao gio lam hong luong dat ban. Moi ket qua (gui duoc,
 * bo qua vi chua cau hinh, hay loi) deu duoc ghi vao notification_logs de
 * quan ly chi nhanh tra cuu.
 */
class BookingNotifier
{
    /** @var array<string, class-string<NotificationChannel>> */
    protected const CHANNELS = [
        'email' => EmailChannel::class,
        'sms' => SmsChannel::class,
        'zalo' => ZaloChannel::class,
    ];

    /**
     * @return array<int, NotificationLog>
     */
    public function send(Booking $booking, string $event): array
    {
        $booking->loadMissing(['branch', 'diningTables']);

        $logs = [];

        foreach ((array) config('booking.channels', []) as $key) {
            $class = self::CHANNELS[$key] ?? null;

            if (! $class) {
                continue;
            }

            $logs[] = $this->sendVia(new $class, $booking, $event);
        }

        return $logs;
    }

    protected function sendVia(NotificationChannel $channel, Booking $booking, string $event): NotificationLog
    {
        $recipient = $channel->recipient($booking);
        $message = $channel->name() === 'sms'
            ? BookingMessage::sms($booking, $event)
            : BookingMessage::body($booking, $event);

        $log = [
            'booking_id' => $booking->id,
            'channel' => $channel->name(),
            'event' => $event,
            'recipient' => $recipient,
            'message' => $message,
        ];

        if (blank($recipient)) {
            return NotificationLog::create($log + [
                'status' => 'skipped',
                'error' => 'Khách không cung cấp thông tin liên hệ cho kênh này.',
            ]);
        }

        if (! $channel->isConfigured()) {
            return NotificationLog::create($log + [
                'status' => 'skipped',
                'error' => 'Kênh chưa được khai báo thông tin kết nối trong .env.',
            ]);
        }

        try {
            $channel->send($booking, $event, $message);

            // Kenh nao muon ghi chu them thi khai sentNote(); vi du email o che do
            // ghi log van "gui" thanh cong nhung thu khong ra khoi may.
            $note = method_exists($channel, 'sentNote') ? $channel->sentNote() : null;

            return NotificationLog::create($log + ['status' => 'sent', 'error' => $note]);
        } catch (Throwable $e) {
            Log::warning('Gửi thông báo đặt bàn thất bại', [
                'booking' => $booking->code,
                'channel' => $channel->name(),
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return NotificationLog::create($log + [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
