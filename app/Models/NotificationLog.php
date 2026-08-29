<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'booking_id', 'channel', 'event', 'recipient', 'status', 'message', 'error',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'sent' => 'Đã gửi',
            'skipped' => 'Bỏ qua (chưa cấu hình)',
            'failed' => 'Lỗi',
            default => $this->status,
        };
    }

    public function channelLabel(): string
    {
        return match ($this->channel) {
            'email' => 'Email',
            'sms' => 'SMS',
            'zalo' => 'Zalo OA',
            default => $this->channel,
        };
    }
}
