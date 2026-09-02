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
            <a class="nav-link {{ request()->routeIs('admin.bookings.deletions') ? 'active' : '' }}"
               href="{{ route('admin.bookings.deletions') }}">Nhật ký xóa</a>
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

{{-- Khoa nut sau lan bam dau, cho moi form gui du lieu trong khu quan ly.

     Sau su co don trung 2/9: mot cu bam doi cua le tan tren form "Dat ban ho
     khach" van sinh ra hai don, vi lop chan don trung co y mien tru nhan vien.
     Dat o day thay vi tung trang de ca lop loi nay bi bit mot the.

     Phai nam SAU @stack('scripts'): cac trang tu dat bo bat su kien submit cua
     rieng minh (vi du o xac nhan xoa dat ban), chung phai duoc dang ky truoc de
     con chan lai kip. --}}
<script>
(function () {
    var forms = document.querySelectorAll('form[method="post" i]');

    function nutCua(form) {
        return form.querySelectorAll('button[type="submit"], button:not([type])');
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            // Hop thoai xac nhan bi bam Huy, hoac trang tu chan lai bang
            // preventDefault: khong khoa gi ca, neu khong nut se ket cung du
            // form chua he duoc gui di.
            if (event.defaultPrevented) {
                return;
            }

            if (form.dataset.dangGui === '1') {
                event.preventDefault();
                return;
            }

            form.dataset.dangGui = '1';

            // Tat nut sau mot nhip, de trinh duyet kip gui kem name/value cua
            // chinh nut vua bam.
            window.setTimeout(function () {
                nutCua(form).forEach(function (nut) { nut.disabled = true; });
            }, 0);
        });
    });

    // Bam Quay lai cua trinh duyet: trang duoc khoi phuc nguyen trang thai dang
    // khoa. Mo lai de con thao tac tiep.
    window.addEventListener('pageshow', function (event) {
        if (! event.persisted) {
            return;
        }

        forms.forEach(function (form) {
            form.dataset.dangGui = '0';
            nutCua(form).forEach(function (nut) { nut.disabled = false; });
        });
    });
})();
</script>
</body>
</html>
