<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gui SMS brandname qua eSMS.vn. Doi nha cung cap khac thi chi can sua
 * payload trong send(); phan con lai cua he thong khong doi.
 */
class SmsChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'sms';
    }

    public function recipient(Booking $booking): ?string
    {
        return $this->normalizePhone($booking->customer_phone);
    }

    public function isConfigured(): bool
    {
        return filled(config('booking.sms.api_key')) && filled(config('booking.sms.secret_key'));
    }

    public function send(Booking $booking, string $event, string $message): void
    {
        $phone = $this->recipient($booking);

        $response = Http::timeout(15)->post(config('booking.sms.endpoint'), [
            'ApiKey' => config('booking.sms.api_key'),
            'SecretKey' => config('booking.sms.secret_key'),
            'Brandname' => config('booking.sms.brandname'),
            'SmsType' => '2',
            'Phone' => $phone,
            'Content' => BookingMessage::sms($booking, $event),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS HTTP '.$response->status().': '.$response->body());
        }

        $code = (string) $response->json('CodeResult', '');
        if ($code !== '' && $code !== '100') {
            throw new RuntimeException('SMS CodeResult '.$code.': '.$response->json('ErrorMessage', ''));
        }
    }

    /** 0912345678 -> 84912345678 */
    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '84')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '84'.substr($digits, 1);
        }

        return $digits ?: null;
    }
}
