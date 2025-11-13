<?php
return [
    'paths' => ['api/*', 'save-screen-record', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // 'allowed_origins' => ['https://cvchd7.com'],
    'allowed_origins' => ['https://telemedapi.cvchd7.com','https://cvchd7.com','https://referral-dummy.cvchd7.com','http://192.168.110.23','https://192.168.111.84'],
    // 'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];