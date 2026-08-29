<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Branch;
use App\Services\AvailabilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Du lieu dat ban gia lap de xem thu trang bao cao.
 *
 * KHONG chay tren may that. Moi ban ghi deu co internal_note danh dau, xoa bang:
 *   php artisan db:seed --class=DemoBookingsSeeder -- --xoa
 * hoac goi truc tiep DemoBookingsSeeder::purge().
 */
class DemoBookingsSeeder extends Seeder
{
    public const MARK = '[du-lieu-mau]';

    /** So ngay lui ve qua khu. */
    protected int $days = 90;

    protected array $names = [
        'Nguyễn Minh Anh', 'Trần Quốc Bảo', 'Lê Thu Hà', 'Phạm Gia Huy', 'Vũ Khánh Linh',
        'Đặng Tuấn Kiệt', 'Bùi Ngọc Mai', 'Hoàng Nam Phong', 'Đỗ Thanh Trúc', 'Ngô Bảo Sơn',
        'Alex Turner', 'Marie Dubois', 'Kenji Watanabe', 'Sarah Lim', 'Tom Becker',
    ];

    public function run(): void
    {
        if (in_array('--xoa', $_SERVER['argv'] ?? [], true)) {
            $this->purge();

            return;
        }

        $branches = Branch::where('is_active', true)->with('diningTables')->get();

        if ($branches->isEmpty()) {
            $this->command?->warn('Chưa có địa điểm nào, bỏ qua.');

            return;
        }

        $availability = app(AvailabilityService::class);
        $created = 0;

        // Danh sach so dien thoai dung lai de co khach quay lai va khach bo hen nhieu lan.
        $regulars = collect(range(1, 18))->map(fn (int $i) => '09'.str_pad((string) (10000000 + $i * 37), 8, '0'))->all();

        foreach ($branches as $branch) {
            $slots = $availability->slotTimes($branch);

            if (! $slots || $branch->diningTables->isEmpty()) {
                continue;
            }

            for ($back = $this->days; $back >= 0; $back--) {
                $date = Carbon::today()->subDays($back);

                // Cuoi tuan dong khach hon han ngay thuong.
                $isWeekend = in_array($date->dayOfWeekIso, [5, 6, 7], true);
                $count = random_int($isWeekend ? 6 : 2, $isWeekend ? 14 : 7);

                for ($i = 0; $i < $count; $i++) {
                    $booking = $this->makeBooking($branch, $date, $slots, $regulars, $back);

                    if ($booking) {
                        $created++;
                    }
                }
            }
        }

        $this->command?->info('Đã tạo '.$created.' lượt đặt bàn mẫu trong '.$this->days.' ngày.');
        $this->command?->warn('Xóa bằng: php artisan db:seed --class=DemoBookingsSeeder -- --xoa');
    }

    protected function makeBooking(Branch $branch, Carbon $date, array $slots, array $regulars, int $daysBack): ?Booking
    {
        // Gio cao diem: cac moc giua danh sach duoc chon nhieu hon hai dau.
        $middle = intdiv(count($slots), 2);
        $index = min(count($slots) - 1, max(0, (int) round($middle + random_int(-3, 3))));
        $time = $slots[$index];

        $partySize = [1, 2, 2, 2, 3, 4, 4, 5, 6, 8][random_int(0, 9)];

        // 60% khach quen, con lai la khach moi.
        $phone = random_int(1, 100) <= 60
            ? $regulars[array_rand($regulars)]
            : '09'.str_pad((string) random_int(10000000, 99999999), 8, '0');

        $source = [['online', 70], ['phone', 22], ['walk_in', 8]];
        $roll = random_int(1, 100);
        $pickedSource = 'online';
        $cursor = 0;

        foreach ($source as [$key, $weight]) {
            $cursor += $weight;
            if ($roll <= $cursor) {
                $pickedSource = $key;
                break;
            }
        }

        $status = $this->pickStatus($daysBack);

        $createdAt = $date->copy()
            ->subDays(random_int(0, 6))
            ->setTime(random_int(9, 22), random_int(0, 59));

        if ($createdAt->gt($date->copy()->setTime(23, 59))) {
            $createdAt = $date->copy()->setTime(12, 0);
        }

        $booking = Booking::create([
            'code' => Booking::generateCode(),
            'branch_id' => $branch->id,
            'customer_name' => $this->names[array_rand($this->names)],
            'customer_phone' => $phone,
            'customer_email' => random_int(1, 100) <= 45 ? 'khach'.random_int(1, 999).'@example.com' : null,
            'party_size' => $partySize,
            'booking_date' => $date->toDateString(),
            'start_time' => $time,
            // Ket ca theo turn_minutes cua quan, giong het booking that.
            // De end_time = start_time thi ve ticket se hien "22:00 - 22:00".
            'end_time' => Carbon::createFromFormat('H:i', substr($time, 0, 5))
                ->addMinutes((int) $branch->turn_minutes)
                ->format('H:i:s'),
            'status' => $status,
            'source' => $pickedSource,
            'locale' => random_int(1, 100) <= 85 ? 'vi' : 'en',
            'internal_note' => self::MARK,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'confirmed_at' => $status === Booking::STATUS_PENDING
                ? null
                : $createdAt->copy()->addMinutes(random_int(3, 240)),
        ]);

        $table = $branch->diningTables->firstWhere('seats_max', '>=', $partySize)
            ?? $branch->diningTables->first();

        if ($table) {
            $booking->diningTables()->attach($table->id);
        }

        return $booking;
    }

    /** Ngay da qua thi da co ket qua; ngay sap toi thi con dang cho. */
    protected function pickStatus(int $daysBack): string
    {
        if ($daysBack <= 0) {
            return random_int(1, 100) <= 40 ? Booking::STATUS_PENDING : Booking::STATUS_CONFIRMED;
        }

        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 72 => Booking::STATUS_COMPLETED,
            $roll <= 80 => Booking::STATUS_SEATED,
            $roll <= 92 => Booking::STATUS_CANCELLED,
            default => Booking::STATUS_NO_SHOW,
        };
    }

    public function purge(): void
    {
        $count = Booking::where('internal_note', self::MARK)->count();
        Booking::where('internal_note', self::MARK)->delete();

        $this->command?->info('Đã xóa '.$count.' lượt đặt bàn mẫu.');
    }
}
