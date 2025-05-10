<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Optimization Settings
    |--------------------------------------------------------------------------
    |
    | This file contains various performance optimization settings for the
    | application. These settings help improve page load times and overall
    | performance.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure cache settings for optimal performance
    |
    */
    'cache' => [
        'enabled' => env('CACHE_ENABLED', true),
        'ttl' => env('CACHE_TTL', 3600), // 1 hour
        'prefix' => env('CACHE_PREFIX', 'tiktok_viewer_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | View Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure view caching settings
    |
    */
    'view_cache' => [
        'enabled' => env('VIEW_CACHE_ENABLED', true),
        'ttl' => env('VIEW_CACHE_TTL', 86400), // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure route caching settings
    |
    */
    'route_cache' => [
        'enabled' => env('ROUTE_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Optimization
    |--------------------------------------------------------------------------
    |
    | Configure asset optimization settings
    |
    */
    'assets' => [
        'minify' => env('ASSETS_MINIFY', true),
        'version' => env('ASSETS_VERSION', '1.0.0'),
        'preload' => [
            'enabled' => env('ASSETS_PRELOAD', true),
            'critical_css' => env('ASSETS_CRITICAL_CSS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Optimization
    |--------------------------------------------------------------------------
    |
    | Configure database optimization settings
    |
    */
    'database' => [
        'query_cache' => env('DB_QUERY_CACHE', true),
        'query_cache_ttl' => env('DB_QUERY_CACHE_TTL', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Optimization
    |--------------------------------------------------------------------------
    |
    | Configure session optimization settings
    |
    */
    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours
        'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
        'encrypt' => env('SESSION_ENCRYPT', true),
        'cookie' => env('SESSION_COOKIE', 'tiktok_viewer_session'),
        'path' => '/',
        'domain' => env('SESSION_DOMAIN', null),
        'secure' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => true,
        'same_site' => 'lax',
    ],
]; 