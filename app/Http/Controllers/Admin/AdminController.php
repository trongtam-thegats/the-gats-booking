<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Base cho khu quan tri: gom logic "nguoi dung nay duoc thay chi nhanh nao".
 */
abstract class AdminController extends Controller
{
    /** @return Collection<int, Branch> */
    protected function accessibleBranches(Request $request): Collection
    {
        $ids = $request->user()->visibleBranchIds();

        return Branch::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Chi nhanh dang duoc chon tren thanh loc; null neu admin chon "tat ca".
     */
    protected function selectedBranch(Request $request, Collection $branches): ?Branch
    {
        $requested = $request->query('branch');

        if ($requested) {
            $branch = $branches->firstWhere('id', (int) $requested)
                ?? $branches->firstWhere('slug', $requested);

            if ($branch) {
                return $branch;
            }
        }

        // Nguoi khong phai admin luon bi ghim vao chi nhanh cua minh.
        if (! $request->user()->isAdmin()) {
            return $branches->first();
        }

        return null;
    }

    protected function authorizeBranch(Request $request, int $branchId): void
    {
        abort_unless($request->user()->canAccessBranch($branchId), 403, 'Booking này thuộc chi nhánh khác.');
    }
}
