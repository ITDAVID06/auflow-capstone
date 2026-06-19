<?php

require_once __DIR__.'/../vendor/autoload.php';

// When Laravel's config is cached (bootstrap/cache/config.php exists), it is loaded
// verbatim and the <env> variables in phpunit.xml have no effect — the app connects
// to the dev database instead of auflow_test.  Remove the cache file here, before
// any test code runs, so that config/database.php re-reads the env vars that
// PHPUnit has already injected via putenv().
//
// NOTE: Do NOT run `php artisan test --parallel`.  This project uses a single shared
// test database (auflow_test).  Parallel workers each call migrate:fresh on startup,
// causing deadlocks.  Serial execution (the default) is the only supported mode.
$configCache = __DIR__.'/../bootstrap/cache/config.php';
if (file_exists($configCache)) {
    unlink($configCache);
}
