<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SapoDongBoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nhan tung lo hoa don doc thang tu trang quan tri Sapo FnB.
 *
 * Trinh duyet dang dang nhap Sapo goi /admin/orders.json, gan them thong tin
 * khach, roi POST sang day. Khoa bang ma bi mat SAPO_DONG_BO_TOKEN; chua khai
 * bao ma thi coi nhu cong nay khong ton tai.
 */
class SapoDongBoController extends Controller
{
    public const TOI_DA_MOI_LO = 100;

    public function store(Request $request, SapoDongBoService $dongBo): JsonResponse
    {
        $ma = (string) config('booking.sapo_token');

        abort_if($ma === '', 404);
        abort_unless(hash_equals($ma, (string) $request->header('X-Dong-Bo-Token')), 403);

        $data = $request->validate([
            'store.id' => ['nullable', 'integer'],
            'store.name' => ['nullable', 'string', 'max:255'],
            'orders' => ['present', 'array', 'max:'.self::TOI_DA_MOI_LO],
            'orders.*' => ['array'],
        ]);

        $branch = $dongBo->diaDiem($data['store']['id'] ?? null, $data['store']['name'] ?? null);

        if (! $branch) {
            return response()->json([
                'loi' => 'Chưa biết cửa hàng Sapo "'.($data['store']['name'] ?? '?')
                    .'" (id '.($data['store']['id'] ?? '?').') ứng với địa điểm nào. Khai báo trong SAPO_STORES.',
            ], 422);
        }

        return response()->json(['dia_diem' => $branch->name] + $dongBo->nhap($data['orders'], $branch));
    }
}
