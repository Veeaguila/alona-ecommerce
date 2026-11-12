<?php

return [
    // Applies only to orders snapshotted at checkout after the schema is installed.
    'enabled' => env('PAYMENT_DISTRIBUTION_ENABLED', true),
    'release_days' => 7,
    'settlement_account' => env('PAYMENT_SETTLEMENT_ACCOUNT'),
    // This is an internal verified-settlement adapter secret, not a gateway API key.
    'webhook_secret' => env('PAYMENT_SETTLEMENT_WEBHOOK_SECRET'),
    'merchant' => env('PAYMENT_SETTLEMENT_MERCHANT'),
];
