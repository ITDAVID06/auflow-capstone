# Deployment Runbook

This runbook covers first-time setup and routine updates for a manually deployed AUFlow production server.

## 1. First-Time Host Preparation

On an Ubuntu 22.04/24.04 host:

```bash
apt update && apt upgrade -y
apt install -y php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-bcmath \
    php8.4-exif php8.4-pcntl php8.4-zip php8.4-redis \
    mysql-server redis-server nginx supervisor certbot python3-certbot-nginx \
    git curl unzip
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

Clone repository:

```bash
mkdir -p /opt/auflow
git clone <repo-url> /opt/auflow
cd /opt/auflow
```

## 2. Configure Environment

```bash
cp .env.example .env
```

Set at minimum:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<your-domain>`
- `DB_HOST=127.0.0.1`, `DB_*` credentials
- `SNAPSHOT_SIGNING_KEY`
- mail credentials (`RESEND_API_KEY` if using Resend)

## 3. Initial Boot

```bash
cd /opt/auflow
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force
php artisan storage:link
php artisan optimize
chown -R www-data:www-data /opt/auflow
chmod -R 755 /opt/auflow/storage /opt/auflow/bootstrap/cache
```

## 4. Verify Services

```bash
supervisorctl status
tail -n 50 /opt/auflow/storage/logs/laravel.log
curl -I https://\<your-domain\>
```

## 5. Configure Nightly Backups

```bash
chmod +x /opt/auflow/scripts/backup-db.sh /opt/auflow/scripts/deploy.sh
(crontab -l 2>/dev/null; echo "0 3 * * * /opt/auflow/scripts/backup-db.sh") | sort -u | crontab -
```

## 6. Routine Deployment

Preferred command:

```bash
/opt/auflow/scripts/deploy.sh
```

What this handles automatically:

- git pull + ownership normalization
- composer install (production, no dev)
- npm build
- safe migrations (`migrate --force`)
- cache clear and warm
- Supervisor worker restart
- basic HTTP health check

## 7. Rollback Strategy (Practical)

If new release is unhealthy:

1. inspect logs: `tail -n 200 /opt/auflow/storage/logs/laravel.log`
2. restore previous Git revision: `git checkout <previous-tag>`
3. rerun deploy script
4. if data corruption is involved, restore latest SQL backup then rerun safe migrations

Backup restore pattern:

```bash
gunzip -c /opt/backups/auflow_<timestamp>.sql.gz \
  | mysql -h 127.0.0.1 -u root -p auflow
php artisan migrate --force
php artisan optimize:clear
supervisorctl restart auflow-queue:*
```

## 8. Production Guardrails

Never use destructive commands in production:

- `php artisan migrate:fresh`
- `php artisan migrate:refresh`

Protect and back up secrets:

- `APP_KEY`
- `SNAPSHOT_SIGNING_KEY`
