<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gui thong bao day (Web Push) den trinh duyet da dang ky.
 *
 * Viet tay bang OpenSSL + hash_hkdf co san trong PHP, KHONG them goi Composer:
 * hosting chi chay PHP tran va repo nay co chu truong khong them thu vien khi
 * tranh duoc. Hai chuan can theo:
 *   - RFC 8291: ma hoa noi dung, kieu "aes128gcm"
 *   - RFC 8292: chu ky VAPID, kieu "vapid" voi JWT ES256
 *
 * iPhone chi nhan duoc khi trang da duoc "Them vao Man hinh chinh" (iOS 16.4+).
 * Safari trong Safari thuong khong dang ky duoc - do la gioi han cua Apple.
 */
class WebPush
{
    /** Cac ma loi noi "dia chi nay chet han roi", xoa khoi co so du lieu. */
    public const DA_CHET = [404, 410];

    /**
     * Sinh mot cap khoa VAPID moi. Chay mot lan, cat vao .env.
     *
     * @return array{public: string, private: string}
     */
    public static function khoaMoi(): array
    {
        $key = self::capKhoa();

        if (! $key) {
            throw new RuntimeException('Không sinh được khoá VAPID: '.openssl_error_string());
        }

        $chiTiet = openssl_pkey_get_details($key)['ec'];

        return [
            'public' => self::b64("\x04".self::dem($chiTiet['x']).self::dem($chiTiet['y'])),
            'private' => self::b64(self::dem($chiTiet['d'])),
        ];
    }

    /**
     * Gui mot thong bao. Tra ve ma HTTP cua dich vu day (201 la nhan).
     *
     * @param  array{endpoint: string, p256dh: string, auth: string}  $dangKy
     */
    public function gui(array $dangKy, string $noiDung, int $songLau = 1800): int
    {
        $than = $this->maHoa($noiDung, $dangKy['p256dh'], $dangKy['auth']);

        $tra = Http::withHeaders([
            'Authorization' => $this->vapid($dangKy['endpoint']),
            'Content-Type' => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'TTL' => (string) $songLau,
            'Urgency' => 'high',
        ])->timeout(10)->withBody($than, 'application/octet-stream')->post($dangKy['endpoint']);

        return $tra->status();
    }

    /**
     * Ma hoa noi dung theo RFC 8291.
     *
     * Goi tin = salt(16) | do dai ban ghi(4) | do dai khoa(1) | khoa cong khai
     * cua may chu(65) | phan da ma hoa.
     */
    public function maHoa(string $noiDung, string $p256dh, string $auth): string
    {
        $khachCongKhai = self::giaiB64($p256dh);
        $bimat = self::giaiB64($auth);

        // Cap khoa dung mot lan cho rieng lan gui nay.
        $tam = self::capKhoa();

        if (! $tam) {
            throw new RuntimeException('Không sinh được khoá tạm: '.openssl_error_string());
        }

        $chiTiet = openssl_pkey_get_details($tam)['ec'];
        $mayChuCongKhai = "\x04".self::dem($chiTiet['x']).self::dem($chiTiet['y']);

        // Bi mat chung ECDH giua may chu va trinh duyet.
        $chung = openssl_pkey_derive(self::khoaCongKhai($khachCongKhai), $tam, 32);

        if ($chung === false) {
            throw new RuntimeException('Không tạo được bí mật chung: '.openssl_error_string());
        }

        // "WebPush: info" gan hai khoa cong khai lai de rang buoc vao dung phien nay.
        $prk = hash_hkdf('sha256', $chung, 32, "WebPush: info\x00".$khachCongKhai.$mayChuCongKhai, $bimat);

        $salt = random_bytes(16);
        $khoa = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $salt);

        // \x02 la dau het ban ghi cuoi cung (chi gui mot ban ghi).
        $daMa = openssl_encrypt($noiDung."\x02", 'aes-128-gcm', $khoa, OPENSSL_RAW_DATA, $nonce, $the);

        if ($daMa === false) {
            throw new RuntimeException('Không mã hoá được nội dung: '.openssl_error_string());
        }

