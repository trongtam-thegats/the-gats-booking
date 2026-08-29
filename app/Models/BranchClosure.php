<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchClosure extends Model
{
    protected $fillable = ['branch_id', 'date', 'start_time', 'end_time', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Nghi tron ngay hay chi mot khung gio. */
    public function isFullDay(): bool
    {
        return $this->start_time === null || $this->end_time === null;
    }
}
