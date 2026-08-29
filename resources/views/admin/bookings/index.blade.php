@extends('layouts.admin')

@section('title', 'Danh sách đặt bàn')

@section('content')
    <div class="page-head">
        <div>
            <h1>Danh sách đặt bàn</h1>
            <p>{{ $branch?->name ?? 'Tất cả chi nhánh bạn phụ trách' }}</p>
        </div>
        <a class="btn" href="{{ route('admin.bookings.create') }}">Đặt bàn hộ khách</a>
    </div>

    <form method="get" class="filters">
        @include('admin.partials.branch-filter')
        <div class="field">
            <label for="from">Từ ngày</label>
            <input type="date" id="from" name="from" value="{{ $filters['from'] }}">
        </div>
        <div class="field">
            <label for="to">Đến ngày</label>
            <input type="date" id="to" name="to" value="{{ $filters['to'] }}">
        </div>
        <div class="field">
            <label for="status">Trạng thái</label>
            <select id="status" name="status">
                <option value="">Tất cả</option>
                @foreach ([
                    \App\Models\Booking::STATUS_PENDING => 'Chờ xác nhận',
                    \App\Models\Booking::STATUS_CONFIRMED => 'Đã xác nhận',
                    \App\Models\Booking::STATUS_SEATED => 'Khách đã đến',
                    \App\Models\Booking::STATUS_COMPLETED => 'Hoàn tất',
                    \App\Models\Booking::STATUS_CANCELLED => 'Đã hủy',
                    \App\Models\Booking::STATUS_NO_SHOW => 'Khách không đến',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="min-width:200px">
            <label for="q">Tìm theo mã / tên / SĐT</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="TG7KQ4M2, 09xx…">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn-ghost" type="submit">Lọc</button>
        </div>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Ngày giờ</th>
                    <th>Mã</th>
                    <th>Khách</th>
                    <th class="num">Số khách</th>
                    <th>Bàn</th>
                    @unless ($branch)<th>Chi nhánh</th>@endunless
                    <th>Nguồn</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($bookings as $item)
                    @php($late = $item->status === \App\Models\Booking::STATUS_CONFIRMED
                        && $item->startsAt()->addMinutes(15)->isPast())
                    <tr @class(['is-late' => $late])>
                        <td>
                            <b>{{ $item->booking_date->format('d/m/Y') }}</b><br>
                            <span class="muted small">{{ $item->timeRangeLabel() }}</span>
                            @if ($late)<br><span class="pill status-cancelled">Quá giờ hẹn</span>@endif
                        </td>
                        <td><a href="{{ route('admin.bookings.show', $item) }}">{{ $item->code }}</a></td>
                        <td>{{ $item->customer_name }}<br><span class="muted small">{{ $item->customer_phone }}</span></td>
                        <td class="num">{{ $item->party_size }}</td>
                        <td>{{ $item->tableCodes() }}</td>
                        @unless ($branch)<td class="small muted">{{ $item->branch->name }}</td>@endunless
                        <td class="small muted">{{ $item->sourceLabel() }}</td>
                        <td><span class="pill status-{{ $item->status }}">{{ $item->statusLabel() }}</span></td>
                        <td>@include('admin.partials.row-actions', ['booking' => $item])</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Không có đặt bàn nào khớp bộ lọc.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">{{ $bookings->links() }}</div>
    </div>
@endsection
