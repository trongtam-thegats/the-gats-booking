@extends('layouts.admin')

@section('title', 'Khu vực & bàn')

@section('content')
    <div class="page-head">
        <div>
            <h1>Khu vực &amp; bàn</h1>
            <p>{{ $branch->name }} · {{ $branch->diningTables->where('is_active', true)->count() }} bàn đang dùng,
                tổng {{ $branch->totalSeats() }} chỗ.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.branches.edit', $branch) }}">Cấu hình chi nhánh</a>
    </div>

    @if ($branches->count() > 1)
        <form method="get" class="filters">
            @include('admin.partials.branch-filter', ['allowAll' => false])
        </form>
    @endif

    <div class="grid-2">
        <div class="card">
            <h2>Khu vực</h2>
            <p class="sub">Ví dụ: Tầng trệt, Sân thượng, Phòng VIP. Bàn cùng khu mới được ghép với nhau.</p>

            <form method="post" action="{{ route('admin.areas.store', $branch) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="area_name">Tên khu vực</label>
                    <input type="text" id="area_name" name="name" required>
                </div>
                <div class="field">
                    <label for="area_sort">Thứ tự</label>
                    <input type="number" id="area_sort" name="sort_order" min="0" max="999" value="0">
                </div>
                <div class="field full">
                    <label class="check">
                        <input type="checkbox" name="bookable" value="1" checked> Nhận khách đặt online
                    </label>
                    <span class="hint">
                        Tắt thì bàn trong khu này chỉ dành cho khách gọi điện hoặc khách vãng lai,
                        hệ thống không tự xếp cho đơn đặt trên web.
                    </span>
                </div>
                <div class="field full">
                    <button class="btn btn-ghost btn-sm" type="submit">Thêm khu vực</button>
                </div>
            </form>

            <div class="table-wrap" style="margin-top:14px">
                <table>
                    <thead><tr><th>Khu vực</th><th class="num">Bàn</th><th>Đặt online</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($branch->areas as $area)
                        <tr>
                            <td>
                                <b>{{ $area->name }}</b>
                                <details class="inline-edit">
                                    <summary>Sửa</summary>
                                    <div>
                                        <form method="post" action="{{ route('admin.areas.update', [$branch, $area]) }}" class="form-grid">
                                            @csrf @method('PUT')
                                            <div class="field">
                                                <label>Tên</label>
                                                <input type="text" name="name" value="{{ $area->name }}" required>
                                            </div>
                                            <div class="field">
                                                <label>Thứ tự</label>
                                                <input type="number" name="sort_order" value="{{ $area->sort_order }}" min="0" max="999">
                                            </div>
                                            <div class="field full">
                                                <label class="check">
                                                    <input type="checkbox" name="bookable" value="1" @checked($area->bookable)>
                                                    Nhận khách đặt online
                                                </label>
                                            </div>
                                            <div class="field full">
                                                <button class="btn btn-ghost btn-sm" type="submit">Lưu</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            </td>
                            <td class="num">{{ $branch->diningTables->where('area_id', $area->id)->count() }}</td>
                            <td class="small">{{ $area->bookable ? 'Có' : 'Không' }}</td>
                            <td class="num">
                                <form method="post" action="{{ route('admin.areas.destroy', [$branch, $area]) }}"
                                      onsubmit="return confirm('Xóa khu vực {{ $area->name }}? Bàn trong khu sẽ chuyển về mục chưa phân khu.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Chưa có khu vực nào. Không bắt buộc, nhưng nên có để xếp bàn dễ hơn.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Thêm bàn</h2>
            <p class="sub">Thêm từng bàn, hoặc tạo hàng loạt theo dải số.</p>

            <form method="post" action="{{ route('admin.tables.store', $branch) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="code">Mã bàn</label>
                    <input type="text" id="code" name="code" placeholder="B01" maxlength="20" required>
                </div>
                <div class="field">
                    <label for="table_area">Khu vực</label>
                    <select id="table_area" name="area_id">
                        <option value="">Chưa phân khu</option>
                        @foreach ($branch->areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="seats_min">Sức chứa tối thiểu</label>
                    <input type="number" id="seats_min" name="seats_min" min="1" max="50" value="1" required>
                </div>
                <div class="field">
                    <label for="seats_max">Sức chứa tối đa</label>
                    <input type="number" id="seats_max" name="seats_max" min="1" max="50" value="4" required>
                </div>
                <div class="field full">
                    <label class="check"><input type="checkbox" name="combinable" value="1" checked> Cho phép ghép bàn</label>
                    <label class="check"><input type="checkbox" name="is_active" value="1" checked> Đang sử dụng</label>
                </div>
                <div class="field full">
                    <button class="btn btn-ghost btn-sm" type="submit">Thêm bàn</button>
                </div>
            </form>

            <hr style="border:none; border-top:1px solid var(--line); margin:18px 0">

            <h2>Tạo hàng loạt</h2>
            <p class="sub">Ví dụ tiền tố B, từ 1 đến 12, 4 chỗ sẽ tạo B01 … B12.</p>
            <form method="post" action="{{ route('admin.tables.bulk', $branch) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="prefix">Tiền tố</label>
                    <input type="text" id="prefix" name="prefix" value="B" maxlength="10" required>
                </div>
                <div class="field">
                    <label for="from">Từ</label>
                    <input type="number" id="from" name="from" min="1" max="999" value="1" required>
                </div>
                <div class="field">
                    <label for="to">Đến</label>
                    <input type="number" id="to" name="to" min="1" max="999" value="10" required>
                </div>
                <div class="field">
                    <label for="bulk_seats">Số chỗ mỗi bàn</label>
                    <input type="number" id="bulk_seats" name="seats_max" min="1" max="50" value="4" required>
                </div>
                <div class="field">
                    <label for="bulk_area">Khu vực</label>
                    <select id="bulk_area" name="area_id">
                        <option value="">Chưa phân khu</option>
                        @foreach ($branch->areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field full">
                    <button class="btn btn-ghost btn-sm" type="submit">Tạo hàng loạt</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>Danh sách bàn</h2>
        <p class="sub">Bàn đã từng có khách sẽ được ẩn thay vì xóa, để giữ lịch sử đặt bàn.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Mã</th><th>Khu vực</th><th class="num">Sức chứa</th><th>Ghép bàn</th><th>Trạng thái</th><th>Ghi chú</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($branch->diningTables as $table)
                    <tr>
                        <td>
                            <b>{{ $table->code }}</b>
                            <details class="inline-edit">
                                <summary>Sửa</summary>
                                <div>
                                    <form method="post" action="{{ route('admin.tables.update', [$branch, $table]) }}" class="form-grid">
                                        @csrf @method('PUT')
                                        <div class="field">
                                            <label>Mã bàn</label>
                                            <input type="text" name="code" value="{{ $table->code }}" required>
                                        </div>
                                        <div class="field">
                                            <label>Khu vực</label>
                                            <select name="area_id">
                                                <option value="">Chưa phân khu</option>
                                                @foreach ($branch->areas as $area)
                                                    <option value="{{ $area->id }}" @selected($table->area_id === $area->id)>{{ $area->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>Tối thiểu</label>
                                            <input type="number" name="seats_min" value="{{ $table->seats_min }}" min="1" max="50" required>
                                        </div>
                                        <div class="field">
                                            <label>Tối đa</label>
                                            <input type="number" name="seats_max" value="{{ $table->seats_max }}" min="1" max="50" required>
                                        </div>
                                        <div class="field">
                                            <label>Thứ tự</label>
                                            <input type="number" name="sort_order" value="{{ $table->sort_order }}" min="0" max="999">
                                        </div>
                                        <div class="field full">
                                            <label>Ghi chú</label>
                                            <input type="text" name="note" value="{{ $table->note }}" maxlength="150">
                                        </div>
                                        <div class="field full">
                                            <label class="check"><input type="checkbox" name="combinable" value="1" @checked($table->combinable)> Cho phép ghép bàn</label>
                                            <label class="check"><input type="checkbox" name="is_active" value="1" @checked($table->is_active)> Đang sử dụng</label>
                                        </div>
                                        <div class="field full">
                                            <button class="btn btn-ghost btn-sm" type="submit">Lưu</button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        </td>
                        <td class="small muted">{{ $table->area?->name ?? 'Chưa phân khu' }}</td>
                        <td class="num">{{ $table->seats_min }}–{{ $table->seats_max }}</td>
                        <td class="small">{{ $table->combinable ? 'Có' : 'Không' }}</td>
                        <td>
                            <span class="pill {{ $table->is_active ? 'status-confirmed' : 'status-cancelled' }}">
                                {{ $table->is_active ? 'Đang dùng' : 'Đã ẩn' }}
                            </span>
                        </td>
                        <td class="small muted">{{ $table->note }}</td>
                        <td class="num">
                            <form method="post" action="{{ route('admin.tables.destroy', [$branch, $table]) }}"
                                  onsubmit="return confirm('Xóa bàn {{ $table->code }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" type="submit">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Chưa có bàn nào. Chi nhánh chưa khai báo bàn thì khách không đặt được.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
