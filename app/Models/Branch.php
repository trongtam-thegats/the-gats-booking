<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
    /** Gio mo cua quy ve so phut tinh tu 00:00. */
    public function openMinutes(): int
    {
        return self::phutTrongNgay((string) $this->open_time);
    }

    /**
     * Thoi diem thuc te cua mot moc gio thuoc dem kinh doanh cua chi nhanh.
     *
     * Quan dong cua sau nua dem, nen don "19:00 ngay 30/8" va don "01:00 ngay
     * 30/8" deu thuoc dem 30/8 - nhung don sau roi vao rang sang 31/8.
     *
     * Day la NGUON DUY NHAT cua quy tac nay. Booking::startsAt() va
     * AvailabilityService::slotStartsAt() deu goi ve day; truoc kia moi cho
     * tu tinh mot kieu va do la goc cua ca loat loi gio giac ca khuya.
     */
    public static function thoiDiemTrongDem(Carbon|string $ngay, string $gio, int $phutMoCua): Carbon
    {
        $phut = self::phutTrongNgay($gio);
        $moc = $ngay instanceof Carbon ? $ngay->copy() : Carbon::parse($ngay);

        if ($phut < $phutMoCua) {
            $moc->addDay();
        }

        return $moc->setTime(intdiv($phut, 60), $phut % 60);
    }

    /** "01:30:00" -> 90. */
    public static function phutTrongNgay(string $gio): int
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', substr(trim($gio), 0, 5)), 2, 0));

        return $h * 60 + $m;
    }

    public function totalSeats(): int
    {
        return (int) $this->diningTables()->where('is_active', true)->sum('seats_max');
    }
}
