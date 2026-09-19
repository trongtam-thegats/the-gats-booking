@extends('layouts.admin')

@section('title', 'Thông báo')

@section('content')
    <div class="page-head">
        <div>
            <h1>Thông báo đặt bàn</h1>
            <p>Có đơn mới là điện thoại kêu ngay, không phải mở trang ra xem.</p>
        </div>
    </div>

    <div class="card">
        <h2>Máy bạn đang dùng</h2>
        <div id="trang-thai-thong-bao" class="alert alert-info">Đang kiểm tra…</div>

        <button class="btn" type="button" id="nut-thong-bao"
                data-khoa="{{ $khoa }}"
                data-dang-ky="{{ route('admin.thong-bao.dang-ky') }}"
                data-huy="{{ route('admin.thong-bao.huy') }}"
                data-csrf="{{ csrf_token() }}">Bật thông báo trên máy này</button>

        <form method="post" action="{{ route('admin.thong-bao.gui-thu') }}" style="display:inline-block;margin-left:8px">
            @csrf
            <button class="btn btn-ghost" type="submit">Gửi thử</button>
        </form>

        @if (! $khoa)
            <p class="alert alert-error" style="margin-top:14px">
                Hệ thống chưa khai khoá thông báo. Chạy <code>php artisan push:khoa-moi</code> trên máy chủ
                rồi dán hai dòng khoá vào tệp cấu hình.
            </p>
        @endif
    </div>

    <div class="card">
        <h2>Cách bật trên iPhone</h2>
        <ol class="hint" style="line-height:1.9">
            <li>Mở <b>booking.thegats.vn</b> bằng <b>Safari</b> (Chrome trên iPhone không làm được).</li>
            <li>Bấm nút <b>Chia sẻ</b> ở thanh dưới, chọn <b>Thêm vào MH chính</b>.</li>
            <li>Đóng Safari, mở lại trang bằng <b>biểu tượng vừa thêm</b> ngoài màn hình.</li>
            <li>Vào lại trang này, bấm <b>Bật thông báo trên máy này</b>, rồi chọn <b>Cho phép</b>.</li>
            <li>Bấm <b>Gửi thử</b> để chắc chắn máy kêu.</li>
        </ol>
        <p class="hint">
            Dấu trang thường trong Safari <b>không nhận được</b> thông báo — Apple chỉ cho phép với
            biểu tượng đã thêm vào Màn hình chính. Máy tính thì dùng Chrome hoặc Edge là được ngay.
        </p>
    </div>

    <div class="card">
        <h2>Thiết bị đang nhận thông báo của bạn</h2>
        @if ($thietBi->isEmpty())
            <p class="empty mb-0">Chưa có máy nào.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Máy</th><th>Bật từ</th><th>Nhận gần nhất</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($thietBi as $may)
                        <tr>
                            <td class="small">{{ \Illuminate\Support\Str::limit($may->thiet_bi, 70) ?: 'Không rõ' }}</td>
                            <td class="small muted">{{ $may->created_at->format('H:i d/m/Y') }}</td>
                            <td class="small muted">{{ $may->lan_cuoi_ok?->format('H:i d/m/Y') ?? 'chưa lần nào' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/thong-bao.js') }}"></script>
@endpush
