@extends('layouts.admin')

@section('title', 'Cài đặt gửi tin')

@php
    $channels = explode(',', (string) ($values['notify_channels'] ?? 'email'));
    $has = fn (string $key) => filled($values[$key] ?? null);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Cài đặt gửi tin</h1>
            <p>Khai báo ngay tại đây, không cần sửa file trên hosting. Giá trị lưu ở đây sẽ ghi đè cấu hình trong <code>.env</code>.</p>
        </div>
    </div>

    @if ($failures > 0)
        <div class="alert alert-error">
            7 ngày qua có {{ $failures }} lượt gửi tin thất bại. Xem nhật ký cuối trang để biết lý do.
        </div>
    @endif

    <form method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <h2>Kênh gửi tin</h2>
            <p class="sub">Mỗi lần khách đặt, được xác nhận, bị hủy hoặc đến giờ nhắc lịch, hệ thống gửi qua các kênh đang bật.</p>

            <div class="row" style="gap:20px">
                @foreach (['email' => 'Email', 'sms' => 'SMS brandname', 'zalo' => 'Zalo OA (ZNS)'] as $key => $label)
                    <label class="check">
                        <input type="checkbox" name="notify_channels[]" value="{{ $key }}"
                               @checked(in_array($key, $channels, true))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="field" style="max-width:260px; margin-top:16px">
                <label for="reminder_lead_minutes">Nhắc lịch trước bao nhiêu phút</label>
                <input type="number" id="reminder_lead_minutes" name="reminder_lead_minutes" min="15" max="1440" step="15"
                       value="{{ old('reminder_lead_minutes', $values['reminder_lead_minutes'] ?? 180) }}" required>
                <span class="hint">Cần đặt cron chạy <code>php artisan booking:remind</code> thì tin nhắc mới gửi.</span>
            </div>
        </div>

        <div class="card">
            <h2>Email</h2>
            <p class="sub">Để <b>Ghi log</b> khi chạy thử: thư không gửi ra ngoài mà lưu vào <code>storage/logs/laravel.log</code>.</p>

            <div class="form-grid">
                <div class="field">
                    <label for="mail_mailer">Cách gửi</label>
                    <select id="mail_mailer" name="mail_mailer">
                        @foreach (['smtp' => 'SMTP (gửi thật)', 'log' => 'Ghi log (chạy thử)'] as $key => $label)
                            <option value="{{ $key }}" @selected(($values['mail_mailer'] ?? config('mail.default')) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="mail_host">Máy chủ SMTP</label>
                    <input type="text" id="mail_host" name="mail_host" value="{{ $values['mail_host'] ?? '' }}"
                           placeholder="smtp.gmail.com">
                </div>
                <div class="field">
                    <label for="mail_port">Cổng</label>
                    <input type="number" id="mail_port" name="mail_port" value="{{ $values['mail_port'] ?? '' }}"
                           placeholder="587" min="1" max="65535">
                </div>
                <div class="field">
                    <label for="mail_encryption">Mã hóa</label>
                    <select id="mail_encryption" name="mail_encryption">
                        @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'null' => 'Không'] as $key => $label)
                            <option value="{{ $key }}" @selected(($values['mail_encryption'] ?? 'tls') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="mail_username">Tài khoản</label>
                    <input type="text" id="mail_username" name="mail_username" value="{{ $values['mail_username'] ?? '' }}">
                </div>
                <div class="field">
                    <label for="mail_password">Mật khẩu {!! $has('mail_password') ? '<span class="muted">(đã lưu — để trống nếu giữ nguyên)</span>' : '' !!}</label>
                    <input type="password" id="mail_password" name="mail_password" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="mail_from_address">Email người gửi</label>
                    <input type="email" id="mail_from_address" name="mail_from_address"
                           value="{{ $values['mail_from_address'] ?? '' }}" placeholder="datban@thegats.vn">
                </div>
                <div class="field">
                    <label for="mail_from_name">Tên người gửi</label>
                    <input type="text" id="mail_from_name" name="mail_from_name"
                           value="{{ $values['mail_from_name'] ?? '' }}" placeholder="The Gats">
                </div>
            </div>
        </div>

        <div class="card">
            <h2>SMS brandname</h2>
            <p class="sub">Đang dùng eSMS.vn. Nhà cung cấp khác thì sửa lại địa chỉ API bên dưới và payload trong <code>SmsChannel</code>.</p>

            <div class="form-grid">
                <div class="field">
                    <label for="sms_brandname">Brandname</label>
                    <input type="text" id="sms_brandname" name="sms_brandname"
                           value="{{ $values['sms_brandname'] ?? 'THEGATS' }}">
                </div>
                <div class="field">
                    <label for="sms_api_key">API key {!! $has('sms_api_key') ? '<span class="muted">(đã lưu)</span>' : '' !!}</label>
                    <input type="password" id="sms_api_key" name="sms_api_key" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="sms_secret_key">Secret key {!! $has('sms_secret_key') ? '<span class="muted">(đã lưu)</span>' : '' !!}</label>
                    <input type="password" id="sms_secret_key" name="sms_secret_key" autocomplete="new-password">
                </div>
                <div class="field full">
                    <label for="sms_endpoint">Địa chỉ API</label>
                    <input type="text" id="sms_endpoint" name="sms_endpoint"
                           value="{{ $values['sms_endpoint'] ?? config('booking.sms.endpoint') }}">
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Zalo OA</h2>
            <p class="sub">
                Zalo bắt buộc duyệt trước từng mẫu tin (ZNS). Mỗi loại tin cần một mã template riêng —
                thiếu mã nào thì loại tin đó ghi log <i>bỏ qua</i> chứ không làm hỏng luồng đặt bàn.
            </p>

            @php($tokenNote = match (true) {
                $zaloToken['can_refresh'] => 'Đã khai báo đủ — hệ thống tự gia hạn access token, anh không phải dán lại.',
                $zaloToken['has_token'] => 'Mới chỉ có access token dán tay. Token này hết hạn sau khoảng một giờ và sẽ ngừng gửi được. Hãy điền thêm App ID, Secret key và Refresh token.',
                default => 'Chưa khai báo gì cho Zalo.',
            })

            <p class="{{ $zaloToken['can_refresh'] ? 'alert alert-ok' : ($zaloToken['has_token'] ? 'alert alert-error' : 'hint') }}"
               style="margin-bottom:14px">
                {{ $tokenNote }}
                @if ($zaloToken['expires_at'])
                    Hạn token hiện tại: {{ $zaloToken['expires_at'] }}{{ $zaloToken['expired'] ? ' — đã hết hạn' : '' }}.
                @endif
            </p>

            <div class="form-grid">
                <div class="field">
                    <label for="zalo_app_id">App ID</label>
                    <input type="text" id="zalo_app_id" name="zalo_app_id" value="{{ $values['zalo_app_id'] ?? '' }}">
                </div>
                <div class="field">
                    <label for="zalo_secret_key">Secret key {!! $has('zalo_secret_key') ? '<span class="muted">(đã lưu)</span>' : '' !!}</label>
                    <input type="password" id="zalo_secret_key" name="zalo_secret_key" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="zalo_refresh_token">Refresh token {!! $has('zalo_refresh_token') ? '<span class="muted">(đã lưu)</span>' : '' !!}</label>
                    <input type="password" id="zalo_refresh_token" name="zalo_refresh_token" autocomplete="new-password">
                    <span class="hint">Có ô này thì hệ thống tự gia hạn, không phải dán lại mỗi giờ.</span>
                </div>
                <div class="field">
                    <label for="zalo_access_token">Access token {!! $has('zalo_access_token') ? '<span class="muted">(đã lưu)</span>' : '' !!}</label>
                    <input type="password" id="zalo_access_token" name="zalo_access_token" autocomplete="new-password">
                    <span class="hint">Chỉ để thử nhanh — token này sống khoảng một giờ.</span>
                </div>
                @foreach ([
                    'zalo_template_created' => 'Mã template — khách vừa gửi yêu cầu',
                    'zalo_template_confirmed' => 'Mã template — đã xác nhận',
                    'zalo_template_cancelled' => 'Mã template — đã hủy',
                    'zalo_template_reminder' => 'Mã template — nhắc lịch',
                ] as $key => $label)
                    <div class="field">
                        <label for="{{ $key }}">{{ $label }}</label>
                        <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ $values[$key] ?? '' }}">
                    </div>
                @endforeach
                <div class="field full">
                    <label for="zalo_endpoint">Địa chỉ API</label>
                    <input type="text" id="zalo_endpoint" name="zalo_endpoint"
                           value="{{ $values['zalo_endpoint'] ?? config('booking.zalo.endpoint') }}">
                </div>
            </div>
        </div>

        <div class="row" style="margin-top:16px">
            <button class="btn" type="submit">Lưu cấu hình</button>
        </div>
    </form>

    <div class="card">
        <h2>Gửi thử</h2>
        <p class="sub">
            Kiểm tra Email và SMS ngay. Zalo ZNS chỉ gửi được theo template đã duyệt gắn với một đặt bàn thật,
            nên hãy thử bằng cách xác nhận một booking.
        </p>
        <form method="post" action="{{ route('admin.settings.test') }}" class="form-grid">
            @csrf
            <div class="field">
                <label for="channel">Kênh</label>
                <select id="channel" name="channel">
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                </select>
            </div>
            <div class="field">
                <label for="recipient">Gửi tới</label>
                <input type="text" id="recipient" name="recipient" placeholder="email@... hoặc 09xx xxx xxx" required>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button class="btn btn-ghost" type="submit">Gửi thử</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Nhật ký gửi tin gần đây</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Thời điểm</th><th>Mã đặt bàn</th><th>Kênh</th><th>Sự kiện</th><th>Người nhận</th><th>Kết quả</th><th>Lý do</th></tr>
                </thead>
                <tbody>
                @forelse ($recentLogs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('H:i d/m') }}</td>
                        <td class="small">
                            @if ($log->booking)
                                <a href="{{ route('admin.bookings.show', $log->booking) }}">{{ $log->booking->code }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="small">{{ $log->channelLabel() }}</td>
                        <td class="small muted">{{ $log->event }}</td>
                        <td class="small">{{ $log->recipient ?: '—' }}</td>
                        <td class="small">{{ $log->statusLabel() }}</td>
                        <td class="small muted">{{ $log->error ?: '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Chưa có lượt gửi nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
