@extends('layouts.admin')

@section('title', 'Tài khoản')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tài khoản</h1>
            <p>Quản trị thấy toàn chuỗi. Quản lý và chỉ xem chỉ thấy quán được gắn.</p>
        </div>
    </div>

    <div class="card">
        <h2>Thêm tài khoản</h2>
        <form method="post" action="{{ route('admin.users.store') }}" class="form-grid">
            @csrf
            <div class="field">
                <label for="name">Họ tên</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="email">Email đăng nhập</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>
            <div class="field">
                <label for="role">Vai trò</label>
                <select id="role" name="role">
                    @foreach (\App\Support\Roles::ALL as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ \App\Support\Roles::label($role) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="brand_id">Quán phụ trách</label>
                <select id="brand_id" name="brand_id">
                    <option value="">Toàn chuỗi (chỉ dành cho quản trị)</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="phone">Điện thoại</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}">
            </div>
            <div class="field full">
                <button class="btn" type="submit">Tạo tài khoản</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Danh sách</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Người dùng</th><th>Vai trò</th><th>Quán</th><th>Trạng thái</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($users as $item)
                    <tr>
                        <td>
                            <b>{{ $item->name }}</b><br>
                            <span class="muted small">{{ $item->email }}</span>
                            <details class="inline-edit">
                                <summary>Sửa</summary>
                                <div>
                                    <form method="post" action="{{ route('admin.users.update', $item) }}" class="form-grid">
                                        @csrf @method('PUT')
                                        <div class="field">
                                            <label>Họ tên</label>
                                            <input type="text" name="name" value="{{ $item->name }}" required>
                                        </div>
                                        <div class="field">
                                            <label>Email</label>
                                            <input type="email" name="email" value="{{ $item->email }}" required>
                                        </div>
                                        <div class="field">
                                            <label>Mật khẩu mới <span class="muted">(bỏ trống nếu giữ nguyên)</span></label>
                                            <input type="password" name="password" minlength="8">
                                        </div>
                                        <div class="field">
                                            <label>Vai trò</label>
                                            <select name="role" @disabled($item->id === auth()->id())>
                                                @foreach (\App\Support\Roles::ALL as $role)
                                                    <option value="{{ $role }}" @selected($item->role === $role)>{{ \App\Support\Roles::label($role) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>Quán phụ trách</label>
                                            <select name="brand_id">
                                                <option value="">Toàn chuỗi</option>
                                                @foreach ($brands as $brand)
                                                    <option value="{{ $brand->id }}" @selected($item->brand_id === $brand->id)>{{ $brand->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>Điện thoại</label>
                                            <input type="tel" name="phone" value="{{ $item->phone }}">
                                        </div>
                                        <div class="field full">
                                            <label class="check">
                                                <input type="checkbox" name="is_active" value="1" @checked($item->is_active)
                                                       @disabled($item->id === auth()->id())>
                                                Tài khoản đang hoạt động
                                            </label>
                                        </div>
                                        <div class="field full">
                                            <button class="btn btn-ghost btn-sm" type="submit">Lưu</button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        </td>
                        <td class="small">{{ $item->roleLabel() }}</td>
                        <td class="small muted">{{ $item->brand?->name ?? 'Toàn chuỗi' }}</td>
                        <td>
                            <span class="pill {{ $item->is_active ? 'status-confirmed' : 'status-cancelled' }}">
                                {{ $item->is_active ? 'Hoạt động' : 'Đã khóa' }}
                            </span>
                        </td>
                        <td class="num">
                            @if ($item->id !== auth()->id() && $item->is_active)
                                <form method="post" action="{{ route('admin.users.destroy', $item) }}"
                                      onsubmit="return confirm('Khóa tài khoản {{ $item->email }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Khóa</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
