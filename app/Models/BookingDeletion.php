<?php

namespace App\Models;

use App\Support\NguonDatBan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mot dong = mot lan quan tri xoa han mot dat ban.
 *
 * Bang nay chi ghi them, khong bao gio sua hay xoa: no la doi chung duy nhat
 * con lai sau khi dong bookings da bien mat.
 */
class BookingDeletion extends Model
{
    protected $fillable = [
        'code', 'branch_id', 'branch_name', 'customer_name', 'customer_phone',
        'party_size', 'booking_date', 'start_time', 'status', 'source',
        'du_lieu', 'deleted_by', 'deleted_by_name', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'du_lieu' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function statusLabel(): string
    {
        $key = 'booking.status.'.$this->status;
        $label = __($key);

        return $label === $key ? $this->status : $label;
    }

    public function sourceLabel(): string
    {
        return NguonDatBan::nhan($this->source);
    }
}
