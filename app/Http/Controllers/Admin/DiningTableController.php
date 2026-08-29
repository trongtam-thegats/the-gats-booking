<?php

namespace App\Http\Controllers\Admin;

use App\Models\Area;
use App\Models\Branch;
use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiningTableController extends AdminController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canManageSetup(), 403);

        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches) ?? $branches->first();

        abort_if(! $branch, 404, 'Chưa có chi nhánh nào.');

        $branch->load(['areas', 'diningTables.area']);

        return view('admin.tables.index', compact('branches', 'branch'));
    }

    public function storeArea(Request $request, Branch $branch)
    {
        $this->guard($request, $branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $branch->areas()->create($data + [
            'bookable' => $request->boolean('bookable', true),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('status', 'Đã thêm khu vực.');
    }

    public function updateArea(Request $request, Branch $branch, Area $area)
    {
        $this->guard($request, $branch);
        abort_unless($area->branch_id === $branch->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $area->update($data + ['bookable' => $request->boolean('bookable')]);

        return back()->with('status', 'Đã cập nhật khu vực.');
    }

    public function destroyArea(Request $request, Branch $branch, Area $area)
    {
        $this->guard($request, $branch);
        abort_unless($area->branch_id === $branch->id, 404);

        $area->delete(); // ban thuoc khu nay chuyen ve "chua phan khu"

        return back()->with('status', 'Đã xóa khu vực. Các bàn trong khu chuyển về mục chưa phân khu.');
    }

    public function store(Request $request, Branch $branch)
    {
        $this->guard($request, $branch);

        $data = $this->validatedTable($request, $branch, null);
        $branch->diningTables()->create($data);

        return back()->with('status', 'Đã thêm bàn '.$data['code'].'.');
    }

    public function update(Request $request, Branch $branch, DiningTable $table)
    {
        $this->guard($request, $branch);
        abort_unless($table->branch_id === $branch->id, 404);

        $table->update($this->validatedTable($request, $branch, $table));

        return back()->with('status', 'Đã cập nhật bàn '.$table->code.'.');
    }

    public function destroy(Request $request, Branch $branch, DiningTable $table)
    {
        $this->guard($request, $branch);
        abort_unless($table->branch_id === $branch->id, 404);

        if ($table->bookings()->exists()) {
            $table->update(['is_active' => false]);

            return back()->with('status', 'Bàn '.$table->code.' đã từng có khách nên được ẩn thay vì xóa.');
        }

        $table->delete();

        return back()->with('status', 'Đã xóa bàn.');
    }

    /**
     * Tao nhanh nhieu ban cung luc, vi du B01..B12 gap 4 cho.
     */
    public function bulkStore(Request $request, Branch $branch)
    {
        $this->guard($request, $branch);

        $data = $request->validate([
            'prefix' => ['required', 'string', 'max:10'],
            'from' => ['required', 'integer', 'min:1', 'max:999'],
            'to' => ['required', 'integer', 'min:1', 'max:999', 'gte:from'],
            'seats_max' => ['required', 'integer', 'min:1', 'max:50'],
            'seats_min' => ['nullable', 'integer', 'min:1', 'max:50'],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('branch_id', $branch->id)],
        ]);

        $created = 0;

        for ($i = (int) $data['from']; $i <= (int) $data['to']; $i++) {
            $code = $data['prefix'].str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            if ($branch->diningTables()->where('code', $code)->exists()) {
                continue;
            }

            $branch->diningTables()->create([
                'code' => $code,
                'area_id' => $data['area_id'] ?? null,
                'seats_min' => $data['seats_min'] ?? 1,
                'seats_max' => $data['seats_max'],
                'combinable' => true,
                'is_active' => true,
                'sort_order' => $i,
            ]);

            $created++;
        }

        return back()->with('status', 'Đã tạo '.$created.' bàn.');
    }

    protected function guard(Request $request, Branch $branch): void
    {
        abort_unless($request->user()->canManageSetup(), 403);
        $this->authorizeBranch($request, $branch->id);
    }

    /** @return array<string, mixed> */
    protected function validatedTable(Request $request, Branch $branch, ?DiningTable $table): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20',
                Rule::unique('dining_tables', 'code')->where('branch_id', $branch->id)->ignore($table?->id)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('branch_id', $branch->id)],
            'seats_min' => ['required', 'integer', 'min:1', 'max:50'],
            'seats_max' => ['required', 'integer', 'min:1', 'max:50', 'gte:seats_min'],
            'note' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return $data + [
            'combinable' => $request->boolean('combinable'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }
}
