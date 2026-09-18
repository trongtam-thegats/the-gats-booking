# Bàn giao: hệ thống đặt bàn The Gats + tích hợp Sapo FnB

Tài liệu này viết cho một công cụ/AI khác tiếp quản. Đọc hết file này **trước**, rồi mới đọc
`CLAUDE.md` (ràng buộc kỹ thuật của repo). Cập nhật lần cuối: 18/09/2026.

---

## 1. Hệ thống đang có gì

| | |
|---|---|
| Mã nguồn | `C:\the-gats-booking` · GitHub `git@github.com:trongtam-thegats/the-gats-booking.git`, nhánh `main` |
| Nền tảng | Laravel 13 + MySQL 8, Blade + JS thuần. **Không Node, không bước build, không npm** |
| Máy chủ | `root@160.30.113.75`, thư mục `/var/www/thegats-booking` (không phải git repo), Ubuntu 24.04 + nginx + PHP 8.4 |
| CSDL máy chủ | `thegats_booking`, mật khẩu ở `/root/.thegats_booking_db` |
| Tên miền | `booking.thegats.vn` (khu quản trị, nhìn cả chuỗi) · `booking.gemination.vn` · `booking.drinkinghealing.com` (trang khách từng quán) |
| Hai quán | `gemination` = Gemination Đà Lạt (branch id 2) · `drinking-healing` = Drinking Healing (branch id 3) |
| Kiểm thử | `php artisan test` — **299 test, tất cả đang xanh**. Chạy trên MySQL (`thegats_booking_test`), không phải SQLite |

Quy ước mã nguồn: **tên hàm/biến/lệnh viết tiếng Việt không dấu**, chú thích tiếng Việt. Đây là chủ ý,
đừng đổi sang tiếng Anh.

Chạy máy lập trình:

```sh
mysqld --datadir="C:/mysql-data"   # MySQL không đăng ký service, phải bật tay
php artisan serve --port=8001
php artisan test
```

Triển khai (đẩy thẳng, không clone git trên máy chủ):

```sh
git archive -o /tmp/deploy.tar HEAD
scp -i ~/.ssh/thegats_vps /tmp/deploy.tar root@160.30.113.75:/root/deploy.tar
ssh -i ~/.ssh/thegats_vps root@160.30.113.75 'cd /var/www/thegats-booking && tar -xf /root/deploy.tar -C . && composer install --no-dev --optimize-autoloader --no-interaction && php artisan migrate --force && chown -R www-data:www-data . && php artisan config:cache && php artisan route:cache && php artisan view:cache'
```

`composer install --optimize-autoloader` là **bắt buộc khi có class mới** (máy chủ dùng autoloader
classmap). Máy chủ chạy `config:cache`, nên **mọi lời gọi `env()` ngoài thư mục `config/` trả về
null** — đã có test quét toàn bộ mã nguồn chặn tái phạm.

---

## 2. Hai luồng tích hợp Sapo FnB đang chạy

Sapo **chưa cấp API chính thức** cho gói này. Cả hai luồng dưới đây dựa trên chính các địa chỉ mà
giao diện web của Sapo tự gọi. Ai tiếp quản cần biết chúng có thể đổi khi Sapo cập nhật.

### 2.1 Kéo hoá đơn từ Sapo về (cần trình duyệt đã đăng nhập)

**Đường dữ liệu:** trình duyệt đang đăng nhập `fnb.mysapo.vn` đọc `/admin/orders.json` rồi POST sang
`booking.thegats.vn/api/sapo/hoa-don`.

| Địa chỉ Sapo | Dùng để |
|---|---|
| `GET /admin/store.json` | cửa hàng đang mở: **46101 = Gemination Đà Lạt**, **89781 = Drinking & Healing** |
| `GET /admin/orders.json?page=1&limit=50&from=<unix>&to=<unix>` | hoá đơn + toàn bộ món; `metadata.total` là tổng |
| `GET /admin/customers/{customer_id}.json` | số điện thoại khách — **đơn hàng chỉ có `customer_id`, không có SĐT** |

