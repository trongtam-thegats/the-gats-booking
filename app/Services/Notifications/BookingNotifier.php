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
     * Danh sach kenh dang bat trong config, da loai bo ten kenh khong hop le.
     *
     * @return array<int, string>
     */
    public function channels(): array
    {
        return array_values(array_filter(
            (array) config('booking.channels', []),
            fn ($key) => isset(self::CHANNELS[$key]),
        ));
    }

    /**
     * @param  array<int, string>|null  $only  Chi gui qua nhung kenh nay; null la gui het.
     * @return array<int, NotificationLog>
     */
    public function send(Booking $booking, string $event, ?array $only = null): array
    {
        $booking->loadMissing(['branch', 'diningTables']);

        $logs = [];

        foreach ($this->channels() as $key) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }

            $class = self::CHANNELS[$key];

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
