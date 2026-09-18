<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Day don dat ban da xac nhan sang trang dat lich cua Sapo FnB.
 *
 * Trang dat lich cua Sapo (sapofnb.vn/booking/form?id=<cua hang>) goi
 * POST /api/booking/submit, khong can dang nhap - may chu goi thang duoc,
 * khong phu thuoc vao trinh duyet nao dang mo.
 *
 * Sapo CHI mo duong tao don. Khong co duong huy hay sua, nen:
 *   - don doi gio  -> day don moi, don cu danh dau "can xu ly tay"
 *   - don bi huy   -> danh dau "can xu ly tay"
 * Nhan vien thay canh bao ngay tren trang don trong khu quan tri.
 *
 * Moi loi deu nuot lai va ghi vao cot sapo_error: day sang Sapo hong thi
 * KHONG duoc lam hong viec dat ban cua khach. Lenh sapo:day-dat-lich chay
 * lai nhung don con sot.
 */
class SapoDatLichService
{
    public function batDuoc(): bool
    {
        return (bool) config('booking.sapo_dat_lich.bat')
            && filled(config('booking.sapo_dat_lich.url'))
            && filled(config('booking.sapo_dat_lich.merchant_id'));
    }

    /** Moc bat tinh nang: don xac nhan truoc moc nay thi khong day nua. */
    public function tuLuc(): ?Carbon
    {
        $moc = (string) config('booking.sapo_dat_lich.tu_luc');

        return $moc === '' ? null : Carbon::parse($moc);
    }

    /** Cua hang Sapo cua dia diem nay, lay nguoc tu SAPO_STORES. */
    public function storeId(Booking $booking): ?int
    {
        $slug = $booking->branch?->slug;

        if (! $slug) {
            return null;
        }

        foreach ((array) config('booking.sapo_stores') as $storeId => $cua) {
            if ($cua === $slug) {
                return (int) $storeId;
            }
        }

        return null;
    }

    /**
     * Don co du dieu kien day sang Sapo khong.
     * Tra ve null neu du dieu kien, nguoc lai la ly do (de ghi lai cho ro).
     */
    public function vuongMac(Booking $booking): ?string
    {
        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            return 'Đơn chưa xác nhận';
        }

        if ($booking->sapo_code) {
            return 'Đã đẩy rồi';
        }

        if (! $this->storeId($booking)) {
            return 'Quán này chưa khai báo cửa hàng Sapo';
        }

        $tuLuc = $this->tuLuc();

        if ($tuLuc && $booking->confirmed_at && $booking->confirmed_at->lt($tuLuc)) {
            return 'Đơn xác nhận trước khi bật đẩy sang Sapo';
        }

        $gio = $booking->startsAt();

        if ($gio->isPast()) {
            return 'Giờ khách đến đã qua';
        }

        // Sapo tu choi don dat sat gio va don qua xa. Hai muc nay quan tu doi
        // duoc trong Sapo (da doi tu 2 tieng xuong 30 phut ngay 18/09), nen doc
        // thang tu Sapo chu khong chep lai vao .env cho lech nhau.
        $quy = $this->quyDinh((int) $this->storeId($booking));

        // Carbon 3 tra so THUC va co dau cho diffInMinutes - so sanh thang la
        // sai dau. Lay hieu timestamp cho chac.
        $conLai = (int) floor(($gio->getTimestamp() - now()->getTimestamp()) / 60);

        if ($quy['som_nhat_phut'] > 0 && $conLai < $quy['som_nhat_phut']) {
            return 'Sapo chỉ nhận đơn đặt trước ít nhất '.$this->doDai($quy['som_nhat_phut']);
        }

        if ($quy['xa_nhat_ngay'] > 0 && $conLai > $quy['xa_nhat_ngay'] * 24 * 60) {
            return 'Sapo chỉ nhận đơn trong vòng '.$quy['xa_nhat_ngay'].' ngày';
        }

