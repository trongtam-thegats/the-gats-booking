@extends('layouts.public')

@section('title', __('booking.hero.title'))

@php
    use Illuminate\Support\Carbon;

    // Tren may chay thu, quan duoc chon bang ?brand=slug. Tham so nay phai di kem
    // moi duong dan sinh ra o trang nay, neu khong lan goi sau se roi ve quan mac dinh.
    $preview = request()->filled('brand') ? ['brand' => request()->query('brand')] : [];

    $today = Carbon::today();
    $lastDay = $today->copy()->addDays((int) $branch->max_advance_days);
    $stripDays = collect(range(0, min(13, (int) $branch->max_advance_days)))
        ->map(fn (int $offset) => $today->copy()->addDays($offset));
    $selectedDate = $initialDate;

    // Khung gio cua lua chon mac dinh da duoc tinh o controller va in thang ra
    // HTML. Trang khong phai goi API mot vong nua truoc khi khach thay gio.
    $initialMessage = $initialSlots['message']
        ?: (count($initialSlots['slots']) ? __('booking.form.slot_legend') : __('booking.form.no_service_day'));

    // Chu dung trong phan JavaScript. Gom o day thay vi nhet thang vao @json
    // vi Blade khong doc duoc mang nhieu dong long ngoac trong doi so directive.
    $jsStrings = [
        'ctaIdle' => __('booking.form.cta_idle'),
        'ctaLead' => __('booking.form.cta_lead', ['minutes' => $branch->min_lead_minutes]),
        'guests' => __('booking.form.guests', ['count' => '__n__']),
        'loading' => __('booking.form.loading_slots'),
        'legend' => __('booking.form.slot_legend'),
        'noService' => __('booking.form.no_service_day'),
        'failed' => __('booking.form.slots_failed'),
        'branch' => $branch->name,
    ];
@endphp

