<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Support\NguonDatBan;
use App\Support\SoDienThoai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dong bo trang thai ban dang phuc vu tu Sapo FnB qua Cloud Cookie de tu dong
 * khoa ban tren The Gats Booking Engine trong khung gio dang ngoi.
 *
 * Ap dung thu nghiem cho Drinking & Healing (store 89781).
 * Tu dong gui tin canh bao vao nhom Zalo cua quan khi cookie het han (HTTP 401/403).
 */
class SapoRealtimeService
{
    public function __construct(
        protected ?ZaloNhomService $zaloNhom = null,
    ) {
        $this->zaloNhom ??= app(ZaloNhomService::class);
    }

    public function batDuoc(string $slug = 'drinking-healing'): bool
    {
        if ($slug === 'drinking-healing') {
            return (bool) config('booking.sapo_realtime.bat_dh')
                && filled($this->layCookie($slug));
        }

        return false;
    }

    public function layCookie(string $slug = 'drinking-healing'): string
    {
        if ($slug === 'drinking-healing') {
            $raw = trim((string) config('booking.sapo_realtime.cookie_dh'));
            if ($raw !== '' && ! str_contains($raw, '=')) {
                return 'token='.$raw;
            }

            return $raw;
        }

        return '';
    }

    public function storeId(string $slug = 'drinking-healing'): int
    {
        if ($slug === 'drinking-healing') {
            return (int) config('booking.sapo_realtime.store_id_dh', 89781);
        }

        return 0;
    }

    /**
     * Tinh dem kinh doanh tuong ung voi mot thoi diem theo gio mo cua chi nhanh.
     */
    public function demKinhDoanh(Branch $branch, Carbon $thoiDiem): string
    {
        $phut = $thoiDiem->hour * 60 + $thoiDiem->minute;
        $phutMo = $branch->openMinutes();

        return ($phut < $phutMo ? $thoiDiem->copy()->subDay() : $thoiDiem)->toDateString();
    }

    /**
     * Chuan hoa ma ban de khop giua Sapo va The Gats: bo tien to "Ban", "Quay",
     * bo khoang trang va chuyen thanh chu hoa.
     */
    public function chuanMaBan(string $ten): string
    {
        $ten = preg_replace('/^(bàn|ban|quầy|quay)\s+/ui', '', trim($ten));

        return mb_strtoupper((string) preg_replace('/\s+/u', '', (string) $ten));
    }

    /**
     * Bang tra cuu ban cua chi nhanh theo ca ma moi va cac bi danh cu (aliases).
     *
     * @return array<string, DiningTable>
     */
    public function bangTraCuuBan(Branch $branch): array
    {
        $tables = $branch->diningTables()->where('is_active', true)->get();
        $bang = [];

        foreach ($tables as $table) {
            $chuan = $this->chuanMaBan((string) $table->code);
            if ($chuan !== '') {
                $bang[$chuan] = $table;
            }
        }

        foreach ($tables as $table) {
            foreach (explode(',', (string) $table->aliases) as $cu) {
                $chuanCu = $this->chuanMaBan($cu);
                if ($chuanCu !== '' && ! isset($bang[$chuanCu])) {
                    $bang[$chuanCu] = $table;
                }
            }
        }

        return $bang;
    }

