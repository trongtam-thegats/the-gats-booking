<?php

namespace App\Models;

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
    protected $fillable = [
        'brand_id', 'phone', 'name', 'note', 'is_vip', 'is_blocked', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Chuan hoa so dien thoai de tra cuu khop nhau du khach go kieu gi. */
    public static function normalize(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }
}
