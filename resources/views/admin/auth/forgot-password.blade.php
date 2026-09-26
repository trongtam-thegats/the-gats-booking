<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quên mật khẩu · The Gats Booking</title>
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

    <h2 style="margin: 0 0 8px; font-size: 1.15rem;">Quên mật khẩu</h2>
    <p class="muted small" style="margin-bottom: 20px;">
        Nhập địa chỉ email tài khoản của bạn. Hệ thống sẽ gửi một liên kết bảo mật để thiết lập mật khẩu mới.
    </p>

    @if (session('status'))
        <div class="alert alert-success" style="margin-bottom: 16px;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom: 16px;">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('admin.password.email') }}" class="form-grid">
        @csrf
        <div class="field full">
            <label for="email">Email tài khoản</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@thegats.vn">
        </div>
        <div class="field full">
            <button class="btn" type="submit" style="width:100%; justify-content:center">Gửi liên kết đặt lại mật khẩu</button>
        </div>
    </form>

    <p class="hint" style="text-align:center; margin-bottom:0; margin-top: 16px;">
        <a href="{{ route('admin.login') }}">Quay lại trang đăng nhập</a>
    </p>
</div>
</body>
</html>