    /**
     * Keo danh sach don dang phuc vu tu Sapo FnB.
     *
     * @return array{thanhCong: bool, maLoi: ?int, thongBaoLoi: ?string, donHang: array<int, array<string, mixed>>}
     */
    public function keoDonMo(string $slug = 'drinking-healing'): array
    {
        $cookie = $this->layCookie($slug);

        if ($cookie === '') {
            return [
                'thanhCong' => false,
                'maLoi' => 400,
                'thongBaoLoi' => 'Chưa cấu hình Cookie Sapo cho chi nhánh '.$slug,
                'donHang' => [],
            ];
        }

        $timeout = (int) config('booking.sapo_realtime.timeout', 10);
        $url = 'https://fnb.mysapo.vn/admin/orders.json';

        try {
            $http = Http::timeout($timeout);
            if (app()->isLocal()) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->withHeaders([
                    'Cookie' => $cookie,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://fnb.mysapo.vn/admin/orders',
                ])
                ->get($url, [
                    'status' => 'open',
                    'limit' => 50,
                ]);

            if ($response->status() === 401 || $response->status() === 403) {
                $this->canhBaoLoiCookie($slug, $response->status());

                return [
                    'thanhCong' => false,
                    'maLoi' => $response->status(),
                    'thongBaoLoi' => 'Cookie Sapo đã hết hạn hoặc bị chặn (HTTP '.$response->status().')',
                    'donHang' => [],
                ];
            }

            if (! $response->successful()) {
                Log::warning('Sapo Realtime: Loi HTTP '.$response->status(), [
                    'branch' => $slug,
                    'body' => substr((string) $response->body(), 0, 300),
                ]);

                return [
                    'thanhCong' => false,
                    'maLoi' => $response->status(),
                    'thongBaoLoi' => 'Lỗi kết nối tới Sapo: HTTP '.$response->status(),
                    'donHang' => [],
                ];
            }

            $json = $response->json();
            $orders = (array) ($json['orders'] ?? []);

            return [
                'thanhCong' => true,
                'maLoi' => null,
                'thongBaoLoi' => null,
                'donHang' => $orders,
            ];
        } catch (Throwable $e) {
            Log::error('Sapo Realtime: Ngoai le khi keo du lieu', [
                'branch' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [
                'thanhCong' => false,
                'maLoi' => 500,
                'thongBaoLoi' => 'Lỗi ngoại lệ: '.$e->getMessage(),
                'donHang' => [],
            ];
        }
    }

    /**
     * Kiem tra nhanh ket noi Sapo voi cookie nhap vao (dung cho trang Cai dat).
     *
     * @return array{thanhCong: bool, maLoi: ?int, thongDiep: string, soDon: int}
     */
    public function kiemTraKetNoi(string $cookie, string $slug = 'drinking-healing'): array
    {
        $cookie = trim($cookie);

        if ($cookie === '') {
            return [
                'thanhCong' => false,
                'maLoi' => 400,
                'thongDiep' => 'Vui lòng dán Cookie hoặc Token của tài khoản Sapo.',
                'soDon' => 0,
            ];
        }

        if (! str_contains($cookie, '=')) {
            $cookie = 'token='.$cookie;
        }

        $timeout = (int) config('booking.sapo_realtime.timeout', 10);
        $url = 'https://fnb.mysapo.vn/admin/orders.json';

        try {
            $http = Http::timeout($timeout);
            if (app()->isLocal()) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->withHeaders([
                    'Cookie' => $cookie,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Referer' => 'https://fnb.mysapo.vn/admin/orders',
                ])
                ->get($url, [
                    'status' => 'open',
                    'limit' => 50,
                ]);

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'thanhCong' => false,
                    'maLoi' => $response->status(),
                    'thongDiep' => 'Cookie không hợp lệ hoặc đã hết hạn (HTTP '.$response->status().').',
                    'soDon' => 0,
                ];
            }

            if (! $response->successful()) {
                return [
                    'thanhCong' => false,
                    'maLoi' => $response->status(),
                    'thongDiep' => 'Không kết nối được tới Sapo (HTTP '.$response->status().').',
                    'soDon' => 0,
                ];
            }

            $orders = (array) ($response->json('orders') ?? []);
            $donDangAn = count(array_filter($orders, function ($o) {
                return ($o['dine_type'] ?? '') === 'dine_in' && ! empty($o['table']['table_name']);
            }));

            return [
                'thanhCong' => true,
                'maLoi' => null,
                'thongDiep' => "Kết nối Sapo thành công! Đang có {$donDangAn} bàn có khách tại quán.",
                'soDon' => $donDangAn,
            ];
        } catch (Throwable $e) {
            return [
                'thanhCong' => false,
                'maLoi' => 500,
                'thongDiep' => 'Lỗi kết nối: '.$e->getMessage(),
                'soDon' => 0,
            ];
        }
    }

