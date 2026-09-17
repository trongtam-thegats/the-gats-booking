<?php

/*
 * Chi mo cho trang quan tri Sapo FnB goi sang cong dong bo hoa don.
 * Trinh duyet dang dang nhap Sapo la noi doc du lieu roi day sang day.
 */
return [
    'paths' => ['api/sapo/*'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => ['https://fnb.mysapo.vn'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Dong-Bo-Token'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
