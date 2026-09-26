Xin chào {{ $user->name }},

@if ($laTaiKhoanMoi)
Tài khoản quản trị của bạn tại The Gats Booking vừa được tạo thành công với email {{ $user->email }}.
Vui lòng truy cập liên kết sau để thiết lập mật khẩu truy cập:
@else
Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản quản trị {{ $user->email }}.
Vui lòng truy cập liên kết sau để tạo mật khẩu mới:
@endif

{{ $resetUrl }}

(Liên kết có hiệu lực trong vòng 60 phút)

Nếu bạn không yêu cầu hành động này, vui lòng bỏ qua thư này. Mật khẩu của bạn vẫn an toàn.

--
The Gats Booking
