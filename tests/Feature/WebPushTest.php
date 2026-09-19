<?php

namespace Tests\Feature;

use App\Support\WebPush;
use Tests\TestCase;

/**
 * Phan ma hoa cua thong bao day.
 *
 * Viet tay theo RFC 8291 (noi dung) va RFC 8292 (chu ky VAPID) nen phai tu
 * kiem: test dong vai TRINH DUYET - giai ma nguoc lai bang khoa rieng cua
 * minh, va xac minh chu ky JWT bang khoa cong khai. Sai mot byte trong khung
 * goi tin la Apple tra 400 ma khong noi vi sao.
 */
class WebPushTest extends TestCase
{
    /** Tren Windows phai chi ro openssl.cnf, xem App\Support\WebPush::capKhoa(). */
    protected function capKhoa(array $them = []): \OpenSSLAsymmetricKey
    {
        $caiDat = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + $them;

        if (filled($cnf = config('booking.push.openssl_cnf'))) {
            $caiDat['config'] = $cnf;
        }

        $key = openssl_pkey_new($caiDat);

        $this->assertNotFalse($key, 'Không sinh được khoá thử: '.openssl_error_string());

        return $key;
    }

    /**
     * Cap khoa cua "trinh duyet": tra ve khoa rieng (de giai ma) va p256dh.
     *
     * @return array{key: \OpenSSLAsymmetricKey, p256dh: string}
     */
    protected function trinhDuyet(): array
    {
        $key = $this->capKhoa();
        $ec = openssl_pkey_get_details($key)['ec'];

        return [
            'key' => $key,
            'p256dh' => WebPush::b64("\x04".str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT).str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT)),
        ];
    }

    /** Giai ma goi tin y nhu trinh duyet lam. */
    protected function giaiMa(string $goiTin, \OpenSSLAsymmetricKey $khoaRieng, string $p256dh, string $auth): string
    {
        $salt = substr($goiTin, 0, 16);
        $doDaiKhoa = ord($goiTin[20]);
        $mayChuCongKhai = substr($goiTin, 21, $doDaiKhoa);
        $phanMa = substr($goiTin, 21 + $doDaiKhoa);

        $mayChu = $this->capKhoa(['ec' => [
            'curve_name' => 'prime256v1',
            'x' => substr($mayChuCongKhai, 1, 32),
            'y' => substr($mayChuCongKhai, 33, 32),
        ]]);

        $chung = openssl_pkey_derive($mayChu, $khoaRieng, 32);
        $prk = hash_hkdf('sha256', $chung, 32, "WebPush: info\x00".WebPush::giaiB64($p256dh).$mayChuCongKhai, WebPush::giaiB64($auth));
        $khoa = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $salt);

        $the = substr($phanMa, -16);
        $ro = openssl_decrypt(substr($phanMa, 0, -16), 'aes-128-gcm', $khoa, OPENSSL_RAW_DATA, $nonce, $the);

        $this->assertNotFalse($ro, 'Trình duyệt không giải mã được gói tin.');

        // Byte cuoi la dau het ban ghi.
        $this->assertSame("\x02", substr((string) $ro, -1));

        return substr((string) $ro, 0, -1);
    }

    public function test_trinh_duyet_giai_ma_lai_dung_noi_dung(): void
    {
        $td = $this->trinhDuyet();
        $auth = WebPush::b64(random_bytes(16));
        $noiDung = json_encode(['tieu_de' => 'Đơn mới', 'noi_dung' => 'Gemination · 4 khách · 19:00'], JSON_UNESCAPED_UNICODE);

        $goiTin = (new WebPush)->maHoa($noiDung, $td['p256dh'], $auth);

        $this->assertSame($noiDung, $this->giaiMa($goiTin, $td['key'], $td['p256dh'], $auth));
    }

    public function test_moi_lan_gui_dung_mot_khoa_khac(): void
    {
        $td = $this->trinhDuyet();
        $auth = WebPush::b64(random_bytes(16));

        $mot = (new WebPush)->maHoa('xin chào', $td['p256dh'], $auth);
        $hai = (new WebPush)->maHoa('xin chào', $td['p256dh'], $auth);

        $this->assertNotSame($mot, $hai, 'Hai lần gửi cùng nội dung không được ra gói tin giống hệt.');
        $this->assertSame('xin chào', $this->giaiMa($hai, $td['key'], $td['p256dh'], $auth));
    }

    public function test_noi_dung_dai_van_giai_ma_duoc(): void
    {
        $td = $this->trinhDuyet();
        $auth = WebPush::b64(random_bytes(16));
        $dai = str_repeat('Khách đặt bàn lúc 19:00. ', 100);

        $goiTin = (new WebPush)->maHoa($dai, $td['p256dh'], $auth);

        $this->assertSame($dai, $this->giaiMa($goiTin, $td['key'], $td['p256dh'], $auth));
    }

    public function test_chu_ky_vapid_xac_minh_duoc_bang_khoa_cong_khai(): void
    {
        $khoa = WebPush::khoaMoi();
        config(['booking.push.public_key' => $khoa['public'], 'booking.push.private_key' => $khoa['private'], 'booking.push.subject' => 'mailto:datban@thegats.vn']);

        $header = (new WebPush)->vapid('https://web.push.apple.com/abc/def?x=1');

        [$t, $k] = explode(', ', $header);
        $jwt = substr($t, strlen('vapid t='));
        $khoaGui = substr($k, strlen('k='));

        [$dau, $than, $chuKy] = explode('.', $jwt);

        $this->assertSame($khoa['public'], $khoaGui);
        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(WebPush::giaiB64($dau), true));

        $noiDung = json_decode(WebPush::giaiB64($than), true);
        // Chi lay goc cua dia chi, khong keo theo duong dan hay tham so.
        $this->assertSame('https://web.push.apple.com', $noiDung['aud']);
        $this->assertSame('mailto:datban@thegats.vn', $noiDung['sub']);
        $this->assertGreaterThan(time(), $noiDung['exp']);
        $this->assertLessThanOrEqual(time() + 24 * 3600, $noiDung['exp'], 'Apple từ chối JWT sống quá 24 tiếng.');

        // Chu ky phai dung dang raw 64 byte, khong phai DER.
        $raw = WebPush::giaiB64($chuKy);
        $this->assertSame(64, strlen($raw));

        $der = $this->rawSangDer($raw);
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode("\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00".WebPush::giaiB64($khoa['public'])), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        $this->assertSame(1, openssl_verify($dau.'.'.$than, $der, $pem, OPENSSL_ALGO_SHA256));
    }

    /** Doi nguoc chu ky raw 64 byte ve DER de openssl_verify doc duoc. */
    protected function rawSangDer(string $raw): string
    {
        $so = function (string $x): string {
            $x = ltrim($x, "\x00");
            $x = $x === '' ? "\x00" : $x;
            $x = ord($x[0]) > 0x7F ? "\x00".$x : $x;

            return "\x02".chr(strlen($x)).$x;
        };

        $than = $so(substr($raw, 0, 32)).$so(substr($raw, 32));

        return "\x30".chr(strlen($than)).$than;
    }
}
