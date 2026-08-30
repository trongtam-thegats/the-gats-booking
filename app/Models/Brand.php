<?php

namespace App\Models;

use App\Support\Assets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thuong hieu trong chuoi The Gats (Gemination, Drinking Healing...).
 * Moi thuong hieu co trang dat ban rieng va mau nhan dien rieng,
 * nhung dung chung mot khu quan tri.
 */
class Brand extends Model
{
    /** Mau nen mac dinh khi quan chua khai bao rieng. */
    public const DEFAULT_GROUND = '#0e0d0c';

    /** Cac ban thu nho cua anh bia, tinh bang pixel. */
    public const COVER_WIDTHS = [800];

    /** Bo font dung khi quan chua tai font rieng len. */
    public const FALLBACK_STACK = '"Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif';

    /**
     * Cac doan chu sua duoc tren trang dat ban, tach rieng cho tung ngon ngu.
     *
     * key => [nhan trong khu quan tri, khoa ban dich mac dinh, kieu o nhap, giai thich]
     * Khoa ban dich null nghia la khong co noi dung mac dinh - bo trong thi khong hien gi.
     *
     * @var array<string, array{0: string, 1: ?string, 2: string, 3: string}>
     */
    public const TEXTS = [
        'hero_title' => [
            'Tiêu đề trang đặt bàn', 'booking.hero.title', 'text',
            'Dòng chữ lớn nhất khách nhìn thấy đầu tiên.',
        ],
        'hero_intro' => [
            'Câu giới thiệu', null, 'textarea',
            'Hiện ngay dưới tiêu đề. Bỏ trống thì chỉ hiện địa chỉ và giờ mở cửa.',
        ],
        'terms' => [
            'Điều khoản đặt bàn', null, 'textarea',
            'Hiện ngay cạnh nút đặt bàn. Ví dụ: bàn chỉ giữ trong 15 phút kể từ giờ hẹn.',
        ],
        'submit_label' => [
            'Chữ trên nút đặt bàn', 'booking.form.submit', 'text',
            'Giữ ngắn để nút không bị vỡ trên điện thoại.',
        ],
        'thanks_title' => [
            'Tiêu đề trang cảm ơn', 'booking.ticket.thanks_title', 'text',
            'Hiện sau khi khách gửi form thành công.',
        ],
        'thanks_body' => [
            'Lời cảm ơn', 'booking.ticket.thanks_body', 'textarea',
            'Câu nói tiếp ngay dưới tiêu đề trang cảm ơn.',
        ],
        'no_slots' => [
            'Lời nhắn khi hết bàn', 'booking.errors.day_full', 'textarea',
            'Hiện khi khách chọn một ngày đã hết bàn.',
        ],
        'closed_message' => [
            'Lời nhắn khi tạm ngưng nhận đặt bàn', null, 'textarea',
            'Hiện khi quán chưa mở hoặc tạm ngưng nhận đặt bàn trực tuyến.',
        ],
    ];

    protected $fillable = [
        'name', 'slug', 'domain', 'tagline', 'description', 'mark', 'logo_path', 'cover_path',
        'display_font_path', 'body_font_path',
        'accent_color', 'ground_color', 'phone', 'mail_from_address', 'mail_from_name',
        'is_active', 'is_default', 'sort_order',
        'website_url', 'facebook_url', 'instagram_url', 'tiktok_url',
    ];

