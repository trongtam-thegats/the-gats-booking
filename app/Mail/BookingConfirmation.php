<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\Notifications\BookingMessage;
use App\Services\TicketImage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Thu gui khach: ban HTML co tam ve, kem ban chu thuan cho trinh doc thu cu.
 *
 * Anh tam ve duoc nhung thang vao thu (CID) chu khong dan link ra ngoai:
 * link anh se hong khi doi ten mien, va nhieu trinh doc thu chan anh tai tu
 * may chu la. Nhung vao thi anh di theo thu mai mai.
 */
class BookingConfirmation extends Mailable
{
    public function __construct(
        public Booking $booking,
        public string $event,
        public string $text,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: BookingMessage::subject($this->booking, $this->event),
        );
    }

    public function content(): Content
    {
        $locale = $this->booking->locale ?: config('app.locale');
        $png = (new TicketImage)->png($this->booking, $locale);

        return new Content(
            view: 'emails.booking',
            text: 'emails.booking-text',
            with: [
                'booking' => $this->booking,
                'event' => $this->event,
                'brand' => $this->booking->branch?->brand,
                'locale' => $locale,
                'rows' => $this->rows($locale),
                'ticketUrl' => BookingMessage::ticketUrl($this->booking),
                'text' => $this->text,
                // Khong ve duoc anh thi thu van gui, chi la khong co hinh.
                'ticketPng' => $png,
            ],
        );
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function rows(string $locale): array
    {
        $b = $this->booking;
        $branch = $b->branch;

        return array_values(array_filter([
            [__('booking.ticket.venue', [], $locale), $branch->name],
            $branch->address ? [__('booking.ticket.address', [], $locale), $branch->address] : null,
            [
                __('booking.ticket.time', [], $locale),
                $b->timeRangeLabel().' · '.$b->booking_date->format('d/m/Y'),
            ],
            [__('booking.ticket.party', [], $locale), (string) $b->party_size],
            [
                __('booking.ticket.booked_by', [], $locale),
                $b->customer_name.' · '.$b->customer_phone,
            ],
        ]));
    }
}
