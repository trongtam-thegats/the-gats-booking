@extends('layouts.admin')

@section('title', 'Đặt bàn ' . $booking->code)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $booking->code }} <span class="pill status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span></h1>
            <p>{{ $booking->branch->name }} · {{ $booking->booking_date->format('d/m/Y') }} · {{ $booking->timeRangeLabel() }}</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.bookings.index') }}">Về danh sách</a>
    </div>

    @if ($guest['total'] > 1 || $guest['note'])
        @php($note = $guest['note'])
        <div class="alert {{ $note?->is_blocked ? 'alert-error' : ($guest['no_show'] >= 2 ? 'alert-error' : 'alert-info') }}">
            <b>Khách quen:</b>
            đã đặt {{ $guest['total'] }} lần, đến {{ $guest['completed'] }} lần
            @if ($guest['no_show'] > 0), <b>{{ $guest['no_show'] }} lần hẹn mà không tới</b>@endif.
            @if ($note?->is_vip) Đánh dấu <b>VIP</b>. @endif
            @if ($note?->is_blocked) Đang <b>bị chặn đặt online</b>. @endif
            @if ($note?->note) <br>Ghi chú: {{ $note->note }} @endif
            <a href="{{ route('admin.guests.index', ['phone' => $guest['phone']]) }}">Xem hồ sơ khách</a>
        </div>
    @endif

    @if (auth()->user()->canWrite())
    <div class="card">
        <h2>Thao tác</h2>
        <p class="sub">Xác nhận sẽ gửi tin cho khách qua các kênh đang bật.</p>
        <div class="row">
            {{-- Don da huy hoac da danh dau khong den van dua ve lai duoc: nhan vien
                 bam nham la chuyen thuong, va khach goi lai doi y cung vay. Buoc xac
                 nhan se tu giu lai bàn, hoac bao loi neu khung gio da kin. --}}
            @php($daNhaBan = in_array($booking->status, [
                \App\Models\Booking::STATUS_CANCELLED,
                \App\Models\Booking::STATUS_NO_SHOW,
            ], true))

            @if ($booking->status === \App\Models\Booking::STATUS_PENDING || $daNhaBan)
                <form method="post" action="{{ route('admin.bookings.confirm', $booking) }}"
                      @if ($daNhaBan) onsubmit="return confirm('Đưa {{ $booking->code }} trở lại và giữ bàn cho khách?')" @endif>
                    @csrf
                    <button class="btn btn-ok" type="submit">
                        {{ $daNhaBan ? 'Đưa trở lại và giữ bàn' : 'Xác nhận đặt bàn' }}
                    </button>
                </form>
            @endif

            @if (in_array($booking->status, [\App\Models\Booking::STATUS_PENDING, \App\Models\Booking::STATUS_CONFIRMED]))
                <form method="post" action="{{ route('admin.bookings.transition', [$booking, 'seated']) }}">
                    @csrf
                    <button class="btn btn-ghost" type="submit">Khách đã đến</button>
                </form>
                <form method="post" action="{{ route('admin.bookings.transition', [$booking, 'no-show']) }}"
                      onsubmit="return confirm('Đánh dấu khách không đến và nhả bàn?')">
                    @csrf
                    <button class="btn btn-ghost" type="submit">Khách không đến</button>
                </form>
            @endif

            @if ($booking->status === \App\Models\Booking::STATUS_SEATED)
                <form method="post" action="{{ route('admin.bookings.transition', [$booking, 'completed']) }}">
                    @csrf
                    <button class="btn btn-ok" type="submit">Hoàn tất</button>
                </form>
            @endif
        </div>

        @if ($booking->isActive())
            <form method="post" action="{{ route('admin.bookings.cancel', $booking) }}" class="form-grid"
                  style="margin-top:16px" onsubmit="return confirm('Hủy đặt bàn {{ $booking->code }}?')">
                @csrf
                <div class="field">
                    <label for="reason">Lý do hủy</label>
                    <input type="text" id="reason" name="reason" maxlength="200" placeholder="Khách báo hủy qua điện thoại…">
                </div>
                <div class="field">
                    <label>&nbsp;</label>
                    <button class="btn btn-danger" type="submit">Hủy đặt bàn</button>
                </div>
            </form>
        @endif
    </div>
    @endif

    @if ($booking->isActive() && auth()->user()->canWrite())
        <div class="card">
            <h2>Đổi lịch / sửa thông tin</h2>
            <p class="sub">
                Đổi giờ xong hệ thống tự kiểm tra còn bàn không. Bàn đang giữ được giữ nguyên nếu khung giờ mới
                vẫn trống, không thì tự chọn bàn khác.
            </p>

            <form method="post" action="{{ route('admin.bookings.reschedule', $booking) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="booking_date">Ngày</label>
                    <input type="date" id="booking_date" name="booking_date"
                           value="{{ $booking->booking_date->toDateString() }}" required>
                </div>
                <div class="field">
                    <label for="start_time">Giờ</label>
                    <select id="start_time" name="start_time" required>
                        @foreach ($slotTimes as $time)
                            <option value="{{ $time }}" @selected(substr($booking->start_time, 0, 5) === $time)>{{ $time }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="party_size">Số khách</label>
                    <input type="number" id="party_size" name="party_size" min="1" max="200"
                           value="{{ $booking->party_size }}" required>
                </div>
                @if ($areas->isNotEmpty())
                    <div class="field">
                        <label for="area_id">Khu vực</label>
                        <select id="area_id" name="area_id">
                            <option value="">Không yêu cầu</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}" @selected($booking->area_id === $area->id)>{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="field">
                    <label for="customer_name">Họ tên</label>
                    <input type="text" id="customer_name" name="customer_name"
                           value="{{ $booking->customer_name }}" required>
                </div>
                <div class="field">
                    <label for="customer_phone">Số điện thoại</label>
                    <input type="tel" id="customer_phone" name="customer_phone"
                           value="{{ $booking->customer_phone }}" required>
                </div>
                <div class="field">
                    <label for="customer_email">Email</label>
                    <input type="email" id="customer_email" name="customer_email"
                           value="{{ $booking->customer_email }}">
                </div>
                <div class="field full">
                    <label for="guest_note_field">Ghi chú của khách</label>
                    <input type="text" id="guest_note_field" name="note" maxlength="500" value="{{ $booking->note }}">
                </div>
                <div class="field full">
                    <label class="check">
                        <input type="checkbox" name="notify_guest" value="1" checked>
                        Nhắn cho khách biết thông tin mới
                    </label>
                </div>
                <div class="field full">
                    <button class="btn" type="submit">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid-2">
        <div class="card">
            <h2>Thông tin khách</h2>
            <table>
                <tbody>
                <tr><td class="muted">Họ tên</td><td>{{ $booking->customer_name }}</td></tr>
                <tr><td class="muted">Điện thoại</td><td>{{ $booking->customer_phone }}</td></tr>
                <tr><td class="muted">Email</td><td>{{ $booking->customer_email ?: '—' }}</td></tr>
                <tr><td class="muted">Số khách</td><td>{{ $booking->party_size }}</td></tr>
                <tr><td class="muted">Khu vực yêu cầu</td><td>{{ $booking->area?->name ?? 'Không yêu cầu' }}</td></tr>
                <tr><td class="muted">Nguồn</td><td>{{ $booking->sourceLabel() }}</td></tr>
                <tr><td class="muted">Ghi chú của khách</td><td>{{ $booking->note ?: '—' }}</td></tr>
                <tr><td class="muted">Tạo lúc</td><td>{{ $booking->created_at->format('H:i d/m/Y') }}
                        @if ($booking->createdBy) · {{ $booking->createdBy->name }} @endif</td></tr>
                @if ($booking->confirmedBy)
                    <tr><td class="muted">Xác nhận bởi</td><td>{{ $booking->confirmedBy->name }} ·
                            {{ $booking->confirmed_at?->format('H:i d/m/Y') }}</td></tr>
                @endif
                @if ($booking->cancelled_at)
                    <tr><td class="muted">Hủy lúc</td><td>{{ $booking->cancelled_at->format('H:i d/m/Y') }}
                            ({{ $booking->cancelled_by_type === 'customer' ? 'khách tự hủy' : 'nhân viên hủy' }})</td></tr>
                    <tr><td class="muted">Lý do</td><td>{{ $booking->cancel_reason ?: '—' }}</td></tr>
                @endif
                </tbody>
            </table>

            @if (auth()->user()->canWrite())
                <form method="post" action="{{ route('admin.bookings.note', $booking) }}" style="margin-top:14px">
                    @csrf
                    <div class="field">
                        <label for="internal_note">Ghi chú nội bộ</label>
                        <textarea id="internal_note" name="internal_note" maxlength="1000">{{ $booking->internal_note }}</textarea>
                    </div>
                    <button class="btn btn-ghost btn-sm" type="submit" style="margin-top:8px">Lưu ghi chú</button>
                </form>
            @elseif ($booking->internal_note)
                <p class="hint" style="margin-top:14px"><b>Ghi chú nội bộ:</b> {{ $booking->internal_note }}</p>
            @endif
        </div>

        <div class="card">
            <h2>Xếp bàn</h2>
            <p class="sub">Đang giữ: <b>{{ $booking->tableCodes() ?: 'Chưa xếp bàn' }}</b>. Chỉ hiện các bàn còn trống trong khung giờ này.</p>

            <form method="post" action="{{ route('admin.bookings.tables', $booking) }}" id="form-xep-ban"
                  @class(['is-readonly' => ! auth()->user()->canWrite()])>
                @csrf
                @php($assigned = $booking->diningTables->pluck('id')->all())
                @php($options = $freeTables->concat($booking->diningTables)->unique('id')->load('combinedTables'))
                @php($optionsByArea = $options->groupBy(fn ($t) => $t->area?->name ?? 'Bàn chưa phân khu'))

                @if ($options->isEmpty())
                    <p class="muted">Chi nhánh chưa khai báo bàn nào, hoặc tất cả đã kín trong khung giờ này.</p>
                @else
                    <div id="table-selects-wrapper" style="display:flex; flex-direction:column; gap:10px; max-width:540px">
                        @php($selectedList = count($assigned) > 0 ? $assigned : [null])
                        @foreach ($selectedList as $idx => $assignedId)
                            <div class="table-row-item" style="display:flex; align-items:center; gap:8px">
                                <div style="flex:1">
                                    <select name="table_ids[]" class="table-picker">
                                        <option value="">-- Chọn bàn --</option>
                                        @foreach ($optionsByArea as $areaName => $tables)
                                            <optgroup label="{{ $areaName }}">
                                                @foreach ($tables as $t)
                                                    <option value="{{ $t->id }}"
                                                            data-seats-min="{{ $t->seats_min }}"
                                                            data-seats-max="{{ $t->seats_max }}"
                                                            data-code="{{ $t->code }}"
                                                            data-combined='@json($t->combinedTables->pluck("id")->all())'
                                                            @selected($t->id === $assignedId)>
                                                        {{ $t->code }} ({{ $t->capacityLabel() }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm btn-remove-table" title="Gỡ bàn này"
                                        style="color:var(--danger, #e53e3e); padding:6px 10px; font-size:13px; line-height:1">
                                    ✕ Gỡ
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <div style="margin-top:12px; display:flex; align-items:center; gap:12px; flex-wrap:wrap">
                        @if (auth()->user()->canWrite())
                            <button type="button" id="btn-add-table" class="btn btn-ghost btn-sm" style="display:inline-flex; align-items:center; gap:4px">
                                <span>+</span> Ghép thêm bàn
                            </button>
                        @endif
                        <span id="table-summary" class="muted small"></span>
                    </div>

                    @if (auth()->user()->canWrite())
                        <div style="margin-top:16px">
                            <button class="btn btn-primary btn-sm" type="submit">Lưu thay đổi</button>
                        </div>
                    @endif
                @endif
            </form>
        </div>
    </div>

    <div class="card">
        <h2>Đặt lịch bên Sapo</h2>
        @if ($booking->sapo_can_xu_ly)
            <p class="alert alert-error">{{ $booking->sapo_can_xu_ly }}</p>
            @if (auth()->user()->canWrite())
                <form method="post" action="{{ route('admin.bookings.sapo-da-xu-ly', $booking) }}">
                    @csrf
                    <button class="btn btn-sm" type="submit">Tôi đã xử lý bên Sapo</button>
                </form>
            @endif
        @elseif ($booking->sapo_code)
            <p class="hint">Đã đẩy sang Sapo · mã <b>{{ $booking->sapo_code }}</b>
                · {{ $booking->sapo_pushed_at?->format('H:i d/m/Y') }}</p>
        @elseif ($booking->sapo_error)
            <p class="hint">Chưa đẩy được: {{ $booking->sapo_error }}.
                Hệ thống tự thử lại mỗi 5 phút cho tới khi Sapo nhận.</p>
        @else
            <p class="hint">Chưa đẩy. Đơn được đẩy sang Sapo ngay khi xác nhận.</p>
        @endif
    </div>

    <div class="card">
        <h2>Nhật ký gửi thông báo</h2>
        <p class="sub">Ghi lại từng lần hệ thống nhắn cho khách, kể cả khi kênh chưa được cấu hình.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Thời điểm</th><th>Kênh</th><th>Sự kiện</th><th>Người nhận</th><th>Kết quả</th><th>Chi tiết</th></tr>
                </thead>
                <tbody>
                @forelse ($booking->notificationLogs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('H:i d/m') }}</td>
                        <td>{{ $log->channelLabel() }}</td>
                        <td class="small muted">{{ $log->event }}</td>
                        <td class="small">{{ $log->recipient ?: '—' }}</td>
                        <td class="small">{{ $log->statusLabel() }}</td>
                        <td class="small muted">{{ $log->error ?: '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Chưa có lượt gửi nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (auth()->user()->isAdmin())
        {{-- Xoa han: chi quan tri, va co y dat cuoi trang cho kho bam nham. --}}
        <div class="card">
            <h2>Xóa vĩnh viễn</h2>
            <p class="sub">
                Dùng khi đơn này <b>sai</b> — đơn trùng, gõ nhầm, đơn thử — và không được phép
                nằm trong báo cáo hay phân tích khách hàng. Khác với <b>hủy</b>: đơn hủy vẫn
                còn trong số liệu với trạng thái “đã hủy”.
            </p>

            <div class="alert alert-error">
                Đơn sẽ biến mất khỏi hệ thống cùng bàn đang giữ và nhật ký gửi tin.
                Một bản sao đầy đủ được lưu ở <a href="{{ route('admin.bookings.deletions') }}">Nhật ký xóa</a>
                kèm tên người xóa và lý do.
            </div>

            <form method="post" action="{{ route('admin.bookings.destroy', $booking) }}" id="form-xoa">
                @csrf
                @method('delete')

                <div class="form-grid">
                    <div class="field">
                        <label for="reason_xoa">Lý do xóa <span class="muted small">(bắt buộc)</span></label>
                        <input type="text" id="reason_xoa" name="reason" required minlength="3" maxlength="200"
                               value="{{ old('reason') }}" placeholder="Đơn trùng do khách bấm hai lần…">
                        @error('reason')<span class="small" style="color:var(--danger)">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="xac_nhan_ma">Gõ lại mã <b>{{ $booking->code }}</b> để xác nhận</label>
                        <input type="text" id="xac_nhan_ma" autocomplete="off" spellcheck="false"
                               placeholder="{{ $booking->code }}">
                    </div>
                </div>

                <button class="btn btn-danger" type="submit" style="margin-top:14px">
                    Xóa vĩnh viễn {{ $booking->code }}
                </button>
            </form>
        </div>

        @push('scripts')
        <script>
        (function () {
            const form = document.getElementById('form-xoa');
            const oMa  = document.getElementById('xac_nhan_ma');
            const ma   = @json($booking->code);

            form.addEventListener('submit', event => {
                if (oMa.value.trim().toUpperCase() !== ma) {
                    event.preventDefault();
                    oMa.focus();
                    alert('Gõ đúng mã ' + ma + ' thì mới xóa được. Đây là thao tác không hoàn tác lại được.');
                }
            });
        })();
        </script>
        @endpush
    @endif

    @push('scripts')
    <script>
    (function () {
        const wrapper = document.getElementById('table-selects-wrapper');
        const form    = document.getElementById('form-xep-ban');
        const btnAdd  = document.getElementById('btn-add-table');
        const summary = document.getElementById('table-summary');
        const partySize = @json($booking->party_size);

        if (!wrapper || !form) return;

        function capNhatSummary() {
            const selects = wrapper.querySelectorAll('select.table-picker');
            let totalSeats = 0;
            let count = 0;
            const selectedIds = new Set();
            let hasDuplicate = false;

            selects.forEach(sel => {
                const opt = sel.options[sel.selectedIndex];
                if (sel.value && opt) {
                    if (selectedIds.has(sel.value)) {
                        hasDuplicate = true;
                    }
                    selectedIds.add(sel.value);
                    totalSeats += parseInt(opt.getAttribute('data-seats-max') || '0', 10);
                    count++;
                }
            });

            if (count === 0) {
                summary.textContent = 'Chưa chọn bàn nào (đoàn ' + partySize + ' khách).';
                summary.style.color = 'var(--muted)';
            } else {
                let msg = 'Đã chọn ' + count + ' bàn · Tổng sức chứa: ' + totalSeats + ' chỗ (đoàn ' + partySize + ' khách)';
                if (hasDuplicate) {
                    msg += ' · ⚠️ Có bàn bị chọn trùng!';
                    summary.style.color = 'var(--danger, #e53e3e)';
                } else if (totalSeats < partySize) {
                    msg += ' · ⚠️ Thiếu ' + (partySize - totalSeats) + ' chỗ';
                    summary.style.color = '#d97706';
                } else {
                    msg += ' · Đủ chỗ ✓';
                    summary.style.color = 'var(--gold)';
                }
                summary.textContent = msg;
            }

            const removeBtns = wrapper.querySelectorAll('.btn-remove-table');
            removeBtns.forEach(btn => {
                btn.style.visibility = (selects.length > 1) ? 'visible' : 'hidden';
            });
        }

        function ganSuKienDong(row) {
            const select = row.querySelector('select.table-picker');
            const btnRemove = row.querySelector('.btn-remove-table');

            if (select) {
                select.addEventListener('change', capNhatSummary);
            }

            if (btnRemove) {
                btnRemove.addEventListener('click', () => {
                    const totalRows = wrapper.querySelectorAll('.table-row-item').length;
                    if (totalRows > 1) {
                        row.remove();
                    } else {
                        if (select) select.value = '';
                    }
                    capNhatSummary();
                });
            }
        }

        wrapper.querySelectorAll('.table-row-item').forEach(ganSuKienDong);

        if (btnAdd) {
            btnAdd.addEventListener('click', () => {
                const currentRows = wrapper.querySelectorAll('.table-row-item');
                if (currentRows.length >= 4) {
                    alert('Tối đa chỉ ghép được 4 bàn.');
                    return;
                }

                const templateRow = currentRows[0];
                if (!templateRow) return;

                const newRow = templateRow.cloneNode(true);
                const select = newRow.querySelector('select.table-picker');
                if (select) {
                    select.value = '';
                }
                wrapper.appendChild(newRow);
                ganSuKienDong(newRow);
                capNhatSummary();
                if (select) select.focus();
            });
        }

        form.addEventListener('submit', () => {
            wrapper.querySelectorAll('select.table-picker').forEach(sel => {
                if (!sel.value) {
                    sel.disabled = true;
                }
            });
        });

        capNhatSummary();
    })();
    </script>
    @endpush
@endsection
