<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gui thong bao dat ban truc tiep vao nhom Zalo cua tung quan thong qua
 * Engine Bot (https://zalo.thegats.vn/api/v1/zalo/send-message).
 *
 * Quy tac song con: gui tin Zalo that bai KHONG DUOC PHEP lam anh huong
 * hay lam hong luong dat ban cua khach. Moi loi deu duoc nuot lai va ghi log.
 */
class ZaloNhomService
{
    /** Kiem tra tinh nang co dang bat hay khong. */
    public function batDuoc(): bool
    {
        return (bool) config('booking.zalo_group.bat', false);
    }

    /** Lay ten nhom Zalo tuong ung voi chi nhanh / quan. */
    public function tenNhomChoQuan(?Branch $branch): ?string
    {
        if (! $branch) {
            return null;
        }

        $nhomMap = (array) config('booking.zalo_group.nhom', []);
        $slug = (string) $branch->slug;

        return $nhomMap[$slug] ?? null;
    }

    /** Gui thong bao khi co don dat ban moi (khach tu dat hoac nhan vien dat ho). */
    public function guiDonMoi(Booking $booking, bool $nhanVienDat = false): bool
    {
        if (! $this->batDuoc()) {
            return false;
        }

        $branch = $booking->branch;
        $tenNhom = $this->tenNhomChoQuan($branch);

        if (blank($tenNhom)) {
            Log::info('Bo qua gui Zalo: chua cau hinh nhom cho quan', [
                'branch' => $branch?->slug,
                'booking' => $booking->code,
            ]);

            return false;
        }

        $noiDung = $this->soanTinDonMoi($booking, $nhanVienDat);

        return $this->guiTin($tenNhom, $noiDung, ['booking' => $booking->code, 'event' => 'don_moi']);
    }

    /** Gui thong bao khi khach tu bam huy don tren trang tra cuu. */
    public function guiKhachHuy(Booking $booking): bool
    {
        if (! $this->batDuoc()) {
            return false;
        }

        $branch = $booking->branch;
        $tenNhom = $this->tenNhomChoQuan($branch);

        if (blank($tenNhom)) {
            return false;
        }

        $noiDung = $this->soanTinKhachHuy($booking);

        return $this->guiTin($tenNhom, $noiDung, ['booking' => $booking->code, 'event' => 'khach_huy']);
    }

    /**
     * Gui tin nhan den Zalo Engine API.
     *
     * @param  array<string, mixed>  $context
     */
    public function guiTin(string $tenNhom, string $noiDung, array $context = []): bool
    {
        $engineUrl = rtrim((string) config('booking.zalo_group.engine_url', 'https://zalo.thegats.vn'), '/');
        $endpoint = $engineUrl.'/api/v1/zalo/send-message';
        $timeout = (int) config('booking.zalo_group.timeout', 10);

        try {
            $response = Http::timeout($timeout)->post($endpoint, [
                'group_name' => $tenNhom,
                'message' => $noiDung,
            ]);

            if ($response->successful()) {
                Log::info('Da gui tin vao nhom Zalo thanh cong', array_merge($context, [
                    'group' => $tenNhom,
                    'response' => $response->json(),
                ]));

                return true;
            }

            Log::warning('Gui tin vao nhom Zalo that bai', array_merge($context, [
                'group' => $tenNhom,
                'status' => $response->status(),
                'body' => $response->body(),
            ]));

            return false;
        } catch (Throwable $e) {
            Log::warning('Khong the ket noi toi Zalo Engine Server', array_merge($context, [
                'group' => $tenNhom,
                'error' => $e->getMessage(),
            ]));

            return false;
        }
    }

    /** Soan noi dung tin don moi. */
    public function soanTinDonMoi(Booking $booking, bool $nhanVienDat = false): string
    {
        $branch = $booking->branch;
        $tenQuan = $branch?->name ?: 'The Gats';
        $gio = substr((string) $booking->start_time, 0, 5);
        $ngay = $booking->booking_date->format('d/m/Y');
        $nguon = $nhanVienDat ? 'Nhân viên đặt hộ' : 'Khách đặt online';

        $lines = [
            "🛎️ ĐƠN ĐẶT BÀN MỚI · {$tenQuan}",
            '───────────────────────',
            "• Mã đơn: #{$booking->code}",
            "• Khách hàng: {$booking->customer_name}",
            "• Số điện thoại: {$booking->customer_phone}",
            "• Số lượng: {$booking->party_size} khách",
            "• Thời gian: {$gio} · Ngày {$ngay}",
        ];

        // Khu vuc & Ban (neu da duoc xep)
        $khuVuc = $booking->area?->name;
        $danhSachBan = $booking->diningTables->pluck('code')->filter()->join(', ');
        if ($khuVuc || $danhSachBan) {
            $chiTietBan = $khuVuc ? "Khu {$khuVuc}" : '';
            if ($danhSachBan) {
                $chiTietBan .= ($chiTietBan ? " (Bàn: {$danhSachBan})" : "Bàn: {$danhSachBan}");
            }
            $lines[] = "• Vị trí: {$chiTietBan}";
        }

        if (filled($booking->note)) {
            $lines[] = "• Ghi chú: {$booking->note}";
        }

        $lines[] = "• Nguồn đặt: {$nguon}";

        // Link quan tri
        $adminDomain = config('booking.admin_domain');
        if ($adminDomain) {
            $lines[] = '───────────────────────';
            $lines[] = "👉 Chi tiết: https://{$adminDomain}/quan-ly/dat-ban/{$booking->id}";
        }

        return implode("\n", $lines);
    }

    /** Soan noi dung tin khach huy. */
    public function soanTinKhachHuy(Booking $booking): string
    {
        $branch = $booking->branch;
        $tenQuan = $branch?->name ?: 'The Gats';
        $gio = substr((string) $booking->start_time, 0, 5);
        $ngay = $booking->booking_date->format('d/m/Y');

        $lines = [
            "❌ KHÁCH HUỶ ĐẶT BÀN · {$tenQuan}",
            '───────────────────────',
            "• Mã đơn: #{$booking->code}",
            "• Khách hàng: {$booking->customer_name} ({$booking->customer_phone})",
            "• Khung giờ đã đặt: {$gio} · Ngày {$ngay}",
            "• Số khách: {$booking->party_size} khách",
        ];

        if (filled($booking->cancel_reason)) {
            $lines[] = "• Lý do huỷ: {$booking->cancel_reason}";
        }

        if ($booking->sapo_code) {
            $lines[] = "⚠️ Lưu ý: Đơn đã có trên Sapo (#{$booking->sapo_code}) - cần thao tác huỷ bên Sapo!";
        }

        $adminDomain = config('booking.admin_domain');
        if ($adminDomain) {
            $lines[] = '───────────────────────';
            $lines[] = "👉 Xem đơn: https://{$adminDomain}/quan-ly/dat-ban/{$booking->id}";
        }

        return implode("\n", $lines);
    }
}
