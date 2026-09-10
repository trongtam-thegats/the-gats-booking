@extends('layouts.admin')

@section('title', 'Tra cứu khách')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tra cứu khách</h1>
            <p>Gõ số điện thoại, tên hoặc mã đặt bàn. Khách gọi tới là biết ngay họ đã đến bao nhiêu lần.</p>
        </div>
    </div>

    <div class="card">
        <form method="get" class="filters" style="margin-bottom:0">
            <div class="field" style="min-width:280px">
                <label for="q">Số điện thoại, tên khách hoặc mã đặt bàn</label>
                <input type="text" id="q" name="q" value="{{ $term }}" placeholder="0912 345 678" autofocus>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button class="btn" type="submit">Tìm</button>
            </div>
        </form>
    </div>

    @if ($results->isNotEmpty())
        <div class="card">
            <h2>{{ $results->count() }} khách khớp</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Khách</th><th>Điện thoại</th><th class="num">Số lần đặt</th><th>Lần gần nhất</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($results as $row)
                        <tr>
                            <td><b>{{ $row['name'] }}</b></td>
                            <td>{{ $row['last']->customer_phone }}</td>
                            <td class="num">{{ $row['total'] }}</td>
                            <td class="small muted">
                                {{ $row['last']->booking_date->format('d/m/Y') }} ·
                                {{ $row['last']->branch->name }} ·
                                <span class="pill status-{{ $row['last']->status }}">{{ $row['last']->statusLabel() }}</span>
                            </td>
                            <td class="num">
                                <a class="btn btn-ghost btn-sm"
                                   href="{{ route('admin.guests.index', ['phone' => $row['phone']]) }}">Xem hồ sơ</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($term !== '' && ! $profile)
        <div class="card">
            <p class="empty mb-0">Không tìm thấy khách nào khớp với “{{ $term }}”.</p>
        </div>
    @endif

    @if ($profile)
        @php($note = $profile['note'])

        <div class="page-head" style="margin-top:24px">
            <div>
                <h1 style="font-size:20px">
                    {{ $profile['name'] ?: 'Khách' }}
                    @if ($note?->is_vip)<span class="pill status-confirmed">VIP</span>@endif
                    @if ($note?->is_blocked)<span class="pill status-cancelled">Đã chặn đặt online</span>@endif
                </h1>
                <p>{{ $profile['phone'] }}</p>
            </div>
            <a class="btn btn-ghost" href="{{ route('admin.guests.index') }}">Tìm khách khác</a>
        </div>

        <div class="stats">
            <div class="stat"><b>{{ $profile['total'] }}</b><span>Lần đặt</span></div>
            <div class="stat accent"><b>{{ $profile['completed'] }}</b><span>Lần đã đến</span></div>
            <div class="stat warn"><b>{{ $profile['no_show'] }}</b><span>Hẹn mà không tới</span></div>
            <div class="stat"><b>{{ $profile['cancelled'] }}</b><span>Lần hủy</span></div>
            <div class="stat"><b>{{ $profile['upcoming'] }}</b><span>Lịch sắp tới</span></div>
            <div class="stat"><b>{{ $profile['guests_served'] }}</b><span>Tổng khách đã phục vụ</span></div>
        </div>

        @if ($profile['no_show'] >= 2)
            <div class="alert alert-error">
                Khách này đã {{ $profile['no_show'] }} lần đặt bàn mà không đến. Cân nhắc gọi xác nhận lại trước giờ hẹn.
            </div>
        @endif

        {{-- Chan dung suy ra tu hoa don POS. Khop theo so dien thoai da chuan hoa. --}}
        @if ($chanDung)
            @php($cd = $chanDung['stats'])
            @php($phutNgoi = $cd['dwell_median'])

            <div class="card">
                <h2>Chân dung từ hóa đơn</h2>
                <p class="muted small">
                    Tính trên {{ $cd['visits'] }} hóa đơn đã thanh toán, khớp theo số điện thoại.
                    @if (! $xemDuocTien)
                        Số tiền chỉ hiện với vai quản lý trở lên.
                    @endif
                </p>

                <div class="stats">
                    <div class="stat">
                        <span>Số lần đã ghé</span>
                        <b>{{ $cd['visits'] }}</b>
                        <small class="muted">
                            {{ $cd['last_at'] ? 'gần nhất '.$cd['last_at']->format('d/m/Y') : '' }}
                        </small>
                    </div>

                    @if ($xemDuocTien)
                        <div class="stat accent">
                            <span>Chi trung bình</span>
                            <b>{{ number_format($cd['avg']) }}<small>đ</small></b>
                            <small class="muted">cao nhất {{ number_format($cd['max']) }}đ</small>
                        </div>
                    @endif

                    <div class="stat">
                        <span>Thường ngồi</span>
                        <b>
                            @if ($phutNgoi === null)
                                —
                            @elseif ($phutNgoi >= 60)
                                {{ intdiv($phutNgoi, 60) }}<small>g</small>{{ $phutNgoi % 60 ? sprintf('%02d', $phutNgoi % 60) : '' }}
                            @else
                                {{ $phutNgoi }}<small> phút</small>
                            @endif
                        </b>
                        <small class="muted">{{ $phutNgoi === null ? 'chưa đủ dữ liệu' : 'trung vị mỗi lần' }}</small>
                    </div>

                    <div class="stat">
                        <span>Hay đi mấy người</span>
                        <b>{{ $cd['party_mode'] ?? '—' }}<small>{{ $cd['party_mode'] ? ' người' : '' }}</small></b>
                        <small class="muted">nhịp ghé {{ $cd['cadence'] === null ? '—' : $cd['cadence'].' ngày' }}</small>
                    </div>
                </div>

                <div class="form-grid" style="margin-top:16px">
                    @foreach ([
                        'mon' => 'Món hay gọi',
                        'danh_muc' => 'Nhóm đồ hay uống',
                        'table' => 'Bàn hay ngồi',
                        'area' => 'Khu vực hay ngồi',
                        'dwell' => 'Thường ngồi bao lâu',
                        'party' => 'Hay đi mấy người',
                        'weekday' => 'Hay ghé thứ mấy',
                        'hour' => 'Khung giờ thanh toán',
                    ] as $khoa => $nhan)
                        @if (! empty($chanDung['habits'][$khoa]))
                            <div class="field">
                                <label>{{ $nhan }}</label>
                                <div class="table-wrap">
                                    <table>
                                        <tbody>
                                        @foreach ($chanDung['habits'][$khoa] as $dong)
                                            <tr>
                                                <td class="small">{{ $dong['label'] }}</td>
                                                <td class="num small muted">{{ $dong['count'] }} lần · {{ $dong['share'] }}%</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($xemDuocTien)
                    <p class="hint">
                        <a href="{{ route('admin.customers.show', $profile['phone']) }}">Xem hồ sơ phân tích đầy đủ →</a>
                    </p>
                @endif
            </div>
        @else
            <p class="hint">
                Chưa có hóa đơn nào khớp số điện thoại này. Hóa đơn được nhập ở trang
                <b>Hóa đơn</b>, và chỉ khoảng một phần tư hóa đơn có ghi số điện thoại khách.
            </p>
        @endif

        <div class="grid-2">
            <div class="card">
                <h2>Ghi chú của quán</h2>
                <p class="sub">Chỉ nhân viên thấy. Khách không đọc được phần này.</p>

                @if (auth()->user()->canWrite())
                    <form method="post" action="{{ route('admin.guests.note') }}">
                        @csrf
                        <input type="hidden" name="phone" value="{{ $profile['phone'] }}">
                        <div class="field">
                            <label for="guest_name">Tên gọi</label>
                            <input type="text" id="guest_name" name="name"
                                   value="{{ $note?->name ?? $profile['name'] }}" maxlength="120">
                        </div>
                        <div class="field" style="margin-top:12px">
                            <label for="guest_note">Ghi chú</label>
                            <textarea id="guest_note" name="note" maxlength="1000"
                                      placeholder="Thích bàn cạnh cửa sổ, dị ứng hải sản, hay đi cùng đối tác…">{{ $note?->note }}</textarea>
                        </div>
                        <div style="margin-top:12px">
                            <label class="check">
                                <input type="checkbox" name="is_vip" value="1" @checked($note?->is_vip)> Khách VIP
                            </label>
                            <label class="check">
                                <input type="checkbox" name="is_blocked" value="1" @checked($note?->is_blocked)>
                                Chặn đặt bàn trực tuyến
                            </label>
                            <p class="hint" style="margin:6px 0 0">
                                Chặn rồi thì khách không tự đặt online được nữa, nhưng nhân viên vẫn đặt hộ được.
                            </p>
                        </div>
                        <button class="btn btn-ghost btn-sm" type="submit" style="margin-top:14px">Lưu ghi chú</button>
                    </form>
                @else
                    <p class="mb-0">{{ $note?->note ?: 'Chưa có ghi chú.' }}</p>
                @endif

                @if ($note?->updatedBy)
                    <p class="hint" style="margin-top:12px">
                        Cập nhật lần cuối bởi {{ $note->updatedBy->name }} · {{ $note->updated_at->format('H:i d/m/Y') }}
                    </p>
                @endif
            </div>

            <div class="card">
                <h2>Mốc thời gian</h2>
                <table>
                    <tbody>
                    <tr><td class="muted">Lần đầu đến</td><td>{{ $profile['first_visit'] ?? '—' }}</td></tr>
                    <tr><td class="muted">Lần gần nhất</td><td>{{ $profile['last_visit'] ?? '—' }}</td></tr>
                    <tr>
                        <td class="muted">Tỉ lệ đến</td>
                        <td>
                            @if ($profile['total'] > 0)
                                {{ round($profile['completed'] / $profile['total'] * 100) }}%
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Lịch sử đặt bàn</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Ngày giờ</th><th>Mã</th><th>Quán</th><th class="num">Khách</th><th>Bàn</th><th>Trạng thái</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($profile['bookings'] as $item)
                        <tr>
                            <td>
                                <b>{{ $item->booking_date->format('d/m/Y') }}</b><br>
                                <span class="muted small">{{ $item->timeRangeLabel() }}</span>
                            </td>
                            <td><a href="{{ route('admin.bookings.show', $item) }}">{{ $item->code }}</a></td>
                            <td class="small muted">{{ $item->branch->name }}</td>
                            <td class="num">{{ $item->party_size }}</td>
                            <td class="small">{{ $item->tableCodes() }}</td>
                            <td><span class="pill status-{{ $item->status }}">{{ $item->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
