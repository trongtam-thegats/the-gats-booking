@extends('layouts.admin')

@section('title', $ho['name'] ?: $ho['phone'])

@php
    use App\Models\GuestNote;
    use App\Services\CustomerInsightService as Insight;
    use Illuminate\Support\Str;

    $co = $ho['stats'];
    $the = $ho['card'];
    $dat = $ho['booking_stats'];

    $mauTinhTrang = [
        'deu_dan' => 'status-confirmed',
        'khach_moi' => 'status-seated',
        'thua_dan' => 'status-pending',
        'nguy_co' => 'status-cancelled',
        'mot_lan' => 'status-completed',
        'xn_se_quay_lai' => 'status-confirmed',
        'xn_khong_quan_tam' => 'status-cancelled',
        'xn_da_chuyen_di' => 'status-completed',
        'xn_so_sai' => 'status-completed',
        'xn_da_roi_bo' => 'status-cancelled',
        'xn_khong_can' => 'status-completed',
    ];

    $ghiChu = $ho['note'] ?? null;
    $mauXemXet = [
        'chua_xem_xet' => 'status-pending',
        'da_xem_xet' => 'status-completed',
        'da_ghe_lai' => 'status-confirmed',
    ];

    /** Nhung dieu dang luu y, sinh tu chinh so lieu chu khong phai viet tay. */
    $luuY = [];

    if ($ho['review'] === 'da_ghe_lai' && $ghiChu?->reviewed_at) {
        $luuY[] = ['good', 'Đã ghé lại sau khi được xem xét ngày '
            .$ghiChu->reviewed_at->format('d/m/Y').'. Không cần chăm sóc nữa.'];
    }

    if ($ho['trang_thai'] === 'nguy_co' && $co['visits'] >= 2 && $ho['review'] !== 'da_ghe_lai') {
        $luuY[] = ['warn', 'Khách quen thường ghé mỗi '.$co['cadence'].' ngày nhưng đã '
            .$co['days_since'].' ngày không thấy. Đáng gọi hỏi thăm.'];
    }

    if ($dat['no_show'] >= 2) {
        $luuY[] = ['warn', 'Đã '.$dat['no_show'].' lần đặt bàn rồi không đến. Nên gọi xác nhận trước khi giữ bàn.'];
    }

    if ($the?->ngayToiSinhNhat() !== null && $the->ngayToiSinhNhat() <= 30) {
        $luuY[] = ['good', 'Sinh nhật '.$the->birthday->format('d/m')
            .($the->ngayToiSinhNhat() === 0 ? ' — hôm nay!' : ' — còn '.$the->ngayToiSinhNhat().' ngày.')];
    }

    if ($co['visits'] >= 5 && $co['avg'] >= 1500000) {
        $luuY[] = ['good', 'Khách chi đậm và đều: '.$co['visits'].' lần, trung bình '
            .number_format($co['avg']).'đ mỗi lần.'];
    }
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $ho['name'] ?: 'Chưa có tên' }}</h1>
            <p>
                {{ $ho['phone'] }}
                @if ($the?->tier) &middot; hạng <b>{{ $the->tier }}</b> @endif
                @if ($the?->points) &middot; {{ number_format($the->points) }} điểm @endif
                @if ($the?->joined_at) &middot; khách từ {{ $the->joined_at->format('m/Y') }} @endif
            </p>
        </div>
        <div>
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.customers.index') }}">← Danh sách khách</a>
            <a class="btn btn-ghost btn-sm" href="tel:{{ $ho['phone'] }}">Gọi</a>
        </div>
    </div>

    @foreach ($luuY as [$kieu, $chu])
        <div class="alert {{ $kieu === 'warn' ? 'alert-error' : 'alert-ok' }}">{{ $chu }}</div>
    @endforeach

    <div class="stats">
        <div class="stat accent">
            <span>Tổng chi tiêu</span>
            <b>{{ number_format($co['spend']) }}<small>đ</small></b>
            <small class="muted">{{ $co['visits'] }} lần ghé</small>
        </div>
        <div class="stat">
            <span>Trung bình mỗi lần</span>
            <b>{{ number_format($co['avg']) }}<small>đ</small></b>
            <small class="muted">cao nhất {{ number_format($co['max']) }}đ</small>
        </div>
        <div class="stat">
            <span>Nhịp ghé</span>
            <b>{{ $co['cadence'] === null ? '—' : $co['cadence'] }}<small>{{ $co['cadence'] === null ? '' : ' ngày' }}</small></b>
            <small class="muted">vắng {{ $co['days_since'] ?? '—' }} ngày</small>
        </div>
        <div class="stat">
            <span>Xem xét</span>
            <b style="font-size:19px">
                <span class="pill {{ $mauXemXet[$ho['review']] }}">{{ Insight::XEM_XET[$ho['review']] }}</span>
            </b>
            <small class="muted">
                @if ($ghiChu?->reviewed_at)
                    {{ $ghiChu->reviewed_at->format('d/m/Y') }}
                    @if ($ghiChu->reviewedBy) · {{ $ghiChu->reviewedBy->name }} @endif
                @else
                    chưa ai xem xét
                @endif
            </small>
        </div>
        <div class="stat">
            <span>Tình trạng</span>
            <b style="font-size:19px">
                <span class="pill {{ $mauTinhTrang[$ho['trang_thai']] ?? '' }}">{{ Insight::nhanTinhTrang($ho['trang_thai']) }}</span>
            </b>
            <small class="muted">
                @if (Insight::laXacNhan($ho['trang_thai']))
                    đã gọi và xác nhận
                @elseif ($co['first_at'])
                    lần đầu {{ $co['first_at']->format('d/m/Y') }}
                @endif
            </small>
        </div>
    </div>

    @if (auth()->user()->canWrite())
        <div class="card">
            <h2>Đánh dấu đã xem xét</h2>
            <p class="muted small">
                Đánh dấu để khách này không hiện lại trong danh sách cần chăm sóc.
                <b>Khách ghé lại lần nữa thì hệ thống tự chuyển sang “Đã ghé lại”</b> — không ai phải vào gỡ tay.
            </p>

            @if ($ghiChu?->review_note)
                <p class="hint" style="margin:0 0 12px">Ghi chú lần trước: {{ $ghiChu->review_note }}</p>
            @endif

            <form method="post" action="{{ route('admin.customers.review', $ho['phone']) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="review_outcome">Kết quả <span class="muted">(có thể bỏ trống)</span></label>
                    <select id="review_outcome" name="review_outcome">
                        <option value="">— Chỉ đánh dấu đã xem —</option>
                        @foreach (GuestNote::KET_QUA as $ma => $nhan)
                            <option value="{{ $ma }}" @selected($ghiChu?->review_outcome === $ma)>{{ $nhan }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="review_note">Ghi chú <span class="muted">(có thể bỏ trống)</span></label>
                    <input type="text" id="review_note" name="review_note" maxlength="500"
                           value="{{ $ghiChu?->review_note }}" placeholder="Đã gọi, khách bận đi công tác">
                </div>
                <div class="field full">
                    <button class="btn" type="submit">
                        {{ $ghiChu?->reviewed_at ? 'Cập nhật đánh dấu' : 'Đánh dấu đã xem xét' }}
                    </button>
                </div>
            </form>

            @if ($ghiChu?->reviewed_at)
                <form method="post" action="{{ route('admin.customers.review', $ho['phone']) }}"
                      onsubmit="return confirm('Bỏ đánh dấu, đưa khách này trở lại danh sách chưa xem xét?')">
                    @csrf
                    <input type="hidden" name="bo_danh_dau" value="1">
                    <button class="btn btn-ghost btn-sm" type="submit">Bỏ đánh dấu</button>
                </form>
            @endif
        </div>
    @endif

    <div class="card">
        <h2>Thói quen</h2>
        <p class="muted small">Tính trên {{ $co['visits'] }} hóa đơn đã thanh toán. Hóa đơn chốt sau nửa đêm được tính vào đêm hôm trước.</p>

        <div class="form-grid">
            @foreach ([
                'weekday' => 'Hay ghé thứ mấy',
                'hour' => 'Khung giờ thanh toán',
                'area' => 'Khu vực hay ngồi',
                'table' => 'Bàn hay ngồi',
                'payment' => 'Cách thanh toán',
            ] as $khoa => $nhan)
                @if (! empty($ho['habits'][$khoa]))
                    <div class="field">
                        <label>{{ $nhan }}</label>
                        <div class="table-wrap">
                            <table>
                                <tbody>
                                @foreach ($ho['habits'][$khoa] as $dong)
                                    <tr>
                                        <td class="small">{{ $dong['label'] }}</td>
                                        <td class="num small muted">{{ $dong['count'] }} lần · {{ $dong['share'] }}%</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    @if ($ho['bookings']->isNotEmpty())
        <div class="card">
            <h2>Lịch sử đặt bàn</h2>
            <p class="muted small">
                {{ $dat['total'] }} lần đặt &middot; đến {{ $dat['arrived'] }} &middot;
                không đến {{ $dat['no_show'] }} &middot; hủy {{ $dat['cancelled'] }}
                @if ($dat['show_rate'] !== null) &middot; tỉ lệ đến <b>{{ $dat['show_rate'] }}%</b> @endif
            </p>

            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ngày</th><th>Giờ</th><th class="num">Khách</th><th>Bàn</th><th>Kết quả</th></tr></thead>
                    <tbody>
                    @foreach ($ho['bookings']->take(30) as $don)
                        <tr>
                            <td class="small">{{ $don->booking_date->format('d/m/Y') }}</td>
                            <td class="small muted">{{ substr($don->start_time, 0, 5) }}</td>
                            <td class="num small">{{ $don->party_size }}</td>
                            <td class="small muted">{{ $don->diningTables->pluck('code')->implode(', ') ?: '—' }}</td>
                            <td><span class="pill status-{{ $don->status }}">{{ $don->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <h2>Hóa đơn</h2>

        @if ($ho['invoices']->isEmpty())
            <p class="muted">Khách này mới chỉ có lịch sử đặt bàn, chưa khớp được hóa đơn nào.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Thanh toán</th><th>Mã</th><th>Chỗ ngồi</th><th class="num">Khách</th><th class="num">Tổng tiền</th><th>Trả bằng</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($ho['invoices'] as $hd)
                        <tr>
                            <td class="small">
                                {{ $hd->paid_at?->format('d/m/Y H:i') ?? '—' }}
                                @if ($hd->daHuy())
                                    <br><span class="pill status-cancelled">Đã hủy</span>
                                @endif
                            </td>
                            <td class="small muted">{{ $hd->code }}</td>
                            <td class="small">{{ $hd->area ?: '—' }}@if ($hd->table_code) <span class="muted">· {{ $hd->table_code }}</span>@endif</td>
                            <td class="num small">{{ $hd->party_size ?: '—' }}</td>
                            <td class="num"><b>{{ number_format($hd->total) }}</b></td>
                            <td class="small muted">{{ Str::limit($hd->payment_method, 20) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($the && ($the->note || $the->email || $the->province))
        <div class="card">
            <h2>Thông tin từ thẻ khách hàng</h2>
            <div class="table-wrap">
                <table>
                    <tbody>
                    @if ($the->email)<tr><td class="small muted">Email</td><td class="small">{{ $the->email }}</td></tr>@endif
                    @if ($the->birthday)<tr><td class="small muted">Sinh nhật</td><td class="small">{{ $the->birthday->format('d/m/Y') }}</td></tr>@endif
                    @if ($the->gender)<tr><td class="small muted">Giới tính</td><td class="small">{{ $the->gender }}</td></tr>@endif
                    @if ($the->province)<tr><td class="small muted">Nơi ở</td><td class="small">{{ trim($the->district.' '.$the->province) }}</td></tr>@endif
                    @if ($the->note)<tr><td class="small muted">Ghi chú POS</td><td class="small">{{ $the->note }}</td></tr>@endif
                    @if ($the->invoice_count)
                        <tr>
                            <td class="small muted">POS ghi nhận</td>
                            <td class="small">
                                {{ number_format($the->invoice_count) }} hóa đơn ·
                                {{ number_format($the->total_spent) }}đ
                                <span class="muted">(toàn chuỗi, tính đến {{ $the->exported_at?->format('d/m/Y') ?? 'lúc xuất tệp' }})</span>
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
