<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Notifications\ZaloTokenStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Access token cua Zalo OA chi song khoang mot gio. Cac test o day bao dam
 * he thong tu gia han thay vi im lang ngung gui sau mot tieng.
 */
class ZaloTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function store(): ZaloTokenStore
    {
        return new ZaloTokenStore('https://oauth.test/token');
    }

    protected function credentials(): void
    {
        Setting::putMany([
            'zalo_app_id' => '123456',
            'zalo_secret_key' => 'secret',
            'zalo_refresh_token' => 'refresh-cu',
        ]);
    }

    public function test_token_con_han_thi_dung_lai_khong_goi_zalo(): void
    {
        Http::fake();

        Setting::putMany([
            'zalo_access_token' => 'token-con-han',
            'zalo_token_expires_at' => Carbon::now()->addHour()->toDateTimeString(),
        ]);

        $this->assertSame('token-con-han', $this->store()->accessToken());

        Http::assertNothingSent();
    }

    public function test_token_sap_het_han_thi_tu_doi_token_moi(): void
    {
        $this->credentials();

        Setting::putMany([
            'zalo_access_token' => 'token-cu',
            // Con 1 phut: nam trong khoang an toan 5 phut nen phai doi truoc.
            'zalo_token_expires_at' => Carbon::now()->addMinute()->toDateTimeString(),
        ]);

        Http::fake(['oauth.test/*' => Http::response([
            'access_token' => 'token-moi',
            'refresh_token' => 'refresh-moi',
            'expires_in' => 3600,
        ])]);

        $this->assertSame('token-moi', $this->store()->accessToken());
        $this->assertSame('token-moi', Setting::get('zalo_access_token'));
    }

    /**
     * Zalo huy refresh token cu sau moi lan doi. Neu khong ghi de cai moi thi
     * lan gia han sau se hong, va hong am tham.
     */
    public function test_phai_ghi_de_refresh_token_moi(): void
    {
        $this->credentials();

        Http::fake(['oauth.test/*' => Http::response([
            'access_token' => 'token-moi',
            'refresh_token' => 'refresh-moi',
            'expires_in' => 3600,
        ])]);

        $this->store()->refresh();

        $this->assertSame('refresh-moi', Setting::get('zalo_refresh_token'));
    }

    public function test_luu_lai_han_su_dung_de_lan_sau_khong_goi_lai(): void
    {
        $this->credentials();

        Http::fake(['oauth.test/*' => Http::response([
            'access_token' => 'token-moi',
            'refresh_token' => 'refresh-moi',
            'expires_in' => 3600,
        ])]);

        $this->store()->refresh();

        $expiresAt = Carbon::parse(Setting::get('zalo_token_expires_at'));

        $this->assertTrue($expiresAt->isFuture());
        $this->assertEqualsWithDelta(3600, Carbon::now()->diffInSeconds($expiresAt), 30);
    }

    public function test_zalo_bao_loi_thi_nem_ngoai_le_ro_rang(): void
    {
        $this->credentials();

        Http::fake(['oauth.test/*' => Http::response([
            'error' => -201,
            'error_description' => 'Refresh token không hợp lệ',
        ])]);

        $this->expectExceptionMessage('Refresh token không hợp lệ');

        $this->store()->refresh();
    }

    public function test_chua_khai_bao_gi_thi_bao_loi_thay_vi_gui_token_rong(): void
    {
        Http::fake();

        $this->expectExceptionMessage('Chưa khai báo access token');

        $this->store()->accessToken();
    }

    public function test_chi_co_token_dan_tay_thi_van_dung_duoc_nhung_khong_tu_gia_han(): void
    {
        Http::fake();

        Setting::putMany(['zalo_access_token' => 'token-dan-tay']);

        $store = $this->store();

        $this->assertSame('token-dan-tay', $store->accessToken());
        $this->assertFalse($store->status()['can_refresh']);

        Http::assertNothingSent();
    }

    public function test_trang_cai_dat_canh_bao_khi_chi_co_token_dan_tay(): void
    {
        $admin = \App\Models\User::create([
            'name' => 'Quản trị', 'email' => 'admin@thegats.vn', 'password' => 'matkhau123',
            'role' => \App\Support\Roles::ADMIN, 'is_active' => true,
        ]);

        Setting::putMany(['zalo_access_token' => 'token-dan-tay']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('hết hạn sau khoảng một giờ');
    }
}
