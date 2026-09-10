<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mot dong mat hang trong hoa don POS.
 *
 * Chi doc: moi lan quan xuat tep moi thi nhap de, khong ai sua tay o day.
 */
class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'sku', 'name', 'category', 'quantity', 'unit', 'unit_price', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