**Phía hệ thống mình:** `App\Services\SapoDongBoService` + `POST /api/sapo/hoa-don`
(`App\Http\Controllers\Api\SapoDongBoController`), khoá bằng header `X-Dong-Bo-Token`, CORS chỉ mở cho
`https://fnb.mysapo.vn` (`config/cors.php`). Ghi vào `invoices`, `invoice_items`, `pos_customers` —
**đúng các bảng mà cổng nhập Excel dùng**, khoá `(branch_id, code)`, chạy lại bao nhiêu lần cũng không
sinh bản trùng.

Script chạy đồng bộ: `~/.thegats_sapo_dong_bo.js` (thay `__TOKEN__` bằng nội dung
`~/.thegats_sapo_token`, `__TU_NGAY__` bằng ngày bắt đầu). Dán vào Console của tab Sapo đã đăng nhập.

**Bốn cái bẫy, đừng dẫm lại:**

1. **Sapo vá `window.fetch`** để gắn header `x-merchant-id`. Header đó làm CORS chặn. Phải POST bằng
   `fetch` lấy từ một `<iframe>` rỗng.
2. **Một phiên đăng nhập chỉ thấy MỘT cửa hàng.** Đã thử `store_id`, `storeId`, `store` trên
   `orders.json` và mọi biến thể `/admin/stores*.json` — không có đường đổi cửa hàng. Người dùng phải
   tự chuyển trong giao diện Sapo. **Đừng dò lại.**
3. **Mã hoá đơn = `payments[].receipt_number`**, không phải `name` (E76496). Hai quán trùng mã là bình
   thường vì Sapo đánh số riêng từng cửa hàng.
4. **Hạng thẻ trên đơn là hạng LÚC ĐẶT ĐƠN**, còn file Excel ghi hạng hiện tại. Sapo trả đơn mới
   trước, nên nếu cứ lấy theo từng đơn thì đơn cũ nạp sau sẽ hạ hạng khách. `pos_customers.tier` chỉ
   nhận giá trị từ hoá đơn **mới nhất** của số đó.

Thuế: cộng toàn bộ `taxes[].taxed_value` (VAT 10% + thuế dịch vụ 8%) mới khớp cột "Thuế VAT" của Excel.

**Cổng nhập Excel cũ vẫn còn** ở `/quan-ly/hoa-don` (`PosImportService`, `XlsxReader` đọc .xlsx bằng
ZipArchive + XMLReader, **cố ý không dùng PhpSpreadsheet**). Giữ lại làm đường lui.

### 2.2 Đẩy đơn đặt bàn sang Sapo (máy chủ gọi thẳng, không cần trình duyệt)

Trang đặt lịch công khai của Sapo: `https://8fcb38937d4e4531add16289f9368c69.sapofnb.vn/booking/form?id=<storeId>`
— merchantId **19579**.

| Địa chỉ | Ghi chú |
|---|---|
| `POST /api/booking/submit` | **không cần đăng nhập**. Trả `{tableBooking:{code}}` |
| `GET /api/booking/code?code=&storeId=&merchantId=` | tra lại một đơn |
| `GET /api/booking/merchant-info?merchantId=19579` | cấu hình từng cửa hàng, gồm mốc nhận đơn |

Gói tin gửi đi:

```json
{"receptionTime": 1789738200, "clientTime": 1789708665, "customerCount": 2,
 "customerPhone": "09xxxxxxxx", "customerName": "...", "customerEmail": null,
 "note": "[booking.thegats.vn TGE4FXL8] · Bàn: T1 · ghi chú của khách",
 "items": [], "combos": [], "storeId": 89781, "merchantId": 19579, "type": "website"}
```

**Sapo KHÔNG có đường huỷ hay sửa đơn cho bên ngoài** — chỉ tạo và tra. Đây là hạn chế lớn nhất của
luồng này. Nên khi đơn bị huỷ hoặc đổi giờ, hệ thống bật cột `bookings.sapo_can_xu_ly`; trang chi tiết
đơn hiện ô đỏ và nút *Tôi đã xử lý bên Sapo* để nhân viên vào Sapo sửa tay rồi gỡ nhắc.

