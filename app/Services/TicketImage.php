<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Brand;
use GdImage;
use Throwable;

/**
 * Ve tam ve xac nhan thanh anh PNG bang GD, de nhung vao thu gui khach.
 *
 * Cung bo cuc voi public/js/ticket.js (ban ve tren trinh duyet cho khach tu
 * luu). Hai ban phai giong nhau ve noi dung; sua ben nay nho nhin lai ben kia.
 *
 * Ve o kho gap doi roi thu nho lai: GD khong lam min canh cung va chu, thu nho
 * mot lan cuoi la cach re nhat de co duong cong va net chu sach se.
 */
class TicketImage
{
    /** Chieu ngang luc ve. Anh xuat ra bang mot nua. */
    protected const W = 1080;

    protected const PAD = 88;

    /** Font du dau tieng Viet, dung khi quan chua gan font rieng. */
    protected const FALLBACK = 'fonts/ticket-fallback.ttf';

    protected GdImage $img;

    protected int $y = 0;

    protected string $display;

    protected string $body;

    /** @var array<string, int> */
    protected array $mau = [];

    /**
     * Tra ve du lieu PNG, hoac null neu khong ve duoc.
     *
     * Khong bao gio nem ngoai le: thieu anh thi thu van phai gui di duoc,
     * chi la khong co hinh.
     */
    public function png(Booking $booking, ?string $locale = null): ?string
    {
        try {
            return $this->ve($booking, $locale ?: ($booking->locale ?: config('app.locale')));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function ve(Booking $booking, string $locale): ?string
    {
        $brand = $booking->branch?->brand;

        $this->display = $this->font($brand?->display_font_path);
        $this->body = $this->font($brand?->body_font_path);

        if (! $this->display || ! $this->body) {
            return null;
        }

        $hang = $this->hang($booking, $locale);

        // Do chieu cao truoc (khong ve), roi tao anh dung kich thuoc.
        $cao = $this->dungKhung($booking, $brand, $hang, $locale, null);

        $this->img = imagecreatetruecolor(self::W, $cao);
        imagealphablending($this->img, true);

        $this->dungKhung($booking, $brand, $hang, $locale, $this->img);

        $nho = imagescale($this->img, (int) (self::W / 2), -1, IMG_BICUBIC);
        imagedestroy($this->img);

        ob_start();
        imagepng($nho, null, 8);
        imagedestroy($nho);

        return ob_get_clean() ?: null;
    }

    /** Duong dan font tuyet doi, roi ve font du dau tieng Viet khi quan chua gan. */
    protected function font(?string $path): string
    {
        foreach ([$path, self::FALLBACK] as $p) {
            if ($p && is_file($full = public_path($p))) {
                return $full;
            }
        }

        return '';
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function hang(Booking $booking, string $locale): array
    {
        $branch = $booking->branch;

        return array_values(array_filter([
            [__('booking.ticket.venue', [], $locale), $branch->name],
            $branch->address ? [__('booking.ticket.address', [], $locale), $branch->address] : null,
            [
                __('booking.ticket.time', [], $locale),
                $booking->timeRangeLabel().' · '.$booking->booking_date->format('d/m/Y'),
            ],
            [__('booking.ticket.party', [], $locale), (string) $booking->party_size],
            [
                __('booking.ticket.booked_by', [], $locale),
                $booking->customer_name.' · '.$booking->customer_phone,
            ],
            // Ghi chu do chinh khach viet: cho no quay lai tren ve de khach
            // thay quan da nhan dung yeu cau. Cat bot de anh khong phinh ra.
            $booking->note
                ? [__('booking.ticket.note', [], $locale), $this->catBot($booking->note, 120)]
                : null,
        ]));
    }

    protected function catBot(string $text, int $toiDa): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strlen($text) > $toiDa
            ? mb_substr($text, 0, $toiDa - 1).'…'
            : $text;
    }

    /**
     * Ve toan bo tam ve. Truyen $img = null de chi do chieu cao.
     *
     * @param  array<int, array{0: string, 1: string}>  $hang
     */
    protected function dungKhung(Booking $booking, ?Brand $brand, array $hang, string $locale, ?GdImage $img): int
    {
        $this->img = $img ?? imagecreatetruecolor(10, 10);

        $nen = $this->hex($brand?->ground() ?: '#0e0d0c');
        $toi = $this->sang($nen) < 0.5;

        $this->mau = [
            'nen' => $this->cap($nen),
            'chu' => $this->cap($this->hex($toi ? '#f4efe6' : '#16130f')),
            'mo' => $this->tron($nen, $this->hex($toi ? '#f4efe6' : '#16130f'), 0.58),
            'vien' => $this->tron($nen, $this->hex($toi ? '#f4efe6' : '#16130f'), 0.16),
            'nhan' => $this->cap($this->hex($brand?->accent_color ?: '#c8a15a')),
        ];

        if ($img) {
            imagefilledrectangle($img, 0, 0, self::W, imagesy($img), $this->mau['nen']);
        }

        $this->y = 96;

        $logo = $brand?->hasLogo() ? public_path($brand->logo_path) : null;

        if ($logo && $this->veLogo($logo, $img)) {
            // veLogo da tu day $this->y xuong.
        } else {
            $this->chu($brand?->name ?? 'The Gats', $this->display, 46, 'chu', 2, $img);
            $this->y += 20;
        }

        // Ma dat ban dat o dau ve, nhung co chu bang dung cac dong thong tin
        // ben duoi — no la thu de tra cuu, khong phai thu phai ho to.
        $this->chu(mb_strtoupper(__('booking.ticket.code', [], $locale)), $this->body, 24, 'mo', 6, $img);
        $this->y += 4;
        $this->chu($booking->code, $this->display, 44, 'nhan', 6, $img, 56);
        $this->y += 28;

        $this->veVienTrangThai($booking->statusLabel($locale), $img);
        $this->veGachNgang($img);
        $this->y += 20;

        foreach ($hang as $i => [$nhan, $giaTri]) {
            $this->y += $i === 0 ? 30 : 56;
            $this->chu(mb_strtoupper($nhan), $this->body, 24, 'mo', 5, $img, 32);
            $this->y += 2;
            $this->chu($giaTri, $this->body, 38, 'chu', 0, $img, 50);
        }

        $this->y += 46;
        $this->veGachNgang($img);
        $this->y += 22;

        // Don chua duyet: noi ro quan se goi lai, de khach khong thap thom.
        if ($booking->status === Booking::STATUS_PENDING) {
            $this->chu(
                __('booking.ticket.pending_note', ['venue' => $booking->branch->name], $locale),
                $this->body, 24, 'mo', 0, $img, 34
            );
        } else {
            $this->chu(__('booking.ticket.image_footer', [], $locale), $this->body, 24, 'mo', 0, $img, 34);
        }

        return $this->y + 72;
    }

    protected function veLogo(string $path, ?GdImage $img): bool
    {
        $src = @imagecreatefrompng($path);

        if (! $src) {
            return false;
        }

        $h = min(104, imagesy($src));
        $w = (int) round(imagesx($src) * ($h / imagesy($src)));

        if ($w > 520) {
            $h = (int) round($h * (520 / $w));
            $w = 520;
        }

        if ($img) {
            imagealphablending($img, true);
            imagecopyresampled($img, $src, (int) ((self::W - $w) / 2), $this->y, 0, 0, $w, $h, imagesx($src), imagesy($src));
        }

        imagedestroy($src);
        $this->y += $h + 46;

        return true;
    }

    protected function veVienTrangThai(string $nhan, ?GdImage $img): void
    {
        $rong = (int) $this->rong($nhan, $this->body, 26, 0) + 64;
        $cao = 56;
        $x = (int) ((self::W - $rong) / 2);

        if ($img) {
            $this->vienBo($img, $x, $this->y, $rong, $cao, (int) ($cao / 2), $this->mau['vien']);

            imagettftext($img, 26, 0, (int) ((self::W - $this->rong($nhan, $this->body, 26, 0)) / 2),
                $this->y + (int) ($cao / 2) + 9, $this->mau['mo'], $this->body, $nhan);
        }

        $this->y += $cao + 54;
    }

    /** Vien bo goc, ghep tu 4 cung tron va 4 doan thang. */
    protected function vienBo(GdImage $img, int $x, int $y, int $w, int $h, int $r, int $mau): void
    {
        $d = $r * 2;

        imagearc($img, $x + $r, $y + $r, $d, $d, 180, 270, $mau);
        imagearc($img, $x + $w - $r, $y + $r, $d, $d, 270, 360, $mau);
        imagearc($img, $x + $w - $r, $y + $h - $r, $d, $d, 0, 90, $mau);
        imagearc($img, $x + $r, $y + $h - $r, $d, $d, 90, 180, $mau);

        imageline($img, $x + $r, $y, $x + $w - $r, $y, $mau);
        imageline($img, $x + $r, $y + $h, $x + $w - $r, $y + $h, $mau);
        imageline($img, $x, $y + $r, $x, $y + $h - $r, $mau);
        imageline($img, $x + $w, $y + $r, $x + $w, $y + $h - $r, $mau);
    }

    protected function veGachNgang(?GdImage $img): void
    {
        if ($img) {
            imagefilledrectangle($img, self::PAD, $this->y, self::W - self::PAD, $this->y + 1, $this->mau['vien']);
        }
    }

    /**
     * Ve mot doan chu can giua, tu xuong dong khi qua rong, va tu day $this->y.
     */
    protected function chu(string $text, string $font, int $co, string $mau, float $gian, ?GdImage $img, ?int $buoc = null): void
    {
        $buoc ??= (int) round($co * 1.3);
        $rongToiDa = self::W - self::PAD * 2;

        foreach ($this->xuongDong($text, $font, $co, $gian, $rongToiDa) as $dong) {
            $this->y += $buoc;

            if (! $img) {
                continue;
            }

            $x = (int) round((self::W - $this->rong($dong, $font, $co, $gian)) / 2);
            $this->veGian($img, $dong, $font, $co, $x, $this->y, $this->mau[$mau], $gian);
        }
    }

    /**
     * Ve tung ky tu de chen them khoang cach — GD khong co tuy chon gian chu.
     */
    protected function veGian(GdImage $img, string $text, string $font, int $co, int $x, int $y, int $mau, float $gian): void
    {
        if ($gian <= 0) {
            imagettftext($img, $co, 0, $x, $y, $mau, $font, $text);

            return;
        }

        foreach (mb_str_split($text) as $ky) {
            imagettftext($img, $co, 0, $x, $y, $mau, $font, $ky);
            $x += (int) round($this->rong($ky, $font, $co, 0) + $gian);
        }
    }

    protected function rong(string $text, string $font, int $co, float $gian): float
    {
        if ($text === '') {
            return 0;
        }

        if ($gian > 0) {
            $t = 0;
            foreach (mb_str_split($text) as $ky) {
                $t += $this->rong($ky, $font, $co, 0) + $gian;
            }

            return $t - $gian;
        }

        $b = imagettfbbox($co, 0, $font, $text);

        return $b ? abs($b[2] - $b[0]) : 0;
    }

    /**
     * @return array<int, string>
     */
    protected function xuongDong(string $text, string $font, int $co, float $gian, int $rongToiDa): array
    {
        $tu = preg_split('/\s+/u', trim($text)) ?: [];
        $dong = [];
        $hienTai = '';

        foreach ($tu as $t) {
            $thu = $hienTai === '' ? $t : $hienTai.' '.$t;

            if ($hienTai !== '' && $this->rong($thu, $font, $co, $gian) > $rongToiDa) {
                $dong[] = $hienTai;
                $hienTai = $t;
            } else {
                $hienTai = $thu;
            }
        }

        if ($hienTai !== '') {
            $dong[] = $hienTai;
        }

        return $dong ?: [''];
    }

    /** @return array{0: int, 1: int, 2: int} */
    protected function hex(string $hex): array
    {
        $h = ltrim(trim($hex), '#');

        if (strlen($h) !== 6) {
            $h = '0e0d0c';
        }

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /** @param array{0: int, 1: int, 2: int} $rgb */
    protected function cap(array $rgb): int
    {
        return imagecolorallocate($this->img, $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $nen
     * @param  array{0: int, 1: int, 2: int}  $tren
     */
    protected function tron(array $nen, array $tren, float $ty): int
    {
        return $this->cap([
            (int) round($nen[0] + ($tren[0] - $nen[0]) * $ty),
            (int) round($nen[1] + ($tren[1] - $nen[1]) * $ty),
            (int) round($nen[2] + ($tren[2] - $nen[2]) * $ty),
        ]);
    }

    /** @param array{0: int, 1: int, 2: int} $rgb */
    protected function sang(array $rgb): float
    {
        return (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) / 255;
    }
}
