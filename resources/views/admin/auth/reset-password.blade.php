<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu · The Gats Booking</title>
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/admin.css') }}">
</head>
<body class="login-page">
<div class="card login-card">
    <div class="side-brand">
        <span class="side-mark">TG</span>
        <span>
            <b>The Gats</b>
            <span>Quản lý đặt bàn</span>
        </span>
    </div>

    <h2 style="margin: 0 0 8px; font-size: 1.15rem;">Đặt lại mật khẩu</h2>
    <p class="muted small" style="margin-bottom: 20px;">
        Vui lòng nhập mật khẩu mới cho tài khoản của bạn (tối thiểu 8 ký tự).
    </p>

    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom: 16px;">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('admin.password.reset.submit') }}" class="form-grid">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field full">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required readonly style="background: var(--surface-2, rgba(255,255,255,0.05)); color: var(--muted, #9c968c);">
        </div>

        <div class="field full">
            <label for="password">Mật khẩu mới</label>
            <input type="password" id="password" name="password" required autofocus minlength="8" placeholder="Tối thiểu 8 ký tự">
        </div>

        <div class="field full">
            <label for="password_confirmation">Xác nhận mật khẩu mới</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" placeholder="Nhập lại mật khẩu mới">
        </div>

        <div class="field full">
            <button class="btn" type="submit" style="width:100%; justify-content:center">Lưu mật khẩu mới</button>
        </div>
    </form>

    <p class="hint" style="text-align:center; margin-bottom:0; margin-top: 16px;">
        <a href="{{ route('admin.login') }}">Quay lại trang đăng nhập</a>
    </p>
</div>
</body>
</html>
