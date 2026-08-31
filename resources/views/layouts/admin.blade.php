<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản lý') · The Gats Booking</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php($user = auth()->user())
<div class="shell">
    <aside class="side">
        <a href="{{ route('admin.dashboard') }}" class="side-brand">
            <span class="side-mark">TG</span>
            <span>
                <b>The Gats</b>
                <span>Đặt bàn</span>
            </span>
        </a>

        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
           href="{{ route('admin.dashboard') }}">Tổng quan hôm nay</a>
        <a class="nav-link {{ request()->routeIs('admin.floor') ? 'active' : '' }}"
           href="{{ route('admin.floor') }}">Sơ đồ bàn</a>
        <a class="nav-link {{ request()->routeIs('admin.bookings.index') ? 'active' : '' }}"
           href="{{ route('admin.bookings.index') }}">Danh sách đặt bàn</a>
        <a class="nav-link {{ request()->routeIs('admin.guests.*') ? 'active' : '' }}"
           href="{{ route('admin.guests.index') }}">Tra cứu khách</a>
        @if ($user->canSeeAnalytics())
            <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
               href="{{ route('admin.reports.index') }}">Báo cáo</a>
            <a class="nav-link {{ request()->routeIs('admin.bookings.create') ? 'active' : '' }}"
               href="{{ route('admin.bookings.create') }}">Đặt bàn hộ khách</a>

            <div class="nav-group">Khách hàng</div>
            <a class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"
               href="{{ route('admin.customers.index') }}">Phân tích khách hàng</a>
            <a class="nav-link {{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}"
               href="{{ route('admin.invoices.index') }}">Hóa đơn</a>
        @endif

        @if ($user->canManageSetup())
            <div class="nav-group">Cấu hình</div>
            <a class="nav-link {{ request()->routeIs('admin.branches.*') ? 'active' : '' }}"
               href="{{ route('admin.branches.index') }}">Địa điểm &amp; giờ mở</a>
            <a class="nav-link {{ request()->routeIs('admin.tables.index') || request()->routeIs('admin.areas.*') ? 'active' : '' }}"
               href="{{ route('admin.tables.index') }}">Khu vực &amp; bàn</a>
            <a class="nav-link {{ request()->routeIs('admin.content.*') ? 'active' : '' }}"
               href="{{ route('admin.content.index') }}">Nội dung trang khách</a>
        @endif

        @if ($user->isAdmin())
            <a class="nav-link {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}"
               href="{{ route('admin.brands.index') }}">Quán &amp; tên miền</a>
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
               href="{{ route('admin.users.index') }}">Tài khoản</a>
            <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
               href="{{ route('admin.settings.index') }}">Cài đặt gửi tin</a>
        @endif

        <div class="side-foot">
            <div class="side-user">
                <b>{{ $user->name }}</b>
                <span>{{ $user->roleLabel() }}@if ($user->branch) · {{ $user->branch->name }} @endif</span>
            </div>
            <a class="nav-link {{ request()->routeIs('admin.password.*') ? 'active' : '' }}"
               href="{{ route('admin.password.edit') }}">Đổi mật khẩu</a>
            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button class="btn btn-ghost btn-sm" type="submit">Đăng xuất</button>
            </form>
        </div>
    </aside>

    <main class="main">
        @if (session('status'))
            <div class="alert alert-ok">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
