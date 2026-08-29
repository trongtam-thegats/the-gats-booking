@extends('layouts.admin')

@section('title', 'Báo cáo')

@php
    use Illuminate\Support\Carbon;

    $t = $report['totals'];
    $p = $report['previous'];
    $range = $report['range'];

    // So sanh voi ky truoc do dai bang nhau. Tra ve null khi ky truoc khong co
    // du lieu, vi "tang vo han" khong noi len dieu gi.
    $delta = function (float $now, float $before): ?float {
        if ($before <= 0) {
            return null;
        }
        return round(($now - $before) / $before * 100, 1);
    };

    $currentPreset = null;
    foreach (\App\Http\Controllers\Admin\ReportController::PRESETS as $days => $label) {
        if ($range['days'] === $days && $range['to'] === Carbon::today()->toDateString()) {
            $currentPreset = $days;
        }
    }

    $queryBase = array_filter(['branch' => $branch?->id]);

    // Khoang dai thi so lieu duoc gop theo tuan (xem ReportService::series).
    $series = $report['series'];
    $unit = $series['unit'];

    // Cau hinh bieu do gom o day thay vi nhet thang vao @json: Blade khong doc
    // duoc mang nhieu dong long ngoac khi nam trong doi so cua directive.
    $vizFunnel = [
        'type' => 'rows',
        'label' => 'Phễu quy trình đặt bàn',
        'colors' => ['#3987e5', '#2a78d6', '#256abf', '#1c5cab'],
        'fields' => [
            ['key' => 'value', 'label' => 'Số lượt'],
            ['key' => 'share', 'label' => 'So với đầu phễu', 'unit' => '%'],
            ['key' => 'stepRate', 'label' => 'Qua được bước này'],
            ['key' => 'droppedText', 'label' => 'Rơi lại'],
        ],
        'rows' => collect($report['funnel'])->map(fn ($step) => [
            'label' => $step['label'],
            'value' => $step['value'],
            'display' => $step['value'].' · '.$step['share'].'%',
            'share' => $step['share'],
            'stepRate' => $step['step_rate'] === null ? '—' : $step['step_rate'].'%',
            'droppedText' => $step['dropped'] === null ? '—' : $step['dropped'].' lượt',
        ])->all(),
    ];

    $vizByDay = [
                'type' => 'area',
                'label' => 'Lượt đặt bàn theo '.$unit,
                'height' => 230,
                'fields' => [
                    ['key' => 'value', 'label' => 'Lượt đặt'],
                    ['key' => 'guests', 'label' => 'Khách đã đến'],
                ],
                'rows' => collect($series['rows'])->map(fn ($d) => [
                    'label' => $d['label'],
                    'tipLabel' => Carbon::parse($d['date'])->translatedFormat('l, d/m'),
                    'value' => $d['bookings'],
                    'guests' => $d['guests'],
                ])->all(),
            ];

    $vizOutcome = [
                'type' => 'stacked',
                'label' => 'Kết quả đặt bàn theo '.$unit,
                'height' => 210,
                'keys' => [
                    ['key' => 'arrived', 'label' => 'Khách đến', 'color' => 'good'],
                    ['key' => 'cancelled', 'label' => 'Hủy', 'color' => 'warning'],
                    ['key' => 'no_show', 'label' => 'Không tới', 'color' => 'critical'],
                ],
                'fields' => [
                    ['key' => 'arrived', 'label' => 'Khách đến', 'color' => 'good'],
                    ['key' => 'cancelled', 'label' => 'Hủy', 'color' => 'warning'],
                    ['key' => 'no_show', 'label' => 'Không tới', 'color' => 'critical'],
                ],
                'rows' => collect($series['rows'])->map(fn ($d) => [
                    'label' => $d['label'],
                    'tipLabel' => Carbon::parse($d['date'])->translatedFormat('l, d/m'),
                    'arrived' => $d['arrived'],
                    'cancelled' => $d['cancelled'],
                    'no_show' => $d['no_show'],
                ])->all(),
            ];

    $vizHour = [
                    'type' => 'columns',
                    'label' => 'Lượt đặt theo khung giờ',
                    'height' => 190,
                    'fields' => [['key' => 'value', 'label' => 'Lượt đặt']],
                    'rows' => collect($report['by_hour'])->map(fn ($h) => [
                        'label' => $h['label'],
                        'value' => $h['bookings'],
                    ])->all(),
                ];

    $vizWeekday = [
                    'type' => 'columns',
                    'label' => 'Lượt đặt theo ngày trong tuần',
                    'height' => 190,
                    'fields' => [
                        ['key' => 'value', 'label' => 'Lượt đặt'],
                        ['key' => 'guests', 'label' => 'Khách đã đến'],
                    ],
                    'rows' => collect($report['by_weekday'])->map(fn ($d) => [
                        'label' => $d['label'],
                        'value' => $d['bookings'],
                        'guests' => $d['guests'],
                    ])->all(),
                ];

    $vizSource = [
                    'type' => 'rows',
                    'label' => 'Nguồn đặt bàn',
                    'colors' => ['#3987e5', '#d95926', '#199e70'],
                    'fields' => [
                        ['key' => 'value', 'label' => 'Lượt đặt'],
                        ['key' => 'no_show_rate', 'label' => 'Bỏ hẹn', 'unit' => '%'],
                    ],
                    'rows' => collect($report['by_source'])->map(fn ($s) => [
                        'label' => $s['label'],
                        'value' => $s['bookings'],
                        'display' => $s['bookings'].' · '.$s['share'].'%',
                        'no_show_rate' => $s['no_show_rate'],
                    ])->all(),
                ];

    $vizLead = [
        'type' => 'rows',
        'label' => 'Khách đặt trước bao lâu',
        'fields' => [
            ['key' => 'value', 'label' => 'Lượt đặt'],
            ['key' => 'share', 'label' => 'Tỉ trọng', 'unit' => '%'],
        ],
        'rows' => collect($report['lead_time'])->map(fn ($l) => [
                        'label' => $l['label'],
                        'value' => $l['bookings'],
                        'display' => $l['bookings'].' · '.$l['share'].'%',
            'share' => $l['share'],
        ])->all(),
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Báo cáo đặt bàn</h1>
            <p>
                {{ $branch?->name ?? 'Tất cả địa điểm bạn phụ trách' }} ·
                {{ Carbon::parse($range['from'])->format('d/m/Y') }} – {{ Carbon::parse($range['to'])->format('d/m/Y') }}
                ({{ $range['days'] }} ngày)
            </p>
        </div>
    </div>

    {{-- Một hàng bộ lọc duy nhất, áp dụng cho toàn bộ trang --}}
    <form method="get" class="filters">
        @include('admin.partials.branch-filter')

        <div class="field">
            <label>Khoảng thời gian</label>
            <div class="range-presets">
                @foreach (\App\Http\Controllers\Admin\ReportController::PRESETS as $days => $label)
                    <a class="{{ $currentPreset === $days ? 'is-active' : '' }}"
                       href="{{ route('admin.reports.index', $queryBase + ['ngay' => $days]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="field">
            <label for="from">Từ ngày</label>
            <input type="date" id="from" name="from" value="{{ $from }}">
        </div>
        <div class="field">
            <label for="to">Đến ngày</label>
            <input type="date" id="to" name="to" value="{{ $to }}">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn-ghost" type="submit">Xem</button>
        </div>
    </form>

    {{-- ---------- Số liệu tổng ---------- --}}

    @php
        $kpis = [
            ['label' => 'Lượt đặt bàn', 'value' => $t['bookings'], 'delta' => $delta($t['bookings'], $p['bookings'])],
            ['label' => 'Khách đã phục vụ', 'value' => $t['guests'], 'delta' => $delta($t['guests'], $p['guests'])],
            ['label' => 'Tỉ lệ khách đến', 'value' => $t['arrival_rate'], 'unit' => '%', 'delta' => $delta($t['arrival_rate'], $p['arrival_rate'])],
            ['label' => 'Hẹn mà không tới', 'value' => $t['no_show_rate'], 'unit' => '%', 'inverse' => true, 'delta' => $delta($t['no_show_rate'], $p['no_show_rate'])],
            ['label' => 'Tỉ lệ hủy', 'value' => $t['cancel_rate'], 'unit' => '%', 'inverse' => true, 'delta' => $delta($t['cancel_rate'], $p['cancel_rate'])],
            ['label' => 'Trung bình mỗi lượt', 'value' => $t['avg_party'], 'unit' => ' khách', 'delta' => $delta($t['avg_party'], $p['avg_party'])],
        ];
    @endphp

    <div class="kpi-grid">
        @foreach ($kpis as $kpi)
            <div class="kpi {{ ($kpi['inverse'] ?? false) ? 'inverse' : '' }}">
                <span class="kpi-label">{{ $kpi['label'] }}</span>
                <span class="kpi-value">{{ $kpi['value'] }}<small>{{ $kpi['unit'] ?? '' }}</small></span>

                @if ($kpi['delta'] === null)
                    <span class="kpi-delta flat"><span>chưa có kỳ trước để so</span></span>
                @else
                    @php($dir = $kpi['delta'] > 0 ? 'up' : ($kpi['delta'] < 0 ? 'down' : 'flat'))
                    <span class="kpi-delta {{ $dir }}">
                        <span class="dir">{{ $kpi['delta'] > 0 ? '▲' : ($kpi['delta'] < 0 ? '▼' : '—') }}
                            {{ abs($kpi['delta']) }}%</span>
                        <span>so với kỳ trước</span>
                    </span>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ---------- Phễu quy trình ---------- --}}

    <div class="card">
        <div class="report-head">
            <div>
                <h2>Quy trình xử lý đặt bàn</h2>
                <p>Mỗi bước là một khâu trong quy trình. Con số bên phải là số lượt còn lại tới bước đó,
                    và phần rơi giữa hai bước cho biết đang mất khách ở đâu.</p>
            </div>
            <button class="viz-toggle" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
        </div>

        <figure class="viz-figure">
            <div class="viz-plot"></div>

            <script type="application/json">@json($vizFunnel)</script>

            <div class="viz-table table-wrap">
                <table>
                    <thead>
                    <tr><th>Bước</th><th class="num">Số lượt</th><th class="num">So với đầu phễu</th><th class="num">Rơi ở bước này</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($report['funnel'] as $step)
                        <tr>
                            <td>{{ $step['label'] }}</td>
                            <td class="num">{{ $step['value'] }}</td>
                            <td class="num">{{ $step['share'] }}%</td>
                            <td class="num">{{ $step['dropped'] === null ? '—' : $step['dropped'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </figure>

        <p class="funnel-drop" style="margin-top:14px">
            @if ($t['bookings'] === 0)
                Chưa có lượt đặt nào trong kỳ này.
            @else
                Thời gian duyệt trung vị:
                <b>{{ $t['median_confirm_minutes'] === null ? 'chưa có' : $t['median_confirm_minutes'].' phút' }}</b>
                · Còn <b>{{ $t['pending'] }}</b> lượt chưa xác nhận
                · <b>{{ $t['cancelled'] }}</b> lượt bị hủy
                · <b>{{ $t['no_show'] }}</b> lượt hẹn mà không tới.
            @endif
        </p>
    </div>

    {{-- ---------- Xu hướng theo ngày ---------- --}}

    <div class="card">
        <div class="report-head">
            <div>
                <h2>Lượt đặt theo {{ $unit }}</h2>
                <p>Nhìn ra {{ $unit }} cao điểm và {{ $unit }} trống để cân nhân sự và chạy khuyến mãi.@if ($series['granularity'] === 'week') Khoảng trên 45 ngày được gộp theo tuần cho dễ đọc; chọn khoảng ngắn hơn để xem từng ngày.@endif</p>
            </div>
            <button class="viz-toggle" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
        </div>

        <figure class="viz-figure">
            <div class="viz-plot"></div>

            <script type="application/json">@json($vizByDay)</script>

            <div class="viz-table table-wrap">
                <table>
                    <thead><tr><th>{{ ucfirst($unit) }}</th><th class="num">Lượt đặt</th><th class="num">Khách đã đến</th></tr></thead>
                    <tbody>
                    @foreach ($series['rows'] as $day)
                        <tr>
                            <td>{{ Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                            <td class="num">{{ $day['bookings'] }}</td>
                            <td class="num">{{ $day['guests'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </figure>
    </div>

    {{-- ---------- Kết quả theo ngày ---------- --}}

    <div class="card">
        <div class="report-head">
            <div>
                <h2>Kết quả từng {{ $unit }}</h2>
                <p>{{ ucfirst($unit) }} nào khách bỏ hẹn nhiều bất thường thì xem lại khâu nhắc lịch của {{ $unit }} đó.</p>
            </div>
            <button class="viz-toggle" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
        </div>

        <figure class="viz-figure">
            <div class="viz-plot"></div>

            <script type="application/json">@json($vizOutcome)</script>

            <div class="viz-table table-wrap">
                <table>
                    <thead><tr><th>{{ ucfirst($unit) }}</th><th class="num">Khách đến</th><th class="num">Hủy</th><th class="num">Không tới</th></tr></thead>
                    <tbody>
                    @foreach ($series['rows'] as $day)
                        <tr>
                            <td>{{ Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                            <td class="num">{{ $day['arrived'] }}</td>
                            <td class="num">{{ $day['cancelled'] }}</td>
                            <td class="num">{{ $day['no_show'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </figure>

        <p class="viz-legend">
            <span><i style="background:#0ca30c"></i> Khách đến</span>
            <span><i style="background:#fab219"></i> Hủy</span>
            <span><i style="background:#d03b3b"></i> Không tới</span>
        </p>
    </div>

    {{-- ---------- Giờ và thứ ---------- --}}

    <div class="viz-grid">
        <div class="card">
            <div class="report-head">
                <div>
                    <h2>Khung giờ khách chọn</h2>
                    <p>Quyết định giờ xếp ca đông người và giờ mở bếp.</p>
                </div>
                <button class="viz-toggle" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
            </div>

            <figure class="viz-figure">
                <div class="viz-plot"></div>

                <script type="application/json">@json($vizHour)</script>

                <div class="viz-table table-wrap">
                    <table>
                        <thead><tr><th>Khung giờ</th><th class="num">Lượt đặt</th></tr></thead>
                        <tbody>
                        @forelse ($report['by_hour'] as $hour)
                            <tr><td>{{ $hour['label'] }}</td><td class="num">{{ $hour['bookings'] }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="empty">Chưa có dữ liệu.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>

        <div class="card">
            <div class="report-head">
                <div>
                    <h2>Ngày trong tuần</h2>
                    <p>So sánh ngày thường với cuối tuần để bố trí nhân sự và sự kiện.</p>
                </div>
                <button class="viz-toggle" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
            </div>

            <figure class="viz-figure">
                <div class="viz-plot"></div>

                <script type="application/json">@json($vizWeekday)</script>

                <div class="viz-table table-wrap">
                    <table>
                        <thead><tr><th>Thứ</th><th class="num">Lượt đặt</th><th class="num">Khách đã đến</th></tr></thead>
                        <tbody>
                        @foreach ($report['by_weekday'] as $day)
                            <tr>
                                <td>{{ $day['label'] }}</td>
                                <td class="num">{{ $day['bookings'] }}</td>
                                <td class="num">{{ $day['guests'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>
    </div>

    {{-- ---------- Nguồn đặt và thời điểm đặt ---------- --}}

    <div class="viz-grid">
        <div class="card">
            <div class="report-head">
                <div>
                    <h2>Khách đặt qua đâu</h2>
                    <p>Kèm tỉ lệ bỏ hẹn của từng nguồn — biết nguồn nào đáng tin hơn.</p>
                </div>
                <button class="viz-toggle" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
            </div>

            <figure class="viz-figure">
                <div class="viz-plot"></div>

                <script type="application/json">@json($vizSource)</script>

                <div class="viz-table table-wrap">
                    <table>
                        <thead><tr><th>Nguồn</th><th class="num">Lượt đặt</th><th class="num">Tỉ trọng</th><th class="num">Bỏ hẹn</th></tr></thead>
                        <tbody>
                        @foreach ($report['by_source'] as $source)
                            <tr>
                                <td>{{ $source['label'] }}</td>
                                <td class="num">{{ $source['bookings'] }}</td>
                                <td class="num">{{ $source['share'] }}%</td>
                                <td class="num">{{ $source['no_show_rate'] }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>

        <div class="card">
            <div class="report-head">
                <div>
                    <h2>Khách đặt trước bao lâu</h2>
                    <p>Quyết định nên nhắc lịch vào lúc nào và mở nhận đặt trước mấy ngày.</p>
                </div>
                <button class="viz-toggle" type="button" data-viz-toggle aria-pressed="false">Xem bảng số</button>
            </div>

            <figure class="viz-figure">
                <div class="viz-plot"></div>

                <script type="application/json">@json($vizLead)</script>

                <div class="viz-table table-wrap">
                    <table>
                        <thead><tr><th>Đặt trước</th><th class="num">Lượt</th><th class="num">Tỉ trọng</th></tr></thead>
                        <tbody>
                        @foreach ($report['lead_time'] as $bucket)
                            <tr>
                                <td>{{ $bucket['label'] }}</td>
                                <td class="num">{{ $bucket['bookings'] }}</td>
                                <td class="num">{{ $bucket['share'] }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>
    </div>

    {{-- ---------- Khách và sức chứa ---------- --}}

    <div class="viz-grid">
        <div class="card">
            <div class="report-head">
                <div>
                    <h2>Khách mới và khách quay lại</h2>
                    <p>Tính theo số điện thoại đã từng đặt trước kỳ báo cáo.</p>
                </div>
            </div>

            <div class="kpi-grid" style="margin-bottom:0">
                <div class="kpi">
                    <span class="kpi-label">Khách khác nhau</span>
                    <span class="kpi-value">{{ $report['guests']['unique'] }}</span>
                </div>
                <div class="kpi">
                    <span class="kpi-label">Khách quay lại</span>
                    <span class="kpi-value">{{ $report['guests']['returning'] }}<small>{{ ' · '.$report['guests']['returning_rate'].'%' }}</small></span>
                </div>
                <div class="kpi">
                    <span class="kpi-label">Khách mới</span>
                    <span class="kpi-value">{{ $report['guests']['new'] }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="report-head">
                <div>
                    <h2>Sức chứa đã dùng</h2>
                    <p>Số chỗ đã phục vụ so với tổng số chỗ quán có, tính gộp cả kỳ.</p>
                </div>
            </div>

            <div class="kpi-grid" style="margin-bottom:0">
                <div class="kpi">
                    <span class="kpi-label">Tổng số chỗ</span>
                    <span class="kpi-value">{{ $report['capacity']['seats'] }}</span>
                </div>
                <div class="kpi">
                    <span class="kpi-label">Khách mỗi đêm</span>
                    <span class="kpi-value">{{ $report['capacity']['avg_per_night'] }}</span>
                </div>
                <div class="kpi">
                    <span class="kpi-label">Tỉ lệ lấp đầy</span>
                    <span class="kpi-value">{{ $report['capacity']['fill_rate'] }}<small>%</small></span>
                </div>
            </div>
        </div>
    </div>

    {{-- ---------- So sánh quán ---------- --}}

    @if ($report['by_branch'])
        <div class="card">
            <div class="report-head">
                <div>
                    <h2>So sánh giữa các quán</h2>
                    <p>Cùng khoảng thời gian, đặt cạnh nhau để thấy nơi nào đang gặp vấn đề.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Quán</th><th class="num">Lượt đặt</th><th class="num">Khách đã phục vụ</th><th class="num">Bỏ hẹn</th><th class="num">Hủy</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($report['by_branch'] as $row)
                        <tr>
                            <td>{{ $row['branch'] }}</td>
                            <td class="num">{{ $row['bookings'] }}</td>
                            <td class="num">{{ $row['guests'] }}</td>
                            <td class="num">{{ $row['no_show_rate'] }}%</td>
                            <td class="num">{{ $row['cancel_rate'] }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ---------- Khách bỏ hẹn nhiều ---------- --}}

    @if ($report['no_show_guests'])
        <div class="card">
            <div class="report-head">
                <div>
                    <h2>Khách hẹn mà không tới nhiều nhất</h2>
                    <p>Cân nhắc gọi xác nhận lại trước giờ hẹn, hoặc chặn đặt online nếu lặp lại nhiều lần.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead><tr><th>Số điện thoại</th><th class="num">Số lần bỏ hẹn</th><th class="num">Số khách đã giữ chỗ</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($report['no_show_guests'] as $guest)
                        <tr>
                            <td>{{ $guest['phone'] }}</td>
                            <td class="num">{{ $guest['no_show'] }}</td>
                            <td class="num">{{ $guest['guests'] }}</td>
                            <td class="num">
                                <a class="btn btn-ghost btn-sm"
                                   href="{{ route('admin.guests.index', ['phone' => $guest['phone']]) }}">Hồ sơ khách</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/charts.js') }}"></script>
@endpush
