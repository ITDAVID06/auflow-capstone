# Troubleshooting

This guide lists common AUFlow issues and proven fixes.

## 1. App Not Reachable

Symptoms:

- browser cannot load the application URL

Checks:

```bash
sudo systemctl status nginx php8.4-fpm
tail -n 100 /var/log/nginx/error.log
```

Fixes:

- ensure Nginx and PHP-FPM are running
- verify `root` in your Nginx config points to `<app>/public`
- free conflicting ports (`80`, `443`)
- ensure Nginx can read/write `<app>/storage` (owned by `www-data`)

## 2. MySQL Connection Errors

Symptoms:

- migration/runtime `SQLSTATE[HY000] [2002] Connection refused`

Checks:

```bash
sudo systemctl status mysql
mysql -h 127.0.0.1 -u auflow -p auflow -e "SELECT 1"
```

Fixes:

- verify MySQL is running: `sudo systemctl start mysql`
- confirm `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` match your MySQL setup
- ensure the `auflow` database and user exist with correct privileges

## 3. Login Keeps Redirecting to Password Change

Cause:

- user has `must_change_password = true`
- `ForcePasswordChange` middleware redirects to `password.change`

Fix:

- complete password change at `/password/change`
- or clear the flag for the affected account if doing admin-level recovery

## 4. 429 Too Many Requests

Symptoms:

- form submission or certain endpoints return throttling errors

Current limits:

- submission limiter `submissions`: 5 requests/minute per account (or IP for guests)
- forgot-password: `throttle:5,1`
- public error reports endpoint: `throttle:10,1`

Fixes:

- retry after cooldown
- avoid repeated background retries from client code

## 5. Queue Jobs Not Running (Emails/Snapshots/Exports Delayed)

Checks:

```bash
supervisorctl status
tail -n 200 storage/logs/worker.log
php artisan queue:failed
```

Fixes:

- ensure the `auflow-queue` Supervisor process is running
- ensure `QUEUE_CONNECTION` in `.env` matches what the worker is consuming
- restart worker after deployment/config changes: `supervisorctl restart auflow-queue:*`

## 6. Scheduled Commands Not Firing

The scheduler runs via Supervisor (the `auflow-scheduler` program defined in your Supervisor conf).

Checks:

```bash
supervisorctl status
tail -n 200 storage/logs/scheduler.log
```

Scheduled tasks configured in `bootstrap/app.php`:

- `workflow:send-approval-reminders` (every minute)
- `reports:send-scheduled-exports` (every minute)
- `partitions:manage` (monthly)

## 7. Vite Manifest / Asset Errors

Symptoms:

- missing built assets
- Laravel Vite manifest errors

Fixes:

```bash
npm run build
# or for development
npm run dev
```

## 8. Inertia Error Page Appears for 403/404/500/503

Behavior:

- in non-local/non-testing environments, Inertia requests receive `Errors/Error` page for 403/404/500/503
- 419 responses redirect back with flash error

Where configured:

- `bootstrap/app.php` exception handling

Debug path:

```bash
tail -n 200 storage/logs/laravel.log
```

## 9. Profile Picture URL Returns 404

Checks:

- file exists on `profile-pictures` disk
- request is authenticated
- path is valid and normalized (no traversal segments)

Fixes:

- re-upload avatar from profile settings
- verify storage configuration and permissions: `php artisan storage:link`

## 10. Safe Local Reset

Warning: destroys local data.

```bash
php artisan migrate:fresh --seed
php artisan optimize:clear
php artisan queue:flush
```
