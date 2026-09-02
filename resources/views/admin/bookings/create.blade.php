@extends('layouts.admin')

@section('title', 'Đặt bàn hộ khách')

@section('content')
    <div class="page-head">
        <div>
            <h1>Đặt bàn hộ khách</h1>
            <p>Dùng khi khách gọi điện hoặc đến trực tiếp. Bản ghi này bỏ qua giới hạn đặt trước dành cho khách online.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.bookings.index') }}">Về danh sách</a>
    </div>

    @if ($branches->count() > 1)
        <div class="card">
            <div class="field" style="max-width:320px">
                <label for="branch-switch">Chi nhánh</label>
                <select id="branch-switch"
                        onchange="window.location = '{{ route('admin.bookings.create') }}?branch=' + this.value">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected($branch->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('admin.bookings.store') }}" class="card">
        @csrf
        <input type="hidden" name="branch_id" value="{{ $branch->id }}">

        <h2>{{ $branch->name }}</h2>
        <p class="sub">
            Nhận khách {{ substr($branch->open_time, 0, 5) }} – {{ substr($branch->close_time, 0, 5) }},
            mỗi lượt giữ bàn {{ $branch->turn_minutes }} phút.
        </p>

        <div class="form-grid">
            <div class="field">
                <label for="booking_date">Ngày</label>
                <input type="date" id="booking_date" name="booking_date"
                       value="{{ old('booking_date', now()->toDateString()) }}" required>
            </div>
            <div class="field">
                <label for="start_time">Giờ</label>
                <select id="start_time" name="start_time" required>
                    @foreach ($slotTimes as $time)
                        <option value="{{ $time }}" @selected(old('start_time') === $time)>{{ $time }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="party_size">Số khách</label>
                <input type="number" id="party_size" name="party_size" min="1" max="200"
                       value="{{ old('party_size', 2) }}" required>
            </div>
            <div class="field">
                <label for="source">Nguồn</label>
                <select id="source" name="source">
                    @foreach ($nguonChon as $ma => $nhan)
                        <option value="{{ $ma }}" @selected(old('source', 'phone') === $ma)>{{ $nhan }}</option>
                    @endforeach
                </select>
            </div>

            @if ($areas->isNotEmpty())
                <div class="field">
                    <label for="area_id">Khu vực</label>
                    <select id="area_id" name="area_id">
                        <option value="">Không yêu cầu</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected(old('area_id') == $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- So dien thoai dat truoc ho ten: go so xong la ten tu dien vao
                 tu danh sach khach hang, khoi phai go lai. --}}
            <div class="field">
                <label for="customer_phone">Số điện thoại</label>
                <input type="tel" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}"
                       autocomplete="off" required>
                <span class="small muted" id="khach-tom-tat"></span>
            </div>
            <div class="field">
                <label for="customer_name">Họ tên khách</label>
                <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}"
                       autocomplete="off" required>
                <span class="small" id="khach-ten-khac"></span>
            </div>
            <div class="field">
                <label for="customer_email">Email</label>
                <input type="email" id="customer_email" name="customer_email" value="{{ old('customer_email') }}">
            </div>
            <div class="field full">
                <label for="note">Ghi chú</label>
                <textarea id="note" name="note" maxlength="500">{{ old('note') }}</textarea>
            </div>
        </div>

        <button class="btn" type="submit" style="margin-top:16px">Tạo đặt bàn</button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var oSdt    = document.getElementById('customer_phone');
    var oTen    = document.getElementById('customer_name');
    var tomTat  = document.getElementById('khach-tom-tat');
    var tenKhac = document.getElementById('khach-ten-khac');
    var duongDan = @json(route('admin.guests.quick'));

    var hen = null;
    var lanGoi = 0;
    var soDaTra = '';

    function xoaGoiY() {
        tomTat.textContent = '';
        tomTat.className = 'small muted';
        tenKhac.textContent = '';
        tenKhac.className = 'small';
    }

    function moTa(k) {
        var y = [];

        if (k.visits > 0) {
            y.push('đã ghé ' + k.visits + ' lần');
        } else if (k.bookings > 0) {
            y.push('đã đặt ' + k.bookings + ' lần');
        }

        if (k.last_visit) y.push('gần nhất ' + k.last_visit);
        if (k.tier)      y.push('hạng ' + k.tier);
        if (k.no_show)   y.push(k.no_show + ' lần hẹn mà không tới');

        return y.join(' · ');
    }

    function hien(k) {
        if (!k.found) {
            tomTat.textContent = 'Khách mới, chưa có trong danh sách.';
            tomTat.className = 'small muted';
            return;
        }

        tomTat.textContent = moTa(k) || 'Đã có trong danh sách khách hàng.';
        tomTat.className = 'small muted';

        // Số bị chặn đặt online, hoặc khách hay bỏ hẹn: nói rõ trước khi giữ bàn.
        if (k.blocked || k.no_show >= 2) {
            tomTat.textContent = (k.blocked ? 'Số này đang bị chặn đặt online. ' : '') + tomTat.textContent;
            tomTat.className = 'small';
            tomTat.style.color = 'var(--danger)';
        } else {
            tomTat.style.color = '';
        }

        if (!k.name) {
            return;
        }

        // Ô tên còn trống thì điền luôn. Đã có chữ rồi thì không đè lên -
        // lễ tân có thể đang cố tình ghi khác đi cho lần đặt này.
        if (oTen.value.trim() === '') {
            oTen.value = k.name;
            tenKhac.textContent = 'Lấy từ ' + (k.name_source || 'lịch sử đặt bàn') + '.';
            tenKhac.className = 'small muted';
            return;
        }

        if (oTen.value.trim().toLowerCase() !== k.name.toLowerCase()) {
            tenKhac.textContent = '';
            tenKhac.className = 'small muted';
            tenKhac.append(document.createTextNode('Trong ' + (k.name_source || 'lịch sử') + ' ghi là “' + k.name + '”. '));

            var nut = document.createElement('button');
            nut.type = 'button';
            nut.className = 'btn btn-ghost btn-sm';
            nut.textContent = 'Dùng tên này';
            nut.addEventListener('click', function () {
                oTen.value = k.name;
                tenKhac.textContent = 'Đã dùng tên trong ' + (k.name_source || 'lịch sử') + '.';
            });

            tenKhac.appendChild(nut);
        }
    }

    async function tra() {
        var so = oSdt.value.trim();

        if (so.replace(/\D/g, '').length < 8) {
            soDaTra = '';
            xoaGoiY();
            return;
        }

        if (so === soDaTra) {
            return;
        }

        soDaTra = so;
        var thePhieu = ++lanGoi;

        try {
            var res = await fetch(duongDan + '?phone=' + encodeURIComponent(so), {
                headers: { 'Accept': 'application/json' },
            });

            if (!res.ok) { return; }

            var k = await res.json();

            // Lễ tân đã gõ tiếp số khác thì bỏ qua kết quả cũ.
            if (thePhieu !== lanGoi) { return; }

            hien(k);
        } catch (e) {
            // Tra cứu hỏng thì im lặng: đây là tiện ích, không được cản việc đặt bàn.
        }
    }

    oSdt.addEventListener('input', function () {
        window.clearTimeout(hen);
        hen = window.setTimeout(tra, 400);
    });

    oSdt.addEventListener('change', tra);

    // Số đã điền sẵn (khách quay lại form sau khi có lỗi) thì tra luôn.
    if (oSdt.value.trim() !== '') {
        tra();
    }
})();
</script>
@endpush
