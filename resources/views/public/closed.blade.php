@extends('layouts.public')

@section('title', __('booking.closed.title'))

@section('content')
    <section class="hero">
        <p class="eyebrow">{{ $brand->name }}</p>
        <h1>{{ __('booking.closed.title') }}</h1>
    </section>

    <div class="card">
        <p style="margin-top:0">
            {{ $brand->text('closed_message') ?: __('booking.closed.body', ['venue' => $brand->name]) }}
            @if ($brand->phone)
                {!! __('booking.closed.call', ['phone' => '<a href="tel:'.preg_replace('/\s+/', '', $brand->phone).'">'.e($brand->phone).'</a>']) !!}
            @else
                {{ __('booking.closed.contact') }}
            @endif
        </p>
        <p class="hint mb-0">{{ __('booking.closed.still_lookup') }}</p>
    </div>
@endsection
