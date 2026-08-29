<?php

namespace App\Http\Controllers\Admin;

use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\Notifications\ZaloTokenStore;
use App\Support\SettingsApplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class SettingController extends AdminController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $values = Setting::values();

        // Nhat ky gui tin gan nhat, de bat loi ket noi ngay tren trang cau hinh.
        $recentLogs = NotificationLog::with('booking:id,code')
            ->latest('id')
            ->limit(15)
            ->get();

        $failures = NotificationLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $zaloToken = (new ZaloTokenStore)->status();

        return view('admin.settings.index', compact('values', 'recentLogs', 'failures', 'zaloToken'));
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'notify_channels' => ['array'],
            'notify_channels.*' => [Rule::in(['email', 'sms', 'zalo'])],
            'reminder_lead_minutes' => ['required', 'integer', 'min:15', 'max:1440'],

            'mail_mailer' => ['required', Rule::in(['smtp', 'log', 'array'])],
            'mail_host' => ['nullable', 'string', 'max:180'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:180'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'null'])],
            'mail_from_address' => ['nullable', 'email', 'max:180'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],

            'sms_endpoint' => ['nullable', 'url', 'max:255'],
            'sms_api_key' => ['nullable', 'string', 'max:255'],
            'sms_secret_key' => ['nullable', 'string', 'max:255'],
            'sms_brandname' => ['nullable', 'string', 'max:60'],

            'zalo_endpoint' => ['nullable', 'url', 'max:255'],
            'zalo_access_token' => ['nullable', 'string', 'max:1000'],
            'zalo_app_id' => ['nullable', 'string', 'max:60'],
            'zalo_secret_key' => ['nullable', 'string', 'max:255'],
            'zalo_refresh_token' => ['nullable', 'string', 'max:1000'],
            'zalo_template_created' => ['nullable', 'string', 'max:60'],
            'zalo_template_confirmed' => ['nullable', 'string', 'max:60'],
            'zalo_template_cancelled' => ['nullable', 'string', 'max:60'],
            'zalo_template_reminder' => ['nullable', 'string', 'max:60'],
        ], [], [
            'reminder_lead_minutes' => 'thời điểm nhắc lịch',
            'mail_mailer' => 'cách gửi email',
            'mail_from_address' => 'email người gửi',
        ]);

        $values = [
            'notify_channels' => implode(',', $data['notify_channels'] ?? []),
            'reminder_lead_minutes' => (string) $data['reminder_lead_minutes'],
        ];

        foreach (SettingsApplier::KEYS as $key) {
            if ($key === 'notify_channels' || $key === 'reminder_lead_minutes') {
                continue;
            }

            $value = $data[$key] ?? null;

            // O bi mat de trong = giu nguyen gia tri cu.
            if (in_array($key, SettingsApplier::SECRETS, true) && blank($value)) {
                continue;
            }

            $values[$key] = (string) ($value ?? '');
        }

        Setting::putMany($values, SettingsApplier::SECRETS);

        // Vua dan token hoac refresh token moi thi xoa han cu di, de lan gui
        // tiep theo tu xac dinh lai han moi.
        if (filled($data['zalo_access_token'] ?? null) || filled($data['zalo_refresh_token'] ?? null)) {
            Setting::where('key', 'zalo_token_expires_at')->delete();
            Setting::flush();
        }

        return back()->with('status', 'Đã lưu cấu hình gửi tin.');
    }

    /**
     * Gui thu mot tin de kiem tra ket noi.
     *
     * Zalo ZNS chi gui duoc theo template da duyet gan voi mot booking that,
     * nen o day chi kiem tra Email va SMS.
     */
    public function test(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'recipient' => ['required', 'string', 'max:180'],
        ], [], ['recipient' => 'người nhận']);

        $message = 'The Gats: tin kiem tra cau hinh gui tin luc '.now()->format('H:i d/m/Y').'.';

        try {
            if ($data['channel'] === 'email') {
                Mail::raw($message, fn ($mail) => $mail->to($data['recipient'])
                    ->subject('[The Gats] Kiểm tra cấu hình gửi tin'));
            } else {
                $this->sendTestSms($data['recipient'], $message);
            }
        } catch (Throwable $e) {
            return back()->withErrors(['recipient' => 'Gửi thử thất bại: '.$e->getMessage()]);
        }

        $note = config('mail.default') === 'log' && $data['channel'] === 'email'
            ? ' (đang để chế độ ghi log nên thư nằm trong storage/logs/laravel.log, chưa gửi ra ngoài)'
            : '';

        return back()->with('status', 'Đã gửi thử tới '.$data['recipient'].$note.'.');
    }

    protected function sendTestSms(string $phone, string $message): void
    {
        if (blank(config('booking.sms.api_key')) || blank(config('booking.sms.secret_key'))) {
            throw new \RuntimeException('Chưa khai báo API key và Secret key cho SMS.');
        }

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->post(config('booking.sms.endpoint'), [
                'ApiKey' => config('booking.sms.api_key'),
                'SecretKey' => config('booking.sms.secret_key'),
                'Brandname' => config('booking.sms.brandname'),
                'SmsType' => '2',
                'Phone' => (new \App\Services\Notifications\SmsChannel)->normalizePhone($phone),
                'Content' => $message,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $code = (string) $response->json('CodeResult', '');

        if ($code !== '' && $code !== '100') {
            throw new \RuntimeException('CodeResult '.$code.': '.$response->json('ErrorMessage', ''));
        }
    }
}
