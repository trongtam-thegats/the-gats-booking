@extends('layouts.admin')

@section('title', 'Thương hiệu')

@section('content')
    <div class="page-head">
        <div>
            <h1>Thương hiệu</h1>
            <p>Mỗi thương hiệu có trang đặt bàn, đường dẫn và màu nhận diện riêng. Chi nhánh nằm dưới thương hiệu.</p>
        </div>
    </div>

    @if ($orphanBranches->isNotEmpty())
        <div class="alert alert-info">
            Có {{ $orphanBranches->count() }} chi nhánh chưa gán thương hiệu nên khách không tìm thấy trên trang đặt bàn:
            @foreach ($orphanBranches as $branch)
                <a href="{{ route('admin.branches.edit', $branch) }}">{{ $branch->name }}</a>@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif

    <div class="card">
        <h2>Thêm thương hiệu</h2>
        <form method="post" action="{{ route('admin.brands.store') }}" class="form-grid" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="name">Tên thương hiệu</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label for="domain">Tên miền đặt bàn</label>
                <input type="text" id="domain" name="domain" value="{{ old('domain') }}"
                       placeholder="booking.tenquan.com">
                <span class="hint">Khách vào tên miền này chỉ thấy quán này, không vào được khu quản lý.</span>
            </div>
            <div class="field">
                <label for="slug">Mã nội bộ</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="tu-dong-tao-tu-ten">
                <span class="hint">Dùng để chạy thử khi chưa trỏ tên miền.</span>
            </div>
            <div class="field">
                <label for="mark">Ký hiệu</label>
                <input type="text" id="mark" name="mark" value="{{ old('mark', 'TG') }}" maxlength="3" required>
                <span class="hint">2–3 ký tự hiện trong huy hiệu tròn.</span>
            </div>
            <div class="field">
                <label for="accent_color">Màu nhận diện</label>
                <input type="text" id="accent_color" name="accent_color" value="{{ old('accent_color', '#c8a15a') }}"
                       placeholder="#c8a15a" required>
            </div>
            <div class="field">
                <label for="ground_color">Màu nền</label>
                <input type="text" id="ground_color" name="ground_color" value="{{ old('ground_color') }}"
                       placeholder="#0e0d0c">
                <span class="hint">Bỏ trống thì dùng nền tối mặc định.</span>
            </div>
            <div class="field">
                <label for="logo">Logo</label>
                <input type="file" id="logo" name="logo" accept="image/*">
                <span class="hint">Nên dùng bản chữ trắng, nền trong suốt. Tối đa 1MB.</span>
            </div>
            <div class="field">
                <label for="phone">Hotline</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}">
            </div>
            <div class="field">
                <label for="mail_from_address">Địa chỉ gửi thư</label>
                <input type="email" id="mail_from_address" name="mail_from_address"
                       value="{{ old('mail_from_address') }}" placeholder="datban@tenmienquan.vn">
                <span class="hint">Để trống thì dùng địa chỉ chung ở Cài đặt gửi tin.</span>
            </div>
            <div class="field">
                <label for="mail_from_name">Tên người gửi</label>
                <input type="text" id="mail_from_name" name="mail_from_name"
                       value="{{ old('mail_from_name') }}" maxlength="120">
            </div>
            <div class="field">
                <label for="sort_order">Thứ tự</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="999">
            </div>
            <div class="field full">
                <label for="tagline">Câu dẫn</label>
                <input type="text" id="tagline" name="tagline" value="{{ old('tagline') }}" maxlength="160">
            </div>
            <div class="field full">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" maxlength="1000">{{ old('description') }}</textarea>
            </div>
            <div class="field full">
                <label class="check"><input type="checkbox" name="is_active" value="1" checked> Đang nhận đặt bàn</label>
            </div>
            <div class="field full">
                <button class="btn" type="submit">Tạo thương hiệu</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Danh sách</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Thương hiệu</th>
                    <th>Tên miền</th>
                    <th>Màu</th>
                    <th class="num">Chi nhánh</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($brands as $item)
                    <tr>
                        <td>
                            <b>{{ $item->name }}</b>
                            @if ($item->tagline)<br><span class="muted small">{{ $item->tagline }}</span>@endif
                            <details class="inline-edit">
                                <summary>Sửa</summary>
                                <div>
                                    <form method="post" action="{{ route('admin.brands.update', $item) }}"
                                          class="form-grid" enctype="multipart/form-data">
                                        @csrf @method('PUT')
                                        <div class="field">
                                            <label>Tên</label>
                                            <input type="text" name="name" value="{{ $item->name }}" required>
                                        </div>
                                        <div class="field">
                                            <label>Tên miền đặt bàn</label>
                                            <input type="text" name="domain" value="{{ $item->domain }}"
                                                   placeholder="booking.tenquan.com">
                                        </div>
                                        <div class="field">
                                            <label>Mã nội bộ</label>
                                            <input type="text" name="slug" value="{{ $item->slug }}">
                                        </div>
                                        <div class="field">
                                            <label>Ký hiệu</label>
                                            <input type="text" name="mark" value="{{ $item->mark }}" maxlength="3" required>
                                        </div>
                                        <div class="field">
                                            <label>Màu nhận diện</label>
                                            <input type="text" name="accent_color" value="{{ $item->accent_color }}" required>
                                        </div>
                                        <div class="field">
                                            <label>Màu nền</label>
                                            <input type="text" name="ground_color" value="{{ $item->ground_color }}"
                                                   placeholder="#0e0d0c">
                                        </div>
                                        <div class="field">
                                            <label>Font tiêu đề</label>
                                            @if ($item->display_font_path)
                                                <span class="hint">Đang dùng: {{ basename($item->display_font_path) }}</span>
                                            @endif
                                            <input type="file" name="display_font" accept=".ttf,.otf,.woff,.woff2">
                                        </div>
                                        <div class="field">
                                            <label>Font nội dung</label>
                                            @if ($item->body_font_path)
                                                <span class="hint">Đang dùng: {{ basename($item->body_font_path) }}</span>
                                            @endif
                                            <input type="file" name="body_font" accept=".ttf,.otf,.woff,.woff2">
                                            <span class="hint">Nhớ chọn font có đủ dấu tiếng Việt.</span>
                                        </div>
                                        <div class="field full">
                                            <label>Logo</label>
                                            @if ($item->hasLogo())
                                                <div style="background: {{ $item->ground() }}; padding:12px; border-radius:8px; margin-bottom:8px">
                                                    <img src="{{ asset($item->logo_path) }}" alt="{{ $item->name }}"
                                                         style="height:28px; width:auto; display:block">
                                                </div>
                                                <label class="check">
                                                    <input type="checkbox" name="remove_logo" value="1"> Gỡ logo hiện tại
                                                </label>
                                            @endif
                                            <input type="file" name="logo" accept="image/*">
                                        </div>
                                        <div class="field">
                                            <label>Hotline</label>
                                            <input type="tel" name="phone" value="{{ $item->phone }}">
                                        </div>
                                        <div class="field">
                                            <label>Địa chỉ gửi thư</label>
                                            <input type="email" name="mail_from_address"
                                                   value="{{ $item->mail_from_address }}"
                                                   placeholder="datban@tenmienquan.vn">
                                            <span class="hint">
                                                Phải là hộp thư đang đăng nhập SMTP, hoặc bí danh đã xác minh
                                                của hộp thư đó. Để trống thì dùng địa chỉ chung.
                                            </span>
                                        </div>
                                        <div class="field">
                                            <label>Tên người gửi</label>
                                            <input type="text" name="mail_from_name"
                                                   value="{{ $item->mail_from_name }}" maxlength="120"
                                                   placeholder="{{ $item->name }}">
                                        </div>
                                        <div class="field">
                                            <label>Thứ tự</label>
                                            <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" max="999">
                                        </div>
                                        @foreach (\App\Models\Brand::SOCIAL_LINKS as $column => $label)
                                            <div class="field">
                                                <label>{{ $label }}</label>
                                                <input type="text" name="{{ $column }}" value="{{ $item->{$column} }}"
                                                       placeholder="https://…">
                                            </div>
                                        @endforeach
                                        <div class="field full">
                                            <label>Câu dẫn</label>
                                            <input type="text" name="tagline" value="{{ $item->tagline }}" maxlength="160">
                                        </div>
                                        <div class="field full">
                                            <label>Mô tả</label>
                                            <textarea name="description" maxlength="1000">{{ $item->description }}</textarea>
                                        </div>
                                        <div class="field full">
                                            <label class="check">
                                                <input type="checkbox" name="is_active" value="1" @checked($item->is_active)>
                                                Đang nhận đặt bàn
                                            </label>
                                        </div>
                                        <div class="field full">
                                            <button class="btn btn-ghost btn-sm" type="submit">Lưu</button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        </td>
                        <td class="small">
                            @if ($item->domain)
                                <a href="https://{{ $item->domain }}" target="_blank" rel="noopener">{{ $item->domain }}</a>
                            @else
                                <span class="muted">Chưa trỏ tên miền</span>
                            @endif
                        </td>
                        <td>
                            <span class="pill" style="background: {{ $item->accent_color }}22; color: {{ $item->accentSoft() }}">
                                {{ $item->accent_color }}
                            </span>
                        </td>
                        <td class="num">{{ $item->branches_count }}</td>
                        <td>
                            <span class="pill {{ $item->is_active ? 'status-confirmed' : 'status-cancelled' }}">
                                {{ $item->is_active ? 'Đang nhận' : 'Tạm ngưng' }}
                            </span>
                        </td>
                        <td class="num">
                            @if ($item->branches_count === 0)
                                <form method="post" action="{{ route('admin.brands.destroy', $item) }}"
                                      onsubmit="return confirm('Xóa thương hiệu {{ $item->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Xóa</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Chưa có thương hiệu nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
