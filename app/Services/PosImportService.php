<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PosCustomer;
use App\Support\SoDienThoai;
use App\Support\XlsxReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Doc tep xuat tu POS (Sapo) va nhap vao he thong.
 *
 * Dung chung cho lenh artisan va cho nut tai tep tren khu quan ly, de hai loi
 * vao khong bao gio hieu tep khac nhau.
 *
 * Ca hai loai tep deu nhap de len duoc: hoa don khop theo ma, khach khop theo
 * so dien thoai. Cu xuat tep moi chong len tep cu, khong sinh ban trung.
 */
class PosImportService
{
    /**
     * Ten cot tep hoa don => cot trong bang invoices.
     *
     * Khop theo phan dau cua ten: POS ghi ca cong thuc vao tieu de
     * ("Tong tien thanh toan (1 + 2 + 3 - 4 + 5)") va co the doi theo phien ban.
     *
     * @var array<string, string>
     */
    public const COT_HOA_DON = [
        'Mã hóa đơn' => 'code',
        'Nguồn đơn' => 'source',
        'Trạng thái đơn hàng' => 'status',
        'Thời gian tạo đơn' => 'ordered_at',
        'Thời gian thanh toán' => 'paid_at',
        'Tổng tiền hàng' => 'subtotal',
        'Thuế VAT' => 'vat',
        'Phí dịch vụ' => 'service_fee',
        'Tổng giảm giá' => 'discount',
        'Phí GH thu khách' => 'delivery_fee',
        'Tổng tiền thanh toán' => 'total',
        'Phương thức TT' => 'payment_method',
        'Tiền Tip' => 'tip',
        'Khách hàng' => 'customer_name',
        'Số điện thoại' => 'customer_phone',
        'Thẻ thành viên' => 'membership_card',
        'Số người' => 'party_size',
        'Ghi chú khách hàng' => 'customer_note',
        'Loại hình phục vụ' => 'service_type',
        'Khu vực' => 'area',
        'Bàn' => 'table_code',
        'Thu ngân' => 'cashier',
        'Hoàn tiền đơn' => 'refund',
        'Ghi chú đơn' => 'order_note',
    ];

    /**
     * Ten cot tep khach hang => cot trong bang pos_customers.
     *
     * @var array<string, string>
     */
    public const COT_KHACH = [
        'Họ' => 'ho',
        'Tên' => 'ten',
        'Số điện thoại' => 'phone',
        'Email' => 'email',
        'Ngày sinh' => 'birthday',
        'Giới tính' => 'gender',
        'Tỉnh' => 'province',
        'Quận' => 'district',
        'Địa chỉ' => 'address',
        'Ghi chú' => 'note',
        'Ngày tham gia' => 'joined_at',
        'Hóa đơn' => 'invoice_count',
        'Tổng chi tiêu' => 'total_spent',
        'Mã thẻ thành viên' => 'member_code',
        'Hạng thẻ' => 'tier',
        'Điểm tích lũy' => 'points',
    ];

    /**
     * Cot co ten qua ngan, de nuot nham cot khac neu khop theo phan dau.
     * "Hoa don" (so lan ghe) khong duoc an "Hoa don gan nhat" (ma hoa don).
     *
     * @var array<int, string>
     */
    protected const KHOP_DUNG = ['Họ', 'Tên', 'Hóa đơn', 'Email', 'Ghi chú', 'Bàn'];

    /** Cot ngay gio trong tep hoa don, luu duoi dang so cua Excel. */
    protected const NGAY_HOA_DON = ['ordered_at', 'paid_at'];

    /** Cot tien trong tep hoa don. */
    protected const TIEN_HOA_DON = [
        'subtotal', 'vat', 'service_fee', 'discount', 'delivery_fee', 'total', 'tip', 'refund',
    ];

    /** Gioi han cua cot so nguyen khong dau trong MySQL. */
    protected const SO_NGUYEN_TOI_DA = 4294967295;

