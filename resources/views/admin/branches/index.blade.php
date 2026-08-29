@extends('layouts.admin')

@section('title', 'Chi nhánh')

@section('content')
    <div class="page-head">
        <div>
            <h1>Chi nhánh &amp; giờ mở cửa</h1>
            <p>Giờ mở cửa, bước chia khung giờ và thời lượng giữ bàn quyết định khung giờ khách nhìn thấy khi đặt.</p>
        </div>
        @if (auth()->user()->isAdmin())
            <a class="btn" href="{{ route('admin.branches.create') }}">Thêm chi nhánh</a>
        @endif
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Chi nhánh</th>
                    <th>Thương hiệu</th>
                    <th>Giờ nhận khách</th>
                    <th class="num">Bước giờ</th>
                    <th class="num">Giữ bàn</th>
                    <th class="num">Bàn</th>
                    <th>Khu vực</th>
                    <th>Duyệt</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($branches as $item)
                    <tr>
                        <td>
                            <b>{{ $item->name }}</b><br>
                            <span class="muted small">{{ $item->address ?: '—' }}</span>
                        </td>
                        <td class="small">
                            @if ($item->brand)
                                <span class="pill" style="background: {{ $item->brand->accent_color }}22; color: {{ $item->brand->accentSoft() }}">
                                    {{ $item->brand->name }}
                                </span>
                            @else
                                <span class="pill status-cancelled">Chưa gán</span>
                            @endif
                        </td>
                        <td>{{ substr($item->open_time, 0, 5) }} – {{ substr($item->close_time, 0, 5) }}</td>
                        <td class="num">{{ $item->slot_minutes }}′</td>
                        <td class="num">{{ $item->turn_minutes }}′</td>
                        <td class="num">{{ $item->tables_count }}</td>
                        <td class="small muted">{{ $item->areas->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="small">{{ $item->auto_confirm ? 'Tự động' : 'Thủ công' }}</td>
                        <td>
                            <span class="pill {{ $item->is_active ? 'status-confirmed' : 'status-cancelled' }}">
                                {{ $item->is_active ? 'Đang nhận' : 'Tạm ngưng' }}
                            </span>
                        </td>
                        <td class="num">
                            <div class="row" style="justify-content:flex-end">
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.branches.edit', $item) }}">Cấu hình</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.tables.index', ['branch' => $item->id]) }}">Bàn</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty">Chưa có chi nhánh nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
