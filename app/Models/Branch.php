<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'name', 'slug', 'phone', 'address', 'description', 'map_url',
        'open_time', 'close_time', 'last_booking_time', 'slot_minutes', 'turn_minutes',
        'min_lead_minutes', 'max_advance_days', 'max_party_size',
        'auto_confirm', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'auto_confirm' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class)->orderBy('sort_order')->orderBy('name');
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('sort_order')->orderBy('code');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(BranchClosure::class);
    }

    /**
     * Cau nhac khach: sau gio chot booking quan van mo nhung khong nhan dat moi.
     * Tra ve null neu quan khong khai bao gio chot rieng.
     */
    public function lateNote(): ?string
    {
        if (! $this->last_booking_time) {
            return null;
        }

        $last = substr((string) $this->last_booking_time, 0, 5);
        $close = substr((string) $this->close_time, 0, 5);

        if ($last === $close) {
            return null;
        }

        return __('booking.form.late_note', ['last' => $last, 'close' => $close]);
    }

    /** Tong so cho ngoi kha dung cua chi nhanh. */
    public function totalSeats(): int
    {
        return (int) $this->diningTables()->where('is_active', true)->sum('seats_max');
    }
}
