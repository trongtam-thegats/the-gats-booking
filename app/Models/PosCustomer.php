<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The khach hang ben POS: hang the, diem tich luy, tong chi tieu.
 *
 * Day la anh chup tai thoi diem xuat tep, khong phai so lieu song. Con so he
 * thong tu tinh tu bang invoices moi la so lieu chinh; cac cot o day dung de
 * doi chieu va de biet nhung gi POS co ma hoa don khong noi (sinh nhat, hang the).
 */
class PosCustomer extends Model
{
    protected $fillable = [
        'brand_id', 'phone', 'name', 'email', 'birthday', 'gender',
        'province', 'district', 'address', 'note', 'joined_at',
        'invoice_count', 'total_spent', 'member_code', 'tier', 'points', 'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'joined_at' => 'datetime',
            'exported_at' => 'datetime',
            'total_spent' => 'decimal:2',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Sinh nhat trong vong bao nhieu ngay toi, null neu khong khai bao. */
    public function ngayToiSinhNhat(): ?int
    {
        if (! $this->birthday) {
            return null;
        }

        $namNay = $this->birthday->copy()->setYear(now()->year);

        if ($namNay->isBefore(now()->startOfDay())) {
            $namNay->addYear();
        }

        return (int) now()->startOfDay()->diffInDays($namNay);
    }
}
