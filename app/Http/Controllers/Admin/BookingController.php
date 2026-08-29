<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Branch;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BookingController extends AdminController
{
    public function __construct(
        protected BookingService $bookings,
        protected AvailabilityService $availability,
    ) {}

    public function index(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches);

        $filters = [
            'status' => $request->query('status', ''),
            'from' => $request->query('from', Carbon::today()->toDateString()),
            'to' => $request->query('to', Carbon::today()->addDays(7)->toDateString()),
            'q' => trim((string) $request->query('q', '')),
        ];

        $bookings = Booking::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch, fn ($q) => $q->whereIn('branch_id', $branches->pluck('id')))
            ->when($filters['status'], fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['from'], fn ($q) => $q->whereDate('booking_date', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('booking_date', '<=', $filters['to']))
            ->when($filters['q'], function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where(fn ($sub) => $sub
                    ->where('code', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term));
            })
            ->with(['branch', 'diningTables', 'area'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->paginate(30)
            ->withQueryString();

        return view('admin.bookings.index', compact('branches', 'branch', 'bookings', 'filters'));
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorizeBranch($request, $booking->branch_id);

        $booking->load(['branch', 'diningTables.area', 'area', 'confirmedBy', 'createdBy', 'notificationLogs']);

        $openMin = $this->availability->openMinutes($booking->branch);
        $startMin = $this->availability->normalize(
            $this->availability->toMinutes((string) $booking->start_time), $openMin
        );
        $endMin = $this->availability->normalize(
            $this->availability->toMinutes((string) $booking->end_time), $openMin
        );
        if ($endMin <= $startMin) {
            $endMin += 1440;
        }

        // Ban dang trong + ban dang gan cho chinh booking nay.
        $freeTables = $this->availability->availableTables(
            $booking->branch,
            $booking->booking_date->toDateString(),
            $startMin,
            $endMin,
            null,
            $booking->id
        );

        return view('admin.bookings.show', [
            'booking' => $booking,
            'freeTables' => $freeTables,
            'slotTimes' => $this->availability->slotTimes($booking->branch),
            'areas' => $booking->branch->areas,
            'guest' => app(\App\Services\GuestProfileService::class)->forPhone(
                $booking->customer_phone,
                $request->user()->visibleBranchIds(),
                $booking->branch->brand_id
            ),
        ]);
    }

    /** Le tan tao booking ho khach qua dien thoai. */
    public function create(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches) ?? $branches->first();

        abort_if(! $branch, 404, 'Chưa có chi nhánh nào.');

        return view('admin.bookings.create', [
            'branches' => $branches,
            'branch' => $branch,
            'areas' => $branch->areas,
            'slotTimes' => $this->availability->slotTimes($branch),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:180'],
            'party_size' => ['required', 'integer', 'min:1', 'max:200'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'area_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
            'source' => ['required', Rule::in(['phone', 'walk_in', 'online'])],
        ]);

        $this->authorizeBranch($request, (int) $data['branch_id']);
        $branch = Branch::findOrFail($data['branch_id']);

        try {
            $booking = $this->bookings->create($branch, $data, $request->user());
        } catch (BookingUnavailableException $e) {
            return back()->withInput()->withErrors(['start_time' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'Đã tạo đặt bàn '.$booking->code.'.');
    }

    public function confirm(Request $request, Booking $booking)
    {
        $this->authorizeBranch($request, $booking->branch_id);
        $this->bookings->confirm($booking, $request->user());

        return back()->with('status', 'Đã xác nhận '.$booking->code.' và gửi thông báo cho khách.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeBranch($request, $booking->branch_id);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $this->bookings->cancel($booking, $data['reason'] ?? null, 'staff', $request->user());

        return back()->with('status', 'Đã hủy '.$booking->code.'.');
    }

    public function transition(Request $request, Booking $booking, string $action)
    {
        $this->authorizeBranch($request, $booking->branch_id);

        match ($action) {
            'seated' => $this->bookings->markSeated($booking),
            'completed' => $this->bookings->markCompleted($booking),
            'no-show' => $this->bookings->markNoShow($booking),
            default => abort(404),
        };

        return back()->with('status', 'Đã cập nhật trạng thái '.$booking->code.'.');
    }

    public function assignTables(Request $request, Booking $booking)
    {
        $this->authorizeBranch($request, $booking->branch_id);

        $data = $request->validate([
            'table_ids' => ['array'],
            'table_ids.*' => ['integer', 'exists:dining_tables,id'],
        ]);

        try {
            $this->bookings->assignTables($booking, array_map('intval', $data['table_ids'] ?? []));
        } catch (BookingUnavailableException $e) {
            return back()->withErrors(['table_ids' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã cập nhật bàn cho '.$booking->code.'.');
    }

    /** Doi ngay gio, so khach hoac thong tin lien he cua mot dat ban da co. */
    public function reschedule(Request $request, Booking $booking)
    {
        $this->authorizeBranch($request, $booking->branch_id);

        $data = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:200'],
            'area_id' => ['nullable', 'integer',
                Rule::exists('areas', 'id')->where('branch_id', $booking->branch_id)],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:180'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'booking_date' => 'ngày',
            'start_time' => 'giờ',
            'party_size' => 'số khách',
            'customer_name' => 'họ tên',
            'customer_phone' => 'số điện thoại',
        ]);

        try {
            $this->bookings->reschedule(
                $booking,
                $data,
                $request->user(),
                $request->boolean('notify_guest')
            );
        } catch (BookingUnavailableException $e) {
            return back()->withErrors(['start_time' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã cập nhật đặt bàn '.$booking->code.'.');
    }

    public function updateNote(Request $request, Booking $booking)
    {
        $this->authorizeBranch($request, $booking->branch_id);

        $data = $request->validate(['internal_note' => ['nullable', 'string', 'max:1000']]);
        $booking->update($data);

        return back()->with('status', 'Đã lưu ghi chú nội bộ.');
    }
}
