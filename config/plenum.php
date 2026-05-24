<?php

declare(strict_types=1);

use Vented\Plenum\Support\NodeListParser;

return [

    'strategy' => env('PLENUM_STRATEGY', 'auth-user'),

    'drivers' => [

        'database' => [
            'enabled' => env('PLENUM_DB_ENABLED'),
            'nodes' => NodeListParser::parse((string) env('PLENUM_DB_NODES', ''), 5432),
            'connection_template' => [
                'driver' => env('PLENUM_DB_DRIVER', 'pgsql'),
                'database' => env('PLENUM_DB_DATABASE', env('DB_DATABASE')),
                'username' => env('PLENUM_DB_USERNAME', env('DB_USERNAME')),
                'password' => env('PLENUM_DB_PASSWORD', env('DB_PASSWORD')),
                'charset' => env('PLENUM_DB_CHARSET', 'utf8'),
                'prefix' => '',
                'schema' => env('PLENUM_DB_SCHEMA', 'public'),
                'sslmode' => env('PLENUM_DB_SSLMODE', 'prefer'),
                'options' => [],
            ],
        ],

        'redis' => [
            'enabled' => env('PLENUM_REDIS_ENABLED'),
            'nodes' => NodeListParser::parse((string) env('PLENUM_REDIS_NODES', ''), 6379),
            'connection_template' => [
                'password' => env('PLENUM_REDIS_PASSWORD'),
                'database' => env('PLENUM_REDIS_DATABASE', 0),
                'read_write_timeout' => env('PLENUM_REDIS_RW_TIMEOUT', 60),
            ],
            'client' => env('PLENUM_REDIS_CLIENT', 'phpredis'),
        ],

    ],

    'health' => [
        'driver' => 'ping',
        'cache_store' => env('PLENUM_HEALTH_CACHE_STORE'),
        'cache_prefix' => env('PLENUM_HEALTH_CACHE_PREFIX', 'plenum:health:'),
        'healthy_ttl_seconds' => (int) env('PLENUM_HEALTHY_CACHE_TTL', 10),
        'down_ttl_seconds' => (int) env('PLENUM_DOWN_CACHE_TTL', 30),
        'probe_timeout_seconds' => (int) env('PLENUM_PROBE_TIMEOUT', 3),
    ],

    'probe' => [
        'enabled' => (bool) env('PLENUM_PROBE_ENABLED', true),
        'interval_seconds' => (int) env('PLENUM_PROBE_INTERVAL', 10),
    ],

    'hash' => [
        'replicas_per_node' => (int) env('PLENUM_HASH_REPLICAS', 64),
    ],

    'middleware' => [
        'auto_register' => (bool) env('PLENUM_MIDDLEWARE_AUTO_REGISTER', true),
        'groups' => ['web', 'api'],
    ],

    'max_failover_attempts' => (int) env('PLENUM_MAX_FAILOVER_ATTEMPTS', 3),

    'expose_debug_header' => (bool) env('PLENUM_EXPOSE_DEBUG_HEADER', false),

    'dashboard' => [
        // null = auto: enabled when app is in the local environment.
        // true/false = explicit override.
        'enabled' => env('PLENUM_DASHBOARD_ENABLED'),
        'path' => env('PLENUM_DASHBOARD_PATH', 'plenum'),
        'domain' => env('PLENUM_DASHBOARD_DOMAIN'),
        'middleware' => ['web'],
        'distribution_samples' => (int) env('PLENUM_DASHBOARD_SAMPLES', 1000),
    ],

];
