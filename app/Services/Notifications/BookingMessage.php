<?php

namespace App\Services\Notifications;

use App\Models\Booking;

/**
 * Soan noi dung tin nhan gui khach, dung chung cho email, SMS va Zalo de noi
 * dung khong bi lech giua cac kenh.
 *
 * Tin gui bang dung thu tieng khach da dung luc dat (cot bookings.locale),
 * khong phai ngon ngu cua nguoi bam nut trong khu quan tri.
 */
class BookingMessage
{
    protected static function locale(Booking $booking): string
    {
        return $booking->locale ?: config('app.locale');
    }

    public static function subject(Booking $booking, string $event): string
    {
        $locale = self::locale($booking);

        return __('booking.notify.subject.'.$event, [
            'venue' => $booking->branch->name,
            'code' => $booking->code,
        ], $locale);
    }

    public static function body(Booking $booking, string $event): string
    {
        $locale = self::locale($booking);
        $branch = $booking->branch;
        $date = $booking->booking_date->format('d/m/Y');
        $time = substr((string) $booking->start_time, 0, 5);

        $lines = [
            __('booking.notify.lead.'.$event, [
                'name' => $booking->customer_name,
                'code' => $booking->code,
            ], $locale),
            '',
            __('booking.notify.code', [], $locale).': '.$booking->code,
            __('booking.notify.venue', [], $locale).': '.$branch->name,
        ];

        if ($branch->address) {
            $lines[] = __('booking.notify.address', [], $locale).': '.$branch->address;
        }

        $lines[] = __('booking.notify.time', [], $locale).': '
            .__('booking.notify.time_value', ['time' => $time, 'date' => $date], $locale);
        $lines[] = __('booking.notify.party', [], $locale).': '.$booking->party_size;

        if (in_array($event, ['confirmed', 'updated'], true) && $booking->diningTables->isNotEmpty()) {
            $lines[] = __('booking.notify.tables', [], $locale).': '.$booking->tableCodes();
        }

        if ($event === 'cancelled' && $booking->cancel_reason) {
            $lines[] = __('booking.notify.reason', [], $locale).': '.$booking->cancel_reason;
        }

        if ($event !== 'cancelled') {
            $lines[] = '';
            $lines[] = __('booking.notify.link_hint', [], $locale).': '.self::ticketUrl($booking);
        }

        if ($branch->phone) {
            $lines[] = '';
            $lines[] = __('booking.notify.call_hint', ['phone' => $branch->phone], $locale);
        }

        return implode("\n", $lines);
    }

    /**
     * Dia chi trang xac nhan, tren ten mien cua chinh quan do.
     *
     * Khong dung route() truc tiep vi tin co the duoc gui tu khu quan tri
     * (booking.thegats.vn) — link phai tro ve mien khach dang dung, neu khong
     * bam vao se ra trang 404 do middleware chan cheo quan.
     */
    public static function ticketUrl(Booking $booking): string
    {
        $domain = $booking->branch->brand?->domain;

        return $domain
            ? 'https://'.$domain.'/ma/'.$booking->code
            : route('booking.show', $booking);
    }

    /** Ban rut gon cho SMS, khong dau de khong bi chia thanh nhieu tin. */
    public static function sms(Booking $booking, string $event): string
    {
        $locale = self::locale($booking);

        return __('booking.notify.sms.'.$event, [
            'code' => $booking->code,
            'venue' => $booking->branch->name,
            'time' => substr((string) $booking->start_time, 0, 5),
            'date' => $booking->booking_date->format('d/m'),
            'count' => $booking->party_size,
        ], $locale);
    }
}
