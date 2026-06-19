<?php

return [
    'reminder_delays' => env('WORKFLOW_REMINDER_DELAYS', '1d,2d,3d'),
    'reminder_default_interval' => env('WORKFLOW_REMINDER_DEFAULT_INTERVAL', '1d'),
    'reminder_time' => env('WORKFLOW_REMINDER_TIME', '09:00'),
    'snapshot' => [
        'signing_key' => env('SNAPSHOT_SIGNING_KEY', env('APP_KEY', '')),
        'allow_legacy_hash_verification' => env('SNAPSHOT_ALLOW_LEGACY_HASH_VERIFICATION', false),
    ],
];
