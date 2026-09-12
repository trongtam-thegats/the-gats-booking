<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Don nhat ky nhac lich bi lap.
 *
 * Cron nhac lich chay 5 phut mot lan trong suot khung nhac (mac dinh 3 tieng).
 * Truoc khi sua SendBookingReminders, moi lan chay lai de them mot dong nhat ky
 * cho cung mot dat ban - toi 36 dong giong het nhau cho moi don khach khong de
 * email. Lenh nay don dong lap con sot lai.
 *
 * Chi dung cho su kien "reminder". Cac su kien khac (created/confirmed/updated/
 * cancelled) do nguoi that bam ra, hai dong giong nhau nghia la hai lan bam -
 * do la thong tin, khong phai rac.
 *
 * Mac dinh chi xem truoc. Them --ghi moi that su xoa.
 */
class DonNhatKyNhacLich extends Command
{
    /** Xoa theo tung dot cho nhe co so du lieu. */
    protected const CO_DOT = 2000;

    protected $signature = 'booking:don-nhat-ky
        {--ghi : That su xoa khoi co so du lieu}';

    protected $description = 'Don cac dong nhat ky nhac lich bi lap (giu lai dong dau tien moi loai)';

    public function handle(): int
    {
        $ghi = (bool) $this->option('ghi');

        $tong = DB::table('notification_logs')->where('event', 'reminder')->count();
        $thua = $this->demDongThua();

        $this->info('Nhat ky nhac lich: '.$tong.' dong, trong do '.$thua.' dong lap.');

        if ($thua === 0) {
            $this->line('Khong co gi de don.');

            return self::SUCCESS;
        }

        $this->bangTomTat();

        if (! $ghi) {
            $this->newLine();
            $this->line('Che do: chi xem truoc, khong xoa gi. Them --ghi de xoa that.');

            return self::SUCCESS;
        }

        $daXoa = 0;

        // Lay id theo tung dot roi xoa thang theo id: MySQL khong cho xoa tren
        // chinh bang dang duoc doc trong cau lenh con (loi 1093).
        while (true) {
            $ids = $this->truyVanDongThua()->limit(self::CO_DOT)->pluck('l.id')->all();

            if ($ids === []) {
                break;
            }

            $daXoa += DB::table('notification_logs')->whereIn('id', $ids)->delete();
            $this->line('Da xoa '.$daXoa.'/'.$thua.'...');
        }

        $this->info('Xong. Da xoa '.$daXoa.' dong lap.');

        return self::SUCCESS;
    }

    /**
     * Nhung dong lap: cung dat ban, cung kenh, cung ket qua, nhung da co mot
     * dong cu hon dung truoc. Giu dong cu nhat vi do la lan nhac that su dau tien.
     */
    protected function truyVanDongThua(): \Illuminate\Database\Query\Builder
    {
        return DB::table('notification_logs as l')
            ->select('l.id')
            ->where('l.event', 'reminder')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('notification_logs as k')
                    ->where('k.event', 'reminder')
                    ->whereColumn('k.booking_id', 'l.booking_id')
                    ->whereColumn('k.channel', 'l.channel')
                    ->whereColumn('k.status', 'l.status')
                    ->whereColumn('k.id', '<', 'l.id');
            })
            ->orderBy('l.id');
    }

    protected function demDongThua(): int
    {
        return $this->truyVanDongThua()->count();
    }

    /** Cho quan ly thay rac nam o dau truoc khi go. */
    protected function bangTomTat(): void
    {
        $dong = DB::table('notification_logs as l')
            ->select('l.channel', 'l.status', DB::raw('COUNT(*) as so_dong'))
            ->whereIn('l.id', function ($q) {
                $q->select('l2.id')
                    ->from('notification_logs as l2')
                    ->where('l2.event', 'reminder')
                    ->whereExists(function ($s) {
                        $s->select(DB::raw(1))
                            ->from('notification_logs as k')
                            ->where('k.event', 'reminder')
                            ->whereColumn('k.booking_id', 'l2.booking_id')
                            ->whereColumn('k.channel', 'l2.channel')
                            ->whereColumn('k.status', 'l2.status')
                            ->whereColumn('k.id', '<', 'l2.id');
                    });
            })
            ->groupBy('l.channel', 'l.status')
            ->get();

        $this->table(
            ['Kenh', 'Ket qua', 'So dong lap'],
            $dong->map(fn ($d) => [$d->channel, $d->status, $d->so_dong])->all(),
        );
    }
}
