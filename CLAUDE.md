# Hệ thống đặt bàn The Gats

Một bản cài phục vụ nhiều tên miền. Khách đặt bàn ở tên miền của quán, nhân viên vào khu quản lý ở
một tên miền khác. Hiện chạy cho **Gemination Đà Lạt** và **Drinking Healing**.

Mã nguồn dùng **tiếng Việt không dấu** cho tên phương thức, biến, và lệnh artisan mới; chú thích viết
tiếng Việt. Đây là quy ước của dự án, không phải nhầm lẫn — đừng đổi sang tiếng Anh.

## Ràng buộc phải nhớ

- **Hosting chỉ chạy PHP.** Không Node, không bước build, không npm. Front-end là Blade + JS thuần,
  CSS viết tay trong `public/css/`. Đừng đề xuất Vite, Tailwind, React, hay thư viện biểu đồ ngoài.
- **Không thêm gói Composer nếu tránh được.** Ví dụ: đọc `.xlsx` bằng `App\Support\XlsxReader`
  (ZipArchive + SimpleXML có sẵn) thay vì PhpSpreadsheet. Đó là lựa chọn có chủ ý.
- **Mọi tính toán ngày tháng làm trong PHP**, không dùng hàm ngày tháng của MySQL, để chạy được trên
  cả cơ sở dữ liệu khác (`ReportService`, `CustomerInsightService` đều theo lối này).

## Chạy trên máy lập trình

```sh
mysqld --datadir="C:/mysql-data"   # MySQL không đăng ký service, phải bật tay
php artisan serve --port=8001
php artisan test
```

Test chạy trên **MySQL** (`thegats_booking_test`), không phải SQLite — chính sách Application Control
của Windows đang chặn `php_pdo_sqlite.dll` trên máy này. Bản `phpunit.xml` gốc dùng SQLite nằm ở
`phpunit.xml.bak`.

## Những chỗ trông như lỗi nhưng là cố ý

Đọc mục này trước khi "sửa" bất cứ điều gì bên dưới.

**`env()` chỉ được gọi trong thư mục `config/`.** Máy thật chạy `config:cache` nên mọi lời gọi `env()`
ngoài đó trả về null. Đã từng làm khu quản trị 404 sạch trên máy thật mà máy lập trình không tái hiện
được. Có test quét toàn bộ mã nguồn chặn tái phạm.

**`EnsureAdminSite` phải chạy trước middleware đăng nhập.** Laravel xếp ưu tiên qua interface
`AuthenticatesRequests`, không qua tên class — xem `bootstrap/app.php`.

**`AvailabilityService::daySlots()` đọc dữ liệu một lần cho cả ngày** rồi tính trong bộ nhớ. Trước đây
mỗi khung giờ tự truy vấn lại: 69 câu lệnh cho một lần khách bấm đổi ngày, nay còn 4. Đừng gọi
`availableTables()` hay `isClosed()` trong vòng lặp khung giờ; dùng `closuresFor()` / `bookableTables()`
/ `blockingIntervals()` rồi `closedIn()` / `busyIdsIn()`.

**Trang đặt bàn in sẵn khung giờ của lựa chọn mặc định vào HTML** (`initialSlots` từ controller) để
khỏi phải gọi API một vòng trước khi khách thấy giờ. JS chỉ gọi mạng khi khách đổi lựa chọn.

**`invoices` unique theo `(branch_id, code)`, còn `bookings.code` unique toàn hệ thống.** Khác nhau có
lý do: POS đánh số hoá đơn riêng từng quán nên hai quán trùng mã là bình thường (đã gặp 21 mã trùng
thật), còn mã đặt bàn phải duy nhất toàn cục vì khách tra cứu ở `/ma/{code}`.

**Trạng thái "đã ghé lại" của khách là tính ra, không lưu.** `guest_notes.reviewed_at` chỉ ghi thời
điểm nhân viên xem xét; hệ thống so với lần ghé gần nhất để suy ra. Nhãn tĩnh sẽ mục dần vì khách quay
lại mà không ai nhớ gỡ. Đừng thêm cột trạng thái.

**Bàn chưa phân khu (`area_id` null) vẫn nhận đặt online.** Cờ `areas.bookable` nghĩa là "khu này có
nhận đơn từ web không". Lọc bằng `whereHas` đơn thuần sẽ loại nhầm bàn chưa phân khu và làm đỏ hàng
loạt test.

