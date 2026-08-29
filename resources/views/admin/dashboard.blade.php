@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tổng quan {{ \Illuminate\Support\Carbon::parse($date)->isToday() ? 'hôm nay' : \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</h1>
            <p>{{ $branch?->name ?? 'Tất cả chi nhánh bạn phụ trách' }}</p>
        </div>
        <a class="btn" href="{{ route('admin.bookings.create') }}">Đặt bàn hộ khách</a>
    </div>

    <form method="get" class="filters">
        @include('admin.partials.branch-filter')
        <div class="field">
            <label for="date">Ngày</label>
            <input type="date" id="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn-ghost" type="submit">Xem</button>
        </div>
    </form>

    <div class="stats">
        <div class="stat"><b>{{ $stats['total'] }}</b><span>Lượt đặt</span></div>
        <div class="stat accent"><b>{{ $stats['guests'] }}</b><span>Khách dự kiến</span></div>
        <div class="stat"><b>{{ $stats['pending'] }}</b><span>Chờ xác nhận</span></div>
        <div class="stat"><b>{{ $stats['confirmed'] }}</b><span>Đã xác nhận</span></div>
        <div class="stat"><b>{{ $stats['seated'] }}</b><span>Khách đã đến</span></div>
        <div class="stat warn"><b>{{ $stats['cancelled'] }}</b><span>Hủy / không đến</span></div>
    </div>

    @if ($waiting->isNotEmpty())
        <div class="card">
            <h2>Chờ bạn xác nhận ({{ $waiting->count() }})</h2>
            <p class="sub">Khách đã gửi yêu cầu và đang đợi phản hồi. Xác nhận sớm giúp giữ chân khách.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Ngày giờ</th>
                        <th>Khách</th>
                        <th class="num">Số khách</th>
                        <th>Bàn giữ</th>
                        @unless ($branch)<th>Chi nhánh</th>@endunless
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($waiting as $item)
                        <tr>
                            <td><a href="{{ route('admin.bookings.show', $item) }}">{{ $item->code }}</a></td>
                            <td>{{ $item->booking_date->format('d/m') }} · {{ substr($item->start_time, 0, 5) }}</td>
                            <td>{{ $item->customer_name }}<br><span class="muted small">{{ $item->customer_phone }}</span></td>
                            <td class="num">{{ $item->party_size }}</td>
                            <td>{{ $item->tableCodes() }}</td>
                            @unless ($branch)<td class="small muted">{{ $item->branch->name }}</td>@endunless
                            <td>
                                <form method="post" action="{{ route('admin.bookings.confirm', $item) }}">
                                    @csrf
                                    <button class="btn btn-ok btn-sm" type="submit">Xác nhận</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <h2>Lịch đặt bàn trong ngày</h2>
        <p class="sub">Xếp theo giờ nhận khách.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Giờ</th>
                    <th>Mã</th>
                    <th>Khách</th>
                    <th class="num">Số khách</th>
                    <th>Bàn</th>
                    <th>Khu vực</th>
                    @unless ($branch)<th>Chi nhánh</th>@endunless
                    <th>Trạng thái</th>
                    <th>Ghi chú</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($todays as $item)
                    <tr>
                        <td><b>{{ substr($item->start_time, 0, 5) }}</b></td>
                        <td><a href="{{ route('admin.bookings.show', $item) }}">{{ $item->code }}</a></td>
                        <td>{{ $item->customer_name }}<br><span class="muted small">{{ $item->customer_phone }}</span></td>
                        <td class="num">{{ $item->party_size }}</td>
                        <td>{{ $item->tableCodes() }}</td>
                        <td class="small muted">{{ $item->area?->name ?? '—' }}</td>
                        @unless ($branch)<td class="small muted">{{ $item->branch->name }}</td>@endunless
                        <td><span class="pill status-{{ $item->status }}">{{ $item->statusLabel() }}</span></td>
                        <td class="small muted">{{ $item->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Chưa có đặt bàn nào cho ngày này.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>7 ngày tới</h2>
        <p class="sub">Chỉ tính các đặt bàn còn hiệu lực.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Ngày</th><th class="num">Lượt đặt</th><th class="num">Khách</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($upcomingDays as $day)
                    @php($d = \Illuminate\Support\Carbon::parse($day->booking_date))
                    <tr>
                        <td>{{ $d->translatedFormat('l') }}, {{ $d->format('d/m/Y') }}</td>
                        <td class="num">{{ $day->bookings }}</td>
                        <td class="num">{{ $day->guests }}</td>
                        <td class="num">
                            <a class="btn btn-ghost btn-sm"
                               href="{{ route('admin.floor', array_filter(['branch' => $branch?->id, 'date' => $d->toDateString()])) }}">Sơ đồ bàn</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Chưa có đặt bàn nào trong 7 ngày tới.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
