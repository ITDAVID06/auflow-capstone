# Testing

AUFlow uses PHPUnit 11 via Laravel's test runner.

## Test Stack

- Framework: PHPUnit 11 (`phpunit/phpunit`)
- Runner: `php artisan test`
- Test suites:
  - `tests/Feature`
  - `tests/Unit`

## Current Coverage Areas

The current suite covers:

- authentication and forced-password-change flows
- role/permission authorization hardening
- canonical submission writes and dual-write migration safety
- workflow publishing, version snapshots, approvals/rejections, and concurrency locking
- snapshot generation, integrity signing, and immutability guards
- report query/filter/export behaviors (sync and async)
- profile picture private serving and route hardening
- error handling and empty-state behavior
- reminder/notification command and delivery flows

Representative files include:

- `tests/Feature/WorkflowPublishVersionSnapshotTest.php`
- `tests/Feature/StaffSubmissionConcurrentApprovalLockTest.php`
- `tests/Feature/FormBuilder/WriteCanonicalSubmissionActionTest.php`
- `tests/Feature/VerificationSnapshotRouteHardeningTest.php`
- `tests/Unit/SnapshotSecurityServiceTest.php`

## Running Tests

Run all tests:

```bash
php artisan test --compact
```

Run one file:

```bash
php artisan test --compact tests/Feature/WorkflowPublishVersionSnapshotTest.php
```

Run by filter:

```bash
php artisan test --compact --filter=StaffSubmissionConcurrentApprovalLockTest
```

## Test Environment Defaults

From `phpunit.xml`:

- `APP_ENV=testing`
- MySQL test DB: `127.0.0.1:3306`, database `auflow_test`
- `QUEUE_CONNECTION=sync`
- `CACHE_STORE=array`
- `SESSION_DRIVER=array`
- `MAIL_MAILER=array`

## Conventions Followed in This Repository

- Add or update tests for every behavior change.
- Prefer focused feature tests for HTTP and workflow behavior.
- Use unit tests for pure services/evaluators/formatters.
- Keep tests independent (no cross-test side effects).

## Common Test Commands for Daily Work

```bash
# fast feedback after backend edits
php artisan test --compact --filter=Workflow

# run only reports tests
php artisan test --compact --filter=Reports

# run one unit test class
php artisan test --compact tests/Unit/SnapshotSecurityServiceTest.php
```
