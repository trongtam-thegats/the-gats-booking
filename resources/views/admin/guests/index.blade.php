@extends('layouts.admin')

@section('title', 'Tra cứu khách')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tra cứu khách</h1>
            <p>Gõ số điện thoại, tên hoặc mã đặt bàn. Khách gọi tới là biết ngay họ đã đến bao nhiêu lần.</p>
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
                    <tr><th>Khách</th><th>Điện thoại</th><th class="num">Số lần đặt</th><th>Lần gần nhất</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($results as $row)
                        <tr>
                            <td><b>{{ $row['name'] }}</b></td>
                            <td>{{ $row['last']->customer_phone }}</td>
                            <td class="num">{{ $row['total'] }}</td>
                            <td class="small muted">
                                {{ $row['last']->booking_date->format('d/m/Y') }} ·
                                {{ $row['last']->branch->name }} ·
                                <span class="pill status-{{ $row['last']->status }}">{{ $row['last']->statusLabel() }}</span>
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
