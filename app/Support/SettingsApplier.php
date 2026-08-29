<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;

/**
 * Do cau hinh luu trong CSDL de len config luc chay.
 *
 * Thu tu uu tien: gia tri khai bao o trang Cai dat > gia tri trong .env.
 * Key nao chua duoc dat thi giu nguyen .env, nen he thong van chay binh thuong
 * khi bang settings con rong.
 */
class SettingsApplier
{
    /** Cac key khong hien lai gia tri tren giao dien. */
    public const SECRETS = [
        'mail_password',
        'sms_api_key',
        'sms_secret_key',
        'zalo_access_token',
        'zalo_secret_key',
        'zalo_refresh_token',
    ];

    /** Toan bo key trang Cai dat quan ly. */
    public const KEYS = [
        'notify_channels',
        'reminder_lead_minutes',
        'mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password',
        'mail_encryption', 'mail_from_address', 'mail_from_name',
        'sms_endpoint', 'sms_api_key', 'sms_secret_key', 'sms_brandname',
        'zalo_endpoint', 'zalo_access_token',
        'zalo_app_id', 'zalo_secret_key', 'zalo_refresh_token', 'zalo_token_expires_at',
        'zalo_template_created', 'zalo_template_confirmed',
        'zalo_template_cancelled', 'zalo_template_reminder',
    ];

    public function apply(): void
    {
        $values = Setting::values();

        if (! $values) {
            return;
        }

        $set = function (string $key, string $configPath) use ($values) {
            $value = $values[$key] ?? null;

            if ($value !== null && $value !== '') {
                Config::set($configPath, $value);
            }
        };

        // Kenh gui tin
        if (! empty($values['notify_channels'])) {
            Config::set('booking.channels', array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $values['notify_channels'])
            ))));
        }

        if (isset($values['reminder_lead_minutes']) && $values['reminder_lead_minutes'] !== '') {
            Config::set('booking.reminder_lead_minutes', (int) $values['reminder_lead_minutes']);
        }

        // SMS
        $set('sms_endpoint', 'booking.sms.endpoint');
        $set('sms_api_key', 'booking.sms.api_key');
        $set('sms_secret_key', 'booking.sms.secret_key');
        $set('sms_brandname', 'booking.sms.brandname');

        // Zalo OA
        $set('zalo_endpoint', 'booking.zalo.endpoint');
        $set('zalo_access_token', 'booking.zalo.access_token');
        $set('zalo_oauth_endpoint', 'booking.zalo.oauth_endpoint');
        $set('zalo_template_created', 'booking.zalo.templates.created');
        $set('zalo_template_confirmed', 'booking.zalo.templates.confirmed');
        $set('zalo_template_cancelled', 'booking.zalo.templates.cancelled');
        $set('zalo_template_reminder', 'booking.zalo.templates.reminder');

        // Email
        $set('mail_mailer', 'mail.default');
        $set('mail_host', 'mail.mailers.smtp.host');
        $set('mail_username', 'mail.mailers.smtp.username');
        $set('mail_password', 'mail.mailers.smtp.password');
        $set('mail_encryption', 'mail.mailers.smtp.encryption');
        $set('mail_from_address', 'mail.from.address');
        $set('mail_from_name', 'mail.from.name');

        if (! empty($values['mail_port'])) {
            Config::set('mail.mailers.smtp.port', (int) $values['mail_port']);
        }
    }
}