Mốc nhận đơn **đọc thẳng từ `merchant-info`** (nhớ 1 giờ) vì chủ quán đổi được trong Sapo — ngày
18/09/2026 đã đổi từ 2 tiếng xuống **30 phút**, đặt xa nhất 30 ngày. Đừng chép con số này vào `.env`.

Mã nguồn: `App\Services\SapoDatLichService`, lệnh `sapo:day-dat-lich` (có `--xem` để xem trước,
`--gioi-han=N`), cron 5 phút, log `/var/log/thegats-booking-sapo.log`. Cột trên `bookings`:
`sapo_code`, `sapo_pushed_at`, `sapo_error`, `sapo_can_xu_ly`.

**Quy tắc sống còn:** đẩy sang Sapo hỏng thì **không bao giờ được làm hỏng luồng đặt bàn của khách** —
mọi lỗi nuốt lại và ghi vào `sapo_error`, lệnh cron thử lại sau.

### 2.3 Biến môi trường cần có trên máy chủ

Giá trị thật nằm trong `/var/www/thegats-booking/.env` và `~/.thegats_sapo_token` trên máy lập trình.
**Đừng chép giá trị vào tài liệu hay repo.**

```
SAPO_DONG_BO_TOKEN=<chuỗi bí mật 64 ký tự>
SAPO_STORES=46101:gemination,89781:drinking-healing
SAPO_DAT_LICH=true
SAPO_DAT_LICH_URL=https://8fcb38937d4e4531add16289f9368c69.sapofnb.vn
SAPO_MERCHANT_ID=19579
# tuỳ chọn: SAPO_DAT_LICH_TU_LUC (chỉ đẩy đơn xác nhận từ mốc này),
#           SAPO_DAT_LICH_SOM_NHAT_PHUT / SAPO_DAT_LICH_XA_NHAT_NGAY (mức dự phòng)
```

---

## 3. Dữ liệu đang có (máy chủ, 18/09/2026)

| | Gemination Đà Lạt | Drinking Healing |
|---|---|---|
| Hoá đơn | ~20.800 (từ 01/2024) | ~5.900 (từ 11/2025) |
| Dòng món | ~74.000 | ~20.000 |
| Hoá đơn tháng 9 có SĐT | **21,9%** | **62,5%** |

**Điểm yếu vận hành quan trọng:** phần lớn doanh thu không gắn được vào khách nào vì thu ngân không
nhập số điện thoại. Drinking Healing đã cải thiện mạnh trong tháng 9 (21% → 62%), Gemination thì chưa.
Mọi xếp hạng khách quen hiện chỉ dựng trên phần doanh thu có SĐT. **Đây là việc của vận hành, không
sửa được bằng mã.**

Bảng chính: `bookings`, `dining_tables` (chỉ có cột `code`, **không có cột `name`**), `areas`,
`branches`, `brands`, `invoices`, `invoice_items`, `pos_customers`, `guest_notes`, `notification_logs`,
`booking_deletions`, `settings`.

Sao lưu tự động: `/root/backups/backup.sh` chạy 03:00 hằng đêm cho cả ba CSDL, giữ 14 ngày.

---

## 4. Những quyết định đã chốt — đừng làm lại

- **Không làm đối soát doanh thu.** Đã dựng một lần rồi gỡ sạch theo yêu cầu. Hoá đơn POS trong hệ
  thống này chỉ để **phân tích khách quen**.
- **Không gán `no_show` cho đơn không khớp hoá đơn.** Hoá đơn chỉ nhập SĐT ở 12–26% nên suy như vậy là
  sai, và sẽ biến khách quen thành khách bùng kèo trong hồ sơ.
- **Ghép tự động đặt bàn ↔ hoá đơn theo giờ là ngõ cụt**, đã đo: 136/148 đơn không phân biệt nổi.
- **Khu quản trị nền tối, chữ sáng.** Đã thử nền sáng, người dùng bác. Đừng đề xuất lại.
- **Trang khách không được để lộ tên khách theo số điện thoại** (có test chặn) — bất kỳ ai cũng dò
  được tên của hàng nghìn khách.
