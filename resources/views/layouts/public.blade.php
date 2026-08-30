@php($brand = $brand ?? null)
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('booking.hero.title')) · {{ $brand?->name ?? 'The Gats' }}</title>
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/site.css') }}">
    @if ($brand)
        {{-- Nhan dien rieng cua tung quan, ghi de bien CSS chung. --}}
        @php($shades = $brand->groundShades())
        @php($fonts = $brand->webFonts())

        {{-- Bao truoc cho trinh duyet tai font ngay, khong doi doc xong CSS. --}}
        @foreach ($fonts as $font)
            <link rel="preload" href="{{ $font['url'] }}" as="font" type="{{ $font['mime'] }}" crossorigin>
        @endforeach

        <style>
            {!! $brand->fontFaceCss() !!}
            :root {
                {{-- Khong escape vi bo font chua dau nhay kep; ten file font do he thong tu dat. --}}
                --font-body: {!! $brand->bodyStack() !!};
                --font-display: {!! $brand->displayStack() !!};
                --gold: {{ $brand->accent_color }};
                --gold-soft: {{ $brand->accentSoft() }};
                --bg: {{ $brand->ground() }};
                --panel: {{ $shades['panel'] }};
                --panel-2: {{ $shades['panel2'] }};
                --line: {{ $shades['line'] }};
                --field: {{ $shades['field'] }};
                --on-accent: {{ $brand->ground() }};
            }
        </style>
    @endif
</head>
<body>
<div class="wrap">
    <header class="site-head">
        <a href="{{ route('home') }}" class="brand">
            @if ($brand?->hasLogo())
                @php($logo = $brand->imageSize($brand->logo_path))
                <img class="brand-logo" src="{{ \App\Support\Assets::url($brand->logo_path) }}"
                     alt="{{ $brand->name }}" decoding="async"
                     @if ($logo) width="{{ $logo[0] }}" height="{{ $logo[1] }}" @endif>
            @else
                <span class="brand-mark">{{ $brand?->mark ?? 'TG' }}</span>
                <span class="brand-name">{{ $brand?->name ?? 'The Gats' }}</span>
            @endif
        </a>

        <div class="head-actions">
            {{-- Doi ngon ngu: giu nguyen dia chi dang xem, chi doi tham so lang. --}}
            <div class="lang-switch" role="group" aria-label="Language">
                @foreach (\App\Support\Locales::ALL as $code => [$name, $short])
                    <a class="lang-option {{ app()->getLocale() === $code ? 'is-active' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                       hreflang="{{ $code }}" title="{{ $name }}"
                       @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $short }}</a>
                @endforeach
            </div>

            <a href="{{ route('booking.lookup') }}" class="head-link">{{ __('booking.site.lookup_link') }}</a>
        </div>
    </header>

    @yield('content')

    <footer class="site-foot">
        @if ($brand && $brand->socialLinks())
            <p class="foot-social">
                @foreach ($brand->socialLinks() as $label => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                @endforeach
            </p>
        @endif

        <p class="mb-0">
            {{ $brand?->name ?? 'The Gats' }} &middot; {{ __('booking.site.footer') }}
            @if ($brand?->phone)
                &middot; <a href="tel:{{ preg_replace('/\s+/', '', $brand->phone) }}">{{ $brand->phone }}</a>
            @endif
        </p>
    </footer>
</div>
@stack('scripts')
</body>
</html>