        return null;
    }

    /**
     * Quy dinh nhan don cua mot cua hang, doc tu chinh Sapo (nho 1 tieng).
     *
     * Goi hong thi dung muc trong .env - khong chan viec day don chi vi khong
     * hoi duoc cau hinh.
     *
     * @return array{som_nhat_phut: int, xa_nhat_ngay: int}
     */
    public function quyDinh(int $storeId): array
    {
        $macDinh = [
            'som_nhat_phut' => (int) config('booking.sapo_dat_lich.som_nhat_phut'),
            'xa_nhat_ngay' => (int) config('booking.sapo_dat_lich.xa_nhat_ngay'),
        ];

        return Cache::remember('sapo.quy-dinh.'.$storeId, now()->addHour(), function () use ($storeId, $macDinh) {
            try {
                $tra = Http::timeout((int) config('booking.sapo_dat_lich.timeout'))
                    ->acceptJson()
                    ->get(rtrim((string) config('booking.sapo_dat_lich.url'), '/').'/api/booking/merchant-info', [
                        'merchantId' => (int) config('booking.sapo_dat_lich.merchant_id'),
                    ]);

                foreach ((array) $tra->json('stores', []) as $cua) {
                    if ((int) ($cua['id'] ?? 0) !== $storeId) {
                        continue;
                    }

                    foreach ((array) ($cua['storeSettings'] ?? []) as $cai) {
                        if (($cai['settingKey'] ?? '') !== 'features_booking') {
                            continue;
                        }

                        $gt = json_decode((string) ($cai['settingValue'] ?? ''), true);
                        $nhan = $gt['reception_time'] ?? [];
                        $som = $nhan['min_booking_time'] ?? [];

                        return [
                            'som_nhat_phut' => match ($som['unit'] ?? '') {
                                'hour' => (int) ($som['time'] ?? 0) * 60,
                                'minute' => (int) ($som['time'] ?? 0),
                                default => $macDinh['som_nhat_phut'],
                            },
                            'xa_nhat_ngay' => (int) ($nhan['max_booking_date'] ?? $macDinh['xa_nhat_ngay']),
                        ];
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Không đọc được quy định đặt lịch của Sapo', ['loi' => $e->getMessage()]);
            }

            return $macDinh;
        });
    }

    /** 30 -> "30 phút", 120 -> "2 tiếng". */
    protected function doDai(int $phut): string
    {
        return $phut % 60 === 0 ? intdiv($phut, 60).' tiếng' : $phut.' phút';
    }

    /**
     * Day mot don sang Sapo. Tra ve true khi Sapo nhan.
     *
     * Khong bao gio nem ngoai le - nguoi goi la luong dat ban cua khach.
     */
    public function day(Booking $booking): bool
    {
        if (! $this->batDuoc()) {
            return false;
        }

        $vuong = $this->vuongMac($booking);

        if ($vuong !== null) {
            // "Da day roi" khong phai loi, khong ghi de len dau vet cu.
            if ($booking->sapo_code === null) {
                $booking->forceFill(['sapo_error' => $vuong])->saveQuietly();
            }

            return false;
        }

        try {
            $tra = Http::timeout((int) config('booking.sapo_dat_lich.timeout'))
                ->acceptJson()
                ->post(rtrim((string) config('booking.sapo_dat_lich.url'), '/').'/api/booking/submit', $this->goiTin($booking));

            if ($tra->failed()) {
                return $this->ghiLoi($booking, 'Sapo trả HTTP '.$tra->status().': '.mb_substr($tra->body(), 0, 120));
            }

            $ma = (string) ($tra->json('tableBooking.code') ?? '');

            if ($ma === '') {
                return $this->ghiLoi($booking, 'Sapo không trả mã đơn: '.mb_substr($tra->body(), 0, 120));
            }

            $booking->forceFill([
                'sapo_code' => mb_substr($ma, 0, 40),
                'sapo_pushed_at' => now(),
                'sapo_error' => null,
            ])->saveQuietly();

            return true;
        } catch (Throwable $e) {
            return $this->ghiLoi($booking, mb_substr($e->getMessage(), 0, 200));
        }
    }

    /**
     * Don da nam ben Sapo ma nay huy hoac doi gio: bat co de nhan vien vao
     * Sapo sua tay. Don chua tung day sang Sapo thi khong phai lam gi.
     */
    public function canXuLyTay(Booking $booking, string $viec): void
    {
        if (! $booking->sapo_code) {
            return;
        }

        $booking->forceFill(['sapo_can_xu_ly' => mb_substr($viec, 0, 255)])->saveQuietly();
    }

    /** Nhan vien bao da xu ly xong ben Sapo. */
    public function daXuLy(Booking $booking): void
    {
        $booking->forceFill(['sapo_can_xu_ly' => null])->saveQuietly();
    }

    /**
     * Don doi gio: don cu ben Sapo phai sua tay, con don moi thi day lai tu dau.
     */
    public function doiLich(Booking $booking): void
    {
        if ($booking->sapo_code) {
            $this->canXuLyTay(
                $booking,
                'Đơn đã đổi lịch sau khi đẩy sang Sapo (mã Sapo '.$booking->sapo_code.'). Vào Sapo huỷ đơn cũ.'
            );

            $booking->forceFill(['sapo_code' => null, 'sapo_pushed_at' => null])->saveQuietly();
        }

        $this->day($booking->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function goiTin(Booking $booking): array
    {
        return [
            'receptionTime' => $booking->startsAt()->getTimestamp(),
            'clientTime' => now()->getTimestamp(),
            'customerCount' => (int) $booking->party_size,
            'customerPhone' => (string) $booking->customer_phone,
            'customerName' => (string) $booking->customer_name,
            'customerEmail' => $booking->customer_email ?: null,
            'note' => $this->ghiChu($booking),
            'items' => [],
            'combos' => [],
            'storeId' => $this->storeId($booking),
            'merchantId' => (int) config('booking.sapo_dat_lich.merchant_id'),
            'type' => 'website',
        ];
    }

    /** Ghi chu gui kem, de nhan vien nhin ben Sapo la biet don nay tu dau ra. */
    protected function ghiChu(Booking $booking): string
    {
        $phan = ['[booking.thegats.vn '.$booking->code.']'];

        if ($booking->area?->name) {
            $phan[] = 'Khu: '.$booking->area->name;
        }

        if ($booking->diningTables->isNotEmpty()) {
            $phan[] = 'Bàn: '.$booking->diningTables->pluck('name')->implode(', ');
        }

        if (filled($booking->note)) {
            $phan[] = trim((string) $booking->note);
        }

        return mb_substr(implode(' · ', $phan), 0, 500);
    }

    protected function ghiLoi(Booking $booking, string $loi): bool
    {
        $booking->forceFill(['sapo_error' => mb_substr($loi, 0, 255)])->saveQuietly();

        Log::warning('Đẩy đơn sang Sapo không thành công', ['booking' => $booking->code, 'loi' => $loi]);

        return false;
    }
}
