@extends('layouts.admin')

@section('title', 'Sơ đồ bàn')

@section('content')
    <div class="page-head">
        <div>
            <h1>Sơ đồ bàn</h1>
            <p>{{ $branch->name }} · {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</p>
        </div>
        <a class="btn" href="{{ route('admin.bookings.create', ['branch' => $branch->id]) }}">Đặt bàn hộ khách</a>
    </div>

    <form method="get" class="filters">
        @include('admin.partials.branch-filter', ['allowAll' => false])
        <div class="field">
            <label for="date">Ngày</label>
            <input type="date" id="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <div class="row">
                <a class="btn btn-ghost btn-sm"
                   href="{{ route('admin.floor', ['branch' => $branch->id, 'date' => \Illuminate\Support\Carbon::parse($date)->subDay()->toDateString()]) }}">← Hôm trước</a>
                <a class="btn btn-ghost btn-sm"
                   href="{{ route('admin.floor', ['branch' => $branch->id, 'date' => \Illuminate\Support\Carbon::parse($date)->addDay()->toDateString()]) }}">Hôm sau →</a>
            </div>
        </div>
    </form>

    @if ($unassigned->isNotEmpty())
        <div class="alert alert-info">
            Có {{ $unassigned->count() }} đặt bàn chưa được xếp bàn:
            @foreach ($unassigned as $item)
                <a href="{{ route('admin.bookings.show', $item) }}">{{ $item->code }}</a>@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif

    @if ($tables->isEmpty())
        <div class="card">
            <p class="mb-0">Chi nhánh này chưa khai báo bàn nào.
                <a href="{{ route('admin.tables.index', ['branch' => $branch->id]) }}">Khai báo khu vực và bàn</a> để bắt đầu nhận đặt bàn.</p>
        </div>
    @else
        <div class="floor-wrap">
            <table class="floor">
                <thead>
                <tr>
                    <th class="table-head">Bàn</th>
                    @foreach ($slots as $slot)
                        <th class="slot-head">{{ $slot }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($tables as $table)
                    <tr>
                        <td class="table-cell">
                            <b>{{ $table->code }}</b>
                            <span>{{ $table->seats_max }} chỗ@if ($table->area) · {{ $table->area->name }} @endif</span>
                        </td>
                        @foreach ($slots as $slot)
                            @php($booking = $grid[$table->id][$slot] ?? null)
                            @if ($booking)
                                <td class="cell busy is-{{ $booking->status }}">
                                    <a href="{{ route('admin.bookings.show', $booking) }}"
                                       title="{{ $booking->customer_name }} · {{ $booking->party_size }} khách · {{ $booking->statusLabel() }}">
                                        {{ $booking->party_size }}k
                                    </a>
                                </td>
                            @else
                                <td class="cell free"></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="card" style="margin-top:16px">
            <h2>Chi tiết trong ngày</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Giờ</th><th>Mã</th><th>Khách</th><th class="num">Số khách</th><th>Bàn</th><th>Trạng thái</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($bookings as $item)
                        <tr>
                            <td><b>{{ substr($item->start_time, 0, 5) }}</b></td>
                            <td><a href="{{ route('admin.bookings.show', $item) }}">{{ $item->code }}</a></td>
                            <td>{{ $item->customer_name }}<br><span class="muted small">{{ $item->customer_phone }}</span></td>
                            <td class="num">{{ $item->party_size }}</td>
                            <td>{{ $item->tableCodes() }}</td>
                            <td><span class="pill status-{{ $item->status }}">{{ $item->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Ngày này chưa có khách đặt.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
