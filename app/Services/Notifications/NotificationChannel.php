<?php

namespace App\Services\Notifications;

use App\Models\Booking;

interface NotificationChannel
{
    public function name(): string;

    /** Nguoi nhan tren kenh nay, null neu khach khong cung cap. */
    public function recipient(Booking $booking): ?string;

    /** Da khai bao du thong tin ket noi chua. */
    public function isConfigured(): bool;

    /**
     * Gui tin. Nem exception neu that bai.
     */
    public function send(Booking $booking, string $event, string $message): void;
}
