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

            <div class="field">
                <label for="customer_name">Họ tên khách</label>
                <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required>
            </div>
            <div class="field">
                <label for="customer_phone">Số điện thoại</label>
                <input type="tel" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" required>
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
