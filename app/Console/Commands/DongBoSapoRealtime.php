<?php

namespace App\Console\Commands;

use App\Services\SapoRealtimeService;
use Illuminate\Console\Command;

/**
 * Dong bo cac ban dang co khach tu Sapo FnB de tu dong khoa ban tren web dat ban.
 *
 * Chay moi 1-2 phut trong cron:
 *   php artisan sapo:realtime --quan=drinking-healing
 */
class DongBoSapoRealtime extends Command
{
    protected $signature = 'sapo:realtime
        {--quan=drinking-healing : Slug của chi nhánh cần đồng bộ}
        {--xem : Chỉ kiểm tra danh sách đơn mở, không lưu thay đổi vào cơ sở dữ liệu}';

    protected $description = 'Đồng bộ trạng thái bàn đang phục vụ từ Sapo FnB để tự động khóa bàn realtime';

    public function handle(SapoRealtimeService $sapo): int
    {
        $quan = (string) $this->option('quan');

        if (! $sapo->batDuoc($quan)) {
            $this->comment("Chưa bật hoặc chưa cấu hình Cookie Sapo cho chi nhánh '{$quan}'.");

            return self::SUCCESS;
        }

        $ghi = ! (bool) $this->option('xem');
        $this->info("Đang đồng bộ bàn realtime từ Sapo cho quán: {$quan} (Chế độ: ".($ghi ? 'GHI' : 'XEM TRƯỚC').')...');

        $ketQua = $sapo->dongBo($quan, $ghi);

        if ($ketQua['loi'] !== null) {
            $this->error("Lỗi đồng bộ Sapo: {$ketQua['loi']}");

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Thành công: %d đơn mở trên Sapo | %d bàn khóa mới | %d bàn đang ngồi | %d bàn giải phóng.',
            $ketQua['tongDonMo'],
            $ketQua['banKhoaMoi'],
            $ketQua['banDangNgoi'],
            $ketQua['banGiaiPhong'],
        ));

        return self::SUCCESS;
    }
}