- **Chỉ một trang chi tiết khách duy nhất** (`/quan-ly/khach-hang/{sdt}`).
- Quy tắc dùng chung, **đừng tạo bản sao thứ hai**: `Branch::thoiDiemTrongDem()` (đêm kinh doanh),
  `App\Support\SoDienThoai::chuan()` (số điện thoại), `App\Support\TenKhach::chon()` (tên khách),
  `Booking::scopeCuaSoDienThoai()` (lọc đặt bàn theo số).

---

## 5. Bẫy kỹ thuật đã sập, đã vá

- **`Http::fake()` trong test:** gọi lần hai **không** thay thế stub cũ; và trộn `Http::sequence()` với
  stub khác trong cùng mảng thì **mọi** yêu cầu đều rút một câu trả lời khỏi dãy. Dùng một closure duy
  nhất tự chia đường.
- **Carbon 3** trả số thực có dấu cho `diffInMinutes`/`diffInDays` — so sánh giờ bằng hiệu timestamp.
- **Cột mới phải khai trong `casts()`.** Quên `sapo_pushed_at` làm trang chi tiết đơn lỗi 500 trên máy
  thật (18/09). Test xanh mà trang vỡ, vì không test nào mở trang đó — nay đã có.
- **Đêm kinh doanh của hoá đơn cắt lúc 07:00**, khác cách xếp giờ đặt bàn.
- `XlsxReader` phải đọc theo luồng (`XMLReader` + `zip://`); nạp cả bảng vào SimpleXML giết tiến trình
  PHP với tệp 14 MB.

---

## 6. Việc còn treo — gợi ý cho người tiếp quản

1. **Thời gian thực cho hoá đơn.** Hiện phải có trình duyệt đăng nhập Sapo. Nếu Antigravity chạy nền
   được một phiên Sapo thì làm được vòng lặp: mỗi X phút gọi `orders.json` cho cửa hàng đang mở rồi
   POST sang `/api/sapo/hoa-don`. **Vẫn vướng: một phiên chỉ thấy một cửa hàng** — cần hai phiên đăng
   nhập song song cho hai quán, hoặc xin Sapo cấp API key.
2. **Huỷ/sửa đơn bên Sapo.** Đường công khai không có. Nếu chạy nền được phiên quản trị Sapo thì tìm
   trong `/admin/order-table` xem thao tác huỷ gọi địa chỉ nào, rồi nối vào `sapo_can_xu_ly` để tự
   động hoá nốt phần đang phải làm tay.
3. **Gemination chỉ thu SĐT ở 21,9% hoá đơn.** Việc của vận hành, nhưng có thể hỗ trợ bằng báo cáo
   theo thu ngân để quản lý quán biết ai hay bỏ trống.
4. **Giờ mở cửa theo từng thứ trong tuần** (hiện một khung chung cho cả 7 ngày) — người dùng đã nhấn
   mạnh nhiều lần.
5. Đơn Sapo đầu tiên được đẩy thử (mã Sapo **2609181**, quán Drinking Healing 18/09 20:30) có ghi chú
   lỗi `Bàn: ,` do lấy nhầm tên bàn. Đã vá; đơn đó bên Sapo vẫn dùng được.

---

## 7. Cách đóng gói để đưa sang máy/công cụ khác

```bash
# 1. Mã nguồn kèm toàn bộ lịch sử (một file duy nhất)
cd /c/the-gats-booking && git bundle create ../the-gats-booking.bundle --all

# 2. Dữ liệu từ máy chủ (chạy trên VPS, rồi tải về)
ssh -i ~/.ssh/thegats_vps root@160.30.113.75 'mysqldump --single-transaction --routines thegats_booking -u thegats -p"$(cat /root/.thegats_booking_db)" | gzip > /root/thegats_booking.sql.gz'
scp -i ~/.ssh/thegats_vps root@160.30.113.75:/root/thegats_booking.sql.gz .
```

Bên nhận: `git clone the-gats-booking.bundle`, `composer install`, chép `.env` (tự điền bí mật),
`gunzip < thegats_booking.sql.gz | mysql thegats_booking`.

**Không đóng gói kèm:** `.env`, `~/.thegats_sapo_token`, khoá SSH `~/.ssh/thegats_vps`. Bí mật đưa
riêng, không nằm trong repo hay tài liệu.
