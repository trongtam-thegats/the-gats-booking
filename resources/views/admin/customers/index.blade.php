@extends('layouts.admin')

@section('title', 'Phân tích khách hàng')

@php
    use App\Http\Controllers\Admin\CustomerInsightController as Ctl;
    use App\Models\GuestNote;
    use App\Services\CustomerInsightService as Insight;
    use Illuminate\Support\Carbon;

    /** Mau nhan, dung lai bang mau cua trang dat ban. */
    $mauTinhTrang = [
        // May suy ra tu du lieu.
        'deu_dan' => 'status-confirmed',
        'khach_moi' => 'status-seated',
        'thua_dan' => 'status-pending',
        'nguy_co' => 'status-cancelled',
        'mot_lan' => 'status-completed',
        // Nguoi da goi va xac nhan - de len tinh trang may doan.
        'xn_se_quay_lai' => 'status-confirmed',
        'xn_khong_quan_tam' => 'status-cancelled',
        'xn_da_chuyen_di' => 'status-completed',
        'xn_so_sai' => 'status-completed',
        'xn_da_roi_bo' => 'status-cancelled',
        'xn_khong_can' => 'status-completed',
    ];

    $mauXemXet = [
        'chua_xem_xet' => 'status-pending',
        'da_xem_xet' => 'status-completed',
        'da_ghe_lai' => 'status-confirmed',
    ];

    $tienGon = fn ($so) => $so >= 1000000000
        ? rtrim(rtrim(number_format($so / 1000000000, 2), '0'), ',.').' tỉ'
        : ($so >= 1000000
            ? rtrim(rtrim(number_format($so / 1000000, 1), '0'), ',.').' tr'
            : number_format(round($so / 1000)).'k');

    $dangLoc = ! empty($loc);

    // --- Bieu do 1: co cau khach theo tinh trang ---------------------------
    $mauCot = [
        'deu_dan' => '#6fbf7a',
        'khach_moi' => '#78aae6',
        'thua_dan' => '#c8a15a',
        'nguy_co' => '#e0685f',
        'mot_lan' => '#8a8a8a',
        'xn_se_quay_lai' => '#8fd39a',
        'xn_khong_quan_tam' => '#c2564f',
        'xn_da_chuyen_di' => '#6f6a63',
        'xn_so_sai' => '#6f6a63',
        'xn_da_roi_bo' => '#a8443d',
        'xn_khong_can' => '#6f6a63',
    ];

    $dongNhom = collect(Insight::moiTinhTrang())
        ->map(fn ($nhan, $ma) => [
            'ma' => $ma,
            'label' => $nhan,
            'value' => (int) ($nhomTinhTrang[$ma] ?? 0),
            'display' => number_format((int) ($nhomTinhTrang[$ma] ?? 0)).' khách',
            'spendText' => number_format($tatCa->where('trang_thai', $ma)->sum('spend')).'đ',
        ])
        ->filter(fn ($r) => $r['value'] > 0)
        ->values();

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

    // --- Bieu do 2: doanh thu theo thang, tach khach nhan dien va vang lai --
    $vizThang = [
        'type' => 'area',
        'label' => 'Doanh thu theo tháng',
        'height' => 240,
        // Doanh thu len den hang ti, truc phai rut gon thi moi doc duoc.
        'unit' => 'tien',
        'fields' => [
            ['key' => 'value', 'label' => 'Doanh thu'],
            ['key' => 'invoices', 'label' => 'Số hóa đơn'],
            ['key' => 'identified', 'label' => 'Hóa đơn có khách'],
        ],
        'rows' => collect($theoThang)->map(fn ($m) => [
            'label' => $m['label'],
            'tipLabel' => 'Tháng '.Carbon::parse($m['month'].'-01')->format('m/Y'),
            'value' => (int) round($m['revenue']),
            'invoices' => number_format($m['invoices']),
            'identified' => number_format($m['identified']),
        ])->all(),
    ];

    // --- Bieu do 3: khach moi va khach quay lai theo thang ------------------
    $vizKhachThang = [
        'type' => 'stacked',
        'label' => 'Khách mới và khách quay lại theo tháng',
        'height' => 220,
        'keys' => [
            ['key' => 'new_customers', 'label' => 'Khách mới', 'color' => 'good'],
            ['key' => 'returning', 'label' => 'Khách quay lại', 'color' => 'warning'],
        ],
        'fields' => [
            ['key' => 'new_customers', 'label' => 'Khách mới', 'color' => 'good'],
            ['key' => 'returning', 'label' => 'Khách quay lại', 'color' => 'warning'],
        ],
        'rows' => collect($theoThang)->map(fn ($m) => [
            'label' => $m['label'],
            'tipLabel' => 'Tháng '.Carbon::parse($m['month'].'-01')->format('m/Y'),
            'new_customers' => $m['new_customers'],
            'returning' => $m['returning'],
        ])->all(),
    ];

    // --- Bieu do 4: phan bo khach theo so lan ghe ---------------------------
    $vizSoLan = [
        'type' => 'columns',
        'label' => 'Khách theo số lần ghé',
        'height' => 210,
        'fields' => [
            ['key' => 'value', 'label' => 'Số khách'],
            ['key' => 'spendText', 'label' => 'Tổng chi tiêu'],
        ],
        'rows' => collect($theoSoLan)->map(fn ($b) => [
            'label' => $b['label'],
            'value' => $b['value'],
            'spendText' => number_format($b['spend']).'đ',
        ])->all(),
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
            Phần còn lại không truy được khách là ai, nên mọi con số dưới đây chỉ nói về nhóm khách đã ghi nhận được.
        </div>
    @endif

    @if ($khoang)
        <p class="muted small" style="margin:-4px 0 14px">
            Mọi con số bên dưới được <b>tính lại trong {{ mb_strtolower(Ctl::KHOANG[$khoang]) }}</b>
            (từ {{ $tuNgay->format('d/m/Y') }}): số lần ghé, chi tiêu, nhịp ghé và tình trạng.
            Riêng "khách mới" vẫn tính theo lần ghé đầu tiên trong toàn bộ lịch sử, nên khách gắn bó
            lâu năm không bị gọi nhầm là mới.
        </p>
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
            <span>Chưa xem xét</span>
            <b>{{ number_format($nhomXemXet['chua_xem_xet'] ?? 0) }}</b>
            <small class="muted">
                đã xem xét {{ number_format($nhomXemXet['da_xem_xet'] ?? 0) }} ·
                đã ghé lại {{ number_format($nhomXemXet['da_ghe_lai'] ?? 0) }}
            </small>
        </div>
    </div>

    {{-- Bieu do. Di chuot vao cot de xem so lieu; nut chuyen sang bang so. --}}
    <div class="card">
        <div class="page-head" style="margin-bottom:6px">
            <h2 style="margin:0">Doanh thu theo tháng <span class="muted" style="font-weight:400;font-size:13px">· toàn bộ kỳ dữ liệu</span></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
        </div>

        <figure class="viz-figure">
            <div class="viz-plot"></div>
            <script type="application/json">@json($vizThang)</script>
            <div class="viz-table table-wrap">
                <table>
                    <thead><tr><th>Tháng</th><th class="num">Hóa đơn</th><th class="num">Có khách</th><th class="num">Doanh thu</th></tr></thead>
                    <tbody>
                    @foreach ($theoThang as $m)
                        <tr>
                            <td>{{ $m['label'] }}</td>
                            <td class="num">{{ number_format($m['invoices']) }}</td>
                            <td class="num">{{ number_format($m['identified']) }}</td>
                            <td class="num">{{ number_format($m['revenue']) }}đ</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </figure>
    </div>

    <div class="card">
        <div class="page-head" style="margin-bottom:6px">
            <h2 style="margin:0">Khách mới và khách quay lại</h2>
            <button class="btn btn-ghost btn-sm" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
        </div>
        <p class="muted small">
            "Khách mới" là tháng đầu tiên hệ thống ghi nhận được số điện thoại của người đó.
            Cột quay lại càng dày thì việc giữ khách càng tốt.
        </p>

        <figure class="viz-figure">
            <div class="viz-plot"></div>
            <script type="application/json">@json($vizKhachThang)</script>
            <div class="viz-table table-wrap">
                <table>
                    <thead><tr><th>Tháng</th><th class="num">Khách mới</th><th class="num">Quay lại</th></tr></thead>
                    <tbody>
                    @foreach ($theoThang as $m)
                        <tr><td>{{ $m['label'] }}</td><td class="num">{{ $m['new_customers'] }}</td><td class="num">{{ $m['returning'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </figure>
    </div>

    <div class="grid-2">
        @if ($vizNhom['rows'])
            <div class="card">
                <h2>Cơ cấu {{ number_format($tatCa->count()) }} khách</h2>
                <p class="muted small">
                    Tình trạng tính theo <b>nhịp ghé của chính khách đó</b>: vắng quá ba lần nhịp quen thuộc
                    thì coi là có nguy cơ rời bỏ.
                </p>
                <figure class="viz-figure">
                    <div class="viz-plot"></div>
                    <script type="application/json">@json($vizNhom)</script>
                    <div class="viz-table table-wrap">
                        <table>
                            <thead><tr><th>Tình trạng</th><th class="num">Khách</th><th class="num">Chi tiêu</th></tr></thead>
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
            <h2>Khách theo số lần ghé</h2>
            <p class="muted small">Cột bên phải càng nặng tiền thì quán càng sống bằng khách quen.</p>
            <figure class="viz-figure">
                <div class="viz-plot"></div>
                <script type="application/json">@json($vizSoLan)</script>
                <div class="viz-table table-wrap">
                    <table>
                        <thead><tr><th>Số lần ghé</th><th class="num">Khách</th><th class="num">Chi tiêu</th></tr></thead>
                        <tbody>
                        @foreach ($theoSoLan as $b)
                            <tr><td>{{ $b['label'] }}</td><td class="num">{{ number_format($b['value']) }}</td><td class="num">{{ number_format($b['spend']) }}đ</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>
    </div>

    {{-- Bo loc: mot hang cac o tha xuong, chon la chay luon. --}}
    <div class="card">
        <form method="get" class="form-grid filter-row">
            @include('admin.partials.branch-filter')

            <div class="field">
                <label for="khoang">Khoảng thời gian</label>
                <select id="khoang" name="khoang" class="@if ($khoang) dang-loc @endif"
                        onchange="this.form.submit()">
                    <option value="">Toàn bộ dữ liệu</option>
                    @foreach (Ctl::KHOANG as $thang => $nhan)
                        <option value="{{ $thang }}" @selected($khoang === $thang)>{{ $nhan }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tim">Tìm khách</label>
                <input type="search" id="tim" name="tim" class="@if (! empty($loc['tim'])) dang-loc @endif" value="{{ $loc['tim'] ?? '' }}"
                       placeholder="Tên hoặc số điện thoại">
            </div>

            <div class="field">
                <label for="tinh-trang">Tình trạng</label>
                <select id="tinh-trang" name="tinh-trang" class="@if (! empty($loc['segment'])) dang-loc @endif" onchange="this.form.submit()">
                    <option value="">Tất cả tình trạng</option>
                    <optgroup label="Hệ thống suy ra từ dữ liệu">
                        @foreach (Insight::TINH_TRANG as $ma => $nhan)
                            <option value="{{ $ma }}" @selected(in_array($ma, $loc['segment'] ?? [], true))>
                                {{ $nhan }} ({{ number_format($nhomTinhTrang[$ma] ?? 0) }})
                            </option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Đã gọi và xác nhận">
                        @foreach (Insight::XAC_NHAN as $ma => $nhan)
                            @php($khoa = Insight::TIEN_TO_XAC_NHAN.$ma)
                            <option value="{{ $khoa }}" @selected(in_array($khoa, $loc['segment'] ?? [], true))>
                                {{ $nhan }} ({{ number_format($nhomTinhTrang[$khoa] ?? 0) }})
                            </option>
                        @endforeach
                    </optgroup>
                </select>
            </div>

            <div class="field">
                <label for="xem-xet">Xem xét</label>
                <select id="xem-xet" name="xem-xet" class="@if (! empty($loc['review'])) dang-loc @endif" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    @foreach (Insight::XEM_XET as $ma => $nhan)
                        <option value="{{ $ma }}" @selected(in_array($ma, $loc['review'] ?? [], true))>
                            {{ $nhan }} ({{ number_format($nhomXemXet[$ma] ?? 0) }})
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($hangThe->count() > 1)
                <div class="field">
                    <label for="hang-the">Hạng thẻ</label>
                    <select id="hang-the" name="hang-the" class="@if (! empty($loc['tier'])) dang-loc @endif" onchange="this.form.submit()">
                        <option value="">Tất cả hạng</option>
                        @foreach ($hangThe as $hang => $so)
                            <option value="{{ $hang }}" @selected(in_array((string) $hang, $loc['tier'] ?? [], true))>
                                {{ $hang }} ({{ number_format($so) }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="field">
                <label for="so-lan">Số lần ghé</label>
                <select id="so-lan" name="so-lan" class="@if (! empty($loc['visits_min'])) dang-loc @endif" onchange="this.form.submit()">
                    <option value="">Không giới hạn</option>
                    @foreach (Ctl::MOC_SO_LAN as $moc => $nhan)
                        <option value="{{ $moc }}" @selected(($loc['visits_min'] ?? null) == $moc)>{{ $nhan }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="chi-tu">Chi tiêu</label>
                <select id="chi-tu" name="chi-tu" class="@if (! empty($loc['spend_min'])) dang-loc @endif" onchange="this.form.submit()">
                    <option value="">Không giới hạn</option>
                    @foreach (Ctl::MOC_CHI_TIEU as $moc => $nhan)
                        <option value="{{ $moc }}" @selected(($loc['spend_min'] ?? null) == $moc)>{{ $nhan }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="vang-tu">Thời gian vắng</label>
                <select id="vang-tu" name="vang-tu" class="@if (! empty($loc['vang_min'])) dang-loc @endif" onchange="this.form.submit()">
                    <option value="">Không giới hạn</option>
                    @foreach (Ctl::MOC_VANG as $moc => $nhan)
                        <option value="{{ $moc }}" @selected(($loc['vang_min'] ?? null) == $moc)>{{ $nhan }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="dat-ban">Đặt bàn</label>
                <select id="dat-ban" name="dat-ban" class="@if (! empty($loc['co_dat_ban']) || ! empty($loc['co_vang_mat'])) dang-loc @endif" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    @foreach (Ctl::MOC_DAT_BAN as $ma => $nhan)
                        <option value="{{ $ma }}"
                            @selected(($ma === 'co' && ! empty($loc['co_dat_ban'])) || ($ma === 'vang' && ! empty($loc['co_vang_mat'])))>
                            {{ $nhan }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="sap-xep">Xếp theo</label>
                <select id="sap-xep" name="sap-xep" onchange="this.form.submit()">
                    @foreach (Ctl::SAP_XEP as $ma => $nhan)
                        <option value="{{ $ma }}" @selected($sapXep === $ma)>{{ $nhan }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="so-luong">Hiện tối đa</label>
                <select id="so-luong" name="so-luong" onchange="this.form.submit()">
                    @foreach ([50, 100, 200, 500] as $n)
                        <option value="{{ $n }}" @selected($soLuong === $n)>{{ $n }} khách</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="page-head" style="margin-bottom:10px">
            <h2 style="margin:0">
                {{ number_format($soKhopLoc) }} khách khớp bộ lọc{{ $khoang ? ' trong '.mb_strtolower(Ctl::KHOANG[$khoang]) : '' }}
                @if ($soKhopLoc > $khach->count())
                    <span class="muted" style="font-weight:400">— đang hiện {{ number_format($khach->count()) }}</span>
                @endif
            </h2>
            <span class="muted small">
                Tổng chi tiêu nhóm này: <b>{{ number_format($tienKhopLoc) }}đ</b>
                @if ($dangLoc)
                    &nbsp;<a href="{{ route('admin.customers.index', array_filter(['branch' => $branch?->id])) }}">Bỏ lọc</a>
                @endif
            </span>
        </div>

        @if ($khach->isEmpty())
            <p class="muted">Không có khách nào khớp bộ lọc. Thử bỏ bớt điều kiện.</p>
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
                        <th class="num">Nhịp</th>
                        <th class="num">Vắng</th>
                        <th>Tình trạng</th>
                        <th>Xem xét</th>
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
                            <td class="num small">{{ $k['cadence'] === null ? '—' : $k['cadence'].'d' }}</td>
                            <td class="num small">{{ $k['days_since'] === null ? '—' : $k['days_since'].'d' }}</td>
                            <td>
                                <span class="pill {{ $mauTinhTrang[$k['trang_thai']] ?? '' }}">
                                    {{ Insight::nhanTinhTrang($k['trang_thai']) }}
                                </span>
                                @if (Insight::laXacNhan($k['trang_thai']))
                                    <br><span class="muted" style="font-size:11px">đã xác nhận</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($k['review'] === 'chua_xem_xet')
                                    <span class="muted">—</span>
                                @else
                                    <span class="pill {{ $mauXemXet[$k['review']] }}">{{ Insight::XEM_XET[$k['review']] }}</span>
                                    @if ($k['note']?->reviewed_at)
                                        <br><span class="muted">{{ $k['note']->reviewed_at->format('d/m') }}@if ($k['note']->ketQuaLabel()) · {{ $k['note']->ketQuaLabel() }}@endif</span>
                                    @endif
                                @endif
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
