@extends('layouts.admin')

@section('title', 'Đổi mật khẩu')

@section('content')
    <div class="page-head">
        <div>
            <h1>Đổi mật khẩu</h1>
            @if ($batBuoc)
                <p>Tài khoản đang dùng mật khẩu khởi tạo (chính là email). Đặt mật khẩu riêng để tiếp tục.</p>
            @else
                <p>Đặt mật khẩu mới cho tài khoản {{ auth()->user()->email }}.</p>
            @endif
        </div>
    </div>

    @if ($batBuoc)
        <div class="alert alert-info">
            Mật khẩu khởi tạo là email nên người tạo tài khoản cũng biết. Đổi xong mới vào được các trang khác.
        </div>
    @endif

    <div class="card">
        <form method="post" action="{{ route('admin.password.update') }}" class="form-grid">
            @csrf
            <div class="field">
                <label for="current_password">Mật khẩu hiện tại</label>
                <input type="password" id="current_password" name="current_password"
                       autocomplete="current-password" required autofocus>
                @if ($batBuoc)
                    <span class="hint">Là email của bạn: {{ auth()->user()->email }}</span>
                @endif
            </div>
            <div class="field">
                <label for="password">Mật khẩu mới</label>
                <input type="password" id="password" name="password" minlength="8"
                       autocomplete="new-password" required>
                <span class="hint">Ít nhất 8 ký tự, không được trùng với email.</span>
            </div>
            <div class="field">
                <label for="password_confirmation">Nhập lại mật khẩu mới</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       minlength="8" autocomplete="new-password" required>
            </div>
            <div class="field full">
                <button class="btn" type="submit">Lưu mật khẩu mới</button>
            </div>
        </form>
    </div>
@endsection
