@extends('layouts.admin')

@section('title', 'Tra cứu khách')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tra cứu khách</h1>
            <p>Gõ số điện thoại, tên hoặc mã đặt bàn. Tìm cả khách chưa từng đặt bàn — có hoá đơn hoặc có trong danh sách khách hàng Sapo là ra.</p>
        </div>
    </div>

    <div class="card">
        <form method="get" class="filters" style="margin-bottom:0">
            <div class="field" style="min-width:280px">
                <label for="q">Số điện thoại, tên khách hoặc mã đặt bàn</label>
                <input type="text" id="q" name="q" value="{{ $term }}" placeholder="0912 345 678" autofocus>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button class="btn" type="submit">Tìm</button>
            </div>
        </form>
    </div>

    @if ($results->isNotEmpty())
        <div class="card">
            <h2>{{ $results->count() }} khách khớp</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Khách</th><th>Điện thoại</th><th class="num">Lần ghé (hoá đơn)</th><th class="num">Lần đặt bàn</th><th>Gần nhất</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($results as $row)
                        <tr>
                            <td>
                                <b>{{ $row['name'] ?? 'Chưa rõ tên' }}</b>
                                @if ($row['card']?->tier)
                                    <span class="pill">{{ $row['card']->tier }}</span>
                                @endif
                            </td>
                            <td>{{ $row['phone'] }}</td>
                            <td class="num">{{ $row['visits'] }}</td>
                            <td class="num">{{ $row['bookings'] }}</td>
                            <td class="small muted">
                                @if ($row['last'])
                                    {{ $row['last']->format('d/m/Y') }}
                                @elseif ($row['card'])
                                    Chỉ có trong danh sách khách hàng
                                @endif
                            </td>
                            <td class="num">
                                <a class="btn btn-ghost btn-sm"
                                   href="{{ route('admin.customers.show', $row['phone']) }}">Xem hồ sơ</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($term !== '')
        <div class="card">
            <p class="empty mb-0">Không tìm thấy khách nào khớp với “{{ $term }}”.</p>
        </div>
    @endif

@endsection
