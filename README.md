# The Gats — Hệ thống đặt bàn online

Đặt bàn trực tuyến cho chuỗi The Gats: khách tự chọn chi nhánh, ngày giờ và số khách;
hệ thống tự kiểm tra sức chứa theo sơ đồ bàn rồi giữ bàn; quản lý từng chi nhánh xác nhận
và điều bàn trên một màn hình duy nhất.

**Stack:** Laravel 13 + MySQL 8, Blade server-rendered, JavaScript thuần — **không cần Node.js
khi chạy production**, hợp với hosting chỉ hỗ trợ PHP (giống dự án `the-gats-hr-system`).

---

## 1. Chạy trên máy local

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Sửa `.env` phần kết nối CSDL:

```
DB_DATABASE=thegats_booking
DB_USERNAME=thegats
DB_PASSWORD=...
```

Tạo CSDL rồi nạp dữ liệu khởi tạo:

```bash
php artisan migrate --seed
php artisan serve --port=8001
```

- Trang khách: <http://localhost:8001>
- Trang quản lý: <http://localhost:8001/quan-ly>

Seeder tạo sẵn:

| Tài khoản | Vai trò | Mật khẩu khởi tạo |
|---|---|---|
| `trongtam@thegats.vn` | Quản trị / BGĐ | `ThayMatKhauNgay!2026` |
| `quanly.mau@thegats.vn` | Quản lý chi nhánh mẫu | `ThayMatKhauNgay!2026` |

> **Đổi hai mật khẩu này ngay sau lần đăng nhập đầu tiên** tại *Tài khoản → Sửa*.

Kèm theo là một chi nhánh mẫu với 3 khu vực và 16 bàn để chạy thử. Xóa hoặc sửa lại
sau khi khai báo chi nhánh thật.

---

## 2. Phân quyền

| Vai trò | Phạm vi |
|---|---|
| `admin` — Quản trị / BGĐ | Toàn hệ thống: mọi chi nhánh, tạo chi nhánh, tạo tài khoản |
| `branch_manager` — Quản lý chi nhánh | Chỉ chi nhánh được gắn; khai báo khu vực, bàn, giờ mở, lịch nghỉ |
| `staff` — Nhân viên lễ tân | Chỉ chi nhánh được gắn; xem, xác nhận, xếp bàn — không sửa cấu hình |

Tài khoản không phải `admin` bắt buộc phải gắn với một chi nhánh. Mọi truy vấn danh sách
đặt bàn đều lọc theo phạm vi này, và mở trực tiếp booking của chi nhánh khác sẽ bị chặn 403.

---

## 3. Cách hệ thống tính bàn trống

Đây là phần lõi, nằm ở `app/Services/AvailabilityService.php`.

1. **Khung giờ** sinh từ `open_time` đến `close_time − turn_minutes`, bước `slot_minutes`.
   Ví dụ mở 17:00, đóng 23:30, giữ bàn 120 phút, bước 30 phút → 17:00 … 21:30.
2. **Đóng cửa sau nửa đêm** được hỗ trợ: nhập `close_time = 02:00` là đủ. Mọi mốc giờ
   nhỏ hơn giờ mở được quy về "đêm kinh doanh" của `booking_date`, nên 00:30 vẫn thuộc
   đêm hôm trước chứ không nhảy sang ngày mới.
3. **Bàn bận** = bàn đang gắn với booking ở trạng thái `pending`, `confirmed` hoặc `seated`
   có khoảng thời gian chồng lấn.
4. **Chọn bàn** ưu tiên một bàn vừa khít (đủ chỗ, thừa ít nhất). Không có thì ghép các bàn
   `combinable` **trong cùng khu vực**, tối đa 4 bàn.
5. Khung giờ nào không tìm được bộ bàn phù hợp thì bị khóa trên giao diện khách.

Chống đặt trùng: `BookingService::create()` chạy trong transaction có `lockForUpdate`
trên các booking cùng chi nhánh + cùng ngày, nên hai khách bấm cùng lúc không ăn chung một bàn.

Các mốc chặn khác, khai báo theo từng chi nhánh:

- `min_lead_minutes` — đặt trước tối thiểu (mặc định 60 phút)
- `max_advance_days` — đặt trước tối đa (mặc định 30 ngày)
- `max_party_size` — vượt ngưỡng thì mời khách gọi trực tiếp
- `auto_confirm` — bật thì booking vào thẳng trạng thái *Đã xác nhận*, không cần duyệt tay
- **Lịch nghỉ** — nghỉ cả ngày hoặc chỉ một khung giờ (sự kiện riêng, bảo trì)

