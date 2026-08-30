<?php

namespace App\Models;

use App\Support\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'brand_id', 'branch_id', 'phone', 'is_active', 'must_change_password', 'password_changed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Roles::ADMIN;
    }

    /** Vai chi xem thi moi thao tac ghi deu bi chan. */
    public function canWrite(): bool
    {
        return Roles::canWrite($this->role);
    }

    public function canManageSetup(): bool
    {
        return Roles::canManageSetup($this->role);
    }

    public function roleLabel(): string
    {
        return Roles::label($this->role);
    }

    /**
     * Danh sach id dia diem nguoi dung duoc xem.
     * null = xem tat ca (quan tri).
     *
     * Nguoi dung gan voi mot quan thi thay moi dia diem cua quan do; neu gan
     * them mot dia diem cu the thi chi thay dia diem ay.
     *
     * @return array<int>|null
     */
    public function visibleBranchIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        if ($this->branch_id) {
            return [(int) $this->branch_id];
        }

        if ($this->brand_id) {
            return Branch::where('brand_id', $this->brand_id)->pluck('id')->map('intval')->all();
        }

        return [];
    }

    public function canAccessBranch(int $branchId): bool
    {
        $ids = $this->visibleBranchIds();

        return $ids === null || in_array($branchId, $ids, true);
    }
}
