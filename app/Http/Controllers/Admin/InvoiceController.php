<?php

namespace App\Http\Controllers\Admin;

use App\Models\Invoice;
use App\Services\PosImportService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Danh sach hoa don nhap tu POS.
 *
 * Bang nay chi doc: khong ai sua hoa don trong khu quan ly, moi lan quan xuat
 * tep moi tu POS thi tai len de chong len tep cu.
 */
class InvoiceController extends AdminController
{
    /** So dong moi trang. */
    protected const MOI_TRANG = 50;

    public function index(Request $request)
    {
        $branches = $this->accessibleBranches($request);
        $branch = $this->selectedBranch($request, $branches);
        $branchIds = $branch ? [$branch->id] : $request->user()->visibleBranchIds();

        $tim = trim((string) $request->query('q', ''));
        $tuNgay = trim((string) $request->query('tu', ''));
        $denNgay = trim((string) $request->query('den', ''));
        $chiCoSdt = $request->boolean('co-sdt');
        $khuVuc = trim((string) $request->query('khu', ''));

        $loc = Invoice::query()
            ->choDiaDiem($branchIds)
            ->when($chiCoSdt, fn ($q) => $q->coKhach())
            ->when($khuVuc !== '', fn ($q) => $q->where('area', $khuVuc))
            ->when($tuNgay !== '', fn ($q) => $q->where('paid_at', '>=', $tuNgay.' 00:00:00'))
            ->when($denNgay !== '', fn ($q) => $q->where('paid_at', '<=', $denNgay.' 23:59:59'))
            ->when($tim !== '', function ($q) use ($tim) {
                $q->where(function ($w) use ($tim) {
                    $w->where('code', 'like', '%'.$tim.'%')
                        ->orWhere('customer_name', 'like', '%'.$tim.'%')
                        ->orWhere('customer_phone', 'like', '%'.$tim.'%')
                        ->orWhere('table_code', 'like', '%'.$tim.'%');
                });
            });

        // Tong ket tinh tren dung tap dang loc, khong phai tren trang dang xem.
        $thanhCong = (clone $loc)->thanhCong();

        $tongKet = [
            'so_hoa_don' => (clone $loc)->count(),
            'doanh_thu' => (float) (clone $thanhCong)->sum('total'),
            'co_sdt' => (clone $loc)->coKhach()->count(),
            'tip' => (float) (clone $thanhCong)->sum('tip'),
        ];

        $tongKet['trung_binh'] = $tongKet['so_hoa_don']
            ? $tongKet['doanh_thu'] / max(1, (clone $thanhCong)->count())
            : 0.0;

        $hoaDon = $loc->orderByDesc('paid_at')->paginate(self::MOI_TRANG)->withQueryString();

        return view('admin.invoices.index', [
            'branches' => $branches,
            'branch' => $branch,
            'hoaDon' => $hoaDon,
            'tongKet' => $tongKet,
            'khuVucCo' => Invoice::query()->choDiaDiem($branchIds)
                ->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'),
            'boLoc' => compact('tim', 'tuNgay', 'denNgay', 'chiCoSdt', 'khuVuc'),
        ]);
    }

    /** Tai tep xuat tu POS len de nhap, thay cho viec goi lenh tren may chu. */
    public function import(Request $request, PosImportService $nhap)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'tep' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'loai' => ['required', 'in:hoa-don,khach-hang'],
            'branch_id' => ['required_if:loai,hoa-don', 'nullable', 'integer', 'exists:branches,id'],
        ], [], [
            'tep' => 'tệp',
            'loai' => 'loại dữ liệu',
            'branch_id' => 'địa điểm',
        ]);

        $duongDan = $request->file('tep')->getRealPath();

        try {
            if ($data['loai'] === 'hoa-don') {
                $branch = $this->accessibleBranches($request)->firstWhere('id', (int) $data['branch_id']);

                abort_unless($branch, 403, 'Địa điểm này không thuộc quyền của bạn.');

                $k = $nhap->hoaDon($duongDan, $branch, true);

                $loi = 'Đã nhập hóa đơn cho '.$branch->name.': '.$k['moi'].' hóa đơn mới, '
                    .$k['capNhat'].' cập nhật lại, '.$k['coSdt'].' có số điện thoại.';
            } else {
                $k = $nhap->khachHang($duongDan, null, true);

                $loi = 'Đã nhập thẻ khách hàng: '.$k['moi'].' khách mới, '.$k['capNhat']
                    .' cập nhật lại, bỏ qua '.$k['khongSdt'].' dòng không có số điện thoại.';
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['tep' => $e->getMessage()]);
        }

        return back()->with('status', $loi);
    }
}
