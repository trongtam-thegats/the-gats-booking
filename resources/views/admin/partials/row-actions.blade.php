{{-- Thao tac nhanh ngay tren dong, khong phai mo trang chi tiet. --}}
@php($canWrite = auth()->user()->canWrite())

<div class="row-actions">
    @if ($canWrite)
        @if ($booking->status === \App\Models\Booking::STATUS_PENDING)
            <form method="post" action="{{ route('admin.bookings.confirm', $booking) }}">
                @csrf
                <button class="btn btn-ok btn-sm" type="submit">Xác nhận</button>
            </form>
        @endif

        @if (in_array($booking->status, [\App\Models\Booking::STATUS_PENDING, \App\Models\Booking::STATUS_CONFIRMED], true))
            <form method="post" action="{{ route('admin.bookings.transition', [$booking, 'seated']) }}">
                @csrf
                <button class="btn btn-ghost btn-sm" type="submit">Đã đến</button>
            </form>
            <form method="post" action="{{ route('admin.bookings.transition', [$booking, 'no-show']) }}"
                  onsubmit="return confirm('Đánh dấu {{ $booking->code }} là khách không đến và nhả bàn?')">
                @csrf
                <button class="btn btn-danger btn-sm" type="submit">Không đến</button>
            </form>
        @endif

        @if ($booking->status === \App\Models\Booking::STATUS_SEATED)
            <form method="post" action="{{ route('admin.bookings.transition', [$booking, 'completed']) }}">
                @csrf
                <button class="btn btn-ok btn-sm" type="submit">Hoàn tất</button>
            </form>
        @endif
    @endif

    <a class="btn btn-ghost btn-sm" href="{{ route('admin.bookings.show', $booking) }}">Chi tiết</a>
</div>