**Đêm kinh doanh.** Quán đóng cửa sau nửa đêm, nên mọi mốc giờ quy về số phút tính từ 00:00 của đêm
đó; giờ nhỏ hơn giờ mở cửa thì thuộc rạng sáng hôm sau. **`Branch::thoiDiemTrongDem()` là nguồn duy
nhất của quy tắc này** — `Booking::startsAt()` và `AvailabilityService::slotStartsAt()` đều gọi về đó.
Đừng tự tính lại ở chỗ khác: đã từng có hai bản sao lệch nhau, làm khách đặt ca 01:00 không tự hủy
được và không nhận được tin nhắc lịch, suốt một thời gian dài mà không ai phát hiện.
**Giờ đóng cửa và giờ chốt nhận đặt bàn là hai thứ khác nhau** (`branches.last_booking_time`).

**Số điện thoại luôn đi qua `App\Support\SoDienThoai::chuan()`** — số Việt Nam về `0xxx`, số nước
ngoài giữ `+ma`. Ba nguồn dữ liệu (đặt bàn, hoá đơn POS, thẻ khách POS) chỉ ghép được với nhau nhờ
đúng một chuẩn này.

**Mẫu số của tỉ lệ đến = đến + không đến**, bỏ đơn hủy và đơn chờ duyệt. Đừng đổi thành tổng số đơn.

## Bẫy kỹ thuật đã từng sập

- `Carbon::diffInDays()` ở Carbon 3 trả về **số thực** — `$days === 30` luôn sai, phải ép `(int)`.
- Migration đừng dùng `UPDATE ... JOIN` hay `FIELD()` của MySQL (test có thể chạy trên CSDL khác).
- Không xóa được unique index đang gắn khóa ngoại — tạo index mới trước rồi mới xóa cái cũ.
- `@json([...])` với mảng nhiều dòng lồng ngoặc làm Blade lỗi phân tích cú pháp — gom mảng trong khối
  `@php` trước rồi `@json($bien)`.
- `Request::create()` của Laravel tự gửi `Accept-Language: en-us,en` nên test trang khách sẽ ra tiếng
  Anh; `tests/TestCase.php` đã ghim `vi`.
- Route model binding của `Brand` và `Branch` dùng **slug**, không phải id.
- Tệp POS có cặp cột lồng nhau: `Hóa đơn` (số lần ghé) và `Hóa đơn gần nhất` (mã hoá đơn). Khớp tên
  theo phần đầu sẽ nuốt nhầm — xem hằng `KHOP_DUNG` trong `PosImportService`.
- Ngày trong tệp `.xlsx` là **số serial của Excel**, phải đổi qua `XlsxReader::ngay()`.

## Nhập dữ liệu cũ

Mọi lệnh mặc định **chỉ xem trước**, thêm `--ghi` mới thật sự lưu, và nhập đè được (không sinh bản trùng):

```sh
php artisan dat-ban:nhap-nightify tep.csv --quan=drinking-healing [--ghi] [--xoa]
php artisan pos:nhap-hoa-don tep.xlsx --quan=gemination [--ghi]
php artisan pos:nhap-khach-hang tep.xlsx [--ghi]
```

Quản trị viên cũng tải tệp `.xlsx` lên được ở cuối trang *Hóa đơn*, dùng chung `PosImportService` với
lệnh artisan.

`dining_tables.aliases` giữ **tên cũ** của bàn (`Bar 1` ← `B1,B01`) để tệp xuất từ hệ thống cũ vẫn tra
được sau khi quán đổi tên bàn.

## Triển khai

Đẩy thẳng mã nguồn, **không clone git trên máy chủ**:

```sh
git archive HEAD | ssh root@<vps> 'tar -x -C /var/www/thegats-booking'
# rồi trên máy chủ: composer install --no-dev, migrate --force,
# chown -R www-data:www-data, config:cache route:cache view:cache
```

Một khối `server` của nginx khai cả ba `server_name`; ứng dụng tự phân biệt theo Host. `APP_KEY` dùng
chung với máy lập trình **có chủ ý**, để cấu hình đã mã hoá trong bảng `settings` (mật khẩu SMTP) giải
mã được sau khi chuyển sang.
