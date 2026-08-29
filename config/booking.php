<?php

return [

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
