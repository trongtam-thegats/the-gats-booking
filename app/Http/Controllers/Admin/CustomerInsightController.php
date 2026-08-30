<?php

namespace App\Http\Controllers\Admin;

use App\Services\CustomerInsightService;
use Illuminate\Http\Request;

/**
 * Phan tich khach hang: ghep hoa don POS voi lich su dat ban de biet ai la
 * khach quen, ai dang thua dan, va nen cham soc ai truoc.
 */
class CustomerInsightController extends AdminController
{
    /** Cach xep hang cho phep chon. */
    public const SAP_XEP = [
        'spend' => 'Chi tiêu nhiều nhất',
        'visits' => 'Ghé nhiều lần nhất',
        'recent' => 'Ghé gần đây nhất',
        'risk' => 'Cần chăm sóc gấp',
    ];

    public function __construct(protected CustomerInsightService $insight) {}

    public function index(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches);
        $branchIds = $branch ? [$branch->id] : $request->user()->visibleBranchIds();

        $sapXep = $request->query('sap-xep');
        $sapXep = isset(self::SAP_XEP[$sapXep]) ? $sapXep : 'spend';

        $soLuong = (int) $request->query('so-luong', 100);
        $soLuong = max(10, min(300, $soLuong));

        $khach = $this->insight->ranking($branchIds, $sapXep, $soLuong);

        return view('admin.customers.index', [
            'branches' => $branches,
            'branch' => $branch,
            'tongQuan' => $this->insight->overview($branchIds),
            'khach' => $khach,
            'sapXep' => $sapXep,
            'soLuong' => $soLuong,
            'nhomTinhTrang' => $khach->groupBy('segment')->map->count(),
        ]);
    }

    public function show(Request $request, string $phone)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches);
        $branchIds = $branch ? [$branch->id] : $request->user()->visibleBranchIds();

        $ho = $this->insight->profile($phone, $branchIds);

        abort_unless($ho, 404, 'Chưa có dữ liệu về số điện thoại này.');

        return view('admin.customers.show', [
            'branches' => $branches,
            'branch' => $branch,
            'ho' => $ho,
        ]);
    }
}
