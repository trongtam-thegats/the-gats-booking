<?php

namespace App\Http\Controllers\Admin;

use App\Models\GuestNote;
use App\Models\Invoice;
use App\Services\GuestProfileService;
use App\Support\SoDienThoai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O tim khach cho le tan: go so dien thoai, ten hoac ma dat ban.
 *
 * Trang nay chi con lam viec TIM. Tim ra roi thi chuyen sang trang chi tiet
 * khach (customers.show) - tu 09/2026 chi con mot trang chi tiet duy nhat,
 * dung chung cho ca tra cuu lan phan tich.
 */
class GuestController extends AdminController
{
    public function __construct(protected GuestProfileService $guests) {}

    public function index(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $phone = trim((string) $request->query('phone', ''));
        $results = $phone === ''
            ? $this->guests->search($term, $request->user()->visibleBranchIds())
            : collect();

        // Chi co dung mot khach khop thi mo thang ho so, khoi bat bam them lan nua.
        if ($phone === '' && $results->count() === 1) {
            $phone = $results->first()['phone'];
        }

        // Chi tiet mot khach chi con MOT trang duy nhat (customers.show), dung
        // chung cho ca tra cuu lan phan tich - truoc day hai trang hien gan het
        // cung mot thu, nguoi dung phai nho vao dau moi thay cai minh can.
        if ($phone !== '') {
            return redirect()->route('admin.customers.show', GuestNote::normalize($phone));
        }

        return view('admin.guests.index', compact('term', 'results'));
    }

    /**
     * Tra nhanh mot so dien thoai, tra ve JSON cho form dat ban ho khach.
     *
     * Chi mo cho vai duoc phep dat ban (xem route). Co y KHONG dua ra trang
     * khach: trang do ai cung vao duoc, de lo ra thi bat ky ai cung do duoc
     * ten cua toan bo khach hang bang cach go tung so mot.
     */
    public function quickLookup(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $phone = SoDienThoai::chuan($data['phone']);

        // Chua go du so thi khoan tra, tranh tra ve ket qua cua mot so khac.
        if (strlen((string) preg_replace('/\D/', '', $phone)) < 8) {
            return response()->json(['found' => false]);
        }

        $ho = $this->guests->forPhone(
            $phone,
            $request->user()->visibleBranchIds(),
            $this->brandIdFor($request, GuestNote::normalize($phone))
        );

        $the = $ho['card'];
        $ghiChu = $ho['note'];

        return response()->json([
            'found' => filled($ho['name']) || $ho['total'] > 0 || $the !== null,
            'name' => $ho['name'],
            'name_source' => $ho['name_source'],
            'tier' => $the?->tier,
            'visits' => $ho['completed'],
            'bookings' => $ho['total'],
            'no_show' => $ho['no_show'],
            'last_visit' => $ho['last_visit'],
            'vip' => (bool) $ghiChu?->is_vip,
            'blocked' => (bool) $ghiChu?->is_blocked,
            'note' => $ghiChu?->note,
        ]);
    }

    public function saveNote(Request $request)
    {
        abort_unless($request->user()->canWrite(), 403);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $digits = GuestNote::normalize($data['phone']);
        $brandId = $this->brandIdFor($request, $digits);

        if (! $brandId) {
            return back()->withErrors([
                'note' => 'Chưa xác định được khách này thuộc quán nào, nên chưa lưu được ghi chú. '
                    .'Khách cần có ít nhất một hóa đơn hoặc một lần đặt bàn ở một quán.',
            ]);
        }

        GuestNote::updateOrCreate(
            ['brand_id' => $brandId, 'phone' => $digits],
            [
                'name' => $data['name'] ?? null,
                'note' => $data['note'] ?? null,
                'is_vip' => $request->boolean('is_vip'),
                'is_blocked' => $request->boolean('is_blocked'),
                'updated_by' => $request->user()->id,
            ]
        );

        return redirect()
            ->route('admin.customers.show', $digits)
            ->with('status', 'Đã lưu ghi chú về khách.');
    }

    /**
     * Ghi chu khach gan theo quan. Nguoi dung thuoc quan nao thi dung quan do;
     * quan tri thi lay theo quan cua lan dat gan nhat.
     */
    protected function brandIdFor(Request $request, string $phone): ?int
    {
        if ($request->user()->brand_id) {
            return (int) $request->user()->brand_id;
        }

        $branchIds = $request->user()->visibleBranchIds();

        $latest = $this->guests->forPhone($phone, $branchIds)['bookings']->first();

        if ($latest?->branch?->brand_id) {
            return (int) $latest->branch->brand_id;
        }

        // Khach chi co hoa don, chua tung dat ban - 87% khach nhan dien duoc la
        // kieu nay. Truoc day form ghi chu chi nam o trang tim theo dat ban nen
        // luon co don de suy ra quan; tu khi gop ve mot trang chi tiet thi khong
        // con dung nua, va quan tri luu ghi chu bi bao 422.
        return Invoice::query()
            ->choDiaDiem($branchIds)
            ->where('customer_phone', $phone)
            ->latest('paid_at')
            ->with('branch:id,brand_id')
            ->first()
            ?->branch?->brand_id;
    }
}
