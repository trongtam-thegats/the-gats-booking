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


            <div class="field full" id="table-picker-section" style="margin-top:4px">
                <label style="display:flex; justify-content:space-between; align-items:center">
                    <span>Xếp bàn</span>
                    <span id="table-picker-status" class="small muted"></span>
                </label>
                <div style="display:flex; gap:16px; margin-bottom:8px; font-size:14px">
                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer">
                        <input type="radio" name="table_choice_mode" value="auto" checked id="mode-auto">
                        <span>Hệ thống tự xếp bàn</span>
                    </label>
                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer">
                        <input type="radio" name="table_choice_mode" value="manual" id="mode-manual">
                        <span>Nhân viên chỉ định bàn trống</span>
                    </label>
                </div>

                <div id="manual-table-box" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px">
                    <div id="table-loading" class="small muted" style="display:none">Đang tải danh sách bàn trống...</div>
                    <div id="table-empty" class="small" style="color:var(--danger, #dc2626); display:none">Không còn bàn nào trống trong khung giờ này.</div>
                    <div id="table-list-container" style="display:flex; flex-direction:column; gap:12px"></div>
                    <div id="table-selection-summary" class="small" style="margin-top:10px; font-weight:600; color:var(--primary, #0f172a)"></div>
                </div>
                @error('table_ids')
                    <span class="small" style="color:var(--danger, #dc2626); display:block; margin-top:4px">{{ $message }}</span>
                @enderror
            </div>

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
            <div class="field">
                <label for="note">Ghi chú của khách</label>
                <textarea id="note" name="note" maxlength="500" placeholder="Yêu cầu từ khách (vị trí ngồi, ghế trẻ em...)">{{ old('note') }}</textarea>
            </div>
            <div class="field">
                <label for="internal_note">Ghi chú nội bộ</label>
                <textarea id="internal_note" name="internal_note" maxlength="1000" placeholder="Lưu ý nội bộ quán (khách quen, cọc, ghi chú đón tiếp...)">{{ old('internal_note') }}</textarea>
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