        return $salt
            .pack('N', 4096)
            .pack('C', strlen($mayChuCongKhai))
            .$mayChuCongKhai
            .$daMa.$the;
    }

    /** Chu ky VAPID cho mot dich vu day, theo RFC 8292. */
    public function vapid(string $endpoint, ?int $hetHan = null): string
    {
        $phan = parse_url($endpoint);
        $goc = $phan['scheme'].'://'.$phan['host'].(isset($phan['port']) ? ':'.$phan['port'] : '');

        $dau = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $than = self::b64(json_encode([
            'aud' => $goc,
            'exp' => $hetHan ?? (time() + 12 * 3600),
            'sub' => (string) config('booking.push.subject'),
        ]));

        $rieng = self::giaiB64((string) config('booking.push.private_key'));
        $cong = self::giaiB64((string) config('booking.push.public_key'));

        $ky = openssl_pkey_get_private(self::khoaRieng($rieng, $cong));

        if (! $ky || ! openssl_sign($dau.'.'.$than, $der, $ky, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Không ký được VAPID: '.openssl_error_string());
        }

        return 'vapid t='.$dau.'.'.$than.'.'.self::b64(self::derSangRaw($der))
            .', k='.self::b64($cong);
    }

    /**
     * Chu ky ECDSA cua OpenSSL o dang DER; JWT ES256 doi hai so r, s moi so
     * 32 byte noi lien. Khong doi thi Apple va Google deu tra 401.
     */
    protected static function derSangRaw(string $der): string
    {
        $i = 4;
        $doDaiR = ord($der[3]);
        $r = substr($der, $i, $doDaiR);
        $i += $doDaiR + 2;
        $s = substr($der, $i, ord($der[$i - 1]));

        return self::dem(ltrim($r, "\x00")).self::dem(ltrim($s, "\x00"));
    }

    /**
     * Sinh cap khoa P-256, hoac dung lai mot khoa cong khai co san.
     *
     * Tren Windows, OpenSSL khong tu tim thay openssl.cnf va moi loi deu hien
     * ra duoi dang "No such process" rat kho hieu; khai OPENSSL_CNF trong .env
     * la xong. May chu Linux khong can.
     *
     * @param  array<string, string>|null  $ec
     */
    protected static function capKhoa(?array $ec = null): \OpenSSLAsymmetricKey|false
    {
        $caiDat = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];

        if ($ec !== null) {
            $caiDat['ec'] = $ec + ['curve_name' => 'prime256v1'];
        }

        if (filled($cnf = config('booking.push.openssl_cnf'))) {
            $caiDat['config'] = $cnf;
        }

        return openssl_pkey_new($caiDat);
    }

    /** Khoa cong khai P-256 dang 65 byte -> khoa OpenSSL doc duoc. */
    protected static function khoaCongKhai(string $raw): \OpenSSLAsymmetricKey
    {
        $key = self::capKhoa(['x' => substr($raw, 1, 32), 'y' => substr($raw, 33, 32)]);

        if (! $key) {
            throw new RuntimeException('Khoá công khai của trình duyệt không hợp lệ.');
        }

        return $key;
    }

    /** Khoa rieng 32 byte + khoa cong khai -> dang PEM cho openssl_sign. */
    protected static function khoaRieng(string $rieng, string $cong): string
    {
        // Khung DER co dinh cua khoa EC P-256 (RFC 5915), chi thay phan so.
        $der = "\x30\x77\x02\x01\x01\x04\x20".$rieng
            ."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            ."\xa1\x44\x03\x42\x00".$cong;

        return "-----BEGIN EC PRIVATE KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END EC PRIVATE KEY-----\n";
    }

    /** Dem so 0 vao dau cho du 32 byte. */
    protected static function dem(string $so): string
    {
        return str_pad($so, 32, "\x00", STR_PAD_LEFT);
    }

    public static function b64(string $tho): string
    {
        return rtrim(strtr(base64_encode($tho), '+/', '-_'), '=');
    }

    public static function giaiB64(string $chu): string
    {
        return (string) base64_decode(strtr($chu, '-_', '+/'), true);
    }
}
