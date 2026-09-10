<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PosCustomer;
use App\Support\SoDienThoai;
use App\Support\XlsxReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Generator;
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
     * Ten cot tep mat hang => cot trong bang invoice_items.
     *
     * Tep nay moi dong mot MON, va lap lai toan bo cot cua hoa don o moi dong.
     * Chi lay phan mat hang; phan hoa don da nhap tu tep "danh sach hoa don".
     *
     * Luu y hai cap ten de lan nhau trong chinh tep nay:
     *   "Tiền hàng" (cua dong mon) vs "Tổng tiền hàng (1)" (cua ca hoa don)
     *   "Tổng giảm giá" (cua dong mon) vs "Tổng giảm giá (4)" (cua ca hoa don)
     * Khop theo phan dau van phan biet duoc vi chu dau khac nhau.
     *
     * @var array<string, string>
     */
    public const COT_MAT_HANG = [
        'Mã hóa đơn' => 'code',
        'Mã mặt hàng' => 'sku',
        'Tên mặt hàng, combo' => 'name',
        'Danh mục' => 'category',
        'Số lượng' => 'quantity',
        'Đơn vị' => 'unit',
        'Giá bán' => 'unit_price',
        'Tiền hàng' => 'amount',
    ];

    /**
     * Cot co ten qua ngan, de nuot nham cot khac neu khop theo phan dau.
     * "Hoa don" (so lan ghe) khong duoc an "Hoa don gan nhat" (ma hoa don).
     *
     * @var array<int, string>
     */
    protected const KHOP_DUNG = ['Họ', 'Tên', 'Hóa đơn', 'Email', 'Ghi chú', 'Bàn', 'Tiền hàng'];

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
    /**
     * Nhap tep "danh sach mat hang": moi dong mot mon trong mot hoa don.
     *
     * Phai nhap tep hoa don TRUOC. Dong nao tro toi hoa don chua co trong he
     * thong thi bo qua va dem lai - bao lai cho nguoi dung thay vi tu tao hoa
     * don thieu du lieu.
     *
     * Nhap de duoc: mon cua hoa don nao co trong tep thi xoa het roi ghi lai,
     * nen xuat tep moi chong len tep cu khong bao gio sinh ban trung.
     *
     * @return array{tong: int, mon: int, hoaDon: int, khongKhop: int, boQua: int}
     */
    public function matHang(string $tep, Branch $branch, bool $ghi = false): array
    {
        $doc = new XlsxReader($tep);

        // Doc theo luong chu khong goi table(): tep mat hang cua mot quan da
        // hon 19.000 dong, nap het vao mang la het bo nho PHP (128M) ngay tren
        // may that. Da dinh mot lan roi.
        $viTri = $this->viTriCotMatHang($doc);

        if ($viTri === []) {
            throw new RuntimeException(
                'Không tìm thấy cột "Tên mặt hàng, combo". Tệp này có phải danh sách mặt hàng không? '
                .'Tệp danh sách hóa đơn chỉ có một dòng cho mỗi hóa đơn.'
            );
        }

        $thieu = array_diff(['code', 'name'], array_keys($viTri));

        if ($thieu) {
            throw new RuntimeException('Tệp thiếu cột bắt buộc: '.implode(', ', $thieu));
        }

        $idTheoMa = Invoice::where('branch_id', $branch->id)->pluck('id', 'code');
        $ketQua = ['tong' => 0, 'mon' => 0, 'hoaDon' => 0, 'khongKhop' => 0, 'boQua' => 0];
        $idDungToi = [];

        // Luot 1: dem, va gom cac hoa don co trong tep.
        foreach ($this->dongMatHang($doc, $viTri) as $o) {
            $ketQua['tong']++;

            if ($o['code'] === '' || $o['name'] === '') {
                $ketQua['boQua']++;

                continue;
            }

            if (! isset($idTheoMa[$o['code']])) {
                $ketQua['khongKhop']++;

                continue;
            }

            $ketQua['mon']++;
            $idDungToi[$idTheoMa[$o['code']]] = true;
        }

        $ketQua['hoaDon'] = count($idDungToi);

        if (! $ghi || $idDungToi === []) {
            return $ketQua;
        }

        // Luot 2: xoa mon cu cua dung nhung hoa don nay roi ghi lai. Lam hai
        // luot de khong phu thuoc vao viec tep co xep cac dong cua cung mot
        // hoa don lien nhau hay khong.
        DB::transaction(function () use ($doc, $viTri, $idTheoMa, $idDungToi) {
            foreach (array_chunk(array_keys($idDungToi), 500) as $lo) {
                InvoiceItem::whereIn('invoice_id', $lo)->delete();
            }

            $dem = [];
            $luc = now();

            foreach ($this->dongMatHang($doc, $viTri) as $o) {
                if ($o['code'] === '' || $o['name'] === '' || ! isset($idTheoMa[$o['code']])) {
                    continue;
                }

                $dem[] = [
                    'invoice_id' => $idTheoMa[$o['code']],
                    'sku' => $this->chuoi($o['sku'], 60),
                    'name' => mb_substr($o['name'], 0, 255),
                    'category' => $this->chuoi($o['category'], 80),
                    'quantity' => $this->soThuc($o['quantity']),
                    'unit' => $this->chuoi($o['unit'], 30),
                    'unit_price' => $this->soThuc($o['unit_price']),
                    'amount' => $this->soThuc($o['amount']),
                    'created_at' => $luc,
                    'updated_at' => $luc,
                ];

                if (count($dem) >= 500) {
                    InvoiceItem::insert($dem);
                    $dem = [];
                }
            }

            if ($dem) {
                InvoiceItem::insert($dem);
            }
        });

        return $ketQua;
    }

    /**
     * Tim vi tri cac cot can dung trong tep mat hang: ten cot => chi so cot.
     *
     * @return array<string, int>
     */
    protected function viTriCotMatHang(XlsxReader $doc): array
    {
        foreach ($doc->rows() as $row) {
            $chu = array_map(fn ($x) => $doc->gonChu((string) $x), $row);

            if (! in_array('Mã hóa đơn', $chu, true) || ! in_array('Tên mặt hàng, combo', $chu, true)) {
                continue;
            }

            $viTri = [];

            foreach ($chu as $i => $ten) {
                if ($ten === '') {
                    continue;
                }

                foreach (self::COT_MAT_HANG as $dau => $cot) {
                    if (isset($viTri[$cot])) {
                        continue;
                    }

                    $khop = in_array($dau, self::KHOP_DUNG, true)
                        ? $ten === $dau || str_starts_with($ten, $dau.' (')
                        : str_starts_with($ten, $dau);

                    if ($khop) {
                        $viTri[$cot] = $i;
                        break;
                    }
                }
            }

            return $viTri;
        }

        return [];
    }

    /**
     * Doc tung dong mat hang sau dong tieu de, tra ve mang da anh xa san.
     *
     * @param  array<string, int>  $viTri
     * @return Generator<int, array<string, string>>
     */
    protected function dongMatHang(XlsxReader $doc, array $viTri): Generator
    {
        $quaTieuDe = false;

        foreach ($doc->rows() as $row) {
            if (! $quaTieuDe) {
                $chu = array_map(fn ($x) => $doc->gonChu((string) $x), $row);
                $quaTieuDe = in_array('Mã hóa đơn', $chu, true)
                    && in_array('Tên mặt hàng, combo', $chu, true);

                continue;
            }

            $o = [];

            foreach (self::COT_MAT_HANG as $cot) {
                $o[$cot] = isset($viTri[$cot]) ? trim((string) ($row[$viTri[$cot]] ?? '')) : '';
            }

            if ($o['code'] === '' && $o['name'] === '') {
                continue;
            }

            yield $o;
        }
    }

    /** Cat chuoi cho vua cot, tra ve null neu rong. */
    protected function chuoi(mixed $o, int $dai): ?string
    {
        $chu = trim((string) ($o ?? ''));

        return $chu === '' ? null : mb_substr($chu, 0, $dai);
    }

    /** Doc so tu o Excel; o rong hoac khong phai so thi ve 0. */
    protected function soThuc(mixed $o): float
    {
        if ($o === null || $o === '') {
            return 0.0;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $o);
    }

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
