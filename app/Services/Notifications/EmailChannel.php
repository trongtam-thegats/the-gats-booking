<?php

namespace App\Services\Notifications;

use App\Mail\BookingConfirmation;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'email';
    }

    public function recipient(Booking $booking): ?string
    {
        return $booking->customer_email;
    }

    public function isConfigured(): bool
    {
        // Driver "log" van hop le o moi truong dev: thu duoc ghi vao storage/logs.
        return (bool) config('mail.default');
    }

    /**
     * Ghi chu kem vao nhat ky khi thu khong that su ra khoi may.
     *
     * Driver "log" van chay tron tru nen nhat ky ghi la "da gui" — nhin vao
     * tuong nhu khach da nhan duoc thu, trong khi thu chi nam trong
     * storage/logs. Phai noi ro ngay tren dong nhat ky.
     */
    public function sentNote(): ?string
    {
        return config('mail.default') === 'log'
            ? 'Chế độ ghi log — thư nằm trong storage/logs, chưa gửi ra ngoài.'
            : null;
    }

    public function send(Booking $booking, string $event, string $message): void
    {
        $to = $this->recipient($booking);
        $from = $this->from($booking);

        $mailable = new BookingConfirmation($booking, $event, $message);

        if ($from) {
            $mailable->from($from[0], $from[1]);
        }

        Mail::to($to)->send($mailable);
    }

    /**
     * Dia chi gui thu cua chinh quan do, neu quan co khai bao rieng.
     *
     * Hai quan hai ten mien khac nhau: khach dat o gemination.vn ma nhan thu
     * tu mot ten mien khac thi vua lac nhan dien vua de bi loc vao thu rac.
     * Quan chua khai thi tra ve null, Laravel dung dia chi chung.
     *
     * Luu y khi dung Gmail/Workspace: dia chi nay phai la chinh hop thu dang
     * dang nhap SMTP, hoac mot bi danh da xac minh trong "Gui thu voi ten khac"
     * cua hop thu do — neu khong Google se lang le ghi de lai bang dia chi that.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function from(Booking $booking): ?array
    {
        $brand = $booking->branch?->brand;

        if (! $brand || blank($brand->mail_from_address)) {
            return null;
        }

        return [$brand->mail_from_address, $brand->mail_from_name ?: $brand->name];
    }
}
