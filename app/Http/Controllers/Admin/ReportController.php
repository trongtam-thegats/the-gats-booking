<?php

namespace App\Http\Controllers\Admin;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends AdminController
{
    /** Cac khoang thoi gian bam nhanh: so ngay => nhan. */
    public const PRESETS = [
        7 => '7 ngày',
        30 => '30 ngày',
        90 => '90 ngày',
    ];

    public function __construct(protected ReportService $reports) {}

    public function index(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches);

        [$from, $to] = $this->resolveRange($request);

        // Khong chon dia diem cu the thi bao cao gop cac dia diem duoc phep xem.
        $branchIds = $branch
            ? [$branch->id]
            : ($request->user()->isAdmin() ? null : $branches->pluck('id')->all());

        $report = $this->reports->build($from, $to, $branchIds);

        return view('admin.reports.index', compact('branches', 'branch', 'report', 'from', 'to'));
    }

    /**
     * Khoang ngay dang xem. Mac dinh 30 ngay gan nhat tinh den hom nay.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveRange(Request $request): array
    {
        $preset = (int) $request->query('ngay', 0);

        if (isset(self::PRESETS[$preset])) {
            return [
                Carbon::today()->subDays($preset - 1)->toDateString(),
                Carbon::today()->toDateString(),
            ];
        }

        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            $data['from'] ?? Carbon::today()->subDays(29)->toDateString(),
            $data['to'] ?? Carbon::today()->toDateString(),
        ];
    }
}
