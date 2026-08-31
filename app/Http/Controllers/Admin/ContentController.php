<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use App\Models\BrandContent;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sua chu va anh bia tren trang dat ban cua tung quan.
 */
class ContentController extends AdminController
{
    public function index(Request $request)
    {
        $brands = $this->accessibleBrands($request);
        $brand = $this->selectedBrand($request, $brands);

        abort_if(! $brand, 404, 'Chưa có quán nào.');

        $brand->load('contents');

        $locale = $request->query('ngon-ngu');
        $locale = Locales::supported($locale) ? $locale : Locales::DEFAULT;

        return view('admin.content.index', compact('brands', 'brand', 'locale'));
    }

    public function update(Request $request, Brand $brand)
    {
        $this->guard($request, $brand);

        $rules = [
            'locale' => ['required', Rule::in(Locales::codes())],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
        ];

        foreach (Brand::TEXTS as $key => [$label, $default, $type, $hint]) {
            $rules['texts.'.$key] = ['nullable', 'string', 'max:'.($type === 'textarea' ? 1000 : 160)];
        }

        $data = $request->validate($rules, [], ['cover' => 'ảnh bìa', 'locale' => 'ngôn ngữ']);
        $locale = $data['locale'];

        foreach (Brand::TEXTS as $key => $spec) {
            $value = trim((string) ($data['texts'][$key] ?? ''));

            if ($value === '') {
                // Xoa han de lan sau doi noi dung mac dinh thi trang tu cap nhat theo.
                $brand->contents()->where('key', $key)->where('locale', $locale)->delete();

                continue;
            }

            BrandContent::updateOrCreate(
                ['brand_id' => $brand->id, 'key' => $key, 'locale' => $locale],
                ['value' => $value]
            );
        }

        $this->storeCover($request, $brand);

        if ($request->boolean('remove_cover')) {
            $this->deleteCover($brand);
        }

        return redirect()
            ->route('admin.content.index', ['quan' => $brand->id, 'ngon-ngu' => $locale])
            ->with('status', 'Đã lưu nội dung '.Locales::label($locale).' của '.$brand->name.'.');
    }

    /** Chieu rong toi da cua anh bia sau khi thu nho. */
    protected const COVER_MAX_WIDTH = 1600;

    protected function storeCover(Request $request, Brand $brand): void
    {
        if (! $request->hasFile('cover')) {
            return;
        }

        $directory = public_path('brand');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $old = $brand->cover_path;
        $file = $request->file('cover');
        $stamp = now()->format('YmdHis');

        // Anh chup chuyen nghiep thuong nang vai MB. Thu nho va nen lai truoc
        // khi luu, neu khong trang dat ban se rat cham tren 3G.
        $optimised = $this->optimiseCover($file->getRealPath(), $directory, $brand->slug.'-cover-'.$stamp);

        if ($optimised) {
            $name = $optimised;
        } else {
            // Khong co thu vien xu ly anh thi giu nguyen file goc.
            $name = $brand->slug.'-cover-'.$stamp.'.'.strtolower($file->getClientOriginalExtension());
            $file->move($directory, $name);
        }

        $brand->update(['cover_path' => 'brand/'.$name]);

        if ($old !== $brand->cover_path) {
            $this->forgetCoverFiles($old);
        }
    }

    /**
     * Thu nho anh ve chieu rong toi da va nen lai dang JPEG.
     * Tra ve ten file da luu, hoac null neu may chu khong co thu vien GD.
     */
    protected function optimiseCover(string $source, string $directory, string $baseName): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($source));

        if (! $image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > self::COVER_MAX_WIDTH) {
            $scaled = imagescale($image, self::COVER_MAX_WIDTH);

            if ($scaled) {
                imagedestroy($image);
                $image = $scaled;
            }
        }

        $name = $baseName.'.jpg';
        // Nen o muc 82: mat mat khong nhin ra tren anh chup toi, nhung nhe hon nhieu.
        $saved = imagejpeg($image, $directory.DIRECTORY_SEPARATOR.$name, 82);

        if ($saved) {
            $this->narrowCopies($image, $directory, $baseName);
        }

        imagedestroy($image);

        return $saved ? $name : null;
    }

    /**
     * Ban thu nho cua anh bia, cho dien thoai tai thay ban rong.
     *
     * Anh bia chi cao khoang 200-300px tren man hinh nen ban 800px la du net
     * ngay ca voi man hinh net gap doi; ban 1600px nang gap ba lan ma khong
     * nhin ra khac biet.
     *
     * @param  \GdImage  $image
     */
    protected function narrowCopies($image, string $directory, string $baseName): void
    {
        foreach (Brand::COVER_WIDTHS as $width) {
            if (imagesx($image) <= $width) {
                continue;
            }

            $small = imagescale($image, $width);

            if ($small) {
                imagejpeg($small, $directory.DIRECTORY_SEPARATOR.$baseName.'-w'.$width.'.jpg', 80);
                imagedestroy($small);
            }
        }
    }

    protected function deleteCover(Brand $brand): void
    {
        $this->forgetCoverFiles($brand->cover_path);

        $brand->update(['cover_path' => null]);
    }

    /** Xoa anh bia va moi ban thu nho di kem. */
    protected function forgetCoverFiles(?string $path): void
    {
        if (! $path) {
            return;
        }

        $paths = [$path];

        foreach (Brand::COVER_WIDTHS as $width) {
            $paths[] = preg_replace('/(\.[^.]+)$/', '-w'.$width.'$1', $path);
        }

        foreach ($paths as $one) {
            if ($one && is_file(public_path($one))) {
                @unlink(public_path($one));
            }
        }
    }

    /** @return Collection<int, Brand> */
    protected function accessibleBrands(Request $request)
    {
        $user = $request->user();

        return Brand::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('id', $user->brand_id ?: 0))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function selectedBrand(Request $request, $brands): ?Brand
    {
        $requested = $request->query('quan');

        return ($requested ? $brands->firstWhere('id', (int) $requested) : null) ?? $brands->first();
    }

    protected function guard(Request $request, Brand $brand): void
    {
        $user = $request->user();

        // Noi dung trang khach la cau hinh, chi quan tri duoc sua.
        abort_unless($user->isAdmin(), 403);
    }
}
