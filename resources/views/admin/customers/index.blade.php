@extends('layouts.admin')

@section('title', 'Phân tích khách hàng')

@php
    use App\Http\Controllers\Admin\CustomerInsightController as Ctl;
    use App\Services\CustomerInsightService as Insight;

    /** Mau cho tung tinh trang, dung lai bang mau cua trang dat ban. */
    $mauTinhTrang = [
        'deu_dan' => 'status-confirmed',
        'khach_moi' => 'status-seated',
        'thua_dan' => 'status-pending',
        'nguy_co' => 'status-cancelled',
        'mot_lan' => 'status-completed',
    ];

    $tienGon = fn ($so) => $so >= 1000000
        ? rtrim(rtrim(number_format($so / 1000000, 1), '0'), ',.').' tr'
        : number_format(round($so / 1000)).'k';

    // Mau cua cot phai gan voi tinh trang chu khong theo thu tu dong: nhom nao
    // rong se bi loc di, va mang mau xep theo vi tri se lech het mot bac.
    $mauCot = [
        'deu_dan' => '#6fbf7a',
        'khach_moi' => '#78aae6',
        'thua_dan' => '#c8a15a',
        'nguy_co' => '#e0685f',
        'mot_lan' => '#8a8a8a',
    ];

    $dongNhom = collect(Insight::TINH_TRANG)
        ->map(fn ($nhan, $ma) => [
            'ma' => $ma,
            'label' => $nhan,
            'value' => (int) ($nhomTinhTrang[$ma] ?? 0),
            'display' => (int) ($nhomTinhTrang[$ma] ?? 0).' khách',
            'spendText' => number_format($khach->where('segment', $ma)->sum('spend')).'đ',
        ])
        ->filter(fn ($r) => $r['value'] > 0)
        ->values();

    // Bieu do co cau khach theo tinh trang, dung lai charts.js cua trang bao cao.
    $vizNhom = [
        'type' => 'rows',
        'label' => 'Khách theo tình trạng',
        'colors' => $dongNhom->map(fn ($r) => $mauCot[$r['ma']])->all(),
        'fields' => [
            ['key' => 'value', 'label' => 'Số khách'],
            ['key' => 'spendText', 'label' => 'Tổng chi tiêu'],
        ],
        'rows' => $dongNhom->all(),
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Phân tích khách hàng</h1>
            <p>Ghép hóa đơn POS với lịch sử đặt bàn theo số điện thoại, để biết ai là khách quen và nên gọi ai trước.</p>
        </div>
    </div>

    @if ($tongQuan['phone_rate'] < 50)
        <div class="alert alert-info">
            <b>Chỉ {{ $tongQuan['phone_rate'] }}% hóa đơn có số điện thoại khách</b>
            ({{ number_format($tongQuan['invoices_with_phone']) }} / {{ number_format($tongQuan['invoices']) }}),
            tương ứng {{ $tongQuan['revenue_identified_rate'] }}% doanh thu.
            Phần còn lại không truy được khách là ai, nên bảng dưới đây chỉ phản ánh nhóm khách đã ghi nhận được.
            Muốn phân tích dày hơn thì thu ngân cần hỏi số điện thoại khi mở bàn.
        </div>
    @endif

    <div class="stats">
        <div class="stat">
            <span>Khách nhận diện được</span>
            <b>{{ number_format($tongQuan['customers']) }}</b>
            <small class="muted">trong {{ number_format($tongQuan['invoices']) }} hóa đơn</small>
        </div>
        <div class="stat accent">
            <span>Khách quay lại</span>
            <b>{{ number_format($tongQuan['returning']) }}</b>
            <small class="muted">
                {{ $tongQuan['customers'] ? round($tongQuan['returning'] / $tongQuan['customers'] * 100) : 0 }}%
                — từ 2 hóa đơn trở lên
            </small>
        </div>
        <div class="stat">
            <span>Chi tiêu của khách quay lại</span>
            <b>{{ $tienGon($tongQuan['returning_revenue']) }}<small>đ</small></b>
        </div>
        <div class="stat">
            <span>Kỳ dữ liệu</span>
            <b style="font-size:17px">
                {{ $tongQuan['first_paid_at'] ? \Illuminate\Support\Carbon::parse($tongQuan['first_paid_at'])->format('d/m/y') : '—' }}
                →
                {{ $tongQuan['last_paid_at'] ? \Illuminate\Support\Carbon::parse($tongQuan['last_paid_at'])->format('d/m/y') : '—' }}
            </b>
        </div>
    </div>

    @if ($vizNhom['rows'])
        <div class="card">
            <h2>Cơ cấu {{ $khach->count() }} khách đang xem</h2>
            <p class="muted small">
                Tình trạng tính theo <b>nhịp ghé của chính khách đó</b>: vắng quá ba lần nhịp quen thuộc
                thì coi là có nguy cơ rời bỏ. Khách tháng nào cũng đến mà hai tháng không thấy là chuyện
                khác hẳn với khách nửa năm mới ghé một lần.
            </p>

            <figure class="viz-figure">
                <div class="viz-plot"></div>

                <script type="application/json">@json($vizNhom)</script>

                <div class="viz-table table-wrap">
                    <table>
                        <thead><tr><th>Tình trạng</th><th class="num">Số khách</th><th class="num">Tổng chi tiêu</th></tr></thead>
                        <tbody>
                        @foreach ($vizNhom['rows'] as $r)
                            <tr><td>{{ $r['label'] }}</td><td class="num">{{ $r['value'] }}</td><td class="num">{{ $r['spendText'] }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>
    @endif

    <div class="card">
        <form method="get" class="form-grid">
            @include('admin.partials.branch-filter')

            <div class="field">
                <label for="sap-xep">Xếp theo</label>
                <select id="sap-xep" name="sap-xep" onchange="this.form.submit()">
                    @foreach (Ctl::SAP_XEP as $ma => $nhan)
                        <option value="{{ $ma }}" @selected($sapXep === $ma)>{{ $nhan }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="so-luong">Số khách</label>
                <select id="so-luong" name="so-luong" onchange="this.form.submit()">
                    @foreach ([50, 100, 200, 300] as $n)
                        <option value="{{ $n }}" @selected($soLuong === $n)>{{ $n }} khách</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>{{ Ctl::SAP_XEP[$sapXep] }}</h2>

        @if ($khach->isEmpty())
            <p class="muted">Chưa có hóa đơn nào ghi nhận được số điện thoại khách.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Khách</th>
                        <th class="num">Lần ghé</th>
                        <th class="num">Tổng chi</th>
                        <th class="num">TB/lần</th>
                        <th class="num">Nhịp ghé</th>
                        <th class="num">Vắng</th>
                        <th>Tình trạng</th>
                        <th>Đặt bàn</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($khach as $i => $k)
                        <tr>
                            <td class="num muted small">{{ $i + 1 }}</td>
                            <td>
                                <a href="{{ route('admin.customers.show', $k['phone']) }}">
                                    <b>{{ $k['name'] ?: 'Chưa có tên' }}</b>
                                </a>
                                <br><span class="muted small">{{ $k['phone'] }}</span>
                                @if ($k['card']?->tier)
                                    <span class="pill status-confirmed">{{ $k['card']->tier }}</span>
                                @endif
                            </td>
                            <td class="num">{{ $k['visits'] }}</td>
                            <td class="num"><b>{{ number_format($k['spend']) }}</b></td>
                            <td class="num small muted">{{ number_format($k['avg']) }}</td>
                            <td class="num small">{{ $k['cadence'] === null ? '—' : $k['cadence'].' ngày' }}</td>
                            <td class="num small">{{ $k['days_since'] === null ? '—' : $k['days_since'].' ngày' }}</td>
                            <td>
                                <span class="pill {{ $mauTinhTrang[$k['segment']] ?? '' }}">
                                    {{ Insight::TINH_TRANG[$k['segment']] }}
                                </span>
                            </td>
                            <td class="small">
                                @if ($k['booking'])
                                    {{ $k['booking']['bookings'] }} lần
                                    @if ($k['booking']['no_show'])
                                        <br><span class="pill status-no_show">{{ $k['booking']['no_show'] }} lần không đến</span>
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/charts.js') }}"></script>
@endpush
