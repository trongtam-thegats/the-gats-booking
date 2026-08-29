<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchController extends AdminController
{
    public function index(Request $request)
    {
        $branches = $this->accessibleBranches($request)
            ->load(['areas', 'brand'])
            ->loadCount([
                'diningTables as tables_count' => fn ($q) => $q->where('is_active', true),
            ]);

        return view('admin.branches.index', compact('branches'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.branches.form', [
            'brands' => Brand::orderBy('sort_order')->orderBy('name')->get(),
            'branch' => new Branch([
                'open_time' => '17:00',
                'close_time' => '23:30',
                'slot_minutes' => 30,
                'turn_minutes' => 120,
                'min_lead_minutes' => 60,
                'max_advance_days' => 30,
                'max_party_size' => 20,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $this->validated($request, null);
        $branch = Branch::create($data);

        return redirect()
            ->route('admin.branches.edit', $branch)
            ->with('status', 'Đã tạo chi nhánh '.$branch->name.'. Bước tiếp theo: khai báo khu vực và bàn.');
    }

    public function edit(Request $request, Branch $branch)
    {
        $this->authorizeBranch($request, $branch->id);
        abort_unless($request->user()->canManageSetup(), 403);

        $closures = $branch->closures()
            ->whereDate('date', '>=', Carbon::today()->subDays(7)->toDateString())
            ->orderBy('date')
            ->get();

        $brands = Brand::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.branches.form', compact('branch', 'brands', 'closures'));
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorizeBranch($request, $branch->id);
        abort_unless($request->user()->canManageSetup(), 403);

        $data = $this->validated($request, $branch);

        // Quan ly chi nhanh khong duoc tu doi slug hay bat/tat chi nhanh.
        if (! $request->user()->isAdmin()) {
            unset($data['slug'], $data['is_active']);
        }

        $branch->update($data);

        return back()->with('status', 'Đã lưu cấu hình chi nhánh.');
    }

    public function destroy(Request $request, Branch $branch)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($branch->bookings()->exists()) {
            return back()->withErrors([
                'branch' => 'Chi nhánh đã có dữ liệu đặt bàn nên không xóa được. Hãy tắt trạng thái hoạt động thay vì xóa.',
            ]);
        }

        $branch->delete();

        return redirect()->route('admin.branches.index')->with('status', 'Đã xóa chi nhánh.');
    }

    public function storeClosure(Request $request, Branch $branch)
    {
        $this->authorizeBranch($request, $branch->id);
        abort_unless($request->user()->canManageSetup(), 403);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $branch->closures()->create($data);

        return back()->with('status', 'Đã thêm lịch nghỉ.');
    }

    public function destroyClosure(Request $request, Branch $branch, BranchClosure $closure)
    {
        $this->authorizeBranch($request, $branch->id);
        abort_unless($request->user()->canManageSetup(), 403);
        abort_unless($closure->branch_id === $branch->id, 404);

        $closure->delete();

        return back()->with('status', 'Đã xóa lịch nghỉ.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Branch $branch): array
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash',
                Rule::unique('branches', 'slug')->ignore($branch?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url', 'max:255'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i'],
            'last_booking_time' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['required', 'integer', 'min:15', 'max:120'],
            'turn_minutes' => ['required', 'integer', 'min:30', 'max:480'],
            'min_lead_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_party_size' => ['required', 'integer', 'min:1', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['auto_confirm'] = $request->boolean('auto_confirm');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
