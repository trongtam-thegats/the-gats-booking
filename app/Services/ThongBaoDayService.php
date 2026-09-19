<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bao cho nhan vien bang thong bao day tren dien thoai.
 *
 * Nguoi nhan: moi tai khoan dang hoat dong NHIN THAY dia diem do - quan tri
 * thay ca chuoi, nguoi cua quan nao chi nhan don quan minh. Ai khong bat thong
 * bao thi don gian la khong co dia chi day nao, khong phai loi.
 *
 * Nhu BookingNotifier, moi loi deu nuot lai: gui thong bao hong KHONG duoc lam
 * hong viec dat ban.
 */
class ThongBaoDayService
{
    public function __construct(protected WebPush $push) {}

    public function batDuoc(): bool
    {
        return filled(config('booking.push.public_key')) && filled(config('booking.push.private_key'));
    }

    /** Don moi tu trang khach hoac do nhan vien dat ho. */
    public function donMoi(Booking $booking, bool $nhanVienDat = false): void
    {
        $gio = substr((string) $booking->start_time, 0, 5);

        $this->guiToi($this->nguoiCuaDiaDiem($booking->branch), [
            'tieu_de' => $nhanVienDat ? 'Đơn đặt hộ khách' : 'Đơn đặt bàn mới',
            'noi_dung' => sprintf(
                '%s · %s khách · %s %s · %s',
                $booking->branch?->name,
                $booking->party_size,
                $booking->booking_date->format('d/m'),
                $gio,
                $booking->customer_name
            ),
            'duong_dan' => route('admin.bookings.show', $booking),
            'nhan' => 'don-'.$booking->code,
        ]);
    }

    /** Khach tu bam huy tren trang tra cuu. */
    public function khachHuy(Booking $booking): void
    {
        $this->guiToi($this->nguoiCuaDiaDiem($booking->branch), [
            'tieu_de' => 'Khách huỷ đơn',
            'noi_dung' => sprintf(
                '%s · %s %s · %s%s',
                $booking->branch?->name,
                $booking->booking_date->format('d/m'),
                substr((string) $booking->start_time, 0, 5),
                $booking->customer_name,
                $booking->sapo_code ? ' · cần huỷ bên Sapo' : ''
            ),
            'duong_dan' => route('admin.bookings.show', $booking),
            'nhan' => 'huy-'.$booking->code,
        ]);
    }

    /** Day sang Sapo that bai - can nguoi nhap tay ben do. */
    public function sapoHong(Booking $booking, string $loi): void
    {
        $this->guiToi($this->nguoiCuaDiaDiem($booking->branch), [
            'tieu_de' => 'Không đẩy được đơn sang Sapo',
            'noi_dung' => sprintf(
                '%s · %s %s · %s',
                $booking->branch?->name,
                $booking->booking_date->format('d/m'),
                substr((string) $booking->start_time, 0, 5),
                $loi
            ),
            'duong_dan' => route('admin.bookings.show', $booking),
            'nhan' => 'sapo-'.$booking->code,
        ]);
    }

    /**
     * Tai khoan duoc nhan tin cua mot dia diem.
     *
     * @return Collection<int, User>
     */
    public function nguoiCuaDiaDiem(?Branch $branch): Collection
    {
        if (! $branch) {
            return collect();
        }

        return User::where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->canAccessBranch($branch->id))
            ->values();
    }

    /**
     * Gui toi moi thiet bi cua nhung nguoi nay.
     *
     * @param  Collection<int, User>  $nguoi
     * @param  array{tieu_de: string, noi_dung: string, duong_dan?: string, nhan?: string}  $tin
     * @return int So thiet bi nhan duoc
     */
    public function guiToi(Collection $nguoi, array $tin): int
    {
        if (! $this->batDuoc() || $nguoi->isEmpty()) {
            return 0;
        }

        $dangKy = PushSubscription::whereIn('user_id', $nguoi->pluck('id'))->get();
        $noiDung = json_encode($tin, JSON_UNESCAPED_UNICODE);
        $xong = 0;

        foreach ($dangKy as $mot) {
            try {
                $ma = $this->push->gui([
                    'endpoint' => $mot->endpoint,
                    'p256dh' => $mot->p256dh,
                    'auth' => $mot->auth,
                ], $noiDung);
            } catch (Throwable $e) {
                Log::warning('Gửi thông báo đẩy hỏng', ['id' => $mot->id, 'loi' => $e->getMessage()]);

                continue;
            }

            // 404/410: nguoi dung go ung dung hoac xoa quyen - dia chi chet han.
            if (in_array($ma, WebPush::DA_CHET, true)) {
                $mot->delete();

                continue;
            }

            if ($ma >= 200 && $ma < 300) {
                $mot->forceFill(['lan_cuoi_ok' => now()])->saveQuietly();
                $xong++;

                continue;
            }

            Log::warning('Dịch vụ đẩy từ chối', ['id' => $mot->id, 'ma' => $ma]);
        }

        return $xong;
    }
}
