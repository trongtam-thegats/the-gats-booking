@extends('layouts.admin')

@php($isNew = ! $branch->exists)

@section('title', $isNew ? 'Thêm chi nhánh' : $branch->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $isNew ? 'Thêm chi nhánh' : $branch->name }}</h1>
            <p>{{ $isNew ? 'Khai báo giờ hoạt động trước, sau đó thêm khu vực và bàn.' : 'Cấu hình nhận đặt bàn của chi nhánh này.' }}</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.branches.index') }}">Về danh sách</a>
    </div>

    <form method="post" action="{{ $isNew ? route('admin.branches.store') : route('admin.branches.update', $branch) }}" class="card">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <h2>Thông tin chung</h2>
        <p class="sub">Tên và địa chỉ hiển thị cho khách trên trang đặt bàn.</p>

        <div class="form-grid">
            <div class="field">
                <label for="brand_id">Thương hiệu</label>
                <select id="brand_id" name="brand_id" required @disabled(! auth()->user()->isAdmin())>
                    <option value="">— Chọn thương hiệu —</option>
                    @foreach ($brands as $brandOption)
                        <option value="{{ $brandOption->id }}"
                                @selected(old('brand_id', $branch->brand_id) == $brandOption->id)>{{ $brandOption->name }}</option>
                    @endforeach
                </select>
                @if (! auth()->user()->isAdmin())
                    <input type="hidden" name="brand_id" value="{{ $branch->brand_id }}">
                    <span class="hint">Chỉ quản trị mới đổi được thương hiệu của chi nhánh.</span>
                @else
                    <span class="hint">Chi nhánh chưa gán thương hiệu sẽ không hiện trên trang đặt bàn của khách.</span>
                @endif
            </div>
            <div class="field">
                <label for="name">Tên chi nhánh</label>
                <input type="text" id="name" name="name" value="{{ old('name', $branch->name) }}" required>
            </div>
            <div class="field">
                <label for="slug">Đường dẫn</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug', $branch->slug) }}"
                       placeholder="tu-dong-tao-tu-ten" @disabled(! auth()->user()->isAdmin())>
                <span class="hint">Dùng trong link đặt bàn: /dat-ban/<b>{{ $branch->slug ?: 'ten-chi-nhanh' }}</b></span>
            </div>
            <div class="field">
                <label for="phone">Điện thoại</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $branch->phone) }}">
            </div>
            <div class="field full">
                <label for="address">Địa chỉ</label>
                <input type="text" id="address" name="address" value="{{ old('address', $branch->address) }}">
            </div>
            <div class="field full">
                <label for="description">Mô tả ngắn</label>
                <textarea id="description" name="description" maxlength="1000">{{ old('description', $branch->description) }}</textarea>
            </div>
            <div class="field full">
                <label for="map_url">Link bản đồ</label>
                <input type="text" id="map_url" name="map_url" value="{{ old('map_url', $branch->map_url) }}"
                       placeholder="https://maps.google.com/…">
            </div>
        </div>

        <h2 style="margin-top:24px">Quy tắc nhận đặt bàn</h2>
        <p class="sub">Đây là phần quyết định khung giờ và sức chứa mà khách nhìn thấy.</p>

        <div class="form-grid">
            <div class="field">
                <label for="open_time">Bắt đầu nhận khách</label>
                <input type="time" id="open_time" name="open_time"
                       value="{{ old('open_time', substr($branch->open_time ?? '17:00', 0, 5)) }}" required>
            </div>
            <div class="field">
                <label for="close_time">Đóng cửa</label>
                <input type="time" id="close_time" name="close_time"
                       value="{{ old('close_time', substr($branch->close_time ?? '23:30', 0, 5)) }}" required>
                <span class="hint">Đóng sau nửa đêm cứ nhập bình thường, ví dụ 02:00.</span>
            </div>
            <div class="field">
                <label for="last_booking_time">Nhận đặt bàn trễ nhất</label>
                <input type="time" id="last_booking_time" name="last_booking_time"
                       value="{{ old('last_booking_time', $branch->last_booking_time ? substr($branch->last_booking_time, 0, 5) : '') }}">
                <span class="hint">
                    Sau mốc này quán vẫn mở nhưng không nhận đặt bàn mới. Bỏ trống thì hệ thống tự tính
                    theo giờ đóng cửa trừ thời lượng giữ bàn.
                </span>
            </div>
            <div class="field">
                <label for="slot_minutes">Bước chia khung giờ (phút)</label>
                <input type="number" id="slot_minutes" name="slot_minutes" min="15" max="120" step="5"
                       value="{{ old('slot_minutes', $branch->slot_minutes) }}" required>
            </div>
            <div class="field">
                <label for="turn_minutes">Thời lượng giữ bàn (phút)</label>
                <input type="number" id="turn_minutes" name="turn_minutes" min="30" max="480" step="15"
                       value="{{ old('turn_minutes', $branch->turn_minutes) }}" required>
                <span class="hint">Bàn được giữ trong khoảng này rồi tự mở lại cho lượt sau.</span>
            </div>
            <div class="field">
                <label for="min_lead_minutes">Đặt trước tối thiểu (phút)</label>
                <input type="number" id="min_lead_minutes" name="min_lead_minutes" min="0" max="1440" step="15"
                       value="{{ old('min_lead_minutes', $branch->min_lead_minutes) }}" required>
            </div>
            <div class="field">
                <label for="max_advance_days">Đặt trước tối đa (ngày)</label>
                <input type="number" id="max_advance_days" name="max_advance_days" min="1" max="365"
                       value="{{ old('max_advance_days', $branch->max_advance_days) }}" required>
            </div>
            <div class="field">
                <label for="max_party_size">Số khách tối đa cho đặt online</label>
                <input type="number" id="max_party_size" name="max_party_size" min="1" max="200"
                       value="{{ old('max_party_size', $branch->max_party_size) }}" required>
                <span class="hint">Đoàn đông hơn sẽ được mời gọi trực tiếp chi nhánh.</span>
            </div>
            <div class="field">
                <label for="sort_order">Thứ tự hiển thị</label>
                <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                       value="{{ old('sort_order', $branch->sort_order ?? 0) }}">
            </div>
            <div class="field full">
                <label class="check">
                    <input type="checkbox" name="auto_confirm" value="1"
                           @checked(old('auto_confirm', $branch->auto_confirm))>
                    Tự động xác nhận, không cần quản lý duyệt tay
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $branch->exists ? $branch->is_active : true))
                           @disabled(! auth()->user()->isAdmin())>
                    Đang nhận đặt bàn trực tuyến
                </label>
            </div>
        </div>

        <div class="row" style="margin-top:18px">
            <button class="btn" type="submit">{{ $isNew ? 'Tạo chi nhánh' : 'Lưu cấu hình' }}</button>
            @if (! $isNew && auth()->user()->isAdmin())
                <a class="btn btn-ghost" href="{{ route('admin.tables.index', ['branch' => $branch->id]) }}">Khai báo bàn</a>
            @endif
        </div>
    </form>

    @unless ($isNew)
        <div class="card">
            <h2>Lịch nghỉ</h2>
            <p class="sub">Ngày nghỉ hoặc khung giờ bao trọn sự kiện riêng. Khách sẽ không đặt được vào các mốc này.</p>

            <form method="post" action="{{ route('admin.branches.closures.store', $branch) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="closure_date">Ngày</label>
                    <input type="date" id="closure_date" name="date" required>
                </div>
                <div class="field">
                    <label for="closure_start">Từ giờ <span class="muted">(bỏ trống = nghỉ cả ngày)</span></label>
                    <input type="time" id="closure_start" name="start_time">
                </div>
                <div class="field">
                    <label for="closure_end">Đến giờ</label>
                    <input type="time" id="closure_end" name="end_time">
                </div>
                <div class="field">
                    <label for="closure_reason">Lý do</label>
                    <input type="text" id="closure_reason" name="reason" maxlength="120" placeholder="Sự kiện riêng, bảo trì…">
                </div>
                <div class="field">
                    <label>&nbsp;</label>
                    <button class="btn btn-ghost" type="submit">Thêm</button>
                </div>
            </form>

            <div class="table-wrap" style="margin-top:14px">
                <table>
                    <thead><tr><th>Ngày</th><th>Khung giờ</th><th>Lý do</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($closures ?? [] as $closure)
                        <tr>
                            <td>{{ $closure->date->format('d/m/Y') }}</td>
                            <td>{{ $closure->isFullDay() ? 'Cả ngày' : substr($closure->start_time, 0, 5).' – '.substr($closure->end_time, 0, 5) }}</td>
                            <td class="small muted">{{ $closure->reason ?: '—' }}</td>
                            <td class="num">
                                <form method="post" action="{{ route('admin.branches.closures.destroy', [$branch, $closure]) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Chưa khai báo lịch nghỉ nào.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (auth()->user()->isAdmin())
            <div class="card">
                <h2>Xóa chi nhánh</h2>
                <p class="sub">Chỉ xóa được khi chưa phát sinh đặt bàn. Còn lại nên tắt trạng thái nhận đặt bàn.</p>
                <form method="post" action="{{ route('admin.branches.destroy', $branch) }}"
                      onsubmit="return confirm('Xóa chi nhánh {{ $branch->name }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit">Xóa chi nhánh</button>
                </form>
            </div>
        @endif
    @endunless
@endsection