    /** Cac o link mang xa hoi: cot => nhan hien cho khach. */
    public const SOCIAL_LINKS = [
        'website_url' => 'Website',
        'facebook_url' => 'Facebook',
        'instagram_url' => 'Instagram',
        'tiktok_url' => 'TikTok',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class)->orderBy('sort_order')->orderBy('name');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(BrandContent::class);
    }

    /**
     * Doan chu theo key, lay dung ngon ngu khach dang xem.
     *
     * Thu tu: ban quan tu sua cho ngon ngu do > cau mac dinh da dich > rong.
     * Co tinh KHONG lay ban tieng Viet de bu cho tieng Anh: hien tieng Viet
     * tren trang tieng Anh con te hon la khong hien gi.
     *
     * @param  array<string, string>  $replace
     */
    public function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        $custom = $this->contents
            ->first(fn (BrandContent $row) => $row->key === $key && $row->locale === $locale)
            ?->value;

        if (trim((string) $custom) !== '') {
            return $custom;
        }

        $fallbackKey = self::TEXTS[$key][1] ?? null;

        return $fallbackKey ? __($fallbackKey, $replace, $locale) : '';
    }

    public function hasCover(): bool
    {
        return $this->cover_path && is_file(public_path($this->cover_path));
    }

    /**
     * Cac link mang xa hoi da khai bao, dang [nhan => dia chi].
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach (self::SOCIAL_LINKS as $column => $label) {
            if (filled($this->{$column})) {
                $links[$label] = $this->{$column};
            }
        }

        return $links;
    }

    public function activeBranches(): HasMany
    {
        return $this->branches()->where('is_active', true);
    }

    /**
     * Bien the sang hon cua mau nhan dien, dung cho chu va vien.
     * Tron mau goc voi trang theo ti le $amount.
     */
    public function accentSoft(float $amount = 0.35): string
    {
        return $this->mixWithWhite($this->accent_color ?: '#c8a15a', $amount, '#e0c48c');
    }

    public function ground(): string
    {
        return $this->ground_color ?: self::DEFAULT_GROUND;
    }

    /**
     * Cac sac do sang dan cua nen, dung cho the va duong ke.
     *
     * @return array{panel: string, panel2: string, line: string, field: string}
     */
    public function groundShades(): array
    {
        return [
            'panel' => $this->mixWithWhite($this->ground(), 0.07, '#1b1815'),
            'panel2' => $this->mixWithWhite($this->ground(), 0.11, '#221e1a'),
            'line' => $this->mixWithWhite($this->ground(), 0.18, '#332c25'),
            'field' => $this->mixWithWhite($this->ground(), 0.035, '#14110e'),
        ];
    }

    /**
     * Kich thuoc that cua mot anh trong thu muc public, dang [rong, cao].
     * Khai bao san trong the img de trang khong bi giat khi anh tai xong.
     *
     * @return array{0: int, 1: int}|null
     */
    public function imageSize(?string $path): ?array
    {
        if (! $path || ! is_file(public_path($path))) {
            return null;
        }

        $size = @getimagesize(public_path($path));

        return $size ? [(int) $size[0], (int) $size[1]] : null;
    }

    public function hasLogo(): bool
    {
        return $this->logo_path && is_file(public_path($this->logo_path));
    }

    /**
     * Khai bao @font-face cho cac font rieng cua quan.
     * Tra ve chuoi rong neu quan khong tai font nao len.
     */
    public function fontFaceCss(): string
    {
        $css = '';

        foreach ($this->webFonts() as $role => $font) {
            $css .= sprintf(
                '@font-face{font-family:"brand-%s";src:url("%s") format("%s");font-weight:100 900;font-display:swap;}',
                $role,
                $font['url'],
                $font['format']
            );
        }

        return $css;
    }

    /**
     * Cac font that su tai ve cho trinh duyet, dang
     * ['display' => ['url' =>, 'format' =>, 'mime' =>], ...].
     *
     * Ban .woff2 cung ten duoc uu tien: nhe hon file goc chung mot nua, ma van
     * giu nguyen file goc trong co so du lieu cho phan ve anh xac nhan (GD chi
     * doc duoc ttf/otf).
     *
     * @return array<string, array{url: string, format: string, mime: string}>
     */
    public function webFonts(): array
    {
        $fonts = [];

        foreach (['display' => $this->display_font_path, 'body' => $this->body_font_path] as $role => $path) {
            if (! $path || ! is_file(public_path($path))) {
                continue;
            }

            $compact = preg_replace('/\.[^.]+$/', '.woff2', $path);

            if ($compact && is_file(public_path($compact))) {
                $path = $compact;
            }

            [$format, $mime] = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'otf' => ['opentype', 'font/otf'],
                'woff' => ['woff', 'font/woff'],
                'woff2' => ['woff2', 'font/woff2'],
                default => ['truetype', 'font/ttf'],
            };

            $fonts[$role] = [
                'url' => Assets::url($path),
                'format' => $format,
                'mime' => $mime,
            ];
        }

        return $fonts;
    }

    /**
     * Anh bia kem cac ban thu nho, de dien thoai khong phai tai ban 1600px.
     *
     * @return array{src: string, srcset: ?string, width: ?int, height: ?int}|null
     */
    public function coverSources(): ?array
    {
        if (! $this->hasCover()) {
            return null;
        }

        $file = public_path($this->cover_path);
        $size = @getimagesize($file);

        $set = [];

        foreach (self::COVER_WIDTHS as $width) {
            $narrow = preg_replace('/(\.[^.]+)$/', '-w'.$width.'$1', $this->cover_path);

            if ($narrow && is_file(public_path($narrow))) {
                $set[] = Assets::url($narrow).' '.$width.'w';
            }
        }

        if ($set && $size) {
            $set[] = Assets::url($this->cover_path).' '.$size[0].'w';
        }

        return [
            'src' => Assets::url($this->cover_path),
            'srcset' => $set ? implode(', ', $set) : null,
            'width' => $size ? (int) $size[0] : null,
            'height' => $size ? (int) $size[1] : null,
        ];
    }

    /** Bo font cho noi dung chay. */
    public function bodyStack(): string
    {
        return $this->body_font_path && is_file(public_path($this->body_font_path))
            ? '"brand-body", '.self::FALLBACK_STACK
            : self::FALLBACK_STACK;
    }

    /** Bo font cho tieu de; khong co font rieng thi dung luon font noi dung. */
    public function displayStack(): string
    {
        return $this->display_font_path && is_file(public_path($this->display_font_path))
            ? '"brand-display", '.$this->bodyStack()
            : $this->bodyStack();
    }

    protected function mixWithWhite(string $hex, float $amount, string $fallback): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return $fallback;
        }

        $mixed = array_map(function (string $part) use ($amount) {
            $value = hexdec($part);

            return (int) round($value + (255 - $value) * $amount);
        }, str_split($hex, 2));

        return '#'.implode('', array_map(fn (int $v) => str_pad(dechex($v), 2, '0', STR_PAD_LEFT), $mixed));
    }
}
