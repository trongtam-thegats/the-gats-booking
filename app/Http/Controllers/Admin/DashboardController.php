<?php

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends AdminController
{
    public function index(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches);
        $date = $request->query('date', Carbon::today()->toDateString());

        $scope = Booking::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch, fn ($q) => $q->whereIn('branch_id', $branches->pluck('id')));

        $todays = (clone $scope)
            ->forDate($date)
            ->with(['branch', 'diningTables', 'area'])
            ->orderBy('start_time')
            ->get();

        $stats = [
            'total' => $todays->count(),
            'pending' => $todays->where('status', Booking::STATUS_PENDING)->count(),
            'confirmed' => $todays->where('status', Booking::STATUS_CONFIRMED)->count(),
            'seated' => $todays->where('status', Booking::STATUS_SEATED)->count(),
            'guests' => $todays->whereIn('status', Booking::BLOCKING_STATUSES)->sum('party_size'),
            'cancelled' => $todays->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])->count(),
        ];

        // Nhung yeu cau con cho duyet, khong gioi han trong ngay dang xem.
        $waiting = (clone $scope)
            ->where('status', Booking::STATUS_PENDING)
            ->whereDate('booking_date', '>=', Carbon::today()->toDateString())
            ->with(['branch', 'diningTables'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->limit(20)
            ->get();

        $upcomingDays = (clone $scope)
            ->blocking()
            ->whereBetween('booking_date', [
                Carbon::today()->toDateString(),
                Carbon::today()->addDays(6)->toDateString(),
            ])
            ->selectRaw('booking_date, COUNT(*) as bookings, SUM(party_size) as guests')
            ->groupBy('booking_date')
            ->orderBy('booking_date')
            ->get();

        return view('admin.dashboard', compact(
            'branches', 'branch', 'date', 'todays', 'stats', 'waiting', 'upcomingDays'
        ));
    }
}
