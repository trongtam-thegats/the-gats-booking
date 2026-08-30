<?php

namespace App\Http\Controllers\Admin;

use App\Models\GuestNote;
use App\Models\Invoice;
use App\Models\PosCustomer;
use App\Services\CustomerInsightService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $khoang = (int) $request->query('khoang', 0);
        $khoang = isset(self::KHOANG[$khoang]) ? $khoang : 0;
        $tuNgay = $khoang ? now()->subMonths($khoang)->startOfDay() : null;

        // Tinh mot lan roi vua dem tong theo nhom, vua loc ra phan hien thi.
        $tatCa = $this->insight->tatCaKhach($branchIds, $tuNgay);
        $daLoc = $this->insight->locVaXep($tatCa, $sapXep, $loc);

        $tongQuan = $this->insight->overview($branchIds, $tuNgay);

        return view('admin.customers.index', [
            'soSanh' => $this->soSanhKyTruoc($branchIds, $khoang, $tuNgay, $tongQuan),
            'branches' => $branches,
            'branch' => $branch,
            'tongQuan' => $tongQuan,
            'khoang' => $khoang,
            'tuNgay' => $tuNgay,
            'tatCa' => $tatCa,
            'khach' => $daLoc->take($soLuong),
            'soKhopLoc' => $daLoc->count(),
            'tienKhopLoc' => (float) $daLoc->sum('spend'),
            'sapXep' => $sapXep,
            'soLuong' => $soLuong,
            'loc' => $loc,
            'nhomTinhTrang' => $tatCa->groupBy('trang_thai')->map->count(),
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
     * Khoang thoi gian de tinh lai moi chi so, tinh bang thang.
     *
     * Doi khoang la doi han goc nhin: mot thang cho biet thang vua roi ai quay
     * lai va ai moi den; ba den sau thang la trung han; mot nam du de nhin ra
     * ai that su gan bo.
     */
    public const KHOANG = [
        1 => '1 tháng gần nhất',
        3 => '3 tháng gần nhất',
        6 => '6 tháng gần nhất',
        12 => '12 tháng gần nhất',
    ];

    /** Cac muc chon san cua o "ghe it nhat". */
    public const MOC_SO_LAN = [2 => 'Từ 2 lần', 5 => 'Từ 5 lần', 10 => 'Từ 10 lần', 20 => 'Từ 20 lần'];

    /** Cac muc chon san cua o "chi tieu tu". */
    public const MOC_CHI_TIEU = [
        1000000 => 'Từ 1 triệu',
        5000000 => 'Từ 5 triệu',
        10000000 => 'Từ 10 triệu',
        50000000 => 'Từ 50 triệu',
    ];

    /** Cac muc chon san cua o "vang tu". */
    public const MOC_VANG = [
        30 => 'Vắng từ 30 ngày',
        60 => 'Vắng từ 60 ngày',
        90 => 'Vắng từ 90 ngày',
        180 => 'Vắng từ 180 ngày',
    ];

    /** Lien quan den dat ban. */
    public const MOC_DAT_BAN = ['co' => 'Từng đặt bàn', 'vang' => 'Từng đặt rồi không đến'];

    /**
     * So sanh voi ky lien truoc co cung do dai.
     *
     * Chi so sanh khi ky truoc nam tron trong khoang da co du lieu. Drinking
     * Healing moi co chin thang hoa don; dem "12 thang nay so voi 12 thang
     * truoc" o do se ra muc tang gap may lan, hoan toan la bia.
     *
     * @param  array<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    protected function soSanhKyTruoc(?array $branchIds, int $khoang, ?Carbon $tuNgay, array $nay): ?array
    {
        if (! $khoang || ! $tuNgay) {
            return null;
        }

        $batDauKyTruoc = $tuNgay->copy()->subMonths($khoang);
        $som = $this->insight->ngaySomNhat($branchIds);
        $truoc = $this->insight->overview($branchIds, $batDauKyTruoc, $tuNgay);

        // Ky truoc co nam tron trong khoang da co du lieu khong. Drinking
        // Healing moi co chin thang hoa don: "6 thang nay so voi 6 thang truoc"
        // o do ra +4180%, khong phai vi quan bung no ma vi ky truoc gan nhu
        // rong. Thieu du lieu thi bao tang giam bang 0, khong bia so.
        $duDuLieu = $som && $som->lte($batDauKyTruoc);

        $chiSo = ['customers', 'returning', 'returning_revenue', 'revenue'];
        $thayDoi = [];

        foreach ($chiSo as $khoa) {
            $thayDoi[$khoa] = $duDuLieu
                ? $this->phanTramDoi((float) $nay[$khoa], (float) $truoc[$khoa])
                : 0.0;
        }

        return [
            'du_du_lieu' => $duDuLieu,
            'tu' => $batDauKyTruoc,
            'den' => $tuNgay,
            'truoc' => $truoc,
            'thay_doi' => $thayDoi,
        ];
    }

    /**
     * Muc tang giam so voi ky truoc, tinh bang phan tram.
     *
     * Ky truoc khong co gi de so thi tra ve 0 chu khong phai vo cuc: mot con
     * so tang vo han khong noi len dieu gi, ma cho trong thi nguoi doc lai
     * tuong he thong hong.
     */
    protected function phanTramDoi(float $nay, float $truoc): float
    {
        if ($truoc <= 0) {
            return 0.0;
        }

        return round(($nay - $truoc) / $truoc * 100, 1);
    }

    /**
     * Bo loc lay tu dia chi trang, da lam sach.
     *
     * Moi o deu la mot lua chon don de thanh mot hang thanh gon, chon la chay
     * luon. Ben duoi service van nhan mang, nen sau nay muon cho chon nhieu
     * gia tri thi khong phai sua gi them.
     *
     * @return array<string, mixed>
     */
    protected function boLoc(Request $request): array
    {
        $loc = [];

        $chon = [
            'segment' => ['tinh-trang', CustomerInsightService::moiTinhTrang()],
            'review' => ['xem-xet', CustomerInsightService::XEM_XET],
        ];

        foreach ($chon as $khoa => [$thamSo, $hopLe]) {
            $gt = (string) $request->query($thamSo, '');

            if (isset($hopLe[$gt])) {
                $loc[$khoa] = [$gt];
            }
        }

        if ($hang = trim((string) $request->query('hang-the', ''))) {
            $loc['tier'] = [$hang];
        }

        $moc = [
            'visits_min' => ['so-lan', self::MOC_SO_LAN],
            'spend_min' => ['chi-tu', self::MOC_CHI_TIEU],
            'vang_min' => ['vang-tu', self::MOC_VANG],
        ];

        foreach ($moc as $khoa => [$thamSo, $hopLe]) {
            $gt = (int) $request->query($thamSo, 0);

            if (isset($hopLe[$gt])) {
                $loc[$khoa] = $gt;
            }
        }

        $datBan = (string) $request->query('dat-ban', '');

        if ($datBan === 'co') {
            $loc['co_dat_ban'] = true;
        } elseif ($datBan === 'vang') {
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