@section('content')
    @if ($cover = $brand->coverSources())
        {{-- Anh dau trang: tai truoc moi thu khac, va dien thoai chi lay ban hep. --}}
        <img class="cover" src="{{ $cover['src'] }}" alt="{{ $brand->name }}"
             @if ($cover['srcset']) srcset="{{ $cover['srcset'] }}" sizes="min(100vw, 760px)" @endif
             @if ($cover['width']) width="{{ $cover['width'] }}" height="{{ $cover['height'] }}" @endif
             fetchpriority="high" decoding="async">
    @endif

    <section class="hero">
        <p class="eyebrow">{{ $brand->tagline ?: __('booking.hero.eyebrow') }}</p>
        <h1>{{ $brand->text('hero_title') }}</h1>

        @if ($intro = $brand->text('hero_intro'))
            <p>{{ $intro }}</p>
        @endif

        <p>
            @if ($branches->count() === 1 && $branch->address){{ $branch->address }}<br>@endif
            {{ __('booking.hero.hours', ['open' => substr($branch->open_time, 0, 5), 'close' => substr($branch->close_time, 0, 5)]) }}
            @if ($branch->phone) &middot; <a href="tel:{{ preg_replace('/\s+/', '', $branch->phone) }}">{{ $branch->phone }}</a> @endif
            @if ($branch->map_url)
                &middot; <a href="{{ $branch->map_url }}" target="_blank" rel="noopener noreferrer">{{ __('booking.hero.map') }}</a>
            @endif
        </p>
    </section>

    @if ($branches->count() > 1)
        {{-- Quán nhiều địa điểm: chọn ngay trên đầu form, không tách thành trang riêng. --}}
        <div class="step" style="margin-bottom:18px">
            <div class="step-head">
                <span class="step-num">•</span>
                <h2>{{ __('booking.form.branch') }}</h2>
                <span class="step-value">{{ $branch->name }}</span>
            </div>
            <div class="daystrip">
                @foreach ($branches as $option)
                    <a class="day {{ $option->is($branch) ? 'is-selected' : '' }}"
                       style="text-decoration:none; min-width:auto; padding:10px 16px"
                       href="{{ route('home', array_merge(['dia-diem' => $option->slug], $preview)) }}">
                        <b style="font-size:14px">{{ $option->name }}</b>
                        @if ($option->address)
                            <span>{{ \Illuminate\Support\Str::limit($option->address, 28) }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <noscript>
        <div class="alert alert-info">
            {{ __('booking.form.noscript', ['phone' => $branch->phone ?: __('booking.form.the_venue')]) }}
        </div>
    </noscript>

    <form method="post" action="{{ route('booking.store', array_merge(['branch' => $branch], $preview)) }}"
          class="card" id="booking-form">
        @csrf

        {{-- Bước 1: ngày --}}
        <div class="step">
            <div class="step-head">
                <span class="step-num">1</span>
                <h2>{{ __('booking.form.step_date') }}</h2>
                <span class="step-value" id="value-date"></span>
            </div>

            <div class="daystrip" id="daystrip">
                @foreach ($stripDays as $day)
                    <button type="button" class="day" data-date="{{ $day->toDateString() }}">
                        <span>
                            @if ($day->isToday()) {{ __('booking.form.today') }}
                            @elseif ($day->isTomorrow()) {{ __('booking.form.tomorrow') }}
                            @else {{ $day->translatedFormat('D') }}
                            @endif
                        </span>
                        <b>{{ $day->format('d/m') }}</b>
                    </button>
                @endforeach

                @if ($branch->max_advance_days > 13)
                    <button type="button" class="day-more" id="day-more">{{ __('booking.form.other_day') }}</button>
                @endif
            </div>

            <input type="date" id="booking_date" name="booking_date" class="date-fallback"
                   value="{{ $selectedDate }}" min="{{ $today->toDateString() }}"
                   max="{{ $lastDay->toDateString() }}" required>
        </div>

        {{-- Bước 2: số khách --}}
        <div class="step">
            <div class="step-head">
                <span class="step-num">2</span>
                <h2>{{ __('booking.form.step_party') }}</h2>
                <span class="step-value" id="value-party"></span>
            </div>

            <div class="party-chips" id="party-chips">
                @foreach (range(1, 8) as $size)
                    @continue($size > $branch->max_party_size)
                    <button type="button" class="chip" data-size="{{ $size }}">{{ $size }}</button>
                @endforeach
                @if ($branch->max_party_size > 8)
                    <button type="button" class="chip" id="party-more">9+</button>
                @endif
            </div>

            <input type="number" id="party_size" name="party_size" class="party-custom"
                   min="1" max="{{ $branch->max_party_size }}"
                   value="{{ $initialParty }}" required>

            <p class="hint" style="margin:10px 0 0">
                {!! __('booking.form.party_over_max', [
                    'max' => $branch->max_party_size,
                    'phone' => $branch->phone
                        ? '<a href="tel:'.preg_replace('/\s+/', '', $branch->phone).'">'.e($branch->phone).'</a>'
                        : e(__('booking.form.the_venue')),
                ]) !!}
            </p>

        </div>

        {{-- Bước 3: giờ --}}
        <div class="step">
            <div class="step-head">
                <span class="step-num">3</span>
                <h2>{{ __('booking.form.step_time') }}</h2>
                <span class="step-value" id="value-time"></span>
            </div>

            <div class="hint" id="slot-message" style="margin-bottom:10px">{{ $initialMessage }}</div>
            <div class="slots" id="slots">
                @foreach ($initialSlots['slots'] as $slot)
                    <button type="button" class="slot" data-time="{{ $slot['time'] }}"
                            @disabled(! $slot['available'])
                            @if ($slot['reason']) title="{{ $slot['reason'] }}" @endif
                    >{{ $slot['time'] }}</button>
                @endforeach
            </div>

            @if ($lateNote = $branch->lateNote())
                <p class="hint" style="margin:12px 0 0">{{ $lateNote }}</p>
            @endif
            <input type="hidden" name="start_time" id="start_time" value="{{ old('start_time') }}">
        </div>

        {{-- Bước 4: thông tin khách, chỉ hiện sau khi đã chọn giờ --}}
        <div class="step reveal" id="guest-step">
            <div class="step-head">
                <span class="step-num">4</span>
                <h2>{{ __('booking.form.step_guest') }}</h2>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="customer_name">{{ __('booking.form.name') }}</label>
                    <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}"
                           maxlength="120" autocomplete="name" required>
                </div>
                <div class="field">
                    <label for="customer_phone">{{ __('booking.form.phone') }}</label>
                    <input type="tel" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}"
                           placeholder="09xx xxx xxx" inputmode="tel" autocomplete="tel" required>
                </div>
                <div class="field">
                    <label for="customer_email">{{ __('booking.form.email') }} <span class="muted">{{ __('booking.form.optional') }}</span></label>
                    <input type="email" id="customer_email" name="customer_email" value="{{ old('customer_email') }}"
                           inputmode="email" autocomplete="email">
                </div>
                <div class="field full">
                    <label for="note">{{ __('booking.form.note') }} <span class="muted">{{ __('booking.form.optional') }}</span></label>
                    <textarea id="note" name="note" maxlength="500" rows="2"
                              placeholder="{{ __('booking.form.note_placeholder') }}">{{ old('note') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Thanh hành động dính đáy màn hình --}}
        <div class="cta-bar">
            <div class="cta-summary" id="cta-summary">
                <b id="cta-title">{{ __('booking.form.cta_idle') }}</b>
                <span id="cta-sub">{{ __('booking.form.cta_lead', ['minutes' => $branch->min_lead_minutes]) }}</span>
            </div>
            <button class="btn" type="submit" id="submit-btn" disabled>{{ $brand->text('submit_label') }}</button>
        </div>

        @if ($terms = $brand->text('terms'))
            <p class="hint" style="margin:14px 0 0">{{ $terms }}</p>
        @endif
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const slotsUrl   = @json(route('booking.slots', array_merge(['branch' => $branch], $preview)));

    // Chu cho phan chay bang JavaScript, lay tu file ngon ngu de khop voi ban dich.
    const t = @json($jsStrings);
    const initialSlots = @json($initialSlots);
    const dateInput  = document.getElementById('booking_date');
    const partyInput = document.getElementById('party_size');
    const startTime  = document.getElementById('start_time');

    const daystrip   = document.getElementById('daystrip');
    const dayMore    = document.getElementById('day-more');
    const partyChips = document.getElementById('party-chips');
    const partyMore  = document.getElementById('party-more');

    const slotsBox   = document.getElementById('slots');
    const messageBox = document.getElementById('slot-message');
    const guestStep  = document.getElementById('guest-step');
    const submitBtn  = document.getElementById('submit-btn');

    const valueDate  = document.getElementById('value-date');
    const valueParty = document.getElementById('value-party');
    const valueTime  = document.getElementById('value-time');
    const ctaTitle   = document.getElementById('cta-title');
    const ctaSub     = document.getElementById('cta-sub');

    let requestToken = 0;
    let inFlight = null;

    // Nho lai ket qua da tai trong vong mot phut. Khach hay bam qua bam lai
    // giua vai ngay; lan quay lai khong can goi may chu nua.
    const cache = new Map();
    const CACHE_MS = 60000;

    function formatDate(value) {
        const day = daystrip.querySelector('.day[data-date="' + value + '"]');

        if (day) {
            return day.querySelector('span').textContent.trim() + ' ' + day.querySelector('b').textContent.trim();
        }

        const parts = value.split('-');
        return parts[2] + '/' + parts[1];
    }

    function syncHeadings() {
        valueDate.textContent = dateInput.value ? formatDate(dateInput.value) : '';
        valueParty.textContent = partyInput.value ? t.guests.replace('__n__', partyInput.value) : '';
        valueTime.textContent = startTime.value || '';

        const ready = Boolean(startTime.value);
        submitBtn.disabled = !ready;
        guestStep.classList.toggle('is-open', ready);

        if (ready) {
            ctaTitle.textContent = startTime.value + ' · ' + formatDate(dateInput.value);
            ctaSub.textContent = t.guests.replace('__n__', partyInput.value) + ' · ' + t.branch;
        } else {
            ctaTitle.textContent = t.ctaIdle;
            ctaSub.textContent = t.ctaLead;
        }
    }

    function markSelected(container, selector, activeEl) {
        container.querySelectorAll(selector).forEach(el => el.classList.remove('is-selected'));
        if (activeEl) activeEl.classList.add('is-selected');
    }

    function syncDayStrip() {
        const match = daystrip.querySelector('.day[data-date="' + dateInput.value + '"]');
        markSelected(daystrip, '.day', match);

        // Ngay nam ngoai dai 14 ngay thi mo o chon ngay he thong cho khach thay.
        if (!match) dateInput.classList.add('is-open');
    }

    function syncPartyChips() {
        const match = partyChips.querySelector('.chip[data-size="' + partyInput.value + '"]');
        markSelected(partyChips, '.chip', match);

        if (!match) {
            partyInput.classList.add('is-open');
            if (partyMore) partyMore.classList.add('is-selected');
        }
    }

    function renderSlots(payload) {
        const previous = startTime.value;
        const slots = payload.slots || [];
        const fragment = document.createDocumentFragment();

        startTime.value = '';

        slots.forEach(slot => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'slot';
            button.textContent = slot.time;
            button.dataset.time = slot.time;
            button.disabled = !slot.available;
            if (slot.reason) button.title = slot.reason;

            if (slot.available && slot.time === previous) {
                startTime.value = slot.time;
                button.classList.add('is-selected');
            }

            fragment.appendChild(button);
        });

        // Thay ca khoi mot lan: trinh duyet chi tinh lai bo cuc dung mot lan.
        slotsBox.replaceChildren(fragment);

        if (payload.message) {
            messageBox.textContent = payload.message;
        } else if (!slots.length) {
            messageBox.textContent = t.noService;
        } else {
            messageBox.textContent = t.legend;
        }

        syncHeadings();
    }

    async function loadSlots() {
        if (!dateInput.value || !partyInput.value) return;

        const key = dateInput.value + '|' + partyInput.value;
        const hit = cache.get(key);

        if (hit && Date.now() - hit.at < CACHE_MS) {
            requestToken++;
            renderSlots(hit.payload);
            return;
        }

        const token = ++requestToken;

        // Huy han request cu: khach bam nhanh qua vai ngay thi khong viec gi
        // phai cho tai xong nhung ket qua se bi bo di.
        if (inFlight) inFlight.abort();
        inFlight = new AbortController();

        messageBox.textContent = t.loading;

        const params = new URLSearchParams({ date: dateInput.value, party_size: partyInput.value });

        let payload;
        try {
            const res = await fetch(slotsUrl + (slotsUrl.includes('?') ? '&' : '?') + params.toString(),
                { headers: { 'Accept': 'application/json' }, signal: inFlight.signal });

            // Loi mang hoac loi may chu phai bao rieng, khong duoc lan sang
            // thong bao "het ban" - hai chuyen hoan toan khac nhau.
            if (!res.ok) throw new Error('HTTP ' + res.status);

            payload = await res.json();
        } catch (e) {
            // Request bi chinh minh huy thi im lang, da co lan moi thay the.
            if (e.name !== 'AbortError' && token === requestToken) {
                messageBox.textContent = t.failed;
            }
            return;
        }

        // Bo qua ket qua cua request cu neu khach da doi lua chon.
        if (token !== requestToken) return;

        cache.set(key, { at: Date.now(), payload: payload });
        renderSlots(payload);
    }

    slotsBox.addEventListener('click', event => {
        const button = event.target.closest('.slot');
        if (!button || button.disabled) return;

        startTime.value = button.dataset.time;
        markSelected(slotsBox, '.slot', button);
        syncHeadings();
        guestStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    daystrip.addEventListener('click', event => {
        const day = event.target.closest('.day');
        if (!day) return;

        dateInput.value = day.dataset.date;
        dateInput.classList.remove('is-open');
        markSelected(daystrip, '.day', day);
        syncHeadings();
        loadSlots();
    });

    if (dayMore) {
        dayMore.addEventListener('click', () => {
            dateInput.classList.add('is-open');
            if (dateInput.showPicker) {
                try { dateInput.showPicker(); } catch (e) { dateInput.focus(); }
            } else {
                dateInput.focus();
            }
        });
    }

    partyChips.addEventListener('click', event => {
        const chip = event.target.closest('.chip');
        if (!chip) return;

        if (chip === partyMore) {
            partyInput.classList.add('is-open');
            markSelected(partyChips, '.chip', partyMore);
            partyInput.focus();
            partyInput.select();
            return;
        }

        partyInput.value = chip.dataset.size;
        partyInput.classList.remove('is-open');
        markSelected(partyChips, '.chip', chip);
        syncHeadings();
        loadSlots();
    });

    dateInput.addEventListener('change', () => { syncDayStrip(); syncHeadings(); loadSlots(); });
    partyInput.addEventListener('change', () => { syncPartyChips(); syncHeadings(); loadSlots(); });

    // Ket qua dau tien da duoc in san vao HTML; ghi vao bo nho tam de khach
    // quay lai lua chon nay cung khong phai goi may chu.
    cache.set(dateInput.value + '|' + partyInput.value, { at: Date.now(), payload: initialSlots });

    // Khach vua bi tra ve vi thieu thong tin thi gio da chon van con trong form.
    const preselected = startTime.value
        ? slotsBox.querySelector('.slot[data-time="' + startTime.value + '"]:not([disabled])')
        : null;

    if (preselected) {
        markSelected(slotsBox, '.slot', preselected);
    } else {
        startTime.value = '';
    }

    syncDayStrip();
    syncPartyChips();
    syncHeadings();
})();
</script>
@endpush
