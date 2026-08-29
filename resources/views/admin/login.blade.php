<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập · The Gats Booking</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
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

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('admin.login.submit') }}" class="form-grid">
        @csrf
        <div class="field full">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="field full">
            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="field full">
            <label class="check">
                <input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập
            </label>
        </div>
        <div class="field full">
            <button class="btn" type="submit" style="width:100%; justify-content:center">Đăng nhập</button>
        </div>
    </form>

    <p class="hint" style="text-align:center; margin-bottom:0">
        <a href="{{ route('home') }}">Về trang đặt bàn của khách</a>
    </p>
</div>
</body>
</html>
