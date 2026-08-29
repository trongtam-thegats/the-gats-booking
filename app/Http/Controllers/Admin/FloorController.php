<?php

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * So do ban theo khung gio: moi hang la mot ban, moi cot la mot moc gio,
 * o nao co khach thi to mau. Day la man hinh le tan nhin nhieu nhat.
 */
class FloorController extends AdminController
{
    public function __construct(protected AvailabilityService $availability) {}

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

        return view('admin.floor', compact(
            'branches', 'branch', 'date', 'slots', 'tables', 'grid', 'bookings', 'unassigned'
        ));
    }
}
