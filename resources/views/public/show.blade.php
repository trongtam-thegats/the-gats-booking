@extends('layouts.public')

@section('title', __('booking.ticket.code').' '.$booking->code)

@php
    $isCancelled = $booking->status === \App\Models\Booking::STATUS_CANCELLED;

    // Du lieu de ve anh xac nhan bang canvas. Gom san o day cho gon:
    // Blade khong doc duoc mang nhieu dong dat thang trong @json(...).
    $ticketImage = [
        'brand' => $brand?->name ?? 'The Gats',
        'logo' => $brand?->hasLogo() ? asset($brand->logo_path) : null,
        'mark' => $brand?->mark ?? 'TG',
        'accent' => $brand?->accent_color ?: '#c8a15a',
        'ground' => $brand?->ground() ?: '#0e0d0c',
        'codeLabel' => __('booking.ticket.code'),
        'code' => $booking->code,
        'status' => $booking->statusLabel(),
        'footer' => __('booking.ticket.image_footer'),
        'filename' => \Illuminate\Support\Str::slug(__('booking.ticket.saved_title').' '.$booking->code).'.png',
        'title' => __('booking.ticket.saved_title'),
        'timeLabel' => __('booking.ticket.time'),
        'time' => $booking->timeRangeLabel(),
        'date' => $booking->booking_date->format('d/m/Y'),
        // Don chua duyet thi thay dong chan bang loi hen goi lai cho khach.
        'pendingNote' => $booking->status === \App\Models\Booking::STATUS_PENDING
            ? __('booking.ticket.pending_note', ['venue' => $booking->branch->name])
            : null,
        'rows' => array_values(array_filter([
            [__('booking.ticket.venue'), $booking->branch->name],
            $booking->branch->address ? [__('booking.ticket.address'), $booking->branch->address] : null,
            [__('booking.ticket.time'), $booking->timeRangeLabel().' · '.$booking->booking_date->format('d/m/Y')],
            [__('booking.ticket.party'), (string) $booking->party_size],
            [__('booking.ticket.booked_by'), $booking->customer_name.' · '.$booking->customer_phone],
            // Ghi chu khach tu viet — cho no quay lai tren ve, cat bot neu dai.
            $booking->note
                ? [__('booking.ticket.note'), \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $booking->note), 119)]
                : null,
        ])),
        'labels' => [
            'saving' => __('booking.ticket.saving_image'),
            'save' => __('booking.ticket.save_image'),
            'failed' => __('booking.ticket.save_failed'),
        ],
    ];
@endphp