Nhân viên đặt hộ khách qua điện thoại được bỏ qua `min_lead_minutes`, `max_advance_days`
và `max_party_size` — nhưng vẫn không đặt được nếu thật sự hết bàn.

---

## 4. Thông báo cho khách

Bật/tắt kênh trong `.env`:

```
BOOKING_NOTIFY_CHANNELS=email,sms,zalo
```

| Kênh | Trạng thái | Cần gì để chạy thật |
|---|---|---|
| **Email** | Chạy được ngay | Khai báo SMTP trong `.env` (mặc định `MAIL_MAILER=log`, thư ghi vào `storage/logs/laravel.log`) |
| **SMS** | Đã viết, chờ tài khoản | `SMS_API_KEY`, `SMS_SECRET_KEY`, `SMS_BRANDNAME` — đang dùng eSMS.vn; đổi nhà cung cấp chỉ cần sửa payload trong `SmsChannel::send()` |
| **Zalo OA** | Đã viết, chờ tài khoản | `ZALO_OA_ACCESS_TOKEN` và **template ID cho từng loại tin** (`ZALO_OA_TEMPLATE_CONFIRMED`, …) — Zalo bắt buộc duyệt trước từng mẫu ZNS |

Kênh chưa khai báo thông tin kết nối sẽ ghi log `skipped` chứ **không** làm hỏng luồng đặt bàn.
Toàn bộ lịch sử gửi nằm ở bảng `notification_logs` và hiện ngay trong trang chi tiết booking,
nên khi khách nói "tôi không nhận được tin" thì tra được ngay.

**Nhắc lịch trước giờ hẹn** — cài cron trên hosting:

```bash
php artisan booking:remind
```

Mặc định nhắc trước 180 phút (`BOOKING_REMINDER_LEAD_MINUTES`), và không nhắc lại booking đã nhắc.

---

## 5. Bản đồ mã nguồn

```
app/
  Services/AvailabilityService.php     Khung giờ, bàn trống, chọn/ghép bàn
  Services/BookingService.php          Tạo, xác nhận, hủy, đổi bàn (có khóa chống trùng)
  Services/Notifications/              BookingNotifier + 3 kênh Email/SMS/Zalo
  Http/Controllers/PublicBookingController.php   Toàn bộ phía khách
  Http/Controllers/Admin/              Dashboard, booking, sơ đồ bàn, chi nhánh, bàn, tài khoản
  Http/Middleware/EnsureRole.php       Chặn theo vai trò
  Support/Roles.php                    Định nghĩa 3 vai trò
resources/views/
  public/                              Trang khách
  admin/                               Khu quản trị
public/css/site.css, admin.css         Toàn bộ giao diện, không cần build
tests/Feature/                         Test phủ luồng đặt bàn và phân quyền
```

Trang quản trị gồm: **Tổng quan hôm nay** (số liệu + hàng chờ duyệt), **Sơ đồ bàn**
(lưới bàn × khung giờ, ô tô màu là đang có khách, bấm vào là ra booking),
**Danh sách đặt bàn** (lọc theo ngày, trạng thái, tìm theo mã/tên/SĐT),
**Đặt bàn hộ khách**, **Chi nhánh & giờ mở**, **Khu vực & bàn**, **Tài khoản**.

---

## 6. Kiểm thử

```bash
php artisan test
```

40 test, phủ: sinh khung giờ (kể cả chi nhánh đóng cửa sau nửa đêm), chọn bàn vừa khít,
ghép bàn cho đoàn đông, khóa khung giờ khi hết bàn, chống đặt sát giờ, lịch nghỉ,
khách tự hủy và nhả bàn, phân quyền giữa các chi nhánh, và xung đột khi xếp bàn tay.

---

## 7. Việc còn lại trước khi lên production

1. Đổi mật khẩu hai tài khoản khởi tạo.
2. Khai báo chi nhánh thật, khu vực và bàn (có chức năng tạo bàn hàng loạt theo dải số).
3. Xóa chi nhánh mẫu.
4. Khai báo SMTP; đăng ký Zalo OA và/hoặc SMS brandname nếu muốn nhắn tin cho khách.
5. Đặt cron cho `php artisan booking:remind`.
6. Trên hosting: `APP_ENV=production`, `APP_DEBUG=false`, trỏ document root vào thư mục `public/`,
   rồi chạy `php artisan config:cache route:cache view:cache`.

Chưa có trong bản này (cố ý, để bàn sau): đặt cọc online, đồng bộ tài khoản với hệ thống HR,
và báo cáo doanh thu theo lượt đặt.
