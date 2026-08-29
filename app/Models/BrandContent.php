<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chu hien tren trang dat ban, sua duoc trong khu quan tri.
 *
 * Luu dang key/value de them muc moi khong phai doi cau truc bang -
 * chi can them mot dong vao Brand::TEXTS.
 */
class BrandContent extends Model
{
    protected $fillable = ['brand_id', 'key', 'locale', 'value'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
