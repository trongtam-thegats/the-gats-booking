<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PosCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dong bo hoa don doc thang tu Sapo FnB.
 *
 * Mau don hang lay tu du lieu that cua /admin/orders.json (17/09/2026), rut gon.
 */
class SapoDongBoTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'Gemination', 'slug' => 'gemination-brand', 'domain' => 'booking.gemination.test',
            'mark' => 'GM', 'accent_color' => '#c8a15a', 'is_active' => true, 'is_default' => true,
        ]);

        $this->branch = $brand->branches()->create([
            'name' => 'Gemination Đà Lạt', 'slug' => 'gemination',
            'open_time' => '18:00', 'close_time' => '02:00',
        ]);

        config(['booking.sapo_token' => 'ma-thu', 'booking.sapo_stores' => ['46101' => 'gemination']]);
    }

    protected function don(array $them = []): array
    {
        return array_replace([
            'name' => 'E61315',
            'status' => 'completed',
            'financial_status' => 'paid',
            'order_type' => 'at_store',
            'dine_type' => 'dine_in',
            'order_time' => 1789576543,
            'created_on' => 1789586524,
            'customer_count' => 2,
            'cashier_name' => 'Nguyễn Phúc Toàn',
            'note' => '',
            'total_item_price' => 1503000,
            'total_discount' => 0,
            'total_price' => 1578150,
            'service_fees' => [['fee_value' => 75150, 'name' => 'Service Charge (5%)']],
            'taxes' => [],
            'refunds' => [],
            'cancelled' => null,
            'loyalty_card' => ['name' => 'VIP', 'loyalty_point' => 47],
            'table' => ['area_name' => 'Tầng lửng', 'table_name' => 'Bàn H8'],
            'payments' => [[
                'client_time' => 1789586523,
                'money_tips' => 0,
                'payment_method_name' => 'Tiền mặt',
                'receipt_number' => '9000146118',
            ]],
            'items' => [[
                'item_name' => 'Suntory Kakubin', 'quantity' => 2, 'money' => 458000,
                'category' => ['category_name' => 'Whisky'],
                'stock_unit' => ['stock_unit_name' => 'Ly'],
                'variant' => ['code' => 'SK-HIGHBALL', 'unit_price' => 229000],
            ]],
            'customer' => [
                'last_name' => 'Nguyễn Trọng', 'first_name' => 'Tâm', 'phone' => '0865683649',
                'email' => 'trongtam@thegats.vn', 'sex' => 'male', 'birthday' => 813690000,
                'order_count' => 634, 'total_spent' => 472852721, 'loyalty_point' => 9002,
                'member_code' => '0865683649',
            ],
        ], $them);
    }

    protected function gui(array $orders, array $store = ['id' => 46101, 'name' => 'Gemination Đà Lạt'], string $ma = 'ma-thu')
    {
        return $this->withHeaders(['X-Dong-Bo-Token' => $ma, 'Origin' => 'https://fnb.mysapo.vn'])
            ->postJson('/api/sapo/hoa-don', ['store' => $store, 'orders' => $orders]);
    }

    public function test_nhap_hoa_don_mon_va_the_khach(): void
    {
        $this->gui([$this->don()])
            ->assertOk()
            ->assertJson(['dia_diem' => 'Gemination Đà Lạt', 'moi' => 1, 'coSdt' => 1, 'mon' => 1, 'khach' => 1]);

        $hd = Invoice::firstOrFail();
        $this->assertSame('9000146118', $hd->code);
        $this->assertSame('Đã thanh toán', $hd->status);
        $this->assertSame('Ăn tại bàn', $hd->service_type);
        $this->assertSame('0865683649', $hd->customer_phone);
        $this->assertSame('Nguyễn Trọng Tâm', $hd->customer_name);
        $this->assertSame('Bàn H8', $hd->table_code);
        $this->assertSame('VIP', $hd->membership_card);
        $this->assertEquals(75150, $hd->service_fee);
        $this->assertEquals(1578150, $hd->total);
        // Gio luu theo gio Viet Nam, khop voi du lieu nhap tu Excel.
        $this->assertSame('2026-09-17 02:22:03', $hd->paid_at->format('Y-m-d H:i:s'));

        $mon = InvoiceItem::firstOrFail();
        $this->assertSame('Suntory Kakubin', $mon->name);
        $this->assertEquals(229000, $mon->unit_price);

        $the = PosCustomer::where('phone', '0865683649')->firstOrFail();
        $this->assertSame(634, (int) $the->invoice_count);
        $this->assertSame('VIP', $the->tier);
    }

    public function test_thue_cong_ca_vat_lan_thue_dich_vu(): void
    {
        // Don that cua Drinking & Healing: 3.177.000 - 587.000 + 129.500 + 253.840 + 10.360.
        $this->gui([$this->don([
            'total_item_price' => 3177000, 'total_discount' => 587000, 'total_price' => 2983700,
            'service_fees' => [['fee_value' => 129500, 'tax' => 8]],
            'taxes' => [
                ['name' => 'Thuế VAT', 'percentage' => 10, 'taxed_value' => 253840],
                ['name' => 'Thuế dịch vụ', 'percentage' => 8, 'taxed_value' => 10360],
            ],
        ])])->assertOk();

        $this->assertEquals(264200, Invoice::first()->vat);
    }

    public function test_gui_lai_khong_sinh_ban_trung(): void
    {
        $this->gui([$this->don()]);
        $this->gui([$this->don(['total_price' => 1600000])])->assertJson(['moi' => 0, 'capNhat' => 1]);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, InvoiceItem::count());
        $this->assertEquals(1600000, Invoice::first()->total);
    }

    public function test_khong_ghi_de_hang_the_khi_sapo_khong_co(): void
    {
        PosCustomer::create(['phone' => '0865683649', 'name' => 'Tâm', 'tier' => 'Kim cương', 'note' => 'chị Na']);

        $this->gui([$this->don(['loyalty_card' => null, 'customer' => ['phone' => '0865683649', 'note' => '']])])->assertOk();

        $the = PosCustomer::where('phone', '0865683649')->first();
        $this->assertSame('Kim cương', $the->tier);
        $this->assertSame('chị Na', $the->note);
    }

    public function test_don_huy_va_don_chua_thanh_toan(): void
    {
        $this->gui([
            $this->don(['status' => 'cancelled', 'cancelled' => ['reason' => 'thiếu mã']]),
            $this->don(['payments' => []]),
        ])->assertJson(['moi' => 1, 'boQua' => 1]);

        $this->assertSame(Invoice::HUY, Invoice::first()->status);
    }

    public function test_sai_ma_bi_chan_va_tat_cong_khi_chua_khai_ma(): void
    {
        $this->gui([$this->don()], ma: 'sai')->assertForbidden();

        config(['booking.sapo_token' => '']);
        $this->gui([$this->don()], ma: '')->assertNotFound();

        $this->assertSame(0, Invoice::count());
    }

    public function test_cua_hang_chua_khai_bao_bao_loi_ro_rang(): void
    {
        $this->gui([$this->don()], ['id' => 999, 'name' => 'Quán lạ'])->assertStatus(422);

        // Khong khai id nhung trung ten dia diem thi van nhan.
        $this->gui([$this->don()], ['id' => 998, 'name' => 'Gemination Đà Lạt'])->assertOk();
    }

    public function test_cors_chi_mo_cho_sapo(): void
    {
        $this->withHeaders(['Origin' => 'https://fnb.mysapo.vn', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/sapo/hoa-don')
            ->assertHeader('Access-Control-Allow-Origin', 'https://fnb.mysapo.vn');
    }
}
