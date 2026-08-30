<?php

namespace App\Http\Controllers\Admin;

use App\Models\GuestNote;
use App\Models\Invoice;
use App\Models\PosCustomer;
use App\Services\CustomerInsightService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        'avg' => 'Chi nhiều nhất mỗi lần',
        'recent' => 'Ghé gần đây nhất',
        'vang' => 'Vắng lâu nhất',
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

        $soLuong = max(10, min(500, (int) $request->query('so-luong', 100)));
        $loc = $this->boLoc($request);

        // Tinh mot lan roi vua dem tong theo nhom, vua loc ra phan hien thi.
        $tatCa = $this->insight->tatCaKhach($branchIds);
        $daLoc = $this->insight->locVaXep($tatCa, $sapXep, $loc);

        return view('admin.customers.index', [
            'branches' => $branches,
            'branch' => $branch,
            'tongQuan' => $this->insight->overview($branchIds),
            'tatCa' => $tatCa,
            'khach' => $daLoc->take($soLuong),
            'soKhopLoc' => $daLoc->count(),
            'tienKhopLoc' => (float) $daLoc->sum('spend'),
            'sapXep' => $sapXep,
            'soLuong' => $soLuong,
            'loc' => $loc,
            'nhomTinhTrang' => $tatCa->groupBy('segment')->map->count(),
            'nhomXemXet' => $tatCa->groupBy('review')->map->count(),
            'hangThe' => $tatCa->map(fn ($k) => $k['card']?->tier ?: '(không hạng)')
                ->countBy()->sortDesc(),
            'theoThang' => $this->insight->theoThang($branchIds),
            'theoSoLan' => $this->insight->theoSoLanGhe($tatCa),
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

    /**
     * Danh dau "da xem xet khach nay roi".
     *
     * Khong luu nhan "da roi bo" cung nhac: chi ghi thoi diem xem xet, con
     * viec khach da quay lai hay chua thi he thong tu so voi lan ghe gan nhat.
     */
    public function review(Request $request, string $phone)
    {
        abort_unless($request->user()->canWrite(), 403);

        $data = $request->validate([
            'review_outcome' => ['nullable', Rule::in(array_keys(GuestNote::KET_QUA))],
            'review_note' => ['nullable', 'string', 'max:500'],
            'bo_danh_dau' => ['nullable', 'boolean'],
        ]);

        $phone = GuestNote::normalize($phone);
        $brandId = $this->brandIdFor($request, $phone);

        abort_if(! $brandId, 422, 'Chưa xác định được khách này thuộc quán nào.');

        $goBo = $request->boolean('bo_danh_dau');

        GuestNote::updateOrCreate(
            ['brand_id' => $brandId, 'phone' => $phone],
            [
                'reviewed_at' => $goBo ? null : now(),
                'reviewed_by' => $goBo ? null : $request->user()->id,
                'review_outcome' => $goBo ? null : ($data['review_outcome'] ?? null),
                'review_note' => $goBo ? null : ($data['review_note'] ?? null),
            ]
        );

        return back()->with(
            'status',
            $goBo
                ? 'Đã bỏ đánh dấu, khách quay lại danh sách chưa xem xét.'
                : 'Đã đánh dấu xem xét xong. Khách ghé lại lần nữa thì hệ thống tự chuyển sang “Đã ghé lại”.'
        );
    }

    /**
     * Bo loc lay tu dia chi trang, da lam sach.
     *
     * @return array<string, mixed>
     */
    protected function boLoc(Request $request): array
    {
        $loc = [];

        foreach (['segment' => CustomerInsightService::TINH_TRANG, 'review' => CustomerInsightService::XEM_XET] as $khoa => $hopLe) {
            $gt = array_filter((array) $request->query($khoa, []), fn ($x) => isset($hopLe[$x]));

            if ($gt) {
                $loc[$khoa] = array_values($gt);
            }
        }

        if ($hang = array_filter((array) $request->query('tier', []))) {
            $loc['tier'] = array_values(array_map('strval', $hang));
        }

        foreach (['visits_min' => 'so-lan', 'spend_min' => 'chi-tu', 'vang_min' => 'vang-tu'] as $khoa => $thamSo) {
            $gt = $request->query($thamSo);

            if ($gt !== null && $gt !== '' && is_numeric($gt) && (float) $gt > 0) {
                $loc[$khoa] = (float) $gt;
            }
        }

        if ($request->boolean('co-dat-ban')) {
            $loc['co_dat_ban'] = true;
        }

        if ($request->boolean('co-vang-mat')) {
            $loc['co_vang_mat'] = true;
        }

        if ($tim = trim((string) $request->query('tim', ''))) {
            $loc['tim'] = $tim;
        }

        return $loc;
    }

    /**
     * Ghi chu khach gan theo quan. Nguoi dung thuoc quan nao thi dung quan do;
     * quan tri thi lay theo quan cua hoa don gan nhat.
     */
    protected function brandIdFor(Request $request, string $phone): ?int
    {
        if ($request->user()->brand_id) {
            return (int) $request->user()->brand_id;
        }

        $hoaDon = Invoice::with('branch')
            ->choDiaDiem($request->user()->visibleBranchIds())
            ->where('customer_phone', $phone)
            ->orderByDesc('paid_at')
            ->first();

        return $hoaDon?->branch?->brand_id
            ?? PosCustomer::where('phone', $phone)->value('brand_id');
    }
}
