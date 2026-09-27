<?php

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use App\Services\AvailabilityService;
use App\Services\SapoRealtimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * So do ban theo khung gio: moi hang la mot ban, moi cot la mot moc gio,
 * o nao co khach thi to mau. Day la man hinh le tan nhin nhieu nhat.
 */
class FloorController extends AdminController
{
    public function __construct(
        protected AvailabilityService $availability,
        protected SapoRealtimeService $sapoService,
    ) {}

    public function index(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches) ?? $branches->first();

        abort_if(! $branch, 404, 'Chưa có chi nhánh nào.');

        $date = $request->query('date', Carbon::today()->toDateString());
        $slots = $this->availability->slotTimes($branch);
        $openMin = $this->availability->openMinutes($branch);

        $tables = $branch->diningTables()->where('is_active', true)->with('area')->get();

        $bookings = $branch->bookings()
            ->blocking()
            ->forDate($date)
            ->with('diningTables:id')
            ->orderBy('start_time')
            ->get();

        // grid[table_id][slot] = booking | null
        $grid = [];

        foreach ($bookings as $booking) {
            $bStart = $this->availability->normalize(
                $this->availability->toMinutes((string) $booking->start_time), $openMin
            );
            $bEnd = $this->availability->normalize(
                $this->availability->toMinutes((string) $booking->end_time), $openMin
            );
            if ($bEnd <= $bStart) {
                $bEnd += 1440;
            }

            foreach ($booking->diningTables as $table) {
                foreach ($slots as $slot) {
                    $sMin = $this->availability->normalize($this->availability->toMinutes($slot), $openMin);

                    if ($sMin >= $bStart && $sMin < $bEnd) {
                        $grid[$table->id][$slot] = $booking;
                    }
                }
            }
        }

        $unassigned = $bookings->filter(fn (Booking $b) => $b->diningTables->isEmpty());

        $sapoActive = $this->sapoService->batDuoc($branch->slug);
        $sapoLastSync = Cache::get("sapo_last_sync_{$branch->slug}");

        return view('admin.floor', compact(
            'branches', 'branch', 'date', 'slots', 'tables', 'grid', 'bookings', 'unassigned',
            'sapoActive', 'sapoLastSync'
        ));
    }

    public function syncSapo(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches) ?? $branches->first();
        abort_if(! $branch, 404);

        $date = $request->input('date', Carbon::today()->toDateString());

        if (! $this->sapoService->batDuoc($branch->slug)) {
            return redirect()->route('admin.floor', ['branch' => $branch->id, 'date' => $date])
                ->with('error', 'Chưa bật đồng bộ Sapo Realtime hoặc chưa cấu hình Cookie cho quán này.');
        }

        $res = $this->sapoService->dongBo($branch->slug, true);

        if ($res['loi']) {
            return redirect()->route('admin.floor', ['branch' => $branch->id, 'date' => $date])
                ->with('error', 'Lỗi đồng bộ Sapo: '.$res['loi']);
        }

        $msg = "Đã đồng bộ Sapo thành công! {$res['tongDonMo']} đơn đang mở ({$res['banKhoaMoi']} bàn mới, {$res['banDangNgoi']} bàn đang ngồi, {$res['banGiaiPhong']} bàn giải phóng).";

        return redirect()->route('admin.floor', ['branch' => $branch->id, 'date' => $date])
            ->with('status', $msg);
    }
}
