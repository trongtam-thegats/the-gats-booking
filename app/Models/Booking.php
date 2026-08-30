<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SEATED = 'seated';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    /** Cac trang thai van con giu ban -> tinh vao suc chua. */
    public const BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_SEATED,
    ];

    protected $fillable = [
        'code', 'branch_id', 'customer_name', 'customer_phone', 'customer_email',
        'party_size', 'booking_date', 'start_time', 'end_time', 'area_id',
        'status', 'source', 'locale', 'note', 'internal_note',
        'confirmed_by', 'confirmed_at', 'cancelled_at', 'cancel_reason',
        'cancelled_by_type', 'seated_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'seated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function diningTables(): BelongsToMany
    {
        return $this->belongsToMany(DiningTable::class, 'booking_dining_table');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class)->latest('id');
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', self::BLOCKING_STATUSES);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('booking_date', $date);
    }

    /** Ma dat ban gui cho khach, vi du TG7KQ4M2. */
    public static function generateCode(): string
    {
        do {
            $code = 'TG'.strtoupper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function statusLabel(?string $locale = null): string
    {
        $key = 'booking.status.'.$this->status;
        $label = __($key, [], $locale);

        return $label === $key ? $this->status : $label;
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'online' => 'Đặt online',
            'phone' => 'Điện thoại',
            'walk_in' => 'Khách vãng lai',
            default => $this->source,
        };
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::BLOCKING_STATUSES, true);
    }

    /** Khach chi tu huy duoc khi booking chua dien ra va chua bi huy. */
    public function customerCanCancel(): bool
    {
        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true)) {
            return false;
        }

        return $this->startsAt()->isFuture();
    }

    /**
     * Thoi diem bat dau that su cua don.
     *
     * Quan dong cua sau nua dem nen gio nho hon gio mo cua thuoc rang sang hom
     * sau - don 01:00 cua dem 30/8 dien ra luc 01:00 ngay 31/8. Quy tac nam o
     * Branch de ca he thong chi co mot ban.
     */
    public function startsAt(): Carbon
    {
        return Branch::thoiDiemTrongDem(
            $this->booking_date,
            (string) $this->start_time,
            $this->branch?->openMinutes() ?? 0
        );
    }

    public function timeRangeLabel(): string
    {
        return substr((string) $this->start_time, 0, 5).' - '.substr((string) $this->end_time, 0, 5);
    }

    public function tableCodes(): string
    {
        $codes = $this->diningTables->pluck('code')->all();

        return $codes ? implode(', ', $codes) : '—';
    }
}
