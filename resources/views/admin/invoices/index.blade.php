@extends('layouts.admin')

@section('title', 'Hóa đơn')

@php
    use Illuminate\Support\Str;

    $giuLoc = array_filter([
        'branch' => $branch?->id,
        'q' => $boLoc['tim'],
        'tu' => $boLoc['tuNgay'],
        'den' => $boLoc['denNgay'],
        'khu' => $boLoc['khuVuc'],
        'co-sdt' => $boLoc['chiCoSdt'] ? 1 : null,
    ]);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Hóa đơn</h1>
            <p>Dữ liệu bán hàng nhập từ POS. Chỉ đọc — mỗi lần xuất tệp mới thì tải lên để chồng lên tệp cũ.</p>
        </div>
    </div>

    <div class="stats">
        <div class="stat">
            <span>Hóa đơn</span>
            <b>{{ number_format($tongKet['so_hoa_don']) }}</b>
        </div>
        <div class="stat">
            <span>Doanh thu</span>
            <b>{{ number_format($tongKet['doanh_thu']) }}<small>đ</small></b>
        </div>
        <div class="stat">
            <span>Trung bình mỗi hóa đơn</span>
            <b>{{ number_format($tongKet['trung_binh']) }}<small>đ</small></b>
        </div>
        <div class="stat">
            <span>Có số điện thoại</span>
            <b>{{ number_format($tongKet['co_sdt']) }}</b>
            <small class="muted">
                {{ $tongKet['so_hoa_don'] ? round($tongKet['co_sdt'] / $tongKet['so_hoa_don'] * 100, 1) : 0 }}% —
                phần còn lại không truy được khách là ai
            </small>
        </div>
    </div>

    <div class="card">
        <form method="get" class="form-grid">
            @include('admin.partials.branch-filter')

            <div class="field">
                <label for="q">Tìm</label>
                <input type="search" id="q" name="q" value="{{ $boLoc['tim'] }}"
                       placeholder="Mã hóa đơn, tên khách, số điện thoại, bàn">
            </div>
            <div class="field">
                <label for="tu">Từ ngày</label>
                <input type="date" id="tu" name="tu" value="{{ $boLoc['tuNgay'] }}">
            </div>
            <div class="field">
                <label for="den">Đến ngày</label>
                <input type="date" id="den" name="den" value="{{ $boLoc['denNgay'] }}">
            </div>
            <div class="field">
                <label for="khu">Khu vực</label>
                <select id="khu" name="khu">
                    <option value="">Tất cả</option>
                    @foreach ($khuVucCo as $ten)
                        <option value="{{ $ten }}" @selected($boLoc['khuVuc'] === $ten)>{{ $ten }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <label class="check">
                    <input type="checkbox" name="co-sdt" value="1" @checked($boLoc['chiCoSdt'])>
                    Chỉ hiện hóa đơn có số điện thoại khách
                </label>
            </div>
            <div class="field full">
                <button class="btn" type="submit">Lọc</button>
                @if ($giuLoc)
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.invoices.index') }}">Bỏ lọc</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Danh sách</h2>

        @if ($hoaDon->isEmpty())
            <p class="muted">Không có hóa đơn nào khớp bộ lọc.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Thanh toán</th>
                        <th>Mã</th>
                        <th>Khách</th>
                        <th>Chỗ ngồi</th>
                        <th class="num">Khách</th>
                        <th class="num">Tổng tiền</th>
                        <th>Thanh toán bằng</th>
                        <th>Thu ngân</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($hoaDon as $hd)
                        <tr>
                            <td class="small">
                                {{ $hd->paid_at?->format('d/m/Y H:i') ?? '—' }}
                                @if ($hd->daHuy())
                                    <br><span class="pill status-cancelled">Đã hủy</span>
                                @endif
                            </td>
                            <td class="small muted">{{ $hd->code }}</td>
                            <td>
                                @if ($hd->customer_phone)
                                    <a href="{{ route('admin.customers.show', $hd->customer_phone) }}">
                                        <b>{{ $hd->customer_name ?: 'Chưa có tên' }}</b>
                                    </a>
                                    <br><span class="muted small">{{ $hd->customer_phone }}</span>
                                    @if ($hd->membership_card)
                                        <span class="pill status-confirmed">{{ $hd->membership_card }}</span>
                                    @endif
                                @else
                                    <span class="muted">Khách vãng lai</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $hd->area ?: '—' }}
                                @if ($hd->table_code)
                                    <br><span class="muted">{{ $hd->table_code }}</span>
                                @endif
                            </td>
                            <td class="num small">{{ $hd->party_size ?: '—' }}</td>
                            <td class="num"><b>{{ number_format($hd->total) }}</b></td>
                            <td class="small muted">{{ Str::limit($hd->payment_method, 22) }}</td>
                            <td class="small muted">{{ Str::limit($hd->cashier, 18) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{ $hoaDon->links('vendor.pagination.thegats') }}
        @endif
    </div>

    @if (auth()->user()->isAdmin())
        <div class="card">
            <h2>Nhập dữ liệu từ POS</h2>
            <p class="muted small">
                Tệp .xlsx xuất thẳng từ Sapo, tối đa 20 MB. Hóa đơn khớp theo mã, khách khớp theo số điện
                thoại — tải tệp mới lên sẽ cập nhật đè lên dữ liệu cũ chứ không tạo bản trùng.
            </p>

            <form method="post" action="{{ route('admin.invoices.import') }}"
                  enctype="multipart/form-data" class="form-grid">
                @csrf
                <div class="field">
                    <label for="loai">Loại dữ liệu</label>
                    <select id="loai" name="loai">
                        <option value="hoa-don">Danh sách hóa đơn</option>
                        <option value="khach-hang">Danh sách khách hàng (thẻ, điểm, sinh nhật)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="branch_id">Địa điểm <span class="muted">(cho tệp hóa đơn)</span></label>
                    <select id="branch_id" name="branch_id">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($branch?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field full">
                    <label for="tep">Tệp .xlsx</label>
                    <input type="file" id="tep" name="tep" accept=".xlsx" required>
                </div>
                <div class="field full">
                    <button class="btn" type="submit">Tải lên và nhập</button>
                </div>
            </form>
        </div>
    @endif
@endsection
