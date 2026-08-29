<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Giu va tu lam moi access token cua Zalo OA.
 *
 * Access token cua Zalo chi song khoang mot gio. Neu chi dan token vao cau hinh
 * roi thoi thi he thong chay duoc mot tieng rui im lang hong - dung loai loi kho
 * phat hien nhat. Vi vay o day luu them refresh token va tu doi token moi khi
 * sap het han.
 *
 * Luu y: moi lan doi, Zalo tra ve mot refresh token MOI va vo hieu cai cu,
 * nen bat buoc phai ghi de lai refresh token vua nhan.
 */
class ZaloTokenStore
{
    /** Doi token som truoc khi het han, tranh dung dung luc dang gui. */
    public const REFRESH_MARGIN_SECONDS = 300;

    public function __construct(protected ?string $endpoint = null)
    {
        $this->endpoint = $endpoint ?: config('booking.zalo.oauth_endpoint');
    }

    /** Da khai bao du thong tin de tu lam moi token chua. */
    public function canRefresh(): bool
    {
        return filled(Setting::get('zalo_app_id'))
            && filled(Setting::get('zalo_secret_key'))
            && filled(Setting::get('zalo_refresh_token'));
    }

    /**
     * Access token dung duoc ngay bay gio.
     *
     * @throws RuntimeException khi khong con token nao dung duoc
     */
    public function accessToken(): string
    {
        $token = (string) Setting::get('zalo_access_token', '');
        $expiresAt = Setting::get('zalo_token_expires_at');

        $stillValid = $token !== ''
            && $expiresAt
            && Carbon::parse($expiresAt)->subSeconds(self::REFRESH_MARGIN_SECONDS)->isFuture();

        if ($stillValid) {
            return $token;
        }

        if ($this->canRefresh()) {
            return $this->refresh();
        }

        // Chua khai bao refresh token: dung tam token da dan tay, nhung no se
        // het han va khong tu gia han duoc.
        if ($token !== '') {
            return $token;
        }

        throw new RuntimeException('Chưa khai báo access token của Zalo OA.');
    }

    /**
     * Doi refresh token lay access token moi va ghi lai ca hai.
     *
     * @throws RuntimeException
     */
    public function refresh(): string
    {
        $response = Http::asForm()
            ->timeout(15)
            ->withHeaders(['secret_key' => (string) Setting::get('zalo_secret_key')])
            ->post($this->endpoint, [
                'app_id' => (string) Setting::get('zalo_app_id'),
                'refresh_token' => (string) Setting::get('zalo_refresh_token'),
                'grant_type' => 'refresh_token',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Không làm mới được token Zalo: HTTP '.$response->status().' '.$response->body());
        }

        $accessToken = $response->json('access_token');
        $refreshToken = $response->json('refresh_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if (blank($accessToken)) {
            throw new RuntimeException(
                'Zalo không trả về access token: '.($response->json('error_description') ?: $response->body())
            );
        }

        Setting::putMany([
            'zalo_access_token' => $accessToken,
            // Zalo huy refresh token cu sau moi lan doi, phai ghi de cai moi.
            'zalo_refresh_token' => $refreshToken ?: (string) Setting::get('zalo_refresh_token'),
            'zalo_token_expires_at' => Carbon::now()->addSeconds($expiresIn)->toDateTimeString(),
        ], ['zalo_access_token', 'zalo_refresh_token', 'zalo_secret_key']);

        Log::info('Đã làm mới access token Zalo OA', [
            'het_han_luc' => Carbon::now()->addSeconds($expiresIn)->toDateTimeString(),
        ]);

        return $accessToken;
    }

    /** Mo ta trang thai token de hien trong trang Cài đặt. */
    public function status(): array
    {
        $expiresAt = Setting::get('zalo_token_expires_at');

        return [
            'has_token' => filled(Setting::get('zalo_access_token')),
            'can_refresh' => $this->canRefresh(),
            'expires_at' => $expiresAt,
            'expired' => $expiresAt ? Carbon::parse($expiresAt)->isPast() : null,
        ];
    }
}
