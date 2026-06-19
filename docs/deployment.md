# Deployment

This document describes the AUFlow deployment model for a manual server installation (Nginx + PHP-FPM + Supervisor).

## Runtime Topology

Services running on the production server:

- **Nginx** – web server and TLS termination
- **PHP-FPM** – PHP application runtime
- **MySQL 8** – primary database
- **Redis** – cache, session, and queue backend
- **Queue worker** – `php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600` (managed by Supervisor)
- **Scheduler** – `php artisan schedule:work` (managed by Supervisor)

## Data Persistence

MySQL data lives on the host filesystem under the configured MySQL data directory.

Critical safety rule:

- never run `php artisan migrate:fresh` or `php artisan migrate:refresh` in production

## Production Update Script

Primary update path is:

```bash
/opt/auflow/scripts/deploy.sh
```

Current script behavior:

1. `git pull origin main`
2. `chown -R www-data:www-data /opt/auflow`
3. `composer install --no-dev --optimize-autoloader`
4. `npm ci && npm run build`
5. `php artisan migrate --force`
6. `php artisan optimize:clear && php artisan optimize`
7. `supervisorctl restart auflow-queue:*`
8. HTTP health check against `APP_URL`

Note: `scripts/deploy.sh` sets `APP_URL=https://auflow.online` internally. Update this value if your deployment domain differs.

## Backup Script

Nightly backup automation script:

- `scripts/backup-db.sh`

Behavior:

- reads DB credentials from `/opt/auflow/.env`
- dumps the application database using `mysqldump`
- writes gzip archives to `/opt/backups`
- logs to `/var/log/auflow-backup.log`
- retains latest 7 backups

Suggested cron entry:

```bash
0 3 * * * /opt/auflow/scripts/backup-db.sh
```

## Scheduler-Driven Operations

`bootstrap/app.php` schedules:

- `workflow:send-approval-reminders` every minute
- `reports:send-scheduled-exports` every minute
- `partitions:manage` monthly

The Supervisor `auflow-scheduler` process executes these continuously.

## Build and Assets

Frontend assets are built with Vite.

For production deployments:

- build step is included in `scripts/deploy.sh`
- ensure `public/build` exists and matches current release

## Health and Verification Commands

```bash
supervisorctl status
tail -n 100 /opt/auflow/storage/logs/laravel.log
tail -n 100 /opt/auflow/storage/logs/worker.log
curl -I https://<your-domain>
```

## Unsafe Commands in Production

Never run:

- `php artisan migrate:fresh`
- `php artisan migrate:refresh`
- `php artisan migrate:reset`

These are destructive for live data.
