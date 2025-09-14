<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://arcyantix.com',
        'https://www.arcyantix.com',
        'https://api.arcyantix.com',
        'http://localhost:5173', // For local development
        'http://localhost:3000', // Alternative local port
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];