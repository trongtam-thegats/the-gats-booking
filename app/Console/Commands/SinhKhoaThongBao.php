<?php

namespace App\Console\Commands;

use App\Support\WebPush;
use Illuminate\Console\Command;

/**
 * Sinh cap khoa VAPID cho thong bao day. Chay MOT lan cho ca he thong.
 *
 * Doi khoa = moi thiet bi da dang ky deu mat tac dung, nhan vien phai bat lai.
 */
class SinhKhoaThongBao extends Command
{
    protected $signature = 'push:khoa-moi';

    protected $description = 'Sinh cap khoa VAPID de dan vao .env';

    public function handle(): int
    {
        $khoa = WebPush::khoaMoi();

        $this->warn('Dan hai dong nay vao .env roi chay php artisan config:cache:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$khoa['public']);
        $this->line('VAPID_PRIVATE_KEY='.$khoa['private']);
        $this->newLine();
        $this->comment('Doi khoa sau nay se lam moi thiet bi da dang ky ngung nhan thong bao.');

        return self::SUCCESS;
    }
}
