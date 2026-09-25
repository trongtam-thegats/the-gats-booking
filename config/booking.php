<?php

return [

    /*
     * Ten mien cua khu quan tri.
     *
     * PHAI doc qua config chu khong goi env() thang trong ma nguon: khi chay
     * `php artisan config:cache` tren may that, env() ngoai thu muc config/
     * luon tra ve null — khu quan tri se 404 tren dung ten mien cua no.
     */
    'admin_domain' => env('ADMIN_DOMAIN'),

    /*
     * Dong bo hoa don thang tu Sapo FnB (xem SapoDongBoService).
     * sapo_token: ma bi mat trinh duyet gui kem; de trong = tat cong.
     * sapo_stores: "id_cua_hang_sapo:slug_dia_diem,..."; cua hang khong khai
     * thi doi chieu theo ten dia diem.
     */
    /*
     * Day don dat ban da xac nhan sang trang dat lich cua Sapo FnB.
     * url: ten mien trang dat lich cua chuoi (co ma alias trong duong dan).
     * som_nhat_phut / xa_nhat_ngay: muc du phong khi khong hoi duoc Sapo.
     */
    'sapo_dat_lich' => [
        'bat' => (bool) env('SAPO_DAT_LICH', false),
        'url' => env('SAPO_DAT_LICH_URL', ''),
        'merchant_id' => env('SAPO_MERCHANT_ID', ''),
        // Chi dung khi khong hoi duoc Sapo; muc that doc tu /api/booking/merchant-info.
        'som_nhat_phut' => (int) env('SAPO_DAT_LICH_SOM_NHAT_PHUT', 30),
        'xa_nhat_ngay' => (int) env('SAPO_DAT_LICH_XA_NHAT_NGAY', 30),
        // Chi day don duoc xac nhan tu moc nay tro di. Bat tinh nang len ma
        // khong co moc thi toan bo don cu con hieu luc se do sang Sapo mot luc.
        'tu_luc' => env('SAPO_DAT_LICH_TU_LUC', ''),
        'timeout' => (int) env('SAPO_DAT_LICH_TIMEOUT', 10),
    ],

    /*
     * Thong bao day (Web Push) cho nhan vien.
     * Sinh cap khoa mot lan bang lenh: php artisan push:khoa-moi
     */
    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'subject' => env('VAPID_SUBJECT', 'mailto:datban@thegats.vn'),
        // Chi may Windows moi can: duong dan toi openssl.cnf.
        'openssl_cnf' => env('OPENSSL_CNF', ''),
    ],

    /*
     * Gui thong bao dat ban vao nhom Zalo cua tung quan qua Engine Bot (https://zalo.thegats.vn).
     *
     * Bat/tat toan bo hoac ghi de ten nhom cua tung quan qua bien moi truong.
     * Cu phap ZALO_GROUP_MAP: "slug_quan:Ten Nhom Zalo,slug_quan_2:Ten Nhom Zalo 2"
     */
    'zalo_group' => [
        'bat' => (bool) env('ZALO_GROUP_BAT', true),
        'engine_url' => rtrim((string) env('ZALO_ENGINE_URL', 'https://zalo.thegats.vn'), '/'),
        'nhom' => array_merge([
            'drinking-healing' => env('ZALO_GROUP_DH', "DH's Booking"),
            'gemination' => env('ZALO_GROUP_GEMI_DALAT', 'Gemination Đà Lạt - Booking'),
            'gemination-da-lat' => env('ZALO_GROUP_GEMI_DALAT', 'Gemination Đà Lạt - Booking'),
            'gemination-ba-na' => env('ZALO_GROUP_GEMI_BANA', "Gemination Bà Nà's Booking"),
        ], collect(explode(',', (string) env('ZALO_GROUP_MAP', '')))
            ->map(fn ($cap) => array_map('trim', explode(':', $cap, 2)))
            ->filter(fn ($cap) => count($cap) === 2 && $cap[0] !== '' && $cap[1] !== '')
            ->mapWithKeys(fn ($cap) => [$cap[0] => $cap[1]])
            ->all()
        ),
        'timeout' => (int) env('ZALO_GROUP_TIMEOUT', 10),
    ],

    'sapo_token' => env('SAPO_DONG_BO_TOKEN', ''),
    'sapo_stores' => collect(explode(',', (string) env('SAPO_STORES', '')))
        ->map(fn ($cap) => array_map('trim', explode(':', $cap, 2)))
        ->filter(fn ($cap) => count($cap) === 2 && $cap[0] !== '' && $cap[1] !== '')
        ->mapWithKeys(fn ($cap) => [$cap[0] => $cap[1]])
        ->all(),

    /*
    |--------------------------------------------------------------------------
    | Kenh gui thong bao cho khach
    |--------------------------------------------------------------------------
    |
    | Danh sach kenh se duoc goi moi khi booking thay doi trang thai.
    | Kenh nao chua khai bao thong tin ket noi se ghi log "skipped" chu khong
    | lam hong luong dat ban.
    |
    */

    'channels' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BOOKING_NOTIFY_CHANNELS', 'email'))
    ))),

    'zalo' => [
        'access_token' => env('ZALO_OA_ACCESS_TOKEN'),
        'endpoint' => env('ZALO_OA_ENDPOINT', 'https://business.openapi.zalo.me/message/template'),
        // Dia chi doi refresh token lay access token moi.
        'oauth_endpoint' => env('ZALO_OA_OAUTH_ENDPOINT', 'https://oauth.zaloapp.com/v4/oa/access_token'),
        'templates' => [
            'created' => env('ZALO_OA_TEMPLATE_CREATED'),
            'confirmed' => env('ZALO_OA_TEMPLATE_CONFIRMED'),
            'cancelled' => env('ZALO_OA_TEMPLATE_CANCELLED'),
            'reminder' => env('ZALO_OA_TEMPLATE_REMINDER'),
        ],
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'esms'),
        'endpoint' => env('SMS_ENDPOINT', 'https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post_json/'),
        'api_key' => env('SMS_API_KEY'),
        'secret_key' => env('SMS_SECRET_KEY'),
        'brandname' => env('SMS_BRANDNAME', 'THEGATS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nhac lich
    |--------------------------------------------------------------------------
    |
    | So phut truoc gio hen se gui tin nhac khach (lenh booking:remind).
    |
    */

    'reminder_lead_minutes' => (int) env('BOOKING_REMINDER_LEAD_MINUTES', 180),

];