    /**
     * Nhap tep hoa don.
     *
     * @return array{moi: int, capNhat: int, boQua: int, coSdt: int, tong: int}
     */
    public function hoaDon(string $tep, Branch $branch, bool $ghi = false): array
    {
        $doc = new XlsxReader($tep);

        // Tep POS co mot khoi tieu de bao cao o tren; dong tieu de that la dong
        // dau tien co ca o "Stt" lan o "Ma hoa don".
        [$tieuDe, $dong] = $doc->table(function (array $o) use ($doc): bool {
            $chu = array_map(fn ($x) => $doc->gonChu((string) $x), $o);

            return in_array('Stt', $chu, true) && in_array('Mã hóa đơn', $chu, true);
        });

        if (! $tieuDe) {
            throw new RuntimeException('Không tìm thấy dòng tiêu đề. Tệp này có phải danh sách hóa đơn không?');
        }

        $anhXa = $this->anhXaCot($tieuDe, self::COT_HOA_DON);
        $thieu = array_diff(['code', 'total', 'paid_at'], array_values($anhXa));

        if ($thieu) {
            throw new RuntimeException('Tệp thiếu cột bắt buộc: '.implode(', ', $thieu));
        }

        $ketQua = ['moi' => 0, 'capNhat' => 0, 'boQua' => 0, 'coSdt' => 0, 'tong' => count($dong)];

        // Chi so hoa don cua rieng dia diem nay. POS danh so rieng cho tung
        // quan nen hai quan de trung ma; doi chieu toan he thong se ghi de
        // nham len hoa don cua quan khac.
        $daCo = Invoice::where('branch_id', $branch->id)->pluck('id', 'code');

        $viec = function () use ($dong, $anhXa, $branch, $daCo, $doc, &$ketQua, $ghi) {
            foreach ($dong as $r) {
                $ban = $this->dongHoaDon($r, $anhXa, $doc);

                if (! $ban) {
                    $ketQua['boQua']++;

                    continue;
                }

                if ($ban['customer_phone'] !== '') {
                    $ketQua['coSdt']++;
                }

                isset($daCo[$ban['code']]) ? $ketQua['capNhat']++ : $ketQua['moi']++;

                if ($ghi) {
                    Invoice::updateOrCreate(
                        ['branch_id' => $branch->id, 'code' => $ban['code']],
                        $ban
                    );
                }
            }
        };

        $ghi ? DB::transaction($viec) : $viec();

        return $ketQua;
    }

    /**
     * Nhap tep the khach hang.
     *
     * @return array{moi: int, capNhat: int, khongSdt: int, trung: int, tong: int}
     */
    public function khachHang(string $tep, ?int $brandId = null, bool $ghi = false): array
    {
        $doc = new XlsxReader($tep);

        [$tieuDe, $dong] = $doc->table(function (array $o) use ($doc): bool {
            foreach ($o as $mot) {
                if (str_starts_with($doc->gonChu((string) $mot), 'Số điện thoại')) {
                    return true;
                }
            }

            return false;
        });

        if (! $tieuDe) {
            throw new RuntimeException('Không tìm thấy dòng tiêu đề. Tệp này có phải danh sách khách hàng không?');
        }

        $anhXa = $this->anhXaCot($tieuDe, self::COT_KHACH);

        if (! in_array('phone', $anhXa, true)) {
            throw new RuntimeException('Tệp không có cột số điện thoại.');
        }

        $xuatLuc = Carbon::createFromTimestamp((int) filemtime($tep));
        $ketQua = ['moi' => 0, 'capNhat' => 0, 'khongSdt' => 0, 'trung' => 0, 'tong' => count($dong)];
        $daCo = PosCustomer::pluck('id', 'phone');
        $daGap = [];

        $viec = function () use ($dong, $anhXa, $brandId, $daCo, $doc, $xuatLuc, &$ketQua, &$daGap, $ghi) {
            foreach ($dong as $r) {
                $ban = $this->dongKhach($r, $anhXa, $doc);

                if (! $ban) {
                    $ketQua['khongSdt']++;

                    continue;
                }

                // Cung mot so xuat hien nhieu lan trong tep thi lay dong dau.
                if (isset($daGap[$ban['phone']])) {
                    $ketQua['trung']++;

                    continue;
                }

                $daGap[$ban['phone']] = true;

                isset($daCo[$ban['phone']]) ? $ketQua['capNhat']++ : $ketQua['moi']++;

                if ($ghi) {
                    PosCustomer::updateOrCreate(
                        ['phone' => $ban['phone']],
                        $ban + ['brand_id' => $brandId, 'exported_at' => $xuatLuc]
                    );
                }
            }
        };

        $ghi ? DB::transaction($viec) : $viec();

        return $ketQua;
    }