    /**
     * Dong bo trang thai ban dang phuc vu vao database de AvailabilityService tu dong khoa.
     *
     * @return array{tongDonMo: int, banKhoaMoi: int, banDangNgoi: int, banGiaiPhong: int, loi: ?string}
     */
    public function dongBo(string $slug = 'drinking-healing', bool $ghi = true): array
    {
        $branch = Branch::where('slug', $slug)->first();

        if (! $branch) {
            return [
                'tongDonMo' => 0,
                'banKhoaMoi' => 0,
                'banDangNgoi' => 0,
                'banGiaiPhong' => 0,
                'loi' => 'Không tìm thấy chi nhánh: '.$slug,
            ];
        }

        $keo = $this->keoDonMo($slug);

        if (! $keo['thanhCong']) {
            return [
                'tongDonMo' => 0,
                'banKhoaMoi' => 0,
                'banDangNgoi' => 0,
                'banGiaiPhong' => 0,
                'loi' => $keo['thongBaoLoi'],
            ];
        }

        $now = now();
        $demKinhDoanh = $this->demKinhDoanh($branch, $now);
        $turnMinutes = (int) ($branch->turn_minutes ?: config('booking.sapo_realtime.turn_minutes_dh', 120));
        $bangBan = $this->bangTraCuuBan($branch);

        $ketQua = [
            'tongDonMo' => count($keo['donHang']),
            'banKhoaMoi' => 0,
            'banDangNgoi' => 0,
            'banGiaiPhong' => 0,
            'loi' => null,
        ];

        $maSapoDangMo = [];

        foreach ($keo['donHang'] as $order) {
            // Chi xu ly don an tai ban co ten ban
            if (($order['dine_type'] ?? '') !== 'dine_in') {
                continue;
            }

            $tenBanSapo = (string) ($order['table']['table_name'] ?? '');
            if ($tenBanSapo === '') {
                continue;
            }

            // Don da thanh toan hoac da huy thi bo qua
            if (in_array($order['status'] ?? '', ['completed', 'cancelled'], true)) {
                continue;
            }

            $chuanTen = $this->chuanMaBan($tenBanSapo);
            $table = $bangBan[$chuanTen] ?? null;

            if (! $table) {
                Log::info('Sapo Realtime: Khong tim thay ban tuong ung', [
                    'branch' => $slug,
                    'sapo_table' => $tenBanSapo,
                    'chuan' => $chuanTen,
                ]);

                continue;
            }

            $orderId = (string) ($order['id'] ?? '');
            $orderName = (string) ($order['name'] ?? $orderId);
            $sapoCode = 'ORD-'.$orderId;
            $maSapoDangMo[] = $sapoCode;

            $timestamp = (int) ($order['order_time'] ?? $order['created_on'] ?? $now->timestamp);
            $orderTime = Carbon::createFromTimestamp($timestamp, config('app.timezone'));

            // Neu gio dat vuot qua 24 tieng truoc thi bo qua (tranh don quen dong tu ngay cu)
            if ($orderTime->diffInHours($now) > 24) {
                continue;
            }

            // Kiem tra xem ban nay hom nay da co booking nao dang phuc vu hoac da xac nhan chua
            $donSanCo = $branch->bookings()
                ->where('booking_date', $demKinhDoanh)
                ->whereHas('diningTables', fn ($q) => $q->where('dining_tables.id', $table->id))
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING, Booking::STATUS_SEATED])
                ->first();

            if ($donSanCo) {
                // Truong hop 1: Don dat online san co cua khach
                if ($donSanCo->status !== Booking::STATUS_SEATED) {
                    if ($ghi) {
                        $ghiChu = trim(($donSanCo->internal_note ? $donSanCo->internal_note."\n" : '')."Đã mở bàn trên Sapo (#{$orderName})");
                        $donSanCo->update([
                            'status' => Booking::STATUS_SEATED,
                            'seated_at' => $orderTime,
                            'sapo_code' => $sapoCode,
                            'internal_note' => $ghiChu,
                        ]);
                    }
                    $ketQua['banKhoaMoi']++;
                } else {
                    $ketQua['banDangNgoi']++;
                }

                continue;
            }

            // Truong hop 2: Khach vang lai (Walk-in)
            $donWalkIn = Booking::where('branch_id', $branch->id)
                ->where('booking_date', $demKinhDoanh)
                ->where('sapo_code', $sapoCode)
                ->where('status', Booking::STATUS_SEATED)
                ->first();

