<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'http://localhost:5175'),
        env('LAN_FRONTEND_URL'),
    ])),

    // Phones on the office LAN need a credentialed origin to test scanning.
    // Requires APP_ENV=local *and* ALLOW_DEV_ORIGINS=true, and only matches
    // loopback plus RFC1918 ranges. The previous pattern accepted any IPv4.
    'allowed_origins_patterns' => env('APP_ENV', 'production') === 'local'
        && filter_var(env('ALLOW_DEV_ORIGINS', false), FILTER_VALIDATE_BOOLEAN)
        ? ['#^https?://(localhost|127(?:\.\d{1,3}){3}|10(?:\.\d{1,3}){3}|192\.168(?:\.\d{1,3}){2}|172\.(?:1[6-9]|2\d|3[01])(?:\.\d{1,3}){2})(:\d+)?$#']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
