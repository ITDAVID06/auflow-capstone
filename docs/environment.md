# Environment Configuration

This file documents environment variables actively used by the current AUFlow implementation.

## Primary Sources

- baseline defaults: `.env.example`
- runtime config mapping:
  - `config/workflow.php`
  - `config/reports.php`
  - `config/auflow.php` (currently empty — reserved for future auflow-specific config)

## Security-Critical Variables

- `APP_KEY`
  - Laravel core encryption/signing key.
- `SNAPSHOT_SIGNING_KEY`
  - HMAC key used for verification snapshot `action_hash` signing.
- `SNAPSHOT_ALLOW_LEGACY_HASH_VERIFICATION`
  - enables compatibility verification for legacy snapshot hash format.

Do not rotate these keys without a migration plan for existing signed data.

## Application Variables

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- locale settings (`APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`)

## Database and Queue Variables

- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `SESSION_DRIVER`
- Redis variables (`REDIS_HOST`, `REDIS_PORT`, etc.)

Current defaults in `.env.example`:

- `DB_HOST=127.0.0.1`
- `REDIS_HOST=127.0.0.1`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`

Production deployments should override all three to Redis-backed stores if not already set.

## Workflow Variables

From `config/workflow.php`:

- `WORKFLOW_REMINDER_DELAYS`
- `WORKFLOW_REMINDER_DEFAULT_INTERVAL`
- `WORKFLOW_REMINDER_TIME`

## Reports Variables

From `config/reports.php`:

- `REPORTS_ASYNC_EXPORT_THRESHOLD` (default 2000)
- `REPORTS_ASYNC_EXPORT_CACHE_TTL_SECONDS` (default 7200)

## Mail Variables

- `MAIL_MAILER`
- `RESEND_API_KEY`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

## Storage Variables

- `FILESYSTEM_DISK`
- optional S3-compatible values:
  - `AWS_ACCESS_KEY_ID`
  - `AWS_SECRET_ACCESS_KEY`
  - `AWS_DEFAULT_REGION`
  - `AWS_BUCKET`
  - `AWS_USE_PATH_STYLE_ENDPOINT`

## Testing Environment

`phpunit.xml` sets test-specific values, including:

- `APP_ENV=testing`
- MySQL test DB on host `127.0.0.1:3306` (`auflow_test`)
- `QUEUE_CONNECTION=sync`
- `CACHE_STORE=array`
- `SESSION_DRIVER=array`
- `MAIL_MAILER=array`