            if ($donWalkIn) {
                $ketQua['banDangNgoi']++;

                continue;
            }

            // Tao booking moi de khoa ban
            if ($ghi) {
                $customer = (array) ($order['customer'] ?? []);
                $customerName = trim((string) (($customer['last_name'] ?? '').' '.($customer['first_name'] ?? '')));
                if ($customerName === '') {
                    $customerName = 'Khách tại quán (Sapo #'.$orderName.')';
                }

                $customerPhone = SoDienThoai::chuan($customer['phone'] ?? null) ?: '0900000000';
                $partySize = max(1, (int) ($order['customer_count'] ?? $table->seats_min ?? 2));

                $endTime = $orderTime->copy()->addMinutes($turnMinutes);

                $moi = Booking::create([
                    'code' => Booking::generateCode(),
                    'branch_id' => $branch->id,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'party_size' => $partySize,
                    'booking_date' => $demKinhDoanh,
                    'start_time' => $orderTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'area_id' => $table->area_id,
                    'status' => Booking::STATUS_SEATED,
                    'seated_at' => $orderTime,
                    'source' => NguonDatBan::WALK_IN,
                    'sapo_code' => $sapoCode,
                    'internal_note' => 'Khóa bàn tự động từ Sapo FnB #'.$orderName,
                ]);

                $moi->diningTables()->sync([$table->id]);
            }

            $ketQua['banKhoaMoi']++;
        }

        // Giai phong ban cho nhung don Sapo truoc do ma nay da khong con trong danh sach open (da thanh toan hoac huy)
        $donCanGiaiPhong = Booking::query()
            ->where('branch_id', $branch->id)
            ->where('booking_date', $demKinhDoanh)
            ->where('status', Booking::STATUS_SEATED)
            ->where('sapo_code', 'LIKE', 'ORD-%')
            ->when(! empty($maSapoDangMo), fn ($q) => $q->whereNotIn('sapo_code', $maSapoDangMo))
            ->get();

        foreach ($donCanGiaiPhong as $don) {
            if ($ghi) {
                $don->update([
                    'status' => Booking::STATUS_COMPLETED,
                    'completed_at' => $now,
                    'internal_note' => trim(($don->internal_note ? $don->internal_note."\n" : '').'Đã đóng bàn trên Sapo - Tự động giải phóng'),
                ]);
            }
            $ketQua['banGiaiPhong']++;
        }

        return $ketQua;
    }

    /**
     * Ban canh bao vao nhom Zalo khi phat hien cookie het han hoac bi chan.
     * Throttled 30 phut/lan de tranh spam tin lien tuc moi phut.
     */
    public function canhBaoLoiCookie(string $slug, int $httpStatus): void
    {
        $cacheKey = "sapo_cookie_alert_{$slug}";

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes(30));

        $nhomMap = (array) config('booking.zalo_group.nhom', []);
        $tenNhom = $nhomMap[$slug] ?? null;

        if (blank($tenNhom)) {
            Log::warning('Sapo Realtime: Khong tim thay nhom Zalo de bao loi cookie cho '.$slug);

            return;
        }

        $adminDomain = (string) config('booking.admin_domain', 'admin.thegats.vn');
        $caiDatUrl = 'https://'.$adminDomain.'/quan-ly/cai-dat';

        $noiDung = "⚠️ [CẢNH BÁO SAPO - DRINKING & HEALING]\n"
            ."Phiên đăng nhập Sapo FnB (Cookie) đã hết hạn hoặc bị chặn (HTTP {$httpStatus}).\n"
            ."Hệ thống tạm dừng tự động khóa bàn realtime từ Sapo.\n"
            ."👉 Vui lòng đăng nhập lại Sapo và cập nhật Cookie mới tại trang Cài đặt quản trị:\n"
            .$caiDatUrl;

        $this->zaloNhom->guiTin($tenNhom, $noiDung, [
            'event' => 'sapo_cookie_error',
            'branch' => $slug,
            'status' => $httpStatus,
        ]);
    }
}
