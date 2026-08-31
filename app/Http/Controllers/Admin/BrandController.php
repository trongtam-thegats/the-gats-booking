<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\GuestNote;
use App\Support\NguonDatBan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends AdminController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $brands = Brand::withCount('branches')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $orphanBranches = Branch::whereNull('brand_id')->orderBy('name')->get();

        return view('admin.brands.index', [
            'brands' => $brands,
            'orphanBranches' => $orphanBranches,
            // Duong dan rieng cho tung kenh, de quan dan ra ngoai.
            'kenhCoDuongDan' => NguonDatBan::kenhCoDuongDan(),
            'nhanNguon' => NguonDatBan::NHAN,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $brand = Brand::create($this->validated($request, null));
        $this->storeLogo($request, $brand);
        $this->storeFonts($request, $brand);

        return back()->with('status', 'Đã tạo quán '.$brand->name.'.');
    }

    public function update(Request $request, Brand $brand)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $brand->update($this->validated($request, $brand));
        $this->storeLogo($request, $brand);
        $this->storeFonts($request, $brand);

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo($brand);
        }

        return back()->with('status', 'Đã cập nhật quán '.$brand->name.'.');
    }

    public function destroy(Request $request, Brand $brand)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $vuong = [];

        if ($so = $brand->branches()->count()) {
            $vuong[] = number_format($so).' địa điểm';
        }

        // guest_notes gan theo quan va bi xoa theo, tuc la mat sach ghi chu ve
        // khach lan danh dau "da xem xet" - phai bao truoc chu khong xoa lang.
        if ($so = GuestNote::where('brand_id', $brand->id)->count()) {
            $vuong[] = number_format($so).' ghi chú về khách';
        }

        if ($vuong) {
            return back()->withErrors([
                'brand' => 'Không xóa được "'.$brand->name.'": đang có '.implode(' và ', $vuong)
                    .'. Chuyển địa điểm sang quán khác trước, hoặc tắt "Đang hoạt động" nếu chỉ '
                    .'muốn ngừng nhận đặt bàn.',
            ]);
        }

        $ten = $brand->name;
        $brand->delete();

        return back()->with('status', 'Đã xóa quán '.$ten.'.');
    }

    /**
     * Luu logo vao public/brand. Khong dung storage link vi hosting chia se
     * thuong khong tao duoc symlink.
     */
    protected function storeLogo(Request $request, Brand $brand): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }

        $file = $request->file('logo');
        $directory = public_path('brand');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->deleteLogo($brand);

        $name = $brand->slug.'-logo-'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
        $file->move($directory, $name);

        $brand->update(['logo_path' => 'brand/'.$name]);
    }

    /** Luu font rieng cua quan vao public/fonts. */
    protected function storeFonts(Request $request, Brand $brand): void
    {
        foreach (['display_font' => 'display_font_path', 'body_font' => 'body_font_path'] as $input => $column) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $directory = public_path('fonts');

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $old = $brand->{$column};
            $file = $request->file($input);
            $name = $brand->slug.'-'.str_replace('_font', '', $input).'-'.now()->format('YmdHis')
                .'.'.strtolower($file->getClientOriginalExtension());

            $file->move($directory, $name);
            $brand->update([$column => 'fonts/'.$name]);

            if ($old && is_file(public_path($old))) {
                @unlink(public_path($old));
            }
        }
    }

    protected function deleteLogo(Brand $brand): void
    {
        if ($brand->logo_path && is_file(public_path($brand->logo_path))) {
            @unlink(public_path($brand->logo_path));
        }

        if ($brand->logo_path) {
            $brand->update(['logo_path' => null]);
        }
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Brand $brand): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash',
                Rule::unique('brands', 'slug')->ignore($brand?->id),
                // Tranh trung voi cac duong dan mot doan da co cua he thong.
                Rule::notIn(['quan-ly', 'tra-cuu', 'ma', 'dat-ban', 'api', 'up', 'css', 'js', 'storage']),
            ],
            'domain' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9.-]+$/i',
                Rule::unique('brands', 'domain')->ignore($brand?->id)],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'mark' => ['required', 'string', 'max:3'],
            'accent_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ground_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            // Trinh duyet bao kieu file font khong thong nhat, nen chi kiem tra duoi file.
            'display_font' => ['nullable', 'file', 'mimes:ttf,otf,woff,woff2', 'max:2048'],
            'body_font' => ['nullable', 'file', 'mimes:ttf,otf,woff,woff2', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mail_from_address' => ['nullable', 'email', 'max:180'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], [
            'name' => 'tên quán',
            'slug' => 'đường dẫn',
            'domain' => 'tên miền',
            'mail_from_address' => 'địa chỉ gửi thư',
            'mark' => 'ký hiệu',
            'accent_color' => 'màu nhận diện',
            'ground_color' => 'màu nền',
            'logo' => 'logo',
            'display_font' => 'font tiêu đề',
            'body_font' => 'font nội dung',
        ]);

        // Cac file tai len xu ly rieng, khong phai cot cua bang.
        unset($data['logo'], $data['display_font'], $data['body_font']);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['domain'] = $data['domain'] ? strtolower(trim($data['domain'])) : null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
