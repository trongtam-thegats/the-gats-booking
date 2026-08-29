<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Trang khach. Quan da duoc middleware ResolveBrandSite xac dinh tu ten mien,
 * nen moi truy van o day deu bi gioi han trong pham vi mot quan.
 */
class PublicBookingController extends Controller
{
    public function __construct(
        protected AvailabilityService $availability,
        protected BookingService $bookings,
    ) {}

    protected function brand(Request $request): Brand
    {
        return $request->attributes->get('brand');
    }

    /**
     * Trang dat ban. Quan mot dia diem thi vao la thay ngay form;
     * quan nhieu dia diem thi co them mot hang chon dia diem o dau form.
     */
    public function index(Request $request)
    {
        $brand = $this->brand($request);
        $branches = $brand->activeBranches()->get();

        if ($branches->isEmpty()) {
            return view('public.closed', compact('brand'));
        }

        $branch = $this->selectedBranch($request, $branches);

        return view('public.booking', [
            'brand' => $brand,
            'branches' => $branches,
            'branch' => $branch,
            'range' => $this->availability->bookableDateRange($branch),
        ]);
    }

    /** Dia diem dang chon, lay tu ?dia-diem=slug hoac dia diem dau tien. */
    protected function selectedBranch(Request $request, $branches): Branch
    {
        $slug = $request->query('dia-diem');

        return ($slug ? $branches->firstWhere('slug', $slug) : null) ?? $branches->first();
    }

    /** API khung gio con trong, goi bang fetch tu form. */
    public function slots(Request $request, Branch $branch): JsonResponse
    {
        $this->guardBranch($request, $branch);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'party_size' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        if (Carbon::parse($data['date'])->gt(Carbon::today()->addDays((int) $branch->max_advance_days))) {
            return response()->json([
                'slots' => [],
                'message' => __('booking.errors.too_far_days', ['days' => $branch->max_advance_days]),
            ]);
        }

        if ($data['party_size'] > $branch->max_party_size) {
            return response()->json([
                'slots' => [],
                'message' => __('booking.errors.party_call', [
                    'phone' => $branch->phone ?: __('booking.form.the_venue'),
                ]),
            ]);
        }

        $slots = $this->availability->daySlots(
            $branch,
            $data['date'],
            (int) $data['party_size'],
            null,
            onlineOnly: true
        );

        $hasFree = collect($slots)->contains('available', true);

        return response()->json([
            'slots' => $slots,
            'message' => $hasFree ? null : $this->brand($request)->text('no_slots'),
        ]);
    }

    public function store(Request $request, Branch $branch)
    {
        $this->guardBranch($request, $branch);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,}$/'],
            'customer_email' => ['nullable', 'email', 'max:180'],
            'party_size' => ['required', 'integer', 'min:1', 'max:200'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'customer_name' => 'họ tên',
            'customer_phone' => 'số điện thoại',
            'customer_email' => 'email',
            'party_size' => 'số khách',
            'booking_date' => 'ngày',
            'start_time' => 'giờ',
        ]);

        try {
            $booking = $this->bookings->create($branch, $data);
        } catch (BookingUnavailableException $e) {
            return back()->withInput()->withErrors(['start_time' => $e->getMessage()]);
        }

        return redirect()
            ->route('booking.show', $booking)
            ->with('just_booked', true);
    }

    /** Trang chi tiet cho khach, truy cap bang ma dat ban. */
    public function show(Request $request, Booking $booking)
    {
        $booking->load(['branch', 'diningTables', 'area']);
        $this->guardBranch($request, $booking->branch);

        return view('public.show', [
            'booking' => $booking,
            'brand' => $this->brand($request),
            'emailSent' => $this->emailWasSent($booking),
        ]);
    }

    /**
     * Da that su gui duoc email xac nhan chua.
     *
     * Chi tin vao nhat ky gui tin chu khong tin vao viec khach co dien email:
     * khach co the de email nhung kenh email chua cau hinh hoac gui that bai.
     * Khi chua chac thi trang xac nhan moi khach luu anh, thay vi hua hen suong.
     */
    protected function emailWasSent(Booking $booking): bool
    {
        return filled($booking->customer_email)
            && $booking->notificationLogs()
                ->where('channel', 'email')
                ->where('status', 'sent')
                ->exists();
    }

    public function lookup(Request $request)
    {
        $brand = $this->brand($request);
        $code = trim((string) $request->query('code'));
        $phone = trim((string) $request->query('phone'));
        $booking = null;
        $error = null;

        if ($code !== '' || $phone !== '') {
            $booking = Booking::where('code', strtoupper($code))
                ->where('customer_phone', $phone)
                ->whereHas('branch', fn ($q) => $q->where('brand_id', $brand->id))
                ->first();

            $error = $booking ? null : __('booking.lookup.not_found');
        }

        return view('public.lookup', compact('brand', 'booking', 'code', 'phone', 'error'));
    }

    /** Khach tu huy bang ma dat ban va so dien thoai. */
    public function cancel(Request $request, Booking $booking)
    {
        $this->guardBranch($request, $booking->branch);

        $data = $request->validate([
            'customer_phone' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($data['customer_phone'] !== $booking->customer_phone) {
            return back()->withErrors(['customer_phone' => __('booking.errors.phone_mismatch')]);
        }

        if (! $booking->customerCanCancel()) {
            return back()->withErrors(['customer_phone' => __('booking.errors.cannot_cancel')]);
        }

        $this->bookings->cancel($booking, $data['reason'] ?? 'Khách tự hủy', 'customer');

        return redirect()->route('booking.show', $booking)->with('cancelled', true);
    }

    /**
     * Chan viec doc du lieu cua quan khac qua ten mien nay.
     * Vi du go ma dat ban cua Gemination tren booking.drinkinghealing.com.
     */
    protected function guardBranch(Request $request, Branch $branch): void
    {
        abort_unless($branch->brand_id === $this->brand($request)->id, 404);
        abort_unless($branch->is_active, 404);
    }
}
