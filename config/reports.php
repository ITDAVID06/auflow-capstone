<?php

return [
    'async_export_threshold' => env('REPORTS_ASYNC_EXPORT_THRESHOLD', 2000),
    'async_export_cache_ttl_seconds' => env('REPORTS_ASYNC_EXPORT_CACHE_TTL_SECONDS', 7200),
];
