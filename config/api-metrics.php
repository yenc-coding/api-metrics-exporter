<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Metrics Storage Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "redis", "memory"
    |
    */
    'driver' => env('API_METRICS_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | Redis connection name as defined in config/database.php
    |
    */
    'redis' => [
        'connection' => env('API_METRICS_REDIS_CONNECTION', 'default'),
        'prefix' => env('API_METRICS_REDIS_PREFIX', 'api_metrics:'),
        'ttl' => env('API_METRICS_REDIS_TTL', 86400), // 24 hours in seconds
        'client' => env('API_METRICS_REDIS_CLIENT', 'phpredis'), // phpredis or predis
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Endpoint Configuration
    |--------------------------------------------------------------------------
    |
    */
    'endpoint' => [
        'path' => env('API_METRICS_ENDPOINT_PATH', 'metrics'),
        'middleware' => explode(',', env('API_METRICS_ENDPOINT_MIDDLEWARE', 'api')),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging level for metrics collection
    |
    */
    'logging' => [
        'enabled' => env('API_METRICS_LOGGING', true),
        'level' => env('API_METRICS_LOG_LEVEL', 'info'), // debug, info, notice, warning, error, critical, alert, emergency
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection Configuration
    |--------------------------------------------------------------------------
    |
    | The 'enabled' option lets you quickly disable all metrics collection.
    | 
    | For 'excluded_paths', specify path patterns without leading slashes.
    | For example: 'metrics', 'health', 'debug*' 
    | Asterisks (*) can be used as wildcards.
    |
    | For 'excluded_methods', specify HTTP methods to exclude like: 'OPTIONS', 'HEAD'
    |
    */
    'collection' => [
        'enabled' => env('API_METRICS_ENABLED', true),
        'excluded_paths' => explode(',', env('API_METRICS_EXCLUDED_PATHS', '')),
        'excluded_methods' => explode(',', env('API_METRICS_EXCLUDED_METHODS', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Histogram Buckets Configuration
    |--------------------------------------------------------------------------
    |
    | Define the buckets for request duration histogram in seconds
    |
    */
    'histogram_buckets' => [
        0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Endpoint Authentication
    |--------------------------------------------------------------------------
    |
    | Optional HTTP Basic Authentication for /metrics endpoint.
    | Set 'enabled' to true and provide username/password via env or config.
    */
    'basic_auth' => [
        'enabled' => env('API_METRICS_BASIC_AUTH_ENABLED', false),
        'username' => env('API_METRICS_BASIC_AUTH_USERNAME', 'admin'),
        'password' => env('API_METRICS_BASIC_AUTH_PASSWORD', 'admin'),
    ],
];
