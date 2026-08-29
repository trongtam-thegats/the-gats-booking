<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\NotificationLog;
use App\Services\Notifications\BookingMessage;
use App\Services\TicketImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sau khi dat ban xong, khach phai cam duoc mot thu gi do:
 *   - co email va gui duoc -> bao la da gui email
 *   - con lai              -> moi khach luu anh xac nhan
 *
 * Diem quan trong: chi noi "da gui email" khi nhat ky gui tin xac nhan la
 * gui thanh cong. Khach dien email nhung kenh email chua bat thi van phai
 * duoc moi luu anh, khong hua suong.
 */
class ConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'Quán Thử',
            'slug' => 'quan-thu',
            'domain' => 'booking.quanthu.test',
            'mark' => 'QT',
            'accent_color' => '#c8a15a',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->branch = $this->brand->branches()->create([
            'name' => 'Quán Thử',
            'slug' => 'quan-thu',
            'address' => '12 Đường Thử, Đà Lạt',
            'phone' => '0900000000',
            'open_time' => '17:00',
            'close_time' => '23:30',
            'slot_minutes' => 30,
            'turn_minutes' => 120,
            'min_lead_minutes' => 60,
            'max_advance_days' => 30,
            'max_party_size' => 20,
            'is_active' => true,
        ]);

        $area = $this->branch->areas()->create(['name' => 'Tầng 1', 'bookable' => true]);

        foreach (['A01', 'A02'] as $code) {
            $this->branch->diningTables()->create([
                'area_id' => $area->id,
                'code' => $code,
                'table_type' => 'high_table',
                'seats_min' => 1,
                'seats_max' => 4,
                'combinable' => true,
            ]);
        }
    }

    protected function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'branch_id' => $this->branch->id,
            'code' => 'TH'.random_int(100000, 999999),
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0905123456',
            'party_size' => 4,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '21:00',
            'status' => Booking::STATUS_PENDING,
            'source' => 'online',
            'locale' => 'vi',
        ], $attributes));
    }

    protected function markEmailSent(Booking $booking): void
    {
        NotificationLog::create([
            'booking_id' => $booking->id,
            'channel' => 'email',
            'event' => 'created',
            'recipient' => $booking->customer_email,
            'status' => 'sent',
            'message' => 'noi dung',
        ]);
    }

    public function test_khong_co_email_thi_moi_khach_luu_anh_xac_nhan(): void
    {
        $booking = $this->makeBooking(['customer_email' => null]);

        $this->get(route('booking.show', $booking))
            ->assertOk()
            ->assertSee('Bạn chưa để lại email')
            ->assertSee('Lưu ảnh xác nhận')
            ->assertSee('js/ticket.js', false)
            ->assertDontSee('Đã gửi email xác nhận');
    }

    public function test_gui_email_thanh_cong_thi_bao_da_gui_va_khong_moi_luu_anh(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);
        $this->markEmailSent($booking);

        $this->get(route('booking.show', $booking))
            ->assertOk()
            ->assertSee('Đã gửi email xác nhận tới khach@example.com.')
            ->assertDontSee('Lưu ảnh xác nhận')
            ->assertDontSee('js/ticket.js', false);
    }

    /**
     * Truong hop de bo sot nhat: khach dien email nhung kenh email chua bat,
     * nen khong co dong log "sent" nao. Khong duoc bao la da gui.
     */
    public function test_co_email_nhung_chua_gui_duoc_thi_van_moi_luu_anh(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);

        NotificationLog::create([
            'booking_id' => $booking->id,
            'channel' => 'email',
            'event' => 'created',
            'recipient' => 'khach@example.com',
            'status' => 'skipped',
            'message' => 'noi dung',
            'error' => 'Kênh chưa được khai báo.',
        ]);

        $this->get(route('booking.show', $booking))
            ->assertOk()
            ->assertSee('Giữ lại mã đặt bàn')
            ->assertSee('Lưu ảnh xác nhận')
            ->assertDontSee('Đã gửi email xác nhận');
    }

    public function test_don_da_huy_thi_khong_moi_luu_anh_nua(): void
    {
        $booking = $this->makeBooking([
            'customer_email' => null,
            'status' => Booking::STATUS_CANCELLED,
            'cancel_reason' => 'Khách bận',
        ]);

        $this->get(route('booking.show', $booking))
            ->assertOk()
            ->assertDontSee('Lưu ảnh xác nhận')
            ->assertDontSee('js/ticket.js', false);
    }

    public function test_du_lieu_ve_anh_co_du_ma_va_thong_tin_dat_ban(): void
    {
        $booking = $this->makeBooking(['customer_email' => null]);

        $html = $this->get(route('booking.show', $booking))->assertOk()->getContent();

        preg_match('#<script id="ticket-data" type="application/json">(.*?)</script>#s', $html, $m);

        $this->assertNotEmpty($m, 'Thiếu khối dữ liệu để vẽ ảnh xác nhận.');

        $data = json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);

        $this->assertIsArray($data, 'Khối dữ liệu vẽ ảnh không phải JSON hợp lệ.');
        $this->assertSame($booking->code, $data['code']);
        $this->assertStringEndsWith('.png', $data['filename']);
        $this->assertNotEmpty($data['rows']);

        // Moi hang phai la cap [nhan, gia tri] khong rong, neu khong anh se co
        // dong trong trong nhu bi loi.
        foreach ($data['rows'] as $row) {
            $this->assertCount(2, $row);
            $this->assertNotSame('', trim((string) $row[0]));
            $this->assertNotSame('', trim((string) $row[1]));
        }

        $flat = json_encode($data['rows'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString($booking->customer_name, $flat);
        $this->assertStringContainsString('0905123456', $flat);
        $this->assertStringContainsString('12 Đường Thử, Đà Lạt', $flat);
    }

    public function test_ban_tieng_anh_dung_chu_tieng_anh(): void
    {
        $booking = $this->makeBooking(['customer_email' => null]);

        $this->get(route('booking.show', $booking).'?lang=en')
            ->assertOk()
            ->assertSee('You did not leave an email')
            ->assertSee('Save confirmation image');
    }

    public function test_email_xac_nhan_chua_link_ve_dung_ten_mien_cua_quan(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);
        $booking->load('branch.brand');

        $this->assertStringContainsString(
            'https://booking.quanthu.test/ma/'.$booking->code,
            BookingMessage::body($booking, 'created')
        );
    }

    public function test_thu_bao_huy_khong_kem_link_xem_lai(): void
    {
        $booking = $this->makeBooking([
            'customer_email' => 'khach@example.com',
            'status' => Booking::STATUS_CANCELLED,
        ]);
        $booking->load('branch.brand');

        $this->assertStringNotContainsString('/ma/', BookingMessage::body($booking, 'cancelled'));
    }

    /**
     * Dung transport "array" chu khong dung Mail::fake(): EmailChannel gui bang
     * Mail::raw chu khong qua Mailable, nen assertSent() khong bat duoc. Doc
     * thang thu da soan ra moi kiem duoc nguoi nhan va noi dung.
     *
     * @return \Illuminate\Support\Collection<int, \Symfony\Component\Mailer\SentMessage>
     */
    protected function sentMail(): \Illuminate\Support\Collection
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages();
    }

    public function test_khach_de_email_thi_he_thong_thuc_su_gui_thu(): void
    {
        config(['mail.default' => 'array', 'booking.channels' => ['email']]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Trần Thị B',
            'customer_phone' => '0912345678',
            'customer_email' => 'khach@example.com',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertRedirect();

        $messages = $this->sentMail();

        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $code = Booking::where('customer_phone', '0912345678')->value('code');

        $this->assertSame('khach@example.com', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString($code, $email->getSubject());
        $this->assertStringContainsString($code, $email->getTextBody());
        $this->assertStringContainsString('Quán Thử', $email->getTextBody());

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'email',
            'recipient' => 'khach@example.com',
            'status' => 'sent',
        ]);
    }

    /**
     * Che do ghi log van "gui" thanh cong, nen nhat ky ghi la da gui — nhin vao
     * de tuong khach da nhan duoc thu. Phai co ghi chu di kem.
     */
    public function test_che_do_ghi_log_phai_duoc_ghi_chu_ro_trong_nhat_ky(): void
    {
        config(['mail.default' => 'log', 'booking.channels' => ['email']]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Đỗ Thị F',
            'customer_phone' => '0944555666',
            'customer_email' => 'khach@example.com',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertRedirect();

        $log = NotificationLog::where('channel', 'email')->latest('id')->first();

        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString('chưa gửi ra ngoài', (string) $log->error);
    }

    public function test_gui_that_thi_khong_kem_ghi_chu_nao(): void
    {
        config(['mail.default' => 'array', 'booking.channels' => ['email']]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Bùi Văn G',
            'customer_phone' => '0955666777',
            'customer_email' => 'khach@example.com',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertRedirect();

        $this->assertNull(NotificationLog::where('channel', 'email')->latest('id')->first()->error);
    }

    public function test_thu_gui_tu_dia_chi_rieng_cua_quan(): void
    {
        config(['mail.default' => 'array', 'booking.channels' => ['email']]);

        $this->brand->update([
            'mail_from_address' => 'datban@quanthu.test',
            'mail_from_name' => 'Quán Thử · Đặt bàn',
        ]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Phạm Thị D',
            'customer_phone' => '0911222333',
            'customer_email' => 'khach@example.com',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertRedirect();

        $from = $this->sentMail()->first()->getOriginalMessage()->getFrom()[0];

        $this->assertSame('datban@quanthu.test', $from->getAddress());
        $this->assertSame('Quán Thử · Đặt bàn', $from->getName());
    }

    public function test_quan_chua_khai_dia_chi_rieng_thi_dung_dia_chi_chung(): void
    {
        config([
            'mail.default' => 'array',
            'booking.channels' => ['email'],
            'mail.from.address' => 'chung@thegats.test',
            'mail.from.name' => 'The Gats',
        ]);

        $this->assertNull($this->brand->mail_from_address);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Vũ Văn E',
            'customer_phone' => '0933444555',
            'customer_email' => 'khach@example.com',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertRedirect();

        $this->assertSame(
            'chung@thegats.test',
            $this->sentMail()->first()->getOriginalMessage()->getFrom()[0]->getAddress()
        );
    }

    public function test_ve_duoc_tam_ve_thanh_anh_png(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);
        $booking->load('branch.brand');

        $png = (new TicketImage)->png($booking, 'vi');

        $this->assertNotNull($png, 'Không vẽ được ảnh tấm vé.');

        $kichThuoc = getimagesizefromstring($png);

        $this->assertSame('image/png', $kichThuoc['mime']);
        $this->assertSame(540, $kichThuoc[0]);
        $this->assertGreaterThan(400, $kichThuoc[1]);

        // Anh toan mot mau nghia la khong co chu nao duoc ve — font hong chang han.
        $img = imagecreatefromstring($png);
        $nen = imagecolorat($img, 4, 4);
        $khac = 0;

        for ($x = 0; $x < imagesx($img); $x += 4) {
            for ($y = 0; $y < imagesy($img); $y += 4) {
                if (imagecolorat($img, $x, $y) !== $nen) {
                    $khac++;
                }
            }
        }

        $this->assertGreaterThan(500, $khac, 'Ảnh chỉ có nền, không có chữ nào được vẽ.');
    }

    /**
     * Rat nhieu trinh doc thu chan anh mac dinh. Thu phai doc duoc day du
     * ngay ca khi khach khong bao gio tai anh ve.
     */
    public function test_thu_van_du_thong_tin_khi_khach_chan_anh(): void
    {
        $booking = $this->makeBooking(['customer_email' => 'khach@example.com']);
        $booking->load('branch.brand');

        $html = (new BookingConfirmation($booking, 'created', 'ban chu'))->render();

        // Bo het the <img> di roi kiem lai — mo phong trinh doc thu chan anh.
        $khongAnh = preg_replace('/<img\b[^>]*>/i', '', $html);

        $this->assertStringContainsString($booking->code, $khongAnh);
        $this->assertStringContainsString('Quán Thử', $khongAnh);
        $this->assertStringContainsString('12 Đường Thử, Đà Lạt', $khongAnh);
        $this->assertStringContainsString('0905123456', $khongAnh);
        $this->assertStringContainsString('booking.quanthu.test/ma/'.$booking->code, $khongAnh);
    }

    public function test_thu_gui_di_co_ca_ban_html_lan_ban_chu_va_anh_nhung_kem(): void
    {
        config(['mail.default' => 'array', 'booking.channels' => ['email']]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Hoàng Thị H',
            'customer_phone' => '0966777888',
            'customer_email' => 'khach@example.com',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertRedirect();

        $email = $this->sentMail()->first()->getOriginalMessage();
        $code = Booking::where('customer_phone', '0966777888')->value('code');

        $this->assertStringContainsString($code, (string) $email->getHtmlBody());
        $this->assertStringContainsString($code, (string) $email->getTextBody());

        // Anh phai di kem trong thu (CID), khong phai link tai tu may chu la.
        $anh = collect($email->getAttachments())
            ->first(fn ($a) => str_contains($a->getMediaSubtype(), 'png'));

        $this->assertNotNull($anh, 'Thư không kèm ảnh tấm vé.');
        $this->assertStringContainsString('cid:', (string) $email->getHtmlBody());
    }

    public function test_khach_khong_de_email_thi_khong_gui_thu_va_khong_bao_loi(): void
    {
        config(['mail.default' => 'array', 'booking.channels' => ['email']]);

        $this->post(route('booking.store', $this->branch), [
            'customer_name' => 'Lê Văn C',
            'customer_phone' => '0987654321',
            'party_size' => 2,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
        ])->assertRedirect();

        $this->assertCount(0, $this->sentMail());

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'email',
            'status' => 'skipped',
        ]);
    }
}