(function () {
    var modeAuto = document.getElementById('mode-auto');
    var modeManual = document.getElementById('mode-manual');
    var manualBox = document.getElementById('manual-table-box');
    var tableLoading = document.getElementById('table-loading');
    var tableEmpty = document.getElementById('table-empty');
    var tableListContainer = document.getElementById('table-list-container');
    var tableSelectionSummary = document.getElementById('table-selection-summary');
    var tablePickerStatus = document.getElementById('table-picker-status');

    var oDate = document.getElementById('booking_date');
    var oTime = document.getElementById('start_time');
    var oBranch = document.querySelector('input[name="branch_id"]');

    var availableTablesUrl = @json(route('admin.bookings.available-tables'));
    var oldTableIds = @json(array_map('intval', (array) old('table_ids', [])));

    var loadedTables = [];
    var fetchTimer = null;
    var fetchSeq = 0;

    function capNhatTomTat() {
        var checked = tableListContainer.querySelectorAll('input[name="table_ids[]"]:checked');
        if (checked.length === 0) {
            tableSelectionSummary.textContent = 'Chưa chọn bàn nào (sẽ để hệ thống tự xếp nếu gửi).';
            tableSelectionSummary.style.color = 'var(--muted, #64748b)';
            return;
        }

        var codes = [];
        var minSeats = 0;
        var maxSeats = 0;
        checked.forEach(function (cb) {
            codes.push(cb.getAttribute('data-code'));
            minSeats += parseInt(cb.getAttribute('data-seats-min') || '0', 10);
            maxSeats += parseInt(cb.getAttribute('data-seats-max') || '0', 10);
        });

        tableSelectionSummary.textContent = 'Đã chọn ' + checked.length + ' bàn: ' + codes.join(', ') + ' (Sức chứa: ' + minSeats + '–' + maxSeats + ' khách)';
        tableSelectionSummary.style.color = 'var(--primary, #0f172a)';
    }

    function renderDanhSachBan(tables) {
        tableListContainer.innerHTML = '';
        if (!tables || tables.length === 0) {
            tableEmpty.style.display = 'block';
            tableSelectionSummary.textContent = '';
            return;
        }
        tableEmpty.style.display = 'none';

        // Gom theo khu vuc
        var groups = {};
        tables.forEach(function (t) {
            var area = t.area_name || 'Bàn chưa phân khu';
            if (!groups[area]) groups[area] = [];
            groups[area].push(t);
        });

        for (var areaName in groups) {
            var groupDiv = document.createElement('div');
            groupDiv.style.marginBottom = '6px';

            var title = document.createElement('div');
            title.style.fontWeight = '600';
            title.style.fontSize = '12px';
            title.style.textTransform = 'uppercase';
            title.style.letterSpacing = '0.5px';
            title.style.color = '#64748b';
            title.style.marginBottom = '6px';
            title.textContent = areaName;
            groupDiv.appendChild(title);

            var grid = document.createElement('div');
            grid.style.display = 'grid';
            grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(150px, 1fr))';
            grid.style.gap = '8px';

            groups[areaName].forEach(function (t) {
                var card = document.createElement('label');
                card.style.display = 'flex';
                card.style.alignItems = 'center';
                card.style.gap = '8px';
                card.style.background = '#fff';
                card.style.border = '1px solid #cbd5e1';
                card.style.borderRadius = '6px';
                card.style.padding = '6px 10px';
                card.style.cursor = 'pointer';

                var isSelected = oldTableIds.includes(t.id);
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'table_ids[]';
                cb.value = t.id;
                cb.checked = isSelected;
                cb.setAttribute('data-code', t.code);
                cb.setAttribute('data-seats-min', t.seats_min);
                cb.setAttribute('data-seats-max', t.seats_max);
                cb.addEventListener('change', capNhatTomTat);

                var info = document.createElement('div');
                var codeDiv = document.createElement('div');
                codeDiv.style.fontWeight = '600';
                codeDiv.style.fontSize = '13px';
                codeDiv.textContent = t.code;

                var capDiv = document.createElement('div');
                capDiv.style.fontSize = '11px';
                capDiv.style.color = '#64748b';
                capDiv.textContent = t.capacity_label;

                info.appendChild(codeDiv);
                info.appendChild(capDiv);

                card.appendChild(cb);
                card.appendChild(info);
                grid.appendChild(card);
            });

            groupDiv.appendChild(grid);
            tableListContainer.appendChild(groupDiv);
        }

        capNhatTomTat();
    }

    async function taiBanTrong() {
        if (!modeManual.checked) return;

        var branchId = oBranch ? oBranch.value : '';
        var date = oDate ? oDate.value : '';
        var time = oTime ? oTime.value : '';

        if (!branchId || !date || !time) return;

        var curSeq = ++fetchSeq;
        tableLoading.style.display = 'block';
        tableEmpty.style.display = 'none';
        tableListContainer.innerHTML = '';
        tableSelectionSummary.textContent = '';
        tablePickerStatus.textContent = 'Đang kiểm tra bàn trống...';

        try {
            var url = availableTablesUrl + '?branch_id=' + encodeURIComponent(branchId)
                + '&booking_date=' + encodeURIComponent(date)
                + '&start_time=' + encodeURIComponent(time);

            var res = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;

            var data = await res.json();
            if (curSeq !== fetchSeq) return;

            loadedTables = data.tables || [];
            tableLoading.style.display = 'none';
            tablePickerStatus.textContent = loadedTables.length + ' bàn trống';
            renderDanhSachBan(loadedTables);
        } catch (e) {
            tableLoading.style.display = 'none';
            tablePickerStatus.textContent = '';
        }
    }

    function henTaiBanTrong() {
        if (!modeManual.checked) return;
        window.clearTimeout(fetchTimer);
        fetchTimer = window.setTimeout(taiBanTrong, 300);
    }

    modeAuto.addEventListener('change', function () {
        if (this.checked) {
            manualBox.style.display = 'none';
            tablePickerStatus.textContent = '';
            var checked = tableListContainer.querySelectorAll('input[name="table_ids[]"]:checked');
            checked.forEach(function (cb) { cb.checked = false; });
            capNhatTomTat();
        }
    });

    modeManual.addEventListener('change', function () {
        if (this.checked) {
            manualBox.style.display = 'block';
            taiBanTrong();
        }
    });

    if (oDate) oDate.addEventListener('change', henTaiBanTrong);
    if (oTime) oTime.addEventListener('change', henTaiBanTrong);

    // Neu form quay lai do co loi va co old table_ids, bat che do manual ngay
    if (oldTableIds.length > 0) {
        modeManual.checked = true;
        manualBox.style.display = 'block';
        taiBanTrong();
    }
})();
</script>
@endpush
