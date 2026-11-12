<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', 'alona-reverb-key'),
            'secret' => env('REVERB_APP_SECRET', 'alona-reverb-secret'),
            'app_id' => env('REVERB_APP_ID', 'alona-ecommerce-app'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
                'timeout' => (float) env('REVERB_HTTP_TIMEOUT', 1.5),
                'connect_timeout' => (float) env('REVERB_CONNECT_TIMEOUT', 0.5),
            ],
            'forward_http' => (bool) env('REVERB_FORWARD_HTTP', false),
        ],

        'pusher' => [
            'driver' => 'reverb',
            'key' => env('PUSHER_APP_KEY', env('REVERB_APP_KEY', 'alona-reverb-key')),
            'secret' => env('PUSHER_APP_SECRET', env('REVERB_APP_SECRET', 'alona-reverb-secret')),
            'app_id' => env('PUSHER_APP_ID', env('REVERB_APP_ID', 'alona-ecommerce-app')),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                'host' => env('PUSHER_HOST', env('REVERB_HOST', '127.0.0.1')),
                'port' => env('PUSHER_PORT', env('REVERB_PORT', 8080)),
                'scheme' => env('PUSHER_SCHEME', env('REVERB_SCHEME', 'http')),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'http') === 'https',
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