@section('content')
    @if (session('just_booked'))
        <div class="alert alert-ok">
            <b>{{ $brand->text('thanks_title') }}</b>
            @if ($thanks = $brand->text('thanks_body'))
                <br>{{ $thanks }}
            @endif
        </div>
    @endif

    @if (session('cancelled'))
        <div class="alert alert-info">{{ __('booking.ticket.cancelled') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <div class="card ticket">
        <p class="eyebrow mb-0">{{ __('booking.ticket.code') }}</p>
        <p class="ticket-code">{{ $booking->code }}</p>
        <span class="status-pill status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span>

        @if ($booking->status === \App\Models\Booking::STATUS_PENDING)
            <p class="hint" style="margin-top:14px">
                {{ __('booking.ticket.pending_note', ['venue' => $booking->branch->name]) }}
            </p>
        @endif

        <dl class="ticket-rows">
            <div class="ticket-row">
                <dt>{{ __('booking.ticket.venue') }}</dt>
                <dd>{{ $booking->branch->name }}</dd>
            </div>
            @if ($booking->branch->address)
                <div class="ticket-row">
                    <dt>{{ __('booking.ticket.address') }}</dt>
                    <dd>{{ $booking->branch->address }}</dd>
                </div>
            @endif
            <div class="ticket-row">
                <dt>{{ __('booking.ticket.time') }}</dt>
                <dd>{{ $booking->timeRangeLabel() }} &middot; {{ $booking->booking_date->format('d/m/Y') }}</dd>
            </div>
            <div class="ticket-row">
                <dt>{{ __('booking.ticket.party') }}</dt>
                <dd>{{ $booking->party_size }}</dd>
            </div>
            @if ($booking->area)
                <div class="ticket-row">
                    <dt>{{ __('booking.ticket.area') }}</dt>
                    <dd>{{ $booking->area->name }}</dd>
                </div>
            @endif
            <div class="ticket-row">
                <dt>{{ __('booking.ticket.booked_by') }}</dt>
                <dd>{{ $booking->customer_name }} &middot; {{ $booking->customer_phone }}</dd>
            </div>
            @if ($booking->note)
                <div class="ticket-row">
                    <dt>{{ __('booking.ticket.note') }}</dt>
                    <dd>{{ $booking->note }}</dd>
                </div>
            @endif
            @if ($isCancelled && $booking->cancel_reason)
                <div class="ticket-row">
                    <dt>{{ __('booking.ticket.cancel_reason') }}</dt>
                    <dd>{{ $booking->cancel_reason }}</dd>
                </div>
            @endif
        </dl>

        @if ($booking->branch->phone)
            <p class="hint" style="margin-top:14px">
                {{ __('booking.ticket.change_hint', ['phone' => $booking->branch->phone]) }}
            </p>
        @endif
    </div>

    @unless ($isCancelled)
        @if ($emailSent)
            {{-- Chi noi "da gui" khi nhat ky gui tin ghi nhan la gui thanh cong. --}}
            <p class="alert alert-ok" style="margin-top:18px">
                {{ __('booking.ticket.email_sent', ['email' => $booking->customer_email]) }}
            </p>
        @else
            <div class="card keep-card">
                <h3 class="keep-title">
                    {{ $booking->customer_email ? __('booking.ticket.keep_title') : __('booking.ticket.no_email_title') }}
                </h3>
                <p class="hint mb-0">
                    {{ $booking->customer_email
                        ? __('booking.ticket.keep_body', ['email' => $booking->customer_email])
                        : __('booking.ticket.no_email_body') }}
                </p>
                {{-- Hien bang JS: khong co JS thi khach van chup man hinh duoc,
                     mot cai nut bam khong len gi con te hon la khong co nut. --}}
                <button type="button" class="btn keep-btn" id="save-ticket" hidden>
                    {{ __('booking.ticket.save_image') }}
                </button>
                <p class="hint keep-error" id="save-ticket-error" role="alert" hidden></p>
            </div>
        @endif
    @endunless

    @if ($booking->customerCanCancel())
        <div class="card" style="margin-top:18px">
            <h3 style="margin-top:0">{{ __('booking.cancel.title') }}</h3>
            <p class="hint">{{ __('booking.cancel.hint') }}</p>
            <form method="post" action="{{ route('booking.cancel', $booking) }}" class="form-grid"
                  onsubmit="return confirm('{{ __('booking.cancel.confirm', ['code' => $booking->code]) }}')">
                @csrf
                <div class="field">
                    <label for="customer_phone">{{ __('booking.form.phone') }}</label>
                    <input type="tel" id="customer_phone" name="customer_phone" required>
                </div>
                <div class="field">
                    <label for="reason">{{ __('booking.cancel.reason') }} <span class="muted">{{ __('booking.form.optional') }}</span></label>
                    <input type="text" id="reason" name="reason" maxlength="200">
                </div>
                <div class="field full">
                    <button class="btn btn-danger" type="submit">{{ __('booking.cancel.submit') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="row center" style="margin-top:22px; justify-content:center">
        <a class="btn btn-ghost" href="{{ route('home') }}">{{ __('booking.ticket.book_again') }}</a>
    </div>
@endsection

@push('scripts')
    @unless ($isCancelled || $emailSent)
        <script id="ticket-data" type="application/json">@json($ticketImage)</script>
        <script src="{{ asset('js/ticket.js') }}" defer></script>
    @endunless
@endpush
