@extends('layouts.public')

@section('title', __('booking.site.lookup_link'))

@section('content')
    <section class="hero">
        <p class="eyebrow">{{ __('booking.lookup.eyebrow') }}</p>
        <h1>{{ __('booking.lookup.title') }}</h1>
        <p>{{ __('booking.lookup.intro') }}</p>
    </section>

    <div class="card">
        @if ($error)
            <div class="alert alert-error">{{ $error }}</div>
        @endif

        <form method="get" action="{{ route('booking.lookup') }}" class="form-grid">
            <div class="field">
                <label for="code">{{ __('booking.lookup.code') }}</label>
                <input type="text" id="code" name="code" value="{{ $code }}" placeholder="TG7KQ4M2" autocomplete="off" required>
            </div>
            <div class="field">
                <label for="phone">{{ __('booking.lookup.phone') }}</label>
                <input type="tel" id="phone" name="phone" value="{{ $phone }}" placeholder="09xx xxx xxx" required>
            </div>
            <div class="field full">
                <button class="btn" type="submit">{{ __('booking.lookup.submit') }}</button>
            </div>
        </form>
    </div>

    @if ($booking)
        <div class="card" style="margin-top:18px">
            <div class="row" style="justify-content:space-between">
                <div>
                    <p class="muted mb-0" style="font-size:13px">{{ __('booking.ticket.code') }} {{ $booking->code }}</p>
                    <h3 style="margin:4px 0 0">{{ $booking->branch->name }}</h3>
                </div>
                <span class="status-pill status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span>
            </div>
            <dl class="ticket-rows">
                <div class="ticket-row">
                    <dt>{{ __('booking.ticket.time') }}</dt>
                    <dd>{{ $booking->timeRangeLabel() }} &middot; {{ $booking->booking_date->format('d/m/Y') }}</dd>
                </div>
                <div class="ticket-row">
                    <dt>{{ __('booking.ticket.party') }}</dt>
                    <dd>{{ $booking->party_size }}</dd>
                </div>
            </dl>
            <div class="row" style="margin-top:18px">
                <a class="btn btn-ghost" href="{{ route('booking.show', $booking) }}">{{ __('booking.lookup.detail') }}</a>
            </div>
        </div>
    @endif
@endsection
