<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gui ZNS (Zalo Notification Service) qua Zalo Official Account.
 *
 * Zalo yeu cau moi loai tin phai duoc duyet thanh mot template rieng, nen
 * moi event map sang mot template_id khai bao trong .env. Event nao chua co
 * template thi coi nhu kenh chua cau hinh cho event do.
 */
class ZaloChannel implements NotificationChannel
{
    public function __construct(protected ?ZaloTokenStore $tokens = null)
    {
        $this->tokens = $tokens ?: new ZaloTokenStore;
    }

    public function name(): string
    {
        return 'zalo';
    }

    public function recipient(Booking $booking): ?string
    {
        return (new SmsChannel)->normalizePhone($booking->customer_phone);
    }

    public function isConfigured(): bool
    {
        // Du mot trong hai: token dan tay, hoac bo thong tin de tu lam moi token.
        return filled(Setting::get('zalo_access_token'))
            || filled(config('booking.zalo.access_token'))
            || $this->tokens->canRefresh();
    }

    public function send(Booking $booking, string $event, string $message): void
    {
        $templateId = config('booking.zalo.templates.'.$event);

        if (blank($templateId)) {
            throw new RuntimeException('Chưa khai báo template Zalo cho sự kiện "'.$event.'".');
        }

        // Token het han sau khoang mot gio nen phai lay qua kho token,
        // no se tu doi token moi khi can.
        $response = Http::timeout(15)
            ->withHeaders(['access_token' => $this->tokens->accessToken()])
            ->post(config('booking.zalo.endpoint'), [
                'phone' => $this->recipient($booking),
                'template_id' => $templateId,
                'template_data' => [
                    'ma_dat_ban' => $booking->code,
                    'ten_khach' => $booking->customer_name,
                    'chi_nhanh' => $booking->branch->name,
                    'ngay' => $booking->booking_date->format('d/m/Y'),
                    'gio' => substr((string) $booking->start_time, 0, 5),
                    'so_khach' => (string) $booking->party_size,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Zalo HTTP '.$response->status().': '.$response->body());
        }

        $errorCode = (int) $response->json('error', 0);
        if ($errorCode !== 0) {
            throw new RuntimeException('Zalo error '.$errorCode.': '.$response->json('message', ''));
        }
    }
}
