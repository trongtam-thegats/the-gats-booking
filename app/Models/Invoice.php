<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mot hoa don nhap tu POS. Chi doc trong khu quan ly.
 *
 * Cau noi voi phan dat ban la cot customer_phone: cung mot so dien thoai thi
 * ghep duoc lich su dat ban voi lich su chi tieu cua cung mot khach.
 */
class Invoice extends Model
{
    /** Trang thai POS ghi cho hoa don da huy. */
    public const HUY = 'Đã hủy';

    protected $fillable = [
        'branch_id', 'code', 'status', 'source', 'ordered_at', 'paid_at',
        'subtotal', 'vat', 'service_fee', 'discount', 'delivery_fee', 'total',
        'tip', 'refund', 'payment_method',
        'customer_name', 'customer_phone', 'membership_card',
        'party_size', 'service_type', 'area', 'table_code', 'cashier',
        'customer_note', 'order_note',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'tip' => 'decimal:2',
            'refund' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Hoa don thuc su mang lai doanh thu (bo don da huy). */
    public function scopeThanhCong(Builder $query): Builder
    {
        return $query->where('status', '!=', self::HUY);
    }

    /** Hoa don co ghi nhan duoc khach la ai. */
    public function scopeCoKhach(Builder $query): Builder
    {
        return $query->whereNotNull('customer_phone')->where('customer_phone', '!=', '');
    }

    /** Gioi han theo cac dia diem nguoi dung duoc xem; null = xem tat ca. */
    public function scopeChoDiaDiem(Builder $query, ?array $branchIds): Builder
    {
        return $branchIds === null ? $query : $query->whereIn('branch_id', $branchIds ?: [0]);
    }

    public function daHuy(): bool
    {
        return $this->status === self::HUY;
    }
}
