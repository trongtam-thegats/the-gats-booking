<?php

namespace App\Models;

use App\Support\SoDienThoai;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ghi chu cua quan ve mot khach, gan theo so dien thoai.
 *
 * Khong phai bang khach hang: danh sach khach van suy ra tu bang bookings.
 * Day chi la nhung gi quan tu ghi them - VIP, di ung, hoac chan dat ban.
 */
class GuestNote extends Model
{
    /**
     * Ket qua sau khi xem xet mot khach. Co tinh de ngan va de bo trong:
     * cai chinh la biet "da xem xet roi", con ly do chi la ghi chu them.
     *
     * @var array<string, string>
     */
    public const KET_QUA = [
        'da_lien_he' => 'Đã liên hệ',
        'se_quay_lai' => 'Hẹn sẽ quay lại',
        'khong_quan_tam' => 'Không quan tâm',
        'da_chuyen_di' => 'Đã chuyển đi xa',
        'so_sai' => 'Số sai, không liên lạc được',
        'da_roi_bo' => 'Đã rời bỏ',
        'khong_can' => 'Không cần chăm sóc',
    ];

    protected $fillable = [
        'brand_id', 'phone', 'name', 'note', 'is_vip', 'is_blocked', 'updated_by',
        'reviewed_at', 'reviewed_by', 'review_outcome', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'is_blocked' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function ketQuaLabel(): ?string
    {
        return $this->review_outcome ? (self::KET_QUA[$this->review_outcome] ?? $this->review_outcome) : null;
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Chuan hoa so dien thoai de tra cuu khop nhau du khach go kieu gi.
     *
     * Dung chung mot chuan voi hoa don va don dat ban, neu khong thi ghi chu
     * ve khach se khong ghep duoc voi lich su chi tieu cua chinh khach do -
     * ro nhat la voi so nuoc ngoai.
     */
    public static function normalize(?string $phone): string
    {
        return SoDienThoai::chuan($phone);
    }
}
