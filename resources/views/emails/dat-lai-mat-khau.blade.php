{{--
    Thu dat lai mat khau cho nhan vien / quan tri vien.
    Su dung bang HTML inline CSS de hien thi tot tren moi trinh doc email.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ $laTaiKhoanMoi ? 'Thiết lập mật khẩu' : 'Đặt lại mật khẩu' }}</title>
</head>
<body style="margin:0; padding:0; background:#0e0d0c; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#f4efe6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0e0d0c; width:100%; margin:0; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:540px; background:#141312; border:1px solid #2b2724; border-radius:8px; overflow:hidden;">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:28px 24px 20px; border-bottom:1px solid #2b2724; background:#181615;">
                            <span style="display:inline-block; font-size:18px; font-weight:700; letter-spacing:3px; color:#c8a15a; text-transform:uppercase;">
                                THE GATS
                            </span>
                            <div style="font-size:12px; color:#9c968c; letter-spacing:1px; margin-top:4px;">
                                HỆ THỐNG ĐẶT BÀN QUẢN TRỊ
                            </div>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:32px 28px;">
                            <h2 style="font-size:20px; font-weight:600; color:#f4efe6; margin:0 0 16px;">
                                Xin chào {{ $user->name }},
                            </h2>

                            <p style="font-size:15px; line-height:1.6; color:#d6d0c7; margin:0 0 20px;">
                                @if ($laTaiKhoanMoi)
                                    Tài khoản quản trị của bạn tại The Gats Booking vừa được tạo thành công với email <b>{{ $user->email }}</b>. Vui lòng bấm nút bên dưới để thiết lập mật khẩu truy cập:
                                @else
                                    Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản quản trị <b>{{ $user->email }}</b>. Vui lòng bấm vào nút bên dưới để tạo mật khẩu mới:
                                @endif
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0;">
                                <tr>
                                    <td align="center" style="border-radius:6px; background:#c8a15a;">
                                        <a href="{{ $resetUrl }}" target="_blank" style="display:inline-block; padding:14px 28px; font-size:14px; font-weight:700; letter-spacing:1px; color:#141312; text-decoration:none; text-transform:uppercase; border-radius:6px;">
                                            {{ $laTaiKhoanMoi ? 'Thiết lập mật khẩu' : 'Đặt lại mật khẩu' }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:13px; line-height:1.6; color:#9c968c; margin:20px 0 0;">
                                ⏱️ <i>Liên kết này có hiệu lực trong vòng <b>60 phút</b> kể từ thời điểm gửi.</i>
                            </p>

                            <p style="font-size:13px; line-height:1.6; color:#9c968c; margin:12px 0 0;">
                                Nếu nút bấm trên không hoạt động, bạn có thể sao chép và dán liên kết sau vào trình duyệt:<br>
                                <a href="{{ $resetUrl }}" style="color:#c8a15a; word-break:break-all; font-size:12px;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 28px; background:#100f0e; border-top:1px solid #2b2724; font-size:12px; line-height:1.5; color:#78736b;">
                            Nếu bạn không yêu cầu hành động này, vui lòng bỏ qua thư này hoặc thông báo cho Quản trị viên hệ thống. Mật khẩu của bạn vẫn an toàn.<br><br>
                            &copy; {{ date('Y') }} The Gats Booking. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
