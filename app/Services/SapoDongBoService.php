<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PosCustomer;
use App\Support\SoDienThoai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nhap hoa don doc thang tu Sapo FnB (JSON), khong qua tep Excel.
 *
 * Trang quan tri Sapo tu lay du lieu qua /admin/orders.json; trinh duyet da
 * dang nhap Sapo doc duoc dia chi do va day tung lo sang day. Nho vay khong
 * con phai xuat Excel, cho email, tai tep, tai len.
 *
 * Ket qua ghi vao DUNG cac bang cua cong nhap Excel (invoices, invoice_items,
 * pos_customers), cung khoa (branch_id, code), nen hai loi vao chong len nhau
 * khong sinh ban trung. Anh xa cot giu dung gia tri ma tep Excel ghi ra
 * ("Da thanh toan", "An tai ban"...) de cac trang phan tich khong phai biet
 * du lieu den tu duong nao.
 */
class SapoDongBoService
{
    protected const LOAI_HINH = [
        'dine_in' => 'Ăn tại bàn',
        'take_away' => 'Mang đi',
        'takeaway' => 'Mang đi',
        'delivery' => 'Giao hàng',
    ];

    /**
     * Dia diem ung voi mot cua hang Sapo: theo cau hinh truoc, roi theo ten.
     */
    public function diaDiem(?int $storeId, ?string $tenCuaHang): ?Branch
    {
        $slug = config('booking.sapo_stores')[(string) $storeId] ?? null;

        if ($slug) {
            return Branch::where('slug', $slug)->first();
        }

        $ten = trim((string) $tenCuaHang);

        return $ten === '' ? null : Branch::where('name', $ten)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $donHang  Don hang tho cua Sapo, kem khoa "customer" neu co
     * @return array{tong: int, moi: int, capNhat: int, boQua: int, coSdt: int, mon: int, khach: int}
     */
    public function nhap(array $donHang, Branch $branch): array
    {
        $ketQua = ['tong' => count($donHang), 'moi' => 0, 'capNhat' => 0, 'boQua' => 0, 'coSdt' => 0, 'mon' => 0, 'khach' => 0];

        DB::transaction(function () use ($donHang, $branch, &$ketQua) {
            $luc = now();

            foreach ($donHang as $don) {
                $hoaDon = $this->hoaDon($don);

                if ($hoaDon === null) {
                    $ketQua['boQua']++;

                    continue;
                }

                if ($hoaDon['customer_phone'] !== '') {
                    $ketQua['coSdt']++;
                }

                $ban = Invoice::updateOrCreate(
                    ['branch_id' => $branch->id, 'code' => $hoaDon['code']],
                    $hoaDon
                );

                $ban->wasRecentlyCreated ? $ketQua['moi']++ : $ketQua['capNhat']++;

                // Mon cua hoa don nay: xoa roi ghi lai, nhu cong nhap Excel.
                InvoiceItem::where('invoice_id', $ban->id)->delete();
                $mon = $this->mon($don, $ban->id, $luc);

                if ($mon) {
                    InvoiceItem::insert($mon);
                    $ketQua['mon'] += count($mon);
                }

                $moiNhat = $hoaDon['customer_phone'] !== '' && ! Invoice::where('customer_phone', $hoaDon['customer_phone'])
                    ->where('paid_at', '>', $hoaDon['paid_at'])
                    ->exists();

                if ($this->theKhach($don, $moiNhat)) {
                    $ketQua['khach']++;
                }
            }
        });

        return $ketQua;
    }

    /**
     * @param  array<string, mixed>  $don
     * @return array<string, mixed>|null
     */
    public function hoaDon(array $don): ?array
    {
        $thanhToan = array_values(array_filter((array) ($don['payments'] ?? []), 'is_array'));
        $ma = '';

        foreach ($thanhToan as $tt) {
            $ma = trim((string) ($tt['receipt_number'] ?? ''));

            if ($ma !== '') {
                break;
            }
        }

        // Ma hoa don trong tep Excel chinh la so bien nhan cua lan thanh toan.
        // Don chua thanh toan thi chua co ma - bo qua, lan sau se co.
        if ($ma === '') {
            return null;
        }

        $khach = is_array($don['customer'] ?? null) ? $don['customer'] : [];
        $ban = is_array($don['table'] ?? null) ? $don['table'] : [];
        $tong = (float) ($don['total_price'] ?? 0);
        $hoan = $this->tong($don['refunds'] ?? [], ['total_refund', 'refund_money', 'money', 'amount', 'total']);
        $gioTra = collect($thanhToan)->pluck('client_time')->filter()->max();

        $trangThai = match (true) {
            ($don['status'] ?? null) === 'cancelled' || ! empty($don['cancelled']) => Invoice::HUY,
            $hoan > 0 && $hoan >= $tong => 'Hoàn tiền toàn bộ',
            $hoan > 0 => 'Hoàn tiền một phần',
            ($don['financial_status'] ?? null) === 'pending' => 'Chờ xác nhận thanh toán',
            default => 'Đã thanh toán',
        };

        return [
            'code' => mb_substr($ma, 0, 40),
            'status' => $trangThai,
            'source' => ($don['order_type'] ?? 'at_store') === 'at_store'
                ? 'Tại nhà hàng'
                : $this->chuoi($don['partner_type'] ?? $don['order_type'] ?? null, 40),
            'ordered_at' => $this->ngay($don['order_time'] ?? $don['created_on'] ?? null),
            'paid_at' => $this->ngay($gioTra ?? $don['created_on'] ?? null),
            'subtotal' => (float) ($don['total_item_price'] ?? 0),
            'vat' => $this->tong($don['taxes'] ?? [], ['taxed_value', 'tax_value', 'money', 'value', 'amount']),
            'service_fee' => $this->tong($don['service_fees'] ?? [], ['fee_value', 'money', 'value', 'amount']),
            'discount' => (float) ($don['total_discount'] ?? 0),
            'delivery_fee' => 0.0,
            'total' => $tong,
            'tip' => $this->tong($thanhToan, ['money_tips']),
            'refund' => $hoan,
            'payment_method' => $this->chuoi(
                collect($thanhToan)->pluck('payment_method_name')->filter()->unique()->implode(', '),
                80
            ),
            'customer_name' => $this->chuoi($this->tenKhach($khach), 255),
            'customer_phone' => SoDienThoai::chuan($khach['phone'] ?? null),
            'membership_card' => $this->chuoi($don['loyalty_card']['name'] ?? null, 60),
            'party_size' => ($don['customer_count'] ?? null) ? (int) $don['customer_count'] : null,
            'service_type' => self::LOAI_HINH[$don['dine_type'] ?? ''] ?? $this->chuoi($don['dine_type'] ?? null, 60),
            'area' => $this->chuoi($ban['area_name'] ?? null, 80),
            'table_code' => $this->chuoi($ban['table_name'] ?? null, 80),
            'cashier' => $this->chuoi($don['cashier_name'] ?? null, 255),
            'customer_note' => $this->chuoi($khach['note'] ?? null, 2000),
            'order_note' => $this->chuoi($don['note'] ?? null, 2000),
        ];
    }

    /**
     * @param  array<string, mixed>  $don
     * @return array<int, array<string, mixed>>
     */
    protected function mon(array $don, int $invoiceId, Carbon $luc): array
    {
        $dong = [];

        foreach ((array) ($don['items'] ?? []) as $mon) {
            if (! is_array($mon) || trim((string) ($mon['item_name'] ?? '')) === '') {
                continue;
            }

            $soLuong = (float) ($mon['quantity'] ?? 0);
            $tien = (float) ($mon['money'] ?? 0);

            $dong[] = [
                'invoice_id' => $invoiceId,
                'sku' => $this->chuoi($mon['variant']['code'] ?? null, 60),
                'name' => mb_substr(trim((string) $mon['item_name']), 0, 255),
                'category' => $this->chuoi($mon['category']['category_name'] ?? null, 80),
                'quantity' => $soLuong,
                'unit' => $this->chuoi($mon['stock_unit']['stock_unit_name'] ?? $mon['stock_unit_name'] ?? null, 30),
                'unit_price' => (float) ($mon['variant']['unit_price'] ?? ($soLuong > 0 ? $tien / $soLuong : 0)),
                'amount' => $tien,
                'created_at' => $luc,
                'updated_at' => $luc,
            ];
        }

        return $dong;
    }

    /**
     * Cap nhat the khach hang POS tu thong tin khach kem theo don.
     * Chi ghi de o nao Sapo co gia tri - khong xoa trang hang the, ghi chu cu.
     */
    protected function theKhach(array $don, bool $laHoaDonMoiNhat): bool
    {
        $khach = is_array($don['customer'] ?? null) ? $don['customer'] : [];
        $sdt = SoDienThoai::chuan($khach['phone'] ?? null);

        if ($sdt === '') {
            return false;
        }

        $gioiTinh = ['male' => 'Nam', 'female' => 'Nữ'][$khach['sex'] ?? ''] ?? null;

        $moi = array_filter([
            'name' => $this->chuoi($this->tenKhach($khach), 255),
            'email' => filter_var(trim((string) ($khach['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null,
            'birthday' => ($khach['birthday'] ?? null) ? $this->ngay($khach['birthday'])?->toDateString() : null,
            'gender' => $gioiTinh,
            'note' => $this->chuoi($khach['note'] ?? null, 1000),
            'joined_at' => $this->ngay($khach['created_on'] ?? null),
            'member_code' => $this->chuoi($khach['member_code'] ?? null, 60),
        ], fn ($gt) => $gt !== null);

        // Hang the tren don la hang LUC DAT DON. Sapo tra don moi nhat truoc,
        // nen neu cu lay theo don thi don cu nap sau se ha hang khach xuong.
        // Chi lay khi day la hoa don gan nhat cua so nay.
        $hang = $this->chuoi($don['loyalty_card']['name'] ?? null, 60);

        if ($hang !== null && $laHoaDonMoiNhat) {
            $moi['tier'] = $hang;
        }

        foreach (['order_count' => 'invoice_count', 'loyalty_point' => 'points'] as $nguon => $cot) {
            if (isset($khach[$nguon])) {
                $moi[$cot] = min(4294967295, max(0, (int) $khach[$nguon]));
            }
        }

        if (isset($khach['total_spent'])) {
            $moi['total_spent'] = max(0, (float) $khach['total_spent']);
        }

        PosCustomer::updateOrCreate(['phone' => $sdt], $moi + ['exported_at' => now()]);

        return true;
    }

    protected function tenKhach(array $khach): ?string
    {
        $ten = trim(trim((string) ($khach['last_name'] ?? '')).' '.trim((string) ($khach['first_name'] ?? '')));

        return $ten === '' ? null : $ten;
    }

    /** Sapo tra moc thoi gian dang giay unix; doi ve gio cua ung dung. */
    protected function ngay(mixed $giay): ?Carbon
    {
        if (! is_numeric($giay) || (int) $giay <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $giay, config('app.timezone'));
    }

    /**
     * Cong mot truong so trong danh sach; truong nao co truoc thi dung.
     *
     * @param  array<int, string>  $khoa
     */
    protected function tong(mixed $ds, array $khoa): float
    {
        $cong = 0.0;

        foreach ((array) $ds as $dong) {
            if (! is_array($dong)) {
                continue;
            }

            foreach ($khoa as $k) {
                if (isset($dong[$k]) && is_numeric($dong[$k])) {
                    $cong += (float) $dong[$k];

                    break;
                }
            }
        }

        return $cong;
    }

    protected function chuoi(mixed $gt, int $dai): ?string
    {
        $chu = is_scalar($gt) ? trim((string) $gt) : '';

        return $chu === '' ? null : mb_substr($chu, 0, $dai);
    }
}