    /**
     * Ten cot trong tep => ten cot trong bang.
     *
     * @param  array<int, string>  $tieuDe
     * @param  array<string, string>  $bang
     * @return array<string, string>
     */
    protected function anhXaCot(array $tieuDe, array $bang): array
    {
        $anhXa = [];

        foreach ($tieuDe as $ten) {
            if ($ten === '') {
                continue;
            }

            foreach ($bang as $dau => $cot) {
                // Cot da nhan roi thi thoi: tep POS co nhung cap ten long nhau
                // ("Hoa don" va "Hoa don gan nhat"), cot dung luon dung truoc.
                if (in_array($cot, $anhXa, true)) {
                    continue;
                }

                $khop = in_array($dau, self::KHOP_DUNG, true)
                    ? $ten === $dau || str_starts_with($ten, $dau.' (')
                    : str_starts_with($ten, $dau);

                if ($khop) {
                    $anhXa[$ten] = $cot;

                    break;
                }
            }
        }

        return $anhXa;
    }

    /**
     * @param  array<string, string|float|null>  $r
     * @param  array<string, string>  $anhXa
     * @return array<string, mixed>|null
     */
    protected function dongHoaDon(array $r, array $anhXa, XlsxReader $doc): ?array
    {
        $ban = $this->layTheoAnhXa($r, $anhXa);

        $ban['code'] = trim((string) ($ban['code'] ?? ''));

        if ($ban['code'] === '') {
            return null;
        }

        foreach (self::NGAY_HOA_DON as $cot) {
            $ban[$cot] = $doc->ngay($ban[$cot] ?? null);
        }

        foreach (self::TIEN_HOA_DON as $cot) {
            $ban[$cot] = (float) ($ban[$cot] ?? 0);
        }

        $ban['customer_phone'] = SoDienThoai::chuan($ban['customer_phone'] ?? null);
        $ban['customer_name'] = trim((string) ($ban['customer_name'] ?? '')) ?: null;
        $ban['party_size'] = ($ban['party_size'] ?? null) ? (int) $ban['party_size'] : null;

        foreach (['status', 'source', 'payment_method', 'membership_card', 'service_type',
            'area', 'table_code', 'cashier', 'customer_note', 'order_note'] as $cot) {
            $ban[$cot] = trim((string) ($ban[$cot] ?? '')) ?: null;
        }

        return $ban;
    }

    /**
     * @param  array<string, string|float|null>  $r
     * @param  array<string, string>  $anhXa
     * @return array<string, mixed>|null
     */
    protected function dongKhach(array $r, array $anhXa, XlsxReader $doc): ?array
    {
        $tho = $this->layTheoAnhXa($r, $anhXa);
        $phone = SoDienThoai::chuan($tho['phone'] ?? null);

        if ($phone === '') {
            return null;
        }

        $ten = trim(trim((string) ($tho['ho'] ?? '')).' '.trim((string) ($tho['ten'] ?? '')));
        $sinhNhat = $doc->ngay($tho['birthday'] ?? null);

        return [
            'phone' => $phone,
            'name' => $ten ?: null,
            'email' => filter_var(trim((string) ($tho['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null,
            'birthday' => $sinhNhat ? substr($sinhNhat, 0, 10) : null,
            'gender' => trim((string) ($tho['gender'] ?? '')) ?: null,
            'province' => trim((string) ($tho['province'] ?? '')) ?: null,
            'district' => trim((string) ($tho['district'] ?? '')) ?: null,
            'address' => trim((string) ($tho['address'] ?? '')) ?: null,
            'note' => trim((string) ($tho['note'] ?? '')) ?: null,
            'joined_at' => $doc->ngay($tho['joined_at'] ?? null),
            // Chan tren cho chac: mot cot bi lech thi bo qua con so do chu
            // khong lam do ca lan nhap.
            'invoice_count' => $this->soNguyen($tho['invoice_count'] ?? 0),
            'total_spent' => max(0, (float) ($tho['total_spent'] ?? 0)),
            'member_code' => trim((string) ($tho['member_code'] ?? '')) ?: null,
            'tier' => trim((string) ($tho['tier'] ?? '')) ?: null,
            'points' => $this->soNguyen($tho['points'] ?? 0),
        ];
    }

    /**
     * @param  array<string, string|float|null>  $r
     * @param  array<string, string>  $anhXa
     * @return array<string, string|float|null>
     */
    protected function layTheoAnhXa(array $r, array $anhXa): array
    {
        $ban = [];

        foreach ($anhXa as $tenCot => $cot) {
            $ban[$cot] = $r[$tenCot] ?? null;
        }

        return $ban;
    }

    protected function soNguyen(mixed $gt): int
    {
        return min(self::SO_NGUYEN_TOI_DA, max(0, (int) $gt));
    }
}
