<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DiningTable extends Model
{
    /** Loai cho ngoi => ten hien thi. */
    public const TYPES = [
        'bar_seat' => 'Ghế quầy bar',
        'high_table' => 'Bàn cao',
        'dining' => 'Bàn ăn',
        'sofa' => 'Sofa',
        'booth' => 'Booth',
        'other' => 'Khác',
    ];

    protected $fillable = [
        'branch_id', 'area_id', 'code', 'table_type', 'seats_min', 'seats_max',
        'combinable', 'is_active', 'note', 'sort_order', 'aliases',
    ];

    protected function casts(): array
    {
        return [
            'combinable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_dining_table');
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' ('.$this->seats_max.' chỗ)';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->table_type] ?? $this->table_type;
    }

    /** "2–4 khách", hoac "1 khách" khi suc chua co dinh. */
    public function capacityLabel(): string
    {
        return $this->seats_min === $this->seats_max
            ? $this->seats_max.' khách'
            : $this->seats_min.'–'.$this->seats_max.' khách';
    }
}
