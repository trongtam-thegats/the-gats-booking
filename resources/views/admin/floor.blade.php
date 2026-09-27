@extends('layouts.admin')

@section('title', 'Sơ đồ bàn')

@section('content')
    <div class="page-head">
        <div>
            <h1>Sơ đồ bàn</h1>
            <p>{{ $branch->name }} · {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</p>
        </div>
        <div class="row" style="align-items:center;gap:10px">
            @if ($sapoActive)
                <form method="post" action="{{ route('admin.floor.sync-sapo') }}" style="display:inline-flex;align-items:center;gap:6px;margin:0">
                    @csrf
                    <input type="hidden" name="branch" value="{{ $branch->id }}">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <span class="pill" style="background:#e6f7ed;color:#0d8a43;border:1px solid #b3e6c5;font-weight:600"
                          title="{{ $sapoLastSync ? 'Đồng bộ lần cuối: '.\Illuminate\Support\Carbon::parse($sapoLastSync['at'])->format('H:i:s d/m') : 'Tự động đồng bộ mỗi phút' }}">
                        🟢 Sapo Realtime
                    </span>
                    <button type="submit" class="btn btn-ghost btn-sm" title="Đồng bộ ngay trạng thái bàn đang có khách từ Sapo FnB">
                        🔄 Đồng bộ Sapo
                    </button>
                </form>
            @elseif ($branch->slug === 'drinking-healing')
                <span class="pill muted" title="Vào Cài đặt gửi tin để bật Sapo Realtime">
                    ⚪ Sapo: Chưa bật
                </span>
            @endif
            <a class="btn" href="{{ route('admin.bookings.create', ['branch' => $branch->id]) }}">Đặt bàn hộ khách</a>
        </div>
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
        <div class="field" style="align-self:flex-end">
            <label class="small muted" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:8px">
                <input type="checkbox" id="auto-refresh" onchange="toggleAutoRefresh(this.checked)"> Tự làm mới (60s)
            </label>
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
                                @php($isSapo = !empty($booking->sapo_code) || str_contains($booking->internal_note ?? '', 'Sapo'))
                                <td class="cell busy is-{{ $booking->status }} @if($isSapo) is-sapo @endif">
                                    <a href="{{ route('admin.bookings.show', $booking) }}"
                                       title="@if($isSapo)[Sapo #{{ $booking->sapo_code }}] @endif{{ $booking->customer_name }} · {{ $booking->party_size }} khách · {{ $booking->statusLabel() }}">
                                        {{ $booking->party_size }}k{!! $isSapo ? '<span class="sapo-tag">Sapo</span>' : '' !!}
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
                        @php($isSapo = !empty($item->sapo_code) || str_contains($item->internal_note ?? '', 'Sapo'))
                        <tr>
                            <td><b>{{ substr($item->start_time, 0, 5) }}</b></td>
                            <td>
                                <a href="{{ route('admin.bookings.show', $item) }}">{{ $item->code }}</a>
                                @if ($isSapo)
                                    <span class="pill" style="background:#fff2e6;color:#d95300;border:1px solid #ffcca3;font-size:10.5px;padding:1px 5px;margin-left:4px" title="Đơn khách đang ngồi từ Sapo POS">⚡ Sapo</span>
                                @endif
                            </td>
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

    <script>
        (function() {
            var checkbox = document.getElementById('auto-refresh');
            if (!checkbox) return;
            var saved = localStorage.getItem('floor_auto_refresh') === '1';
            checkbox.checked = saved;
            var timer = null;

            function startTimer() {
                if (timer) clearInterval(timer);
                timer = setInterval(function() {
                    window.location.reload();
                }, 60000);
            }

            if (saved) {
                startTimer();
            }

            window.toggleAutoRefresh = function(checked) {
                localStorage.setItem('floor_auto_refresh', checked ? '1' : '0');
                if (checked) {
                    startTimer();
                } else if (timer) {
                    clearInterval(timer);
                }
            };
        })();
    </script>
@endsection
