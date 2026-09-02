<?php

namespace App\Http\Controllers\Admin;

use App\Models\GuestNote;
use App\Services\GuestProfileService;
use App\Support\SoDienThoai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tra cuu khach cho le tan: go so dien thoai la ra lich su va ghi chu,
 * xu ly duoc ngay tai cho.
 */
class GuestController extends AdminController
{
    public function __construct(protected GuestProfileService $guests) {}

    public function index(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $phone = trim((string) $request->query('phone', ''));
        $branchIds = $request->user()->visibleBranchIds();

        $results = $phone === '' ? $this->guests->search($term, $branchIds) : collect();
        $profile = null;

        // Chi co dung mot khach khop thi mo thang ho so, khoi bat bam them lan nua.
        if ($phone === '' && $results->count() === 1) {
            $phone = $results->first()['phone'];
        }

        if ($phone !== '') {
            $profile = $this->guests->forPhone($phone, $branchIds, $this->brandIdFor($request, $phone));
            $results = collect();
        }

        return view('admin.guests.index', compact('term', 'phone', 'results', 'profile'));
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

        abort_if(! $brandId, 422, 'Chưa xác định được khách này thuộc quán nào.');

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
            ->route('admin.guests.index', ['phone' => $digits])
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

        $latest = $this->guests
            ->forPhone($phone, $request->user()->visibleBranchIds())['bookings']
            ->first();

        return $latest?->branch?->brand_id;
    }
}
