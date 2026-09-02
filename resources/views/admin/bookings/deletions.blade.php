@extends('layouts.admin')

@section('title', 'Nhật ký xóa đặt bàn')

@section('content')
    <div class="page-head">
        <div>
            <h1>Nhật ký xóa</h1>
            <p>Các đơn đã bị xóa vĩnh viễn khỏi hệ thống. Bảng này chỉ ghi thêm, không sửa và không xóa được.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.bookings.index') }}">Về danh sách đặt bàn</a>
    </div>

    <div class="card">
        <p class="sub">
            Đơn đã xóa không còn nằm trong báo cáo hay phân tích khách hàng. Nếu một con số
            trông hụt so với trí nhớ, đối chiếu ở đây trước.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Xóa lúc</th>
                    <th>Mã</th>
                    <th>Địa điểm</th>
                    <th>Khách</th>
                    <th>Lịch đặt</th>
                    <th>Trạng thái lúc xóa</th>
                    <th>Người xóa</th>
                    <th>Lý do</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($deletions as $item)
                    <tr>
                        <td class="small">{{ $item->created_at->format('H:i d/m/Y') }}</td>
                        <td><b>{{ $item->code }}</b></td>
                        <td class="small">{{ $item->branch_name ?: '—' }}</td>
                        <td class="small">
                            {{ $item->customer_name }}
                            <br><span class="muted">{{ $item->customer_phone }}</span>
                        </td>
                        <td class="small">
                            {{ $item->booking_date->format('d/m/Y') }} ·
                            {{ substr((string) $item->start_time, 0, 5) }} ·
                            {{ $item->party_size }} khách
                        </td>
                        <td class="small">{{ $item->statusLabel() }} <span class="muted">· {{ $item->sourceLabel() }}</span></td>
                        {{-- Ten luu san luc xoa, van doc duoc ke ca khi tai khoan da bi xoa. --}}
                        <td class="small">{{ $item->deletedBy?->name ?: $item->deleted_by_name ?: '—' }}</td>
                        <td class="small muted">{{ $item->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">Chưa có đơn nào bị xóa.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $deletions->links() }}
    </div>
@endsection
