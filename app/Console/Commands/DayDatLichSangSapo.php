<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\SapoDatLichService;
use Illuminate\Console\Command;

/**
 * Day lai nhung don da xac nhan ma chua sang duoc Sapo.
 *
 * Luc xac nhan he thong da thu mot lan roi; lenh nay lo cac truong hop mang
 * chap, Sapo bao loi, hoac don xac nhan qua som (Sapo chi nhan don dat truoc
 * it nhat 2 tieng nen don cua ngay mai phai doi den gan gio moi day duoc).
 * Chay moi 5 phut trong /etc/cron.d/thegats-booking.
 */
class DayDatLichSangSapo extends Command
{
    protected $signature = 'sapo:day-dat-lich
        {--gioi-han=50 : So don toi da moi lan chay}
        {--xem : Chi liet ke nhung don se day, khong gui gi sang Sapo}';

    protected $description = 'Day cac don dat ban da xac nhan sang trang dat lich Sapo';

    public function handle(SapoDatLichService $sapo): int
    {
        if (! $sapo->batDuoc()) {
            $this->comment('Chua bat SAPO_DAT_LICH, khong lam gi.');

            return self::SUCCESS;
        }

        $don = Booking::query()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('sapo_code')
            // Chi don tu HOM NAY tro di - don cua dem truoc khong con y nghia
            // ben Sapo nua (nguoi dung chot 18/09/2026).
            ->where('booking_date', '>=', now()->toDateString())
            ->when($sapo->tuLuc(), fn ($q, $moc) => $q->where(
                fn ($c) => $c->whereNull('confirmed_at')->orWhere('confirmed_at', '>=', $moc)
            ))
            ->with(['branch', 'diningTables', 'area'])
            ->orderBy('booking_date')
            ->limit((int) $this->option('gioi-han'))
            ->get();

        if ($this->option('xem')) {
            $this->info($don->count().' đơn đang chờ đẩy sang Sapo:');

            foreach ($don as $mot) {
                $vuong = $sapo->vuongMac($mot);

                $this->line(sprintf(
                    '%-10s %-18s %s %s  %s',
                    $mot->code,
                    $mot->branch?->name,
                    $mot->booking_date->format('d/m'),
                    substr((string) $mot->start_time, 0, 5),
                    $vuong === null ? 'sẵn sàng' : 'vướng: '.$vuong
                ));
            }

            return self::SUCCESS;
        }

        $xong = 0;

        foreach ($don as $mot) {
            if ($sapo->day($mot)) {
                $xong++;
                $this->line($mot->code.' -> Sapo '.$mot->sapo_code);
            }
        }

        $this->info('Da day '.$xong.'/'.$don->count().' don.');

        return self::SUCCESS;
    }
}
